<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Transcription Service using Groq Whisper API
 * Supports Hindi, Hinglish, English, and 50+ other languages
 * Free tier: Unlimited with 30 RPM rate limit
 */
class TranscriptionService
{
    private string $groqApiKey;
    private string $ffmpegPath;

    public function __construct()
    {
        // Get from $_ENV first, then fallback to settings table
        $this->groqApiKey = $this->getEnvWithDbFallback('GROQ_API_KEY');
        $this->ffmpegPath = $this->getEnvWithDbFallback('FFMPEG_PATH') ?: 'ffmpeg';
    }

    /**
     * Get environment variable with database fallback
     */
    private function getEnvWithDbFallback(string $key): string
    {
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        try {
            $db = \App\Config\Database::getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute(['env_' . $key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row['setting_value'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Transcribe audio/video file to text
     * 
     * @param string $filePath Path to video/audio file
     * @param string $language Optional language hint (e.g., 'hi' for Hindi, 'en' for English)
     * @return array ['success' => bool, 'text' => string, 'language' => string, 'error' => string]
     */
    public function transcribe(string $filePath, string $language = ''): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'text' => '', 'error' => 'File not found'];
        }

        if (empty($this->groqApiKey)) {
            log_message('warning', 'Groq API key not configured, skipping transcription');
            return ['success' => false, 'text' => '', 'error' => 'Groq API key not configured'];
        }

        // Extract audio from video if needed
        $audioPath = $this->extractAudio($filePath);
        if (!$audioPath) {
            return ['success' => false, 'text' => '', 'error' => 'Failed to extract audio'];
        }

        try {
            $result = $this->callGroqWhisper($audioPath, $language);
            
            // Cleanup temp audio file
            if ($audioPath !== $filePath && file_exists($audioPath)) {
                @unlink($audioPath);
            }
            
            return $result;
        } catch (\Exception $e) {
            // Cleanup on error
            if ($audioPath !== $filePath && file_exists($audioPath)) {
                @unlink($audioPath);
            }
            
            log_message('error', 'Transcription failed: ' . $e->getMessage());
            return ['success' => false, 'text' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract audio from video file using FFmpeg
     */
    private function extractAudio(string $videoPath): ?string
    {
        $ext = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
        
        // If already audio, return as-is
        if (in_array($ext, ['mp3', 'wav', 'flac', 'm4a', 'ogg'])) {
            return $videoPath;
        }

        // Extract audio to temp file
        $tempAudio = sys_get_temp_dir() . '/fp3_audio_' . uniqid() . '.mp3';
        
        $cmd = sprintf(
            '%s -i %s -vn -acodec libmp3lame -ab 128k -ar 16000 -ac 1 %s -y 2>&1',
            escapeshellcmd($this->ffmpegPath),
            escapeshellarg($videoPath),
            escapeshellarg($tempAudio)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempAudio)) {
            log_message('error', 'FFmpeg audio extraction failed: ' . implode("\n", $output));
            return null;
        }

        // Check file size - Groq limit is 25MB
        if (filesize($tempAudio) > 25 * 1024 * 1024) {
            // Re-encode at lower bitrate
            $tempAudio2 = sys_get_temp_dir() . '/fp3_audio_small_' . uniqid() . '.mp3';
            $cmd = sprintf(
                '%s -i %s -vn -acodec libmp3lame -ab 64k -ar 16000 -ac 1 %s -y 2>&1',
                escapeshellcmd($this->ffmpegPath),
                escapeshellarg($tempAudio),
                escapeshellarg($tempAudio2)
            );
            exec($cmd, $output, $returnCode);
            @unlink($tempAudio);
            
            if ($returnCode === 0 && file_exists($tempAudio2)) {
                return $tempAudio2;
            }
            return null;
        }

        return $tempAudio;
    }

    /**
     * Call Groq Whisper API
     */
    private function callGroqWhisper(string $audioPath, string $language = '', int $retryCount = 0): array
    {
        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');

        $postFields = [
            'file' => new \CURLFile($audioPath, 'audio/mpeg', 'audio.mp3'),
            'model' => 'whisper-large-v3-turbo',
            'response_format' => 'verbose_json',
            'temperature' => 0,
        ];

        // Add language hint if provided
        // For Indian content, we'll try Hindi first if no language specified
        if (!empty($language)) {
            $postFields['language'] = $language;
        }
        
        // Add prompt to help with Hindi/Hinglish detection
        // This helps Whisper understand the context
        $postFields['prompt'] = 'This audio may contain Hindi, Hinglish (Hindi-English mix), or English. Transcribe all words accurately including any profanity or slang.';

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->groqApiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300, // 5 min timeout for long videos
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 429) {
            // Rate limited - wait and retry once
            log_message('info', 'Groq rate limited, waiting 2 seconds...');
            sleep(2);
            return $this->callGroqWhisper($audioPath, $language, $retryCount);
        }

        if ($httpCode !== 200 || !$response) {
            throw new \Exception("Groq API failed: HTTP {$httpCode}, Error: {$curlError}");
        }

        $data = json_decode($response, true);
        
        if (!isset($data['text'])) {
            throw new \Exception('Invalid Groq API response: ' . substr($response, 0, 500));
        }
        
        $detectedLanguage = $data['language'] ?? 'unknown';
        $transcribedText = $data['text'];
        
        log_message('info', "Groq transcription: language={$detectedLanguage}, text=" . substr($transcribedText, 0, 100));
        
        // If detected as English but text looks suspicious (very short, repetitive), 
        // retry with Hindi language hint
        if ($retryCount === 0 && empty($language) && $detectedLanguage === 'english') {
            $wordCount = str_word_count($transcribedText);
            $hasRepetition = preg_match('/(\b\w+\b)(\s+\1){2,}/i', $transcribedText); // Repeated words
            
            if ($wordCount < 10 || $hasRepetition) {
                log_message('info', "Suspicious transcription (words={$wordCount}, repetition={$hasRepetition}), retrying with Hindi hint");
                return $this->callGroqWhisper($audioPath, 'hi', $retryCount + 1);
            }
        }

        return [
            'success' => true,
            'text' => $transcribedText,
            'language' => $detectedLanguage,
            'duration' => $data['duration'] ?? null,
            'segments' => $data['segments'] ?? [],
        ];
    }

    /**
     * Check if transcription service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->groqApiKey);
    }
}

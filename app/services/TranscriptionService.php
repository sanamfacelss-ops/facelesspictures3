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
     * 
     * @param string $audioPath Path to audio file
     * @param string $language Optional language hint
     * @param string $model Which Whisper model to use
     * @param int $retryCount Retry counter for bad transcriptions
     */
    private function callGroqWhisper(string $audioPath, string $language = '', string $model = 'whisper-large-v3', int $retryCount = 0): array
    {
        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');

        $postFields = [
            'file' => new \CURLFile($audioPath, 'audio/mpeg', 'audio.mp3'),
            'model' => $model,
            'response_format' => 'verbose_json',
            'temperature' => 0,
        ];

        // Only set language if explicitly provided - otherwise let Whisper auto-detect
        if (!empty($language)) {
            $postFields['language'] = $language;
        }
        
        // Prompt helps with context but doesn't force language
        $postFields['prompt'] = 'Transcribe exactly what is said. Include any profanity, slang, or gaali words accurately without censoring.';

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->groqApiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 429) {
            log_message('info', 'Groq rate limited, waiting 2 seconds...');
            sleep(2);
            return $this->callGroqWhisper($audioPath, $language, $model, $retryCount);
        }

        if ($httpCode !== 200 || !$response) {
            throw new \Exception("Groq API failed: HTTP {$httpCode}, Error: {$curlError}");
        }

        $data = json_decode($response, true);
        
        if (!isset($data['text'])) {
            throw new \Exception('Invalid Groq API response: ' . substr($response, 0, 500));
        }
        
        $detectedLanguage = $data['language'] ?? 'unknown';
        $transcribedText = trim($data['text']);
        
        log_message('info', "Groq transcription (model={$model}): detected_lang={$detectedLanguage}, text=" . substr($transcribedText, 0, 200));

        // Check for suspicious/garbage transcription patterns
        if ($retryCount < 2) {
            $isSuspicious = $this->isGarbageTranscription($transcribedText);
            
            if ($isSuspicious) {
                log_message('warning', "Suspicious transcription detected (model={$model}, retry={$retryCount}), trying different approach");
                
                // First retry: try with Hindi hint (common case)
                if ($retryCount === 0 && empty($language)) {
                    log_message('info', "Retrying with Hindi language hint");
                    return $this->callGroqWhisper($audioPath, 'hi', $model, $retryCount + 1);
                }
                
                // Second retry: try turbo model if we're on the large model
                if ($retryCount === 1 && $model === 'whisper-large-v3') {
                    log_message('info', "Retrying with turbo model");
                    return $this->callGroqWhisper($audioPath, '', 'whisper-large-v3-turbo', $retryCount + 1);
                }
            }
        }

        return [
            'success' => true,
            'text' => $transcribedText,
            'language' => $this->normalizeLanguageName($detectedLanguage),
            'duration' => $data['duration'] ?? null,
            'segments' => $data['segments'] ?? [],
        ];
    }
    
    /**
     * Check if transcription looks like garbage/hallucination
     */
    private function isGarbageTranscription(string $text): bool
    {
        $text = strtolower(trim($text));
        
        // Empty or very short
        if (strlen($text) < 3) {
            return false; // Could be legitimate short audio
        }
        
        // Check for repetitive patterns like "the the the" or "tons of the tons of the"
        $words = preg_split('/\s+/', $text);
        $wordCount = count($words);
        
        if ($wordCount >= 3) {
            // Check for high repetition
            $uniqueWords = array_unique($words);
            $repetitionRatio = count($uniqueWords) / $wordCount;
            
            // If less than 40% unique words, it's likely garbage
            if ($repetitionRatio < 0.4) {
                log_message('info', "Garbage detection: high repetition ratio " . round($repetitionRatio, 2));
                return true;
            }
        }
        
        // Check for common Whisper hallucination patterns
        $hallucinations = [
            'thanks for watching',
            'subscribe to',
            'like and subscribe',
            'tons of the',
            'the the the',
            'silence',
            '[music]',
            '[applause]',
        ];
        
        foreach ($hallucinations as $pattern) {
            if (str_contains($text, $pattern)) {
                log_message('info', "Garbage detection: hallucination pattern '{$pattern}'");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Normalize language name to proper English name
     */
    private function normalizeLanguageName(string $code): string
    {
        $languages = [
            'en' => 'English',
            'english' => 'English',
            'hi' => 'Hindi',
            'hindi' => 'Hindi',
            'ta' => 'Tamil',
            'tamil' => 'Tamil',
            'te' => 'Telugu',
            'telugu' => 'Telugu',
            'mr' => 'Marathi',
            'marathi' => 'Marathi',
            'bn' => 'Bengali',
            'bengali' => 'Bengali',
            'gu' => 'Gujarati',
            'gujarati' => 'Gujarati',
            'kn' => 'Kannada',
            'kannada' => 'Kannada',
            'ml' => 'Malayalam',
            'malayalam' => 'Malayalam',
            'pa' => 'Punjabi',
            'punjabi' => 'Punjabi',
            'ur' => 'Urdu',
            'urdu' => 'Urdu',
        ];
        
        $lower = strtolower($code);
        return $languages[$lower] ?? ucfirst($code);
    }

    /**
     * Check if transcription service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->groqApiKey);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Transcription Service using multiple providers
 * 
 * Primary: Groq Whisper API (free, unlimited with 30 RPM rate limit)
 * Fallback: HuggingFace IndicWhisper Space (free, better for Hindi)
 * 
 * Supports Hindi, Hinglish, English, and 50+ other languages
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
            // Try Groq Whisper first
            $result = $this->callGroqWhisper($audioPath, $language);
            
            // If Groq result looks like garbage, try IndicWhisper as fallback
            if ($result['success'] && ($result['unreliable'] ?? false)) {
                log_message('info', "Groq produced unreliable transcript, trying IndicWhisper fallback");
                
                $indicResult = $this->callIndicWhisper($audioPath);
                if ($indicResult['success'] && !empty($indicResult['text'])) {
                    // Check if IndicWhisper result is better (not garbage)
                    if (!$this->isGarbageTranscription($indicResult['text'])) {
                        log_message('info', "IndicWhisper produced better result: " . substr($indicResult['text'], 0, 100));
                        $indicResult['provider'] = 'indicwhisper';
                        
                        // Cleanup temp audio file
                        if ($audioPath !== $filePath && file_exists($audioPath)) {
                            @unlink($audioPath);
                        }
                        return $indicResult;
                    }
                }
            }
            
            // Cleanup temp audio file
            if ($audioPath !== $filePath && file_exists($audioPath)) {
                @unlink($audioPath);
            }
            
            $result['provider'] = 'groq';
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
        
        // No instruction prompt: Whisper treats the prompt as "previous text" and often echoes it
        // into the transcript (that echo then tripped the profanity check on clean videos).

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
        $transcribedText = $this->stripPromptEcho(trim($data['text']));
        
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
        
        // Final check: if still garbage after retries, mark it as unreliable
        $isGarbage = $this->isGarbageTranscription($transcribedText);
        
        // Detect language from actual text content (more reliable than Whisper's detection)
        $actualLanguage = $this->detectLanguageFromText($transcribedText, $detectedLanguage);

        return [
            'success' => true,
            'text' => $transcribedText,
            'language' => $actualLanguage,
            'whisper_language' => $this->normalizeLanguageName($detectedLanguage),  // Keep original for debugging
            'duration' => $data['duration'] ?? null,
            'segments' => $data['segments'] ?? [],
            'unreliable' => $isGarbage,  // Flag for garbage transcription
        ];
    }
    
    /**
     * Detect language from actual text content
     * More reliable than trusting Whisper's detection
     */
    private function detectLanguageFromText(string $text, string $whisperLanguage): string
    {
        $text = trim($text);
        
        if (empty($text)) {
            return 'Unknown';
        }
        
        // Check for Indic scripts (Devanagari, Gurmukhi, etc.)
        // If text has these scripts, it's likely Hindi/Indian language
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'Hindi (Devanagari)';
        }
        if (preg_match('/[\x{0A00}-\x{0A7F}]/u', $text)) {
            return 'Punjabi (Gurmukhi)';
        }
        if (preg_match('/[\x{0B80}-\x{0BFF}]/u', $text)) {
            return 'Tamil';
        }
        if (preg_match('/[\x{0C00}-\x{0C7F}]/u', $text)) {
            return 'Telugu';
        }
        if (preg_match('/[\x{0980}-\x{09FF}]/u', $text)) {
            return 'Bengali';
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return 'Urdu/Arabic';
        }
        
        // Text is in Latin script - could be English or Romanized Hindi (Hinglish)
        $lower = strtolower($text);
        
        // Latin script: English, or Romanized Hindi (Hinglish). Count WHOLE words only.
        // (Old code used substring matching, so English words like "are", "main", "her", "the"
        //  counted as Hindi and English videos were labelled Hindi.)
        $hindiIndicators = [
            'kya', 'hai', 'hain', 'nahi', 'nahin', 'aur', 'mein', 'tum', 'aap',
            'kaise', 'kaisa', 'kaisi', 'kyun', 'kyon', 'kahan', 'kahaan',
            'accha', 'achha', 'acha', 'theek', 'thik', 'bahut', 'bohot', 'bhai', 'yaar',
            'abhi', 'phir', 'lekin', 'matlab', 'samajh', 'dekho', 'suno',
            'chalo', 'karo', 'bolo', 'jao', 'aao', 'pakdo', 'ruko', 'haan', 'arre',
            'mujhe', 'tujhe', 'humko', 'tumko', 'kuch', 'koi', 'woh', 'yeh', 'kaun', 'kitna',
            'raha', 'rahi', 'rahe', 'gaya', 'gayi', 'tha', 'thi', 'hoga', 'karna', 'karke',
        ];
        $englishStopwords = [
            'the', 'is', 'are', 'was', 'were', 'have', 'has', 'had', 'will', 'would', 'could',
            'should', 'can', 'this', 'that', 'these', 'those', 'what', 'which', 'who', 'how',
            'why', 'when', 'where', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'my', 'your',
            'his', 'her', 'our', 'and', 'to', 'of', 'in', 'for', 'on', 'with', 'not', 'but',
            'if', 'so', 'do', 'go', 'get', 'just', 'there', 'here', 'me', 'be', 'a', 'an',
        ];

        $words = preg_split('/[^a-z\']+/', $lower, -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);
        if ($totalWords === 0) {
            return $this->normalizeLanguageName($whisperLanguage);
        }
        $hindiSet = array_flip($hindiIndicators);
        $englishSet = array_flip($englishStopwords);
        $hindiHits = 0;
        $englishHits = 0;
        foreach ($words as $w) {
            if (isset($hindiSet[$w])) $hindiHits++;
            if (isset($englishSet[$w])) $englishHits++;
        }
        $hindiRatio = $hindiHits / $totalWords;
        $englishRatio = $englishHits / $totalWords;

        // Normal English speech is ~35-50% stopwords; Hinglish has many Hindi function words
        if ($hindiRatio >= 0.08 && $englishRatio >= 0.15) {
            return 'Hinglish (Hindi-English)';
        }
        if ($hindiRatio >= 0.08) {
            return 'Hindi (Romanized)';
        }
        if ($englishRatio >= 0.2) {
            return 'English';
        }

        if ($this->isGarbageTranscription($text)) {
            return 'Unknown (transcription unreliable)';
        }

        return $this->normalizeLanguageName($whisperLanguage);
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
        
        // NOTE: Removed Indic script checks - these are VALID transcriptions, not garbage!
        // Devanagari, Gurmukhi, Tamil, etc. indicate successful transcription
        
        // Check for repetitive patterns like "the the the" or "tons of the tons of the"
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);
        
        if ($wordCount >= 3 && $wordCount <= 40) {
            // Short text: unique-word ratio works
            $repetitionRatio = count(array_unique($words)) / $wordCount;
            if ($repetitionRatio < 0.4) {
                log_message('info', "Garbage detection: high repetition ratio " . round($repetitionRatio, 2));
                return true;
            }
        } elseif ($wordCount > 40) {
            // Long text: normal speech reuses words a lot ("I", "the", "her"), so the
            // unique-word ratio wrongly marked long real transcripts as garbage.
            // Use repeated 4-word phrases instead — real speech rarely repeats them.
            $grams = [];
            for ($i = 0; $i + 3 < $wordCount; $i++) {
                $grams[] = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2] . ' ' . $words[$i + 3];
            }
            $distinctRatio = count(array_unique($grams)) / max(1, count($grams));
            if ($distinctRatio < 0.5) {
                log_message('info', "Garbage detection: repeated phrases, distinct 4-gram ratio " . round($distinctRatio, 2));
                return true;
            }
        }
        
        // Check for common Whisper hallucination patterns
        $hallucinations = [
            'thanks for watching',
            'like and subscribe',
            'tons of the tons of',
            'the the the',
            '[silence]',
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
     * Remove the old Whisper instruction prompt if it was echoed into the transcript
     * (Whisper sometimes repeats its prompt, even with small spelling changes like "Iclude").
     */
    private function stripPromptEcho(string $text): string
    {
        $clean = preg_replace(
            '/\s*(transcribe exactly what is said\.?\s*)?i?n?cl?ude any profanity,?\s*slang,?\s*or gaali words accurately without censoring\.?/i',
            ' ',
            $text
        );
        $clean = preg_replace('/\s*transcribe exactly what is said\.?/i', ' ', $clean ?? $text);
        return trim(preg_replace('/\s{2,}/', ' ', $clean ?? $text) ?? $text);
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
     * Call HuggingFace IndicWhisper Space as fallback for Hindi
     * This is FREE and better at Hindi transcription
     */
    private function callIndicWhisper(string $audioPath): array
    {
        try {
            // Use HuggingFace Spaces API for IndicWhisper
            // Spaces use Gradio API which can be called via HTTP
            $spaceUrl = 'https://akpande2-ai4bharat-indicwhisper.hf.space/api/predict';
            
            // Read audio file and encode as base64
            $audioData = file_get_contents($audioPath);
            if ($audioData === false) {
                return ['success' => false, 'text' => '', 'error' => 'Could not read audio file'];
            }
            
            $base64Audio = base64_encode($audioData);
            $mimeType = 'audio/mpeg';
            
            // Gradio API expects data in specific format
            $payload = json_encode([
                'data' => [
                    'data:' . $mimeType . ';base64,' . $base64Audio  // Audio file as data URL
                ]
            ]);
            
            $ch = curl_init($spaceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,  // Longer timeout for HF Spaces
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                log_message('warning', "IndicWhisper API failed: HTTP {$httpCode}, Error: {$curlError}");
                return ['success' => false, 'text' => '', 'error' => "API failed: HTTP {$httpCode}"];
            }
            
            $data = json_decode($response, true);
            
            // Gradio returns data in 'data' array
            $text = '';
            if (isset($data['data'][0])) {
                $text = is_string($data['data'][0]) ? trim($data['data'][0]) : '';
            }
            
            if (empty($text)) {
                log_message('warning', "IndicWhisper returned empty response");
                return ['success' => false, 'text' => '', 'error' => 'Empty response'];
            }
            
            log_message('info', "IndicWhisper transcription: " . substr($text, 0, 200));
            
            return [
                'success' => true,
                'text' => $text,
                'language' => 'Hindi',  // IndicWhisper is optimized for Hindi
                'unreliable' => false,
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'IndicWhisper error: ' . $e->getMessage());
            return ['success' => false, 'text' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if transcription service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->groqApiKey);
    }
}

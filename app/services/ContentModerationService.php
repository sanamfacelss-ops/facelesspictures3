<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Content Moderation Service with Fallback Chain
 * 
 * Text Moderation: Azure AI Content Safety → OpenAI Moderation → Local Wordlist
 * Image Moderation: Azure AI Content Safety → SightEngine → API4AI
 */
class ContentModerationService
{
    private PDO $db;
    
    // Azure AI Content Safety
    private string $azureEndpoint;
    private string $azureKey;
    
    // OpenAI (fallback for text)
    private string $openaiKey;
    
    // SightEngine (fallback for images)
    private string $sightengineUser;
    private string $sightengineSecret;
    
    // API4AI via RapidAPI (fallback for images)
    private string $rapidApiKey;

    public function __construct()
    {
        $this->db = Database::getConnection();
        
        // Helper function to get env value with database fallback
        $getEnv = function(string $key): string {
            // First try $_ENV
            if (!empty($_ENV[$key])) {
                return $_ENV[$key];
            }
            // Fallback to settings table with env_ prefix
            try {
                $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = ?");
                $stmt->execute(['env_' . $key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['value'] ?? '';
            } catch (\Exception $e) {
                return '';
            }
        };
        
        // Azure AI Content Safety (Primary)
        $this->azureEndpoint = $getEnv('AZURE_CONTENT_SAFETY_ENDPOINT');
        $this->azureKey = $getEnv('AZURE_CONTENT_SAFETY_KEY');
        
        // OpenAI Moderation (Fallback for text - unlimited free)
        $this->openaiKey = $getEnv('OPENAI_API_KEY');
        
        // SightEngine (Fallback for images)
        $this->sightengineUser = $getEnv('SIGHTENGINE_API_USER');
        $this->sightengineSecret = $getEnv('SIGHTENGINE_API_SECRET');
        
        // RapidAPI / API4AI (Fallback for images)
        $this->rapidApiKey = $getEnv('RAPIDAPI_KEY');
    }

    /**
     * Moderate text content (profanity, hate speech, etc.)
     * Supports all languages including Hindi, Hinglish, Tamil, Telugu, etc.
     */
    public function moderateText(string $text): array
    {
        if (empty(trim($text))) {
            return ['safe' => true, 'score' => 0, 'categories' => [], 'provider' => 'none'];
        }

        // Try Azure first
        $result = $this->tryAzureText($text);
        if ($result !== false) {
            return $result;
        }

        // Fallback to OpenAI
        $result = $this->tryOpenAIText($text);
        if ($result !== false) {
            return $result;
        }

        // Last resort: local wordlist
        return $this->localTextCheck($text);
    }

    /**
     * Moderate image content (NSFW, nudity, violence)
     */
    public function moderateImage(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return ['safe' => true, 'score' => 0, 'categories' => [], 'provider' => 'error', 'error' => 'File not found'];
        }

        $errors = [];

        // Try Azure first
        if (empty($this->azureEndpoint) || empty($this->azureKey)) {
            $errors[] = 'Azure: Not configured (missing endpoint or key)';
            log_message('debug', 'Azure image moderation skipped: not configured');
        } else {
            $result = $this->tryAzureImage($imagePath);
            if ($result !== false) {
                return $result;
            }
            $errors[] = 'Azure: API call failed';
        }

        // Fallback to SightEngine
        if (empty($this->sightengineUser) || empty($this->sightengineSecret)) {
            $errors[] = 'SightEngine: Not configured (missing user or secret)';
            log_message('debug', 'SightEngine skipped: not configured');
        } else {
            $result = $this->trySightEngineImage($imagePath);
            if ($result !== false) {
                return $result;
            }
            $errors[] = 'SightEngine: API call failed';
        }

        // Fallback to API4AI
        if (empty($this->rapidApiKey)) {
            $errors[] = 'RapidAPI: Not configured (missing key)';
            log_message('debug', 'API4AI/RapidAPI skipped: not configured');
        } else {
            $result = $this->tryApi4AIImage($imagePath);
            if ($result !== false) {
                return $result;
            }
            $errors[] = 'RapidAPI: API call failed';
        }

        // All APIs failed - assume safe and let through (don't penalize user for API issues)
        log_message('warning', 'All image moderation APIs failed: ' . implode('; ', $errors));
        return [
            'safe' => true,
            'score' => 0,
            'categories' => ['api_failure'],
            'provider' => 'none',
            'needs_manual_review' => false,
            'error' => implode('; ', $errors)
        ];
    }

    /**
     * Azure AI Content Safety - Text Moderation
     */
    private function tryAzureText(string $text): array|false
    {
        if (empty($this->azureEndpoint) || empty($this->azureKey)) {
            return false;
        }

        try {
            $url = rtrim($this->azureEndpoint, '/') . '/contentsafety/text:analyze?api-version=2024-09-01';
            
            $payload = json_encode([
                'text' => substr($text, 0, 10000),
                'categories' => ['Hate', 'Sexual', 'Violence', 'SelfHarm'],
                'outputType' => 'FourSeverityLevels'
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Ocp-Apim-Subscription-Key: ' . $this->azureKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                log_message('info', 'Azure text moderation rate limit hit, switching to fallback');
                return false;
            }

            if ($httpCode !== 200 || !$response) {
                log_message('warning', "Azure text moderation failed: HTTP {$httpCode}");
                return false;
            }

            $data = json_decode($response, true);
            if (!isset($data['categoriesAnalysis'])) {
                return false;
            }

            $maxSeverity = 0;
            $flaggedCategories = [];

            foreach ($data['categoriesAnalysis'] as $cat) {
                $severity = $cat['severity'] ?? 0;
                if ($severity > $maxSeverity) {
                    $maxSeverity = $severity;
                }
                if ($severity >= 2) {
                    $flaggedCategories[] = strtolower($cat['category']);
                }
            }

            $score = $maxSeverity / 6;

            return [
                'safe' => $maxSeverity < 2,
                'score' => $score,
                'severity' => $maxSeverity,
                'categories' => $flaggedCategories,
                'provider' => 'azure',
                'raw' => $data
            ];

        } catch (\Exception $e) {
            log_message('error', 'Azure text moderation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * OpenAI Moderation API - Text (Unlimited Free)
     */
    private function tryOpenAIText(string $text): array|false
    {
        if (empty($this->openaiKey)) {
            return false;
        }

        try {
            $ch = curl_init('https://api.openai.com/v1/moderations');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'input' => substr($text, 0, 32768),
                    'model' => 'omni-moderation-latest'
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->openaiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                log_message('warning', "OpenAI moderation failed: HTTP {$httpCode}");
                return false;
            }

            $data = json_decode($response, true);
            if (!isset($data['results'][0])) {
                return false;
            }

            $result = $data['results'][0];
            $flagged = $result['flagged'] ?? false;
            $categories = $result['categories'] ?? [];
            $scores = $result['category_scores'] ?? [];

            $flaggedCategories = [];
            $maxScore = 0;
            foreach ($categories as $cat => $isFlagged) {
                $score = $scores[$cat] ?? 0;
                if ($score > $maxScore) {
                    $maxScore = $score;
                }
                if ($isFlagged) {
                    $flaggedCategories[] = $cat;
                }
            }

            return [
                'safe' => !$flagged,
                'score' => $maxScore,
                'categories' => $flaggedCategories,
                'provider' => 'openai',
                'raw' => $result
            ];

        } catch (\Exception $e) {
            log_message('error', 'OpenAI moderation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Local wordlist check (last resort)
     */
    private function localTextCheck(string $text): array
    {
        $bannedWords = [
            'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'damn', 'cunt',
            'bhenchod', 'madarchod', 'chutiya', 'gaand', 'lund', 'randi',
            'behenchod', 'mc', 'bc', 'bhosdike', 'harami', 'kutte', 'kamina',
            'saala', 'chod', 'maa ki', 'baap ki',
            'kill', 'murder', 'terrorist', 'bomb', 'attack', 'rape'
        ];

        $lower = strtolower($text);
        $found = [];

        foreach ($bannedWords as $word) {
            if (str_contains($lower, $word)) {
                $found[] = $word;
            }
        }

        $score = min(1.0, count($found) * 0.3);

        return [
            'safe' => empty($found),
            'score' => $score,
            'categories' => empty($found) ? [] : ['profanity'],
            'matched_words' => $found,
            'provider' => 'local'
        ];
    }

    /**
     * Azure AI Content Safety - Image Moderation
     */
    private function tryAzureImage(string $imagePath): array|false
    {
        if (empty($this->azureEndpoint) || empty($this->azureKey)) {
            return false;
        }

        try {
            $url = rtrim($this->azureEndpoint, '/') . '/contentsafety/image:analyze?api-version=2024-09-01';
            
            $imageData = base64_encode(file_get_contents($imagePath));
            
            $payload = json_encode([
                'image' => ['content' => $imageData],
                'categories' => ['Sexual', 'Violence', 'SelfHarm', 'Hate'],
                'outputType' => 'FourSeverityLevels'
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Ocp-Apim-Subscription-Key: ' . $this->azureKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                log_message('info', 'Azure image moderation rate limit hit, switching to fallback');
                return false;
            }

            if ($httpCode !== 200 || !$response) {
                log_message('warning', "Azure image moderation failed: HTTP {$httpCode}");
                return false;
            }

            $data = json_decode($response, true);
            if (!isset($data['categoriesAnalysis'])) {
                return false;
            }

            $maxSeverity = 0;
            $flaggedCategories = [];

            foreach ($data['categoriesAnalysis'] as $cat) {
                $severity = $cat['severity'] ?? 0;
                if ($severity > $maxSeverity) {
                    $maxSeverity = $severity;
                }
                if ($severity >= 2) {
                    $flaggedCategories[] = strtolower($cat['category']);
                }
            }

            $score = $maxSeverity / 6;

            return [
                'safe' => $maxSeverity < 2,
                'score' => $score,
                'severity' => $maxSeverity,
                'categories' => $flaggedCategories,
                'provider' => 'azure',
                'raw' => $data
            ];

        } catch (\Exception $e) {
            log_message('error', 'Azure image moderation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * SightEngine - Image Moderation (500/month free)
     */
    private function trySightEngineImage(string $imagePath): array|false
    {
        if (empty($this->sightengineUser) || empty($this->sightengineSecret)) {
            return false;
        }

        try {
            $ch = curl_init('https://api.sightengine.com/1.0/check.json');
            
            $postFields = [
                'media' => new \CURLFile($imagePath),
                'models' => 'nudity-2.1,offensive,gore',
                'api_user' => $this->sightengineUser,
                'api_secret' => $this->sightengineSecret,
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                log_message('info', 'SightEngine rate limit hit, switching to fallback');
                return false;
            }

            if ($httpCode !== 200 || !$response) {
                return false;
            }

            $data = json_decode($response, true);
            if (($data['status'] ?? '') !== 'success') {
                return false;
            }

            $nudityScore = max(
                $data['nudity']['sexual_activity'] ?? 0,
                $data['nudity']['sexual_display'] ?? 0,
                $data['nudity']['erotica'] ?? 0
            );
            $offensiveScore = $data['offensive']['prob'] ?? 0;
            $goreScore = $data['gore']['prob'] ?? 0;

            $maxScore = max($nudityScore, $offensiveScore, $goreScore);
            $flaggedCategories = [];

            if ($nudityScore > 0.5) $flaggedCategories[] = 'nudity';
            if ($offensiveScore > 0.5) $flaggedCategories[] = 'offensive';
            if ($goreScore > 0.5) $flaggedCategories[] = 'gore';

            return [
                'safe' => $maxScore < 0.5,
                'score' => $maxScore,
                'categories' => $flaggedCategories,
                'provider' => 'sightengine',
                'raw' => $data
            ];

        } catch (\Exception $e) {
            log_message('error', 'SightEngine error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * API4AI via RapidAPI - NSFW Detection (~100/day free)
     */
    private function tryApi4AIImage(string $imagePath): array|false
    {
        if (empty($this->rapidApiKey)) {
            return false;
        }

        try {
            $ch = curl_init('https://nsfw3.p.rapidapi.com/v1/results');
            
            $postFields = [
                'image' => new \CURLFile($imagePath),
            ];

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'X-RapidAPI-Key: ' . $this->rapidApiKey,
                    'X-RapidAPI-Host: nsfw3.p.rapidapi.com',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 429) {
                log_message('info', 'API4AI rate limit hit');
                return false;
            }

            if ($httpCode !== 200 || !$response) {
                return false;
            }

            $data = json_decode($response, true);
            
            $nsfwScore = 0;
            if (isset($data['results'][0]['entities'][0]['classes'])) {
                $classes = $data['results'][0]['entities'][0]['classes'];
                $nsfwScore = $classes['nsfw'] ?? 0;
            }

            return [
                'safe' => $nsfwScore < 0.5,
                'score' => $nsfwScore,
                'categories' => $nsfwScore >= 0.5 ? ['nsfw'] : [],
                'provider' => 'api4ai',
                'raw' => $data
            ];

        } catch (\Exception $e) {
            log_message('error', 'API4AI error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get provider status for debugging
     */
    public function getProviderStatus(): array
    {
        return [
            'azure' => !empty($this->azureEndpoint) && !empty($this->azureKey),
            'openai' => !empty($this->openaiKey),
            'sightengine' => !empty($this->sightengineUser) && !empty($this->sightengineSecret),
            'rapidapi' => !empty($this->rapidApiKey),
        ];
    }
}

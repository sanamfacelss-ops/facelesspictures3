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
        $getEnv = function(string $key) use (&$debugKeys): string {
            // First try $_ENV
            if (!empty($_ENV[$key])) {
                log_message('info', "ContentModerationService: Got {$key} from \$_ENV");
                return $_ENV[$key];
            }
            
            // Fallback to settings table with env_ prefix
            $dbKey = 'env_' . $key;
            try {
                $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute([$dbKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($row['setting_value'])) {
                    log_message('info', "ContentModerationService: Got {$key} from database (key: {$dbKey}), value length: " . strlen($row['setting_value']));
                    return $row['setting_value'];
                }
                
                // Try without env_ prefix as well
                $stmt2 = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt2->execute([$key]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                if (!empty($row2['setting_value'])) {
                    log_message('info', "ContentModerationService: Got {$key} from database (direct key), value length: " . strlen($row2['setting_value']));
                    return $row2['setting_value'];
                }
                
                log_message('warning', "ContentModerationService: {$key} NOT FOUND in database (tried: {$dbKey} and {$key})");
                return '';
            } catch (\Exception $e) {
                log_message('error', "ContentModerationService: Error getting {$key}: " . $e->getMessage());
                return '';
            }
        };
        
        // Debug: List all env_ keys in database
        try {
            $stmt = $this->db->query("SELECT setting_key FROM settings WHERE setting_key LIKE 'env_%' OR setting_key LIKE '%AZURE%' OR setting_key LIKE '%SIGHTENGINE%' OR setting_key LIKE '%RAPIDAPI%'");
            $allKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);
            log_message('info', "ContentModerationService: Available API keys in DB: " . implode(', ', $allKeys));
        } catch (\Exception $e) {
            log_message('error', "ContentModerationService: Could not list keys: " . $e->getMessage());
        }
        
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
        
        // Log what was loaded
        log_message('info', sprintf(
            "ContentModerationService initialized - Azure: %s, SightEngine: %s, RapidAPI: %s",
            !empty($this->azureEndpoint) && !empty($this->azureKey) ? 'YES' : 'NO',
            !empty($this->sightengineUser) && !empty($this->sightengineSecret) ? 'YES' : 'NO',
            !empty($this->rapidApiKey) ? 'YES' : 'NO'
        ));
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

        log_message('info', "Text moderation request for: " . substr($text, 0, 200));

        // Try Azure first
        $result = $this->tryAzureText($text);
        if ($result !== false) {
            log_message('info', "Azure text moderation: safe=" . ($result['safe'] ? 'true' : 'false') . ", score=" . $result['score']);
            // Also run local check as a safety net for Hindi profanity
            $localResult = $this->localTextCheck($text);
            if (!$localResult['safe'] && $localResult['score'] > $result['score']) {
                log_message('warning', "Local wordlist caught profanity Azure missed: " . implode(', ', $localResult['matched_words'] ?? []));
                $result['local_override'] = true;
                $result['safe'] = false;
                $result['score'] = max($result['score'], $localResult['score']);
                $result['categories'] = array_unique(array_merge($result['categories'], $localResult['categories']));
                $result['matched_words'] = $localResult['matched_words'] ?? [];
            }
            return $result;
        }

        // Fallback to OpenAI
        $result = $this->tryOpenAIText($text);
        if ($result !== false) {
            log_message('info', "OpenAI text moderation: safe=" . ($result['safe'] ? 'true' : 'false') . ", score=" . $result['score']);
            // Also run local check as a safety net
            $localResult = $this->localTextCheck($text);
            if (!$localResult['safe'] && $localResult['score'] > $result['score']) {
                log_message('warning', "Local wordlist caught profanity OpenAI missed: " . implode(', ', $localResult['matched_words'] ?? []));
                $result['local_override'] = true;
                $result['safe'] = false;
                $result['score'] = max($result['score'], $localResult['score']);
                $result['categories'] = array_unique(array_merge($result['categories'], $localResult['categories']));
                $result['matched_words'] = $localResult['matched_words'] ?? [];
            }
            return $result;
        }

        // Last resort: local wordlist
        log_message('info', "Using local wordlist for text moderation");
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
        // Comprehensive Hindi/Hinglish profanity list with common variations/misspellings
        $bannedWords = [
            // English profanity
            'fuck', 'fucking', 'fucker', 'fucked', 'shit', 'bitch', 'asshole', 'bastard', 'damn', 'cunt', 'dick', 'penis', 'vagina', 'pussy',
            
            // Hindi/Hinglish profanity (various spellings)
            'madarchod', 'madarchodd', 'madarchot', 'maderchod', 'maderchot', 'mc',
            'macdrcchod', 'macdrchod', 'madrchod', 'madrcchod',  // common misspellings
            'bhenchod', 'behenchod', 'banchod', 'benchod', 'bhenchot', 'bc',
            'chutiya', 'chutiye', 'chutia', 'chutiyo', 'choot', 'chut',
            'gaand', 'gand', 'gaandu', 'gandu',
            'lund', 'lauda', 'loda', 'lavda', 'lawda',
            'randi', 'raand', 'rand',
            'bhosdike', 'bsdk', 'bhosdiwale', 'bhosdika',
            'harami', 'haramkhor', 'haram',
            'kutte', 'kutta', 'kutiya', 'kutia',
            'kamina', 'kamine', 'kameena', 'kameene',
            'saala', 'sala', 'saale', 'sale',
            'chod', 'choda', 'chodi', 'chodna',
            'maa ki', 'maaki', 'teri maa', 'teri ma',
            'baap ki', 'baapki', 'tera baap',
            'jhaat', 'jhat', 'jhatu',
            'tatte', 'tatti', 'tatte',
            'ullu', 'gadha', 'gadhe',
            
            // Tamil profanity
            'thevdiya', 'thevidiya', 'otha', 'punda', 'pundai', 'sunni', 'thayoli', 'oombu',
            
            // Telugu profanity  
            'lanja', 'lanjakodaka', 'pooka', 'modda', 'dengey', 'gudda',
            
            // Violence/hate
            'kill', 'murder', 'terrorist', 'bomb', 'attack', 'rape', 'suicide'
        ];
        
        // Phonetic/fuzzy patterns - common mistranscriptions of Hindi profanity
        $phoneticPatterns = [
            // "madarchod" - many variations and misspellings
            '/\bmother\s*ch[aou]+[dt]?\b/i' => 'madarchod',
            '/\bmadar\s*ch[aou]+[dt]?\b/i' => 'madarchod',
            '/\bmader\s*ch[aou]+[dt]?\b/i' => 'madarchod',
            '/\bma+[dt]ar?\s*cho+[dt]?\b/i' => 'madarchod',
            '/\bmadr[c]?ch/i' => 'madarchod',
            '/\bmadrc+h/i' => 'madarchod',
            '/\bmadc+h/i' => 'madarchod',
            '/\bmacdr/i' => 'madarchod',
            '/\bm[ae]c?dr/i' => 'madarchod',  // catches "macdr", "madr", "mecdr"
            // "le madarchod" often transcribed with "la" or "lay" or "let"
            '/\b(le|la|lay|let)[\'s]?\s*(ma|mo).*ch[aou]+[dt]?\b/i' => 'le madarchod',
            // "bhenchod" patterns
            '/\bben\s*ch[aou]+[dt]?\b/i' => 'bhenchod',
            '/\bbhen\s*ch/i' => 'bhenchod',
            '/\bsister\s*f[u]+ck/i' => 'bhenchod',
            // "chutiya" patterns  
            '/\bchoo+t[iy]+[ae]?\b/i' => 'chutiya',
            '/\bchut[iy]/i' => 'chutiya',
            // "gaandu/gandu" patterns
            '/\bg[au]+n?d[u]+\b/i' => 'gandu',
            // "bhosdike" patterns
            '/\bb[ho]+s[dt]i?k[ea]?\b/i' => 'bhosdike',
            '/\bbosdi/i' => 'bhosdike',
            '/\bbosri/i' => 'bhosdike',  // common mistranscription
            // "randi" patterns
            '/\br[au]n?di\b/i' => 'randi',
            // "lund/lauda" patterns
            '/\bl[au]+n?d[a]?\b/i' => 'lund',
            '/\bl[ao]+d[a]+\b/i' => 'lauda',
        ];

        $lower = strtolower($text);
        // Remove common separators that might be used to evade detection
        $normalized = preg_replace('/[\s\-_\.]+/', '', $lower);
        
        $found = [];

        // Direct word matching
        foreach ($bannedWords as $word) {
            // Check both original text and normalized version
            if (str_contains($lower, $word) || str_contains($normalized, $word)) {
                $found[] = $word;
            }
        }
        
        // Phonetic/fuzzy pattern matching
        foreach ($phoneticPatterns as $pattern => $represents) {
            if (preg_match($pattern, $text)) {
                $found[] = $represents . ' (phonetic)';
                log_message('info', "Phonetic profanity match: '{$represents}' via pattern in text");
            }
        }

        $score = min(1.0, count($found) * 0.4); // Increased weight per word

        log_message('info', "Local profanity check: found " . count($found) . " matches: " . implode(', ', $found));

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

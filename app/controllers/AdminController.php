<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\Script;
use App\Models\Season;
use App\Models\User;
use App\Models\Video;
use PDO;

class AdminController
{
    private PDO $db;
    private Script $scriptModel;
    private Season $seasonModel;
    private User $userModel;
    private Video $videoModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->scriptModel = new Script();
        $this->seasonModel = new Season();
        $this->userModel = new User();
        $this->videoModel = new Video();
    }

    /**
     * Check admin access - returns false and sends error response if not admin
     */
    private function requireAdmin(): bool
    {
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return false;
        }
        return true;
    }

    /**
     * Verify CSRF token
     */
    private function verifyCsrf(): bool
    {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return false;
        }
        return true;
    }

    // ==================== SCRIPTS ====================

    /**
     * Create a new script
     */
    public function createScript(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = trim($_POST['difficulty'] ?? 'beginner');
        $durationHint = trim($_POST['duration_hint'] ?? '');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (strlen($content) < 10) $errors[] = 'Content must be at least 10 characters';
        if (!in_array($category, ['actor', 'director', 'writer'])) $errors[] = 'Invalid category';
        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) $errors[] = 'Invalid difficulty';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $id = $this->scriptModel->create([
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'difficulty' => $difficulty,
                'duration_hint' => $durationHint ?: null,
            ]);

            debug_log("Admin created script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Script created successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CREATE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create script']);
        }
    }

    /**
     * Update an existing script
     */
    public function updateScript(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = trim($_POST['difficulty'] ?? 'beginner');
        $durationHint = trim($_POST['duration_hint'] ?? '');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (strlen($content) < 10) $errors[] = 'Content must be at least 10 characters';
        if (!in_array($category, ['actor', 'director', 'writer'])) $errors[] = 'Invalid category';
        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) $errors[] = 'Invalid difficulty';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $this->scriptModel->update($id, [
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'difficulty' => $difficulty,
                'duration_hint' => $durationHint ?: null,
            ]);

            debug_log("Admin updated script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Script updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update script']);
        }
    }

    /**
     * Soft-delete a script
     */
    public function deleteScript(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $this->scriptModel->delete($id);
            debug_log("Admin deleted script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Script deleted successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete script']);
        }
    }

    // ==================== SEASONS ====================

    /**
     * Create a new season
     */
    public function createSeason(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $brief = trim($_POST['brief'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (empty($startDate)) $errors[] = 'Start date is required';
        if (empty($endDate)) $errors[] = 'End date is required';
        if (!in_array($status, ['active', 'closed', 'upcoming'])) $errors[] = 'Invalid status';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $id = $this->seasonModel->create([
                'title' => $title,
                'brief' => $brief,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            debug_log("Admin created season ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Season created successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CREATE_SEASON');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create season']);
        }
    }

    /**
     * Update an existing season
     */
    public function updateSeason(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $brief = trim($_POST['brief'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (empty($startDate)) $errors[] = 'Start date is required';
        if (empty($endDate)) $errors[] = 'End date is required';
        if (!in_array($status, ['active', 'closed', 'upcoming'])) $errors[] = 'Invalid status';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $this->seasonModel->update($id, [
                'title' => $title,
                'brief' => $brief,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            debug_log("Admin updated season ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Season updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_SEASON');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update season']);
        }
    }

    // ==================== USERS ====================

    /**
     * Delete a user (admin only)
     */
    public function deleteUser(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        // Don't allow deleting self
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account from admin panel']);
            return;
        }

        try {
            // Delete user's videos first
            $stmt = $this->db->prepare("DELETE FROM videos WHERE user_id = ?");
            $stmt->execute([$id]);

            // Delete the user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            debug_log("Admin deleted user ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_USER');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    }

    // ==================== VIDEOS ====================

    /**
     * Get all videos (for admin video management)
     */
    public function allVideos(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $stmt = $this->db->query(
                "SELECT v.*, u.name as user_name, u.role as user_role, s.title as season_title
                 FROM videos v
                 JOIN users u ON v.user_id = u.id
                 JOIN seasons s ON v.season_id = s.id
                 ORDER BY v.created_at DESC"
            );
            $videos = $stmt->fetchAll();
            echo json_encode($videos);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_ALL_VIDEOS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch videos']);
        }
    }

    /**
     * Delete a single video (permanently - removes file and DB record)
     */
    public function deleteVideo(int $videoId): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            // Get video info first
            $video = $this->videoModel->findById($videoId);
            if (!$video) {
                http_response_code(404);
                echo json_encode(['error' => 'Video not found']);
                return;
            }

            // Delete the file from server
            $filePath = UPLOAD_PATH . '/' . $video['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
                debug_log("Deleted file: {$filePath}", 'ADMIN');
            }

            // Delete moderation logs
            $stmt = $this->db->prepare("DELETE FROM moderation_logs WHERE video_id = ?");
            $stmt->execute([$videoId]);

            // Delete the video record
            $stmt = $this->db->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$videoId]);

            debug_log("Admin deleted video ID: {$videoId} (file: {$video['file_path']})", 'ADMIN');
            log_message('info', "Admin deleted video {$videoId}: {$video['title']}");

            echo json_encode(['success' => true, 'message' => 'Video deleted permanently']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_VIDEO');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete video: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk delete videos (permanently - removes files and DB records)
     */
    public function bulkDeleteVideos(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $videoIds = json_decode($_POST['video_ids'] ?? '[]', true);
        if (empty($videoIds) || !is_array($videoIds)) {
            http_response_code(422);
            echo json_encode(['error' => 'No videos selected']);
            return;
        }

        try {
            $deleted = 0;
            $errors = [];

            foreach ($videoIds as $videoId) {
                $videoId = (int) $videoId;
                
                // Get video info
                $video = $this->videoModel->findById($videoId);
                if (!$video) {
                    $errors[] = "Video {$videoId} not found";
                    continue;
                }

                // Delete the file from server
                $filePath = UPLOAD_PATH . '/' . $video['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Delete moderation logs
                $stmt = $this->db->prepare("DELETE FROM moderation_logs WHERE video_id = ?");
                $stmt->execute([$videoId]);

                // Delete the video record
                $stmt = $this->db->prepare("DELETE FROM videos WHERE id = ?");
                $stmt->execute([$videoId]);

                $deleted++;
            }

            debug_log("Admin bulk deleted {$deleted} videos", 'ADMIN');
            log_message('info', "Admin bulk deleted {$deleted} videos");

            echo json_encode([
                'success' => true, 
                'deleted' => $deleted,
                'errors' => $errors,
                'message' => "{$deleted} video(s) deleted permanently"
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_BULK_DELETE_VIDEOS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete videos: ' . $e->getMessage()]);
        }
    }

    // ==================== GUIDES ====================

    /**
     * Update a guide text for a role
     */
    public function updateGuide(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $role = trim($_POST['role'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!in_array($role, ['actor', 'director', 'writer'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid role']);
            return;
        }

        if (strlen($content) < 10) {
            http_response_code(422);
            echo json_encode(['error' => 'Guide content must be at least 10 characters']);
            return;
        }

        try {
            $settingsModel = new \App\Models\Settings();
            $settingsModel->updateGuide($role, $content);
            
            debug_log("Admin updated guide for role: $role", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Guide updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_GUIDE');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update guide']);
        }
    }

    /**
     * Get AI configuration and provider status
     */
    public function getAIConfig(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $aiSettings = $settingsModel->getAISettings();
            
            // Get provider status
            $moderationService = new \App\Services\ContentModerationService();
            $providerStatus = $moderationService->getProviderStatus();
            
            // Check transcription service
            $transcriptionService = new \App\Services\TranscriptionService();
            $providerStatus['groq'] = $transcriptionService->isAvailable();

            echo json_encode([
                'success' => true,
                'settings' => $aiSettings,
                'providers' => $providerStatus,
                'providers_info' => [
                    'azure' => [
                        'name' => 'Azure AI Content Safety',
                        'type' => 'text,image',
                        'free_tier' => '5K text + 5K images/month',
                        'docs' => 'https://azure.microsoft.com/products/ai-services/content-safety'
                    ],
                    'openai' => [
                        'name' => 'OpenAI Moderation',
                        'type' => 'text',
                        'free_tier' => 'Unlimited free',
                        'docs' => 'https://platform.openai.com/docs/guides/moderation'
                    ],
                    'sightengine' => [
                        'name' => 'SightEngine',
                        'type' => 'image',
                        'free_tier' => '500/month',
                        'docs' => 'https://sightengine.com'
                    ],
                    'rapidapi' => [
                        'name' => 'API4AI NSFW (RapidAPI)',
                        'type' => 'image',
                        'free_tier' => '~100/day',
                        'docs' => 'https://rapidapi.com/api4ai-api4ai-default/api/nsfw3'
                    ],
                    'groq' => [
                        'name' => 'Groq Whisper',
                        'type' => 'transcription',
                        'free_tier' => 'Unlimited (30 RPM)',
                        'docs' => 'https://console.groq.com'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_AI_CONFIG');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get AI configuration']);
        }
    }

    /**
     * Update AI configuration
     */
    public function updateAIConfig(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $settings = [];
        $allowedKeys = [
            'ai_text_providers', 'ai_image_providers', 'ai_transcription_provider',
            'ai_processing_enabled', 'ai_approve_threshold', 'ai_flag_threshold',
            'ai_nsfw_reject_threshold', 'ai_nsfw_flag_threshold',
            'ai_profanity_reject_threshold', 'ai_profanity_flag_threshold',
            'ai_auto_approve', 'ai_min_duration', 'ai_max_duration'
        ];

        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $settings[$key] = $_POST[$key];
            }
        }

        if (empty($settings)) {
            http_response_code(422);
            echo json_encode(['error' => 'No settings provided']);
            return;
        }

        try {
            $settingsModel = new \App\Models\Settings();
            $settingsModel->updateAISettings($settings);
            
            debug_log("Admin updated AI settings: " . json_encode(array_keys($settings)), 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'AI settings updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_AI_CONFIG');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update AI settings']);
        }
    }

    /**
     * Update API keys in .env file or environment
     */
    public function updateAPIKeys(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        // Check for .env file - if not exists, store in database settings instead
        $envFile = BASE_PATH . '/.env';
        $useEnvFile = file_exists($envFile) && is_writable($envFile);

    // Allowed keys that can be updated
        $allowedKeys = [
            'AZURE_CONTENT_SAFETY_ENDPOINT',
            'AZURE_CONTENT_SAFETY_KEY',
            'OPENAI_API_KEY',
            'SIGHTENGINE_API_USER',
            'SIGHTENGINE_API_SECRET',
            'RAPIDAPI_KEY',
            'GROQ_API_KEY',
            'FFMPEG_PATH',
            'FFPROBE_PATH',
            'YOUTUBE_API_KEY',
            'YOUTUBE_CLIENT_ID',
            'YOUTUBE_CLIENT_SECRET',
            'YOUTUBE_REFRESH_TOKEN',
            'YOUTUBE_CHANNEL_ID',
        ];

        $updates = [];
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key]) && $_POST[$key] !== '') {
                $updates[$key] = trim($_POST[$key]);
            }
        }

        if (empty($updates)) {
            http_response_code(422);
            echo json_encode(['error' => 'No valid keys provided']);
            return;
        }

        try {
            if ($useEnvFile) {
                // Update .env file
                $content = file_get_contents($envFile);
                
                foreach ($updates as $key => $value) {
                    // Escape special characters for .env
                    $escapedValue = $value;
                    if (preg_match('/[\s#]/', $value)) {
                        $escapedValue = '"' . addslashes($value) . '"';
                    }
                    
                    // Check if key exists and update, or add new
                    if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
                        $content = preg_replace(
                            '/^' . preg_quote($key, '/') . '=.*/m',
                            $key . '=' . $escapedValue,
                            $content
                        );
                    } else {
                        // Add new key at the end
                        $content = rtrim($content) . "\n" . $key . '=' . $escapedValue . "\n";
                    }
                }

                file_put_contents($envFile, $content);
            } else {
                // No .env file - store in database settings table
                $settingsModel = new \App\Models\Settings();
                foreach ($updates as $key => $value) {
                    $settingsModel->set('env_' . $key, $value, 'api_keys', "API Key: $key");
                }
            }
            
            debug_log("Admin updated API keys: " . implode(', ', array_keys($updates)), 'ADMIN');
            echo json_encode([
                'success' => true, 
                'message' => 'API keys updated successfully.' . (!$useEnvFile ? ' (Stored in database - add to hosting env vars for full effect)' : ''),
                'updated' => array_keys($updates),
                'storage' => $useEnvFile ? 'env_file' : 'database'
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_API_KEYS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update API keys: ' . $e->getMessage()]);
        }
    }

    /**
     * Get current API key status (masked)
     */
    public function getAPIKeyStatus(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        // Try to get from environment variables first, then from database settings
        $settingsModel = new \App\Models\Settings();
        
        $keyNames = [
            'AZURE_CONTENT_SAFETY_ENDPOINT',
            'AZURE_CONTENT_SAFETY_KEY',
            'OPENAI_API_KEY',
            'SIGHTENGINE_API_USER',
            'SIGHTENGINE_API_SECRET',
            'RAPIDAPI_KEY',
            'GROQ_API_KEY',
            'FFMPEG_PATH',
            'FFPROBE_PATH',
            'YOUTUBE_API_KEY',
            'YOUTUBE_CLIENT_ID',
            'YOUTUBE_CLIENT_SECRET',
            'YOUTUBE_REFRESH_TOKEN',
            'YOUTUBE_CHANNEL_ID',
        ];
        
        $defaults = [
            'FFMPEG_PATH' => 'ffmpeg',
            'FFPROBE_PATH' => 'ffprobe',
        ];

        $status = [];
        foreach ($keyNames as $key) {
            // Check environment variable first
            $value = $_ENV[$key] ?? getenv($key) ?: '';
            
            // If not in env, check database
            if (empty($value)) {
                try {
                    $dbValue = $settingsModel->get('env_' . $key);
                    if ($dbValue) {
                        $value = $dbValue;
                    }
                } catch (\Exception $e) {
                    // Settings table might not exist yet
                }
            }
            
            // Apply defaults
            if (empty($value) && isset($defaults[$key])) {
                $value = $defaults[$key];
            }
            
            $status[$key] = [
                'configured' => !empty($value),
                'masked' => !empty($value) ? $this->maskValue($value, $key) : '',
            ];
        }

        echo json_encode(['success' => true, 'keys' => $status]);
    }

    /**
     * Mask sensitive values for display
     */
    private function maskValue(string $value, string $key): string
    {
        // Don't mask paths
        if (str_contains($key, 'PATH')) {
            return $value;
        }
        
        // Don't mask endpoints
        if (str_contains($key, 'ENDPOINT')) {
            return $value;
        }
        
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        
        return substr($value, 0, 4) . str_repeat('•', min($len - 8, 20)) . substr($value, -4);
    }

    /**
     * Test AI providers
     */
    public function testAIProvider(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $results = [];
            $settingsModel = new \App\Models\Settings();
            
            // Helper to get key from env or database
            $getKey = function($key) use ($settingsModel) {
                return $_ENV[$key] ?? getenv($key) ?: $settingsModel->get('env_' . $key) ?: '';
            };
            
            // Test OpenAI Moderation
            $openaiKey = $getKey('OPENAI_API_KEY');
            if (!empty($openaiKey)) {
                try {
                    $ch = curl_init('https://api.openai.com/v1/moderations');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $openaiKey,
                            'Content-Type: application/json'
                        ],
                        CURLOPT_POSTFIELDS => json_encode(['input' => 'test']),
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['openai'] = $httpCode === 200 ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['openai'] = 'Error';
                }
            } else {
                $results['openai'] = 'Not configured';
            }
            
            // Test Groq
            $groqKey = $getKey('GROQ_API_KEY');
            if (!empty($groqKey)) {
                try {
                    $ch = curl_init('https://api.groq.com/openai/v1/models');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $groqKey],
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['groq'] = $httpCode === 200 ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['groq'] = 'Error';
                }
            } else {
                $results['groq'] = 'Not configured';
            }
            
            // Test Azure
            $azureEndpoint = $getKey('AZURE_CONTENT_SAFETY_ENDPOINT');
            $azureKey = $getKey('AZURE_CONTENT_SAFETY_KEY');
            if (!empty($azureEndpoint) && !empty($azureKey)) {
                try {
                    $url = rtrim($azureEndpoint, '/') . '/contentsafety/text:analyze?api-version=2023-10-01';
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Ocp-Apim-Subscription-Key: ' . $azureKey,
                            'Content-Type: application/json'
                        ],
                        CURLOPT_POSTFIELDS => json_encode(['text' => 'test']),
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['azure'] = $httpCode === 200 ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['azure'] = 'Error';
                }
            } else {
                $results['azure'] = 'Not configured';
            }
            
            // Test SightEngine
            $sightUser = $getKey('SIGHTENGINE_API_USER');
            $sightSecret = $getKey('SIGHTENGINE_API_SECRET');
            if (!empty($sightUser) && !empty($sightSecret)) {
                try {
                    // Use a more reliable test image
                    $testImageUrl = 'https://sightengine.com/assets/img/examples/example1.jpg';
                    $url = 'https://api.sightengine.com/1.0/check.json?models=nudity-2.0&api_user=' . $sightUser . '&api_secret=' . $sightSecret . '&url=' . urlencode($testImageUrl);
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $data = json_decode($response, true);
                    if ($httpCode === 200 && isset($data['status']) && $data['status'] === 'success') {
                        $results['sightengine'] = 'OK';
                    } elseif ($httpCode === 200 && isset($data['error'])) {
                        // API responded - credentials are valid, but image download failed
                        // This means the API key is working!
                        $errorMsg = $data['error']['message'] ?? '';
                        if (stripos($errorMsg, 'download') !== false || stripos($errorMsg, 'media') !== false) {
                            $results['sightengine'] = 'OK (API Valid)';
                        } else {
                            $results['sightengine'] = substr($errorMsg, 0, 25);
                        }
                    } else {
                        $results['sightengine'] = 'HTTP ' . $httpCode;
                    }
                } catch (\Exception $e) {
                    $results['sightengine'] = 'Error';
                }
            } else {
                $results['sightengine'] = 'Not configured';
            }
            
            // Test RapidAPI (API4AI NSFW)
            $rapidKey = $getKey('RAPIDAPI_KEY');
            if (!empty($rapidKey)) {
                try {
                    // Use form-urlencoded as per RapidAPI docs
                    $ch = curl_init('https://nsfw3.p.rapidapi.com/v1/results');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'X-RapidAPI-Key: ' . $rapidKey,
                            'X-RapidAPI-Host: nsfw3.p.rapidapi.com',
                            'Content-Type: application/x-www-form-urlencoded'
                        ],
                        CURLOPT_POSTFIELDS => http_build_query([
                            'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/PNG_transparency_demonstration_1.png/280px-PNG_transparency_demonstration_1.png'
                        ]),
                        CURLOPT_TIMEOUT => 15
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['rapidapi'] = ($httpCode === 200) ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['rapidapi'] = 'Error';
                }
            } else {
                $results['rapidapi'] = 'Not configured';
            }
            
            // Test FFmpeg
            $ffmpegPath = $getKey('FFMPEG_PATH') ?: 'ffmpeg';
            $ffprobePath = $getKey('FFPROBE_PATH') ?: 'ffprobe';
            exec($ffmpegPath . ' -version 2>&1', $ffmpegOut, $ffmpegCode);
            exec($ffprobePath . ' -version 2>&1', $ffprobeOut, $ffprobeCode);
            $results['ffmpeg'] = $ffmpegCode === 0 ? 'OK (' . $ffmpegPath . ')' : 'Not found';
            $results['ffprobe'] = $ffprobeCode === 0 ? 'OK (' . $ffprobePath . ')' : 'Not found';
            
            // Test YouTube API
            $youtubeKey = $getKey('YOUTUBE_API_KEY');
            $youtubeClientId = $getKey('YOUTUBE_CLIENT_ID');
            $youtubeRefreshToken = $getKey('YOUTUBE_REFRESH_TOKEN');
            if (!empty($youtubeKey) && !empty($youtubeClientId) && !empty($youtubeRefreshToken)) {
                try {
                    // Test by getting access token
                    $ch = curl_init('https://oauth2.googleapis.com/token');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query([
                            'grant_type' => 'refresh_token',
                            'client_id' => $youtubeClientId,
                            'client_secret' => $getKey('YOUTUBE_CLIENT_SECRET'),
                            'refresh_token' => $youtubeRefreshToken,
                        ]),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $data = json_decode($response, true);
                    if ($httpCode === 200 && !empty($data['access_token'])) {
                        $results['youtube'] = 'OK';
                    } else {
                        $errorMsg = $data['error_description'] ?? $data['error'] ?? 'HTTP ' . $httpCode;
                        $results['youtube'] = substr($errorMsg, 0, 25);
                    }
                } catch (\Exception $e) {
                    $results['youtube'] = 'Error';
                }
            } else {
                $results['youtube'] = 'Not configured';
            }
            
            // Summary
            $okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK')));
            $total = count($results);
            
            debug_log("Admin tested AI providers: " . json_encode($results), 'ADMIN');
            echo json_encode([
                'success' => true, 
                'result' => [
                    'status' => "$okCount/$total services working",
                    'details' => $results
                ]
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_AI_PROVIDER');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Publish a video to YouTube
     */
    public function publishToYouTube(int $videoId): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $video = $this->videoModel->findById($videoId);
            if (!$video) {
                http_response_code(404);
                echo json_encode(['error' => 'Video not found']);
                return;
            }

            if ($video['status'] !== 'approved') {
                http_response_code(400);
                echo json_encode(['error' => 'Video must be approved before publishing']);
                return;
            }

            if (!empty($video['youtube_id'])) {
                echo json_encode(['success' => true, 'message' => 'Already published', 'youtube_id' => $video['youtube_id']]);
                return;
            }

            // Debug: Check YouTube credentials before attempting upload
            $settingsModel = new \App\Models\Settings();
            $getKey = function($key) use ($settingsModel) {
                $value = $_ENV[$key] ?? getenv($key) ?: '';
                if (empty($value)) {
                    try { $value = $settingsModel->get('env_' . $key) ?: ''; } catch (\Exception $e) {}
                }
                return $value;
            };
            
            $debugInfo = [];
            $clientId = $getKey('YOUTUBE_CLIENT_ID');
            $clientSecret = $getKey('YOUTUBE_CLIENT_SECRET');
            $refreshToken = $getKey('YOUTUBE_REFRESH_TOKEN');
            $apiKey = $getKey('YOUTUBE_API_KEY');
            $channelId = $getKey('YOUTUBE_CHANNEL_ID');
            
            $debugInfo['client_id'] = !empty($clientId) ? 'SET (' . strlen($clientId) . ' chars)' : 'MISSING';
            $debugInfo['client_secret'] = !empty($clientSecret) ? 'SET (' . strlen($clientSecret) . ' chars)' : 'MISSING';
            $debugInfo['refresh_token'] = !empty($refreshToken) ? 'SET (' . strlen($refreshToken) . ' chars)' : 'MISSING';
            $debugInfo['api_key'] = !empty($apiKey) ? 'SET (' . strlen($apiKey) . ' chars)' : 'MISSING';
            $debugInfo['channel_id'] = !empty($channelId) ? 'SET (' . strlen($channelId) . ' chars)' : 'MISSING';
            
            debug_log("YouTube credentials check: " . json_encode($debugInfo), 'ADMIN');

            $youtubeService = new \App\Services\YouTubeService();
            
            // Log video state before upload
            debug_log("Attempting YouTube publish for video {$videoId}: status={$video['status']}, ai_status=" . ($video['ai_status'] ?? 'null') . ", needs_review=" . ($video['needs_manual_review'] ?? 'null'), 'ADMIN');
            
            $youtubeId = $youtubeService->uploadVideo($videoId);

            if ($youtubeId) {
                if (is_array($youtubeId) && isset($youtubeId['error'])) {
                    // YouTubeService returned an error - include debug info
                    debug_log("YouTube publish failed: " . $youtubeId['error'], 'ADMIN');
                    echo json_encode([
                        'error' => 'YouTube publish failed: ' . $youtubeId['error'],
                        'debug' => $debugInfo
                    ]);
                    return;
                }
                debug_log("Admin published video {$videoId} to YouTube: {$youtubeId}", 'ADMIN');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Video published to YouTube',
                    'youtube_id' => $youtubeId,
                    'youtube_url' => 'https://youtube.com/watch?v=' . $youtubeId
                ]);
            } else {
                debug_log("YouTube upload returned null - credentials: " . json_encode($debugInfo), 'ADMIN');
                echo json_encode([
                    'error' => 'YouTube upload failed. Check API credentials and video file.',
                    'debug' => $debugInfo
                ]);
            }
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_YOUTUBE_PUBLISH');
            echo json_encode(['error' => 'YouTube upload error: ' . $e->getMessage()]);
        }
    }

    /**
     * Test YouTube connection only (also checks AI is ready)
     */
    public function testYouTube(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            
            $getKey = function($key) use ($settingsModel) {
                $value = $_ENV[$key] ?? getenv($key) ?: null;
                if (empty($value)) {
                    $value = $settingsModel->get('env_' . $key);
                }
                return $value;
            };

            $results = [
                'api_key' => false,
                'client_id' => false,
                'client_secret' => false,
                'refresh_token' => false,
                'channel_id' => false,
                'oauth_test' => false,
                'channel_access' => false,
                'ai_ready' => false,
            ];

            // Check each credential
            $apiKey = $getKey('YOUTUBE_API_KEY');
            $clientId = $getKey('YOUTUBE_CLIENT_ID');
            $clientSecret = $getKey('YOUTUBE_CLIENT_SECRET');
            $refreshToken = $getKey('YOUTUBE_REFRESH_TOKEN');
            $channelId = $getKey('YOUTUBE_CHANNEL_ID');

            $results['api_key'] = !empty($apiKey);
            $results['client_id'] = !empty($clientId);
            $results['client_secret'] = !empty($clientSecret);
            $results['refresh_token'] = !empty($refreshToken);
            $results['channel_id'] = !empty($channelId);

            $accessToken = null;
            $errorMessage = null;

            // Test OAuth token refresh
            if ($results['client_id'] && $results['client_secret'] && $results['refresh_token']) {
                $ch = curl_init('https://oauth2.googleapis.com/token');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query([
                        'grant_type' => 'refresh_token',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'refresh_token' => $refreshToken,
                    ]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $data = json_decode($response, true);
                if ($httpCode === 200 && !empty($data['access_token'])) {
                    $results['oauth_test'] = true;
                    $accessToken = $data['access_token'];
                } else {
                    $errorMessage = $data['error_description'] ?? $data['error'] ?? 'OAuth failed';
                }
            }

            // Test channel access if we have access token
            if ($accessToken && $results['channel_id']) {
                $ch = curl_init('https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . urlencode($channelId));
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $data = json_decode($response, true);
                if ($httpCode === 200 && !empty($data['items'])) {
                    $results['channel_access'] = true;
                    $results['channel_name'] = $data['items'][0]['snippet']['title'] ?? 'Unknown';
                }
            }

            // Check if AI is ready (at least one text + one image provider working)
            $aiWorking = 0;
            
            // Check Azure
            $azureEndpoint = $getKey('AZURE_CONTENT_SAFETY_ENDPOINT');
            $azureKey = $getKey('AZURE_CONTENT_SAFETY_KEY');
            if (!empty($azureEndpoint) && !empty($azureKey)) {
                $aiWorking++;
            }
            
            // Check SightEngine
            $seUser = $getKey('SIGHTENGINE_API_USER');
            $seSecret = $getKey('SIGHTENGINE_API_SECRET');
            if (!empty($seUser) && !empty($seSecret)) {
                $aiWorking++;
            }
            
            // Check Groq (transcription)
            $groqKey = $getKey('GROQ_API_KEY');
            if (!empty($groqKey)) {
                $aiWorking++;
            }
            
            // Check FFmpeg
            $ffmpegPath = $getKey('FFMPEG_PATH') ?: 'ffmpeg';
            exec($ffmpegPath . ' -version 2>&1', $ffmpegOut, $ffmpegCode);
            if ($ffmpegCode === 0) {
                $aiWorking++;
            }
            
            $results['ai_ready'] = ($aiWorking >= 3); // Need at least 3 AI services configured
            $results['ai_services'] = $aiWorking;

            // Summary
            $youtubeChecks = ['api_key', 'client_id', 'client_secret', 'refresh_token', 'channel_id', 'oauth_test', 'channel_access'];
            $youtubePassed = count(array_filter($youtubeChecks, fn($k) => $results[$k] === true));

            echo json_encode([
                'success' => true,
                'results' => $results,
                'youtube_passed' => $youtubePassed,
                'youtube_total' => 7,
                'youtube_ready' => ($youtubePassed >= 6),
                'ai_ready' => $results['ai_ready'],
                'all_ready' => ($youtubePassed >= 6 && $results['ai_ready']),
                'error' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_YOUTUBE');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Test AI providers only (no YouTube)
     */
    public function testAI(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $results = [];
            
            $getKey = function($key) use ($settingsModel) {
                $value = $_ENV[$key] ?? getenv($key) ?: null;
                if (empty($value)) {
                    $value = $settingsModel->get('env_' . $key);
                }
                return $value;
            };

            // Test Azure
            $azureEndpoint = $getKey('AZURE_CONTENT_SAFETY_ENDPOINT');
            $azureKey = $getKey('AZURE_CONTENT_SAFETY_KEY');
            if (!empty($azureEndpoint) && !empty($azureKey)) {
                try {
                    $ch = curl_init(rtrim($azureEndpoint, '/') . '/contentsafety/text:analyze?api-version=2023-10-01');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Ocp-Apim-Subscription-Key: ' . $azureKey],
                        CURLOPT_POSTFIELDS => json_encode(['text' => 'test', 'categories' => ['Hate']]),
                        CURLOPT_TIMEOUT => 15
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['azure'] = ($httpCode === 200) ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['azure'] = 'Error';
                }
            } else {
                $results['azure'] = 'Not configured';
            }

            // Test OpenAI
            $openaiKey = $getKey('OPENAI_API_KEY');
            if (!empty($openaiKey)) {
                try {
                    $ch = curl_init('https://api.openai.com/v1/models');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $openaiKey],
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['openai'] = ($httpCode === 200) ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['openai'] = 'Error';
                }
            } else {
                $results['openai'] = 'Not configured';
            }

            // Test SightEngine
            $seUser = $getKey('SIGHTENGINE_API_USER');
            $seSecret = $getKey('SIGHTENGINE_API_SECRET');
            if (!empty($seUser) && !empty($seSecret)) {
                try {
                    $testImageUrl = 'https://sightengine.com/assets/img/examples/example7.jpg';
                    $ch = curl_init('https://api.sightengine.com/1.0/check.json?' . http_build_query([
                        'models' => 'nudity-2.0',
                        'api_user' => $seUser,
                        'api_secret' => $seSecret,
                        'url' => $testImageUrl
                    ]));
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $data = json_decode($response, true);
                    $results['sightengine'] = ($httpCode === 200 && ($data['status'] ?? '') === 'success') ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['sightengine'] = 'Error';
                }
            } else {
                $results['sightengine'] = 'Not configured';
            }

            // Test Groq
            $groqKey = $getKey('GROQ_API_KEY');
            if (!empty($groqKey)) {
                try {
                    $ch = curl_init('https://api.groq.com/openai/v1/models');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $groqKey],
                        CURLOPT_TIMEOUT => 10
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $results['groq'] = ($httpCode === 200) ? 'OK' : 'HTTP ' . $httpCode;
                } catch (\Exception $e) {
                    $results['groq'] = 'Error';
                }
            } else {
                $results['groq'] = 'Not configured';
            }

            // Test FFmpeg
            $ffmpegPath = $getKey('FFMPEG_PATH') ?: 'ffmpeg';
            $ffprobePath = $getKey('FFPROBE_PATH') ?: 'ffprobe';
            exec($ffmpegPath . ' -version 2>&1', $ffmpegOut, $ffmpegCode);
            exec($ffprobePath . ' -version 2>&1', $ffprobeOut, $ffprobeCode);
            $results['ffmpeg'] = $ffmpegCode === 0 ? 'OK' : 'Not found';
            $results['ffprobe'] = $ffprobeCode === 0 ? 'OK' : 'Not found';

            // Summary
            $okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK')));
            $total = count($results);
            
            echo json_encode([
                'success' => true, 
                'results' => $results,
                'passed' => $okCount,
                'total' => $total,
                'ready' => ($okCount >= 4), // Need at least 4 services working
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_AI');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Send test email
     */
    public function testEmail(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $email = trim($_POST['email'] ?? '');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Invalid email address']);
            return;
        }

        try {
            $emailService = new \App\Services\EmailService();
            $result = $emailService->sendTestEmail($email);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully! Check your inbox.']);
            } else {
                // Get detailed error from EmailService
                $error = $emailService->getLastError();
                $errorMsg = $error ?: 'Failed to send email. Check SMTP settings.';
                echo json_encode(['success' => false, 'error' => $errorMsg]);
            }
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_EMAIL');
            echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
        }
    }

    /**
     * Get email settings
     */
    public function getEmailSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $emailSettings = $settingsModel->getEmailSettings();
            
            $emailService = new \App\Services\EmailService();
            $configStatus = $emailService->getConfigStatus();
            $notificationSettings = $emailService->getNotificationSettings();
            
            echo json_encode([
                'success' => true,
                'config' => $configStatus,
                'settings' => $emailSettings,
                'notifications' => $notificationSettings,
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_EMAIL_SETTINGS');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update email/SMTP settings
     */
    public function updateEmailSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            
            // Collect all email settings from POST
            $settings = [];
            
            // Email provider selection
            if (isset($_POST['email_provider'])) $settings['email_provider'] = trim($_POST['email_provider']);
            
            // SMTP Settings
            if (isset($_POST['smtp_host'])) $settings['smtp_host'] = trim($_POST['smtp_host']);
            if (isset($_POST['smtp_port'])) $settings['smtp_port'] = trim($_POST['smtp_port']);
            if (isset($_POST['smtp_username'])) $settings['smtp_username'] = trim($_POST['smtp_username']);
            if (isset($_POST['smtp_password']) && !empty($_POST['smtp_password'])) {
                $settings['smtp_password'] = $_POST['smtp_password']; // Don't trim passwords
            }
            if (isset($_POST['smtp_encryption'])) $settings['smtp_encryption'] = trim($_POST['smtp_encryption']);
            if (isset($_POST['smtp_from_address'])) $settings['smtp_from_address'] = trim($_POST['smtp_from_address']);
            if (isset($_POST['smtp_from_name'])) $settings['smtp_from_name'] = trim($_POST['smtp_from_name']);
            
            // Resend Settings
            if (isset($_POST['resend_api_key']) && !empty($_POST['resend_api_key'])) {
                $settings['resend_api_key'] = trim($_POST['resend_api_key']);
            }
            if (isset($_POST['resend_from_address'])) $settings['resend_from_address'] = trim($_POST['resend_from_address']);
            if (isset($_POST['resend_from_name'])) $settings['resend_from_name'] = trim($_POST['resend_from_name']);
            
            // Notification toggles (checkboxes - will be '1' if checked, absent if not)
            $settings['email_notify_signup'] = isset($_POST['email_notify_signup']) ? '1' : '0';
            $settings['email_notify_submit'] = isset($_POST['email_notify_submit']) ? '1' : '0';
            $settings['email_notify_processing'] = isset($_POST['email_notify_processing']) ? '1' : '0';
            $settings['email_notify_approved'] = isset($_POST['email_notify_approved']) ? '1' : '0';
            $settings['email_notify_rejected'] = isset($_POST['email_notify_rejected']) ? '1' : '0';
            $settings['email_notify_flagged'] = isset($_POST['email_notify_flagged']) ? '1' : '0';
            
            // Admin notifications
            if (isset($_POST['email_admin_address'])) $settings['email_admin_address'] = trim($_POST['email_admin_address']);
            $settings['email_admin_new_video'] = isset($_POST['email_admin_new_video']) ? '1' : '0';
            $settings['email_admin_flagged'] = isset($_POST['email_admin_flagged']) ? '1' : '0';
            
            debug_log("Saving email settings: " . json_encode(array_keys($settings)), 'ADMIN');
            
            // Save to database
            $result = $settingsModel->updateEmailSettings($settings);
            
            if ($result) {
                debug_log("Admin updated email settings", 'ADMIN');
                echo json_encode(['success' => true, 'message' => 'Email settings saved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
            }
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_EMAIL_SETTINGS');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

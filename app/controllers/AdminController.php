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

        $title            = trim($_POST['title']             ?? '');
        $content          = trim($_POST['content']           ?? '');
        $category         = trim($_POST['category']          ?? '');
        $difficulty       = trim($_POST['difficulty']        ?? 'beginner');
        $durationHint     = trim($_POST['duration_hint']     ?? '');
        $auditionType     = trim($_POST['audition_type']     ?? '');
        $imageUrl         = trim($_POST['image_url']         ?? '');
        $previewVideoUrl  = trim($_POST['preview_video_url'] ?? '');
        $scriptPdfUrl     = trim($_POST['script_pdf_url']    ?? '');
        $tuneYoutubeUrl   = trim($_POST['tune_youtube_url']  ?? '');
        $rules            = trim($_POST['rules']             ?? '');

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
                'title'             => $title,
                'content'           => $content,
                'category'          => $category,
                'difficulty'        => $difficulty,
                'duration_hint'     => $durationHint ?: null,
                'audition_type'     => $auditionType ?: null,
                'image_url'         => $imageUrl ?: null,
                'preview_video_url' => $previewVideoUrl ?: null,
                'script_pdf_url'    => $scriptPdfUrl ?: null,
                'tune_youtube_url'  => $tuneYoutubeUrl ?: null,
                'rules'             => $rules ?: null,
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

        $title        = trim($_POST['title']         ?? '');
        $content      = trim($_POST['content']       ?? '');
        $category     = trim($_POST['category']      ?? '');
        $difficulty   = trim($_POST['difficulty']    ?? 'beginner');
        $durationHint = trim($_POST['duration_hint'] ?? '');
        $auditionType = trim($_POST['audition_type'] ?? '');
        $imageUrl     = trim($_POST['image_url']     ?? '');
        $previewVideoUrl = trim($_POST['preview_video_url'] ?? '');
        $scriptPdfUrl    = trim($_POST['script_pdf_url']    ?? '');
        $tuneYoutubeUrl  = trim($_POST['tune_youtube_url']  ?? '');
        $rules        = trim($_POST['rules']         ?? '');

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
                'title'             => $title,
                'content'           => $content,
                'category'          => $category,
                'difficulty'        => $difficulty,
                'duration_hint'     => $durationHint ?: null,
                'audition_type'     => $auditionType ?: null,
                'image_url'         => $imageUrl ?: null,
                'preview_video_url' => $previewVideoUrl ?: null,
                'script_pdf_url'    => $scriptPdfUrl ?: null,
                'tune_youtube_url'  => $tuneYoutubeUrl ?: null,
                'rules'             => $rules ?: null,
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
                    $results['oauth_error'] = [
                        'message' => $errorMessage,
                        'http_code' => $httpCode,
                        'response' => $data
                    ];
                    log_message('error', "YouTube OAuth test failed: HTTP {$httpCode}, " . json_encode($data));
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
            http_response_code(500);
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

    /**
     * Get YouTube publish status and queue
     */
    public function getYouTubeStatus(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $autoPublish = $settingsModel->isYouTubeAutoPublishEnabled();
            
            // Get videos ready for YouTube (approved but not published)
            $stmt = $this->db->query(
                "SELECT v.id, v.title, v.content_type, v.status, v.ai_status, v.ai_score, 
                        v.youtube_id, v.youtube_status, v.created_at, v.moderated_at,
                        u.name as user_name, u.role as user_role
                 FROM videos v
                 JOIN users u ON v.user_id = u.id
                 WHERE v.status = 'approved' 
                 AND (v.ai_status = 'approved' OR v.ai_status = 'flagged')
                 AND v.youtube_id IS NULL
                 ORDER BY v.moderated_at ASC"
            );
            $publishQueue = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'auto_publish' => $autoPublish,
                'queue_count' => count($publishQueue),
                'queue' => $publishQueue
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_YOUTUBE_STATUS');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Toggle YouTube auto-publish
     */
    public function toggleYouTubePublish(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $currentState = $settingsModel->isYouTubeAutoPublishEnabled();
            $newState = !$currentState;
            
            $result = $settingsModel->setYouTubeAutoPublish($newState);
            
            if ($result) {
                debug_log("YouTube auto-publish toggled to: " . ($newState ? 'enabled' : 'disabled'), 'ADMIN');
                echo json_encode([
                    'success' => true,
                    'auto_publish' => $newState,
                    'message' => 'YouTube auto-publish ' . ($newState ? 'enabled' : 'paused')
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to toggle setting']);
            }
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TOGGLE_YOUTUBE');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk publish videos to YouTube
     */
    public function bulkPublishYouTube(): void
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
            $youtubeService = new \App\Services\YouTubeService();
            $published = 0;
            $errors = [];

            foreach ($videoIds as $videoId) {
                $videoId = (int) $videoId;
                $result = $youtubeService->uploadVideo($videoId);
                
                if (isset($result['youtube_id'])) {
                    $published++;
                } else {
                    $errors[] = "Video {$videoId}: " . ($result['error'] ?? 'Unknown error');
                }
                
                // Small delay to avoid rate limits
                usleep(500000);
            }

            debug_log("Admin bulk published {$published} videos to YouTube", 'ADMIN');
            
            echo json_encode([
                'success' => true,
                'published' => $published,
                'errors' => $errors,
                'message' => "{$published} video(s) published to YouTube"
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_BULK_PUBLISH_YOUTUBE');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to publish videos: ' . $e->getMessage()]);
        }
    }

    /**
     * Refresh videos data (for silent refresh)
     */
    public function refreshVideos(): void
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
            
            // Get AI settings for status
            $settingsModel = new \App\Models\Settings();
            $aiSettings = $settingsModel->getAISettings();
            
            echo json_encode([
                'success' => true,
                'videos' => $videos,
                'youtube_auto_publish' => $aiSettings['youtube_auto_publish'] ?? '1'
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_REFRESH_VIDEOS');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get Google OAuth settings
     */
    public function getGoogleSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            $googleService = new \App\Services\GoogleAuthService();
            
            $configStatus = $googleService->getConfigStatus();
            
            echo json_encode([
                'success' => true,
                'config' => $configStatus,
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_GOOGLE_SETTINGS');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update Google OAuth settings
     */
    public function updateGoogleSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            
            $settings = [];
            
            // Only update if provided (don't clear existing values)
            if (isset($_POST['google_client_id']) && !empty($_POST['google_client_id'])) {
                $settings['google_client_id'] = trim($_POST['google_client_id']);
            }
            if (isset($_POST['google_client_secret']) && !empty($_POST['google_client_secret'])) {
                $settings['google_client_secret'] = trim($_POST['google_client_secret']);
            }
            
            if (empty($settings)) {
                echo json_encode(['success' => false, 'error' => 'No settings to update']);
                return;
            }
            
            // Save each setting
            foreach ($settings as $key => $value) {
                $settingsModel->set($key, $value);
            }
            
            debug_log("Admin updated Google OAuth settings", 'ADMIN');
            
            // Get updated status
            $googleService = new \App\Services\GoogleAuthService();
            $configStatus = $googleService->getConfigStatus();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Google OAuth settings saved!',
                'config' => $configStatus
            ]);
            
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_GOOGLE_SETTINGS');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Test Google OAuth configuration
     */
    public function testGoogleAuth(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $googleService = new \App\Services\GoogleAuthService();
            
            if (!$googleService->isConfigured()) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Google OAuth is not configured. Please add Client ID and Client Secret.'
                ]);
                return;
            }
            
            $configStatus = $googleService->getConfigStatus();
            
            echo json_encode([
                'success' => true,
                'message' => 'Google OAuth is configured correctly!',
                'config' => $configStatus
            ]);
            
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_GOOGLE');
            echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * List all uploaded images available in the media library
     * GET /api/admin/media/images
     */
    public function listUploadedImages(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin()) return;

        $images = [];

        // Scan uploads/settings/
        $settingsDir = UPLOAD_PATH . '/settings';
        if (is_dir($settingsDir)) {
            foreach (glob($settingsDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $file) {
                $filename = basename($file);
                $images[] = [
                    'url'  => '/uploads/settings/' . $filename,
                    'name' => $filename,
                    'size' => filesize($file),
                    'date' => filemtime($file),
                ];
            }
        }

        // Scan uploads/ root for images (script cover images uploaded directly)
        foreach (glob(UPLOAD_PATH . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $file) {
            $filename = basename($file);
            $images[] = [
                'url'  => '/uploads/' . $filename,
                'name' => $filename,
                'size' => filesize($file),
                'date' => filemtime($file),
            ];
        }

        // Sort newest first
        usort($images, fn($a, $b) => $b['date'] - $a['date']);

        echo json_encode(['success' => true, 'images' => $images, 'count' => count($images)]);
    }

    /**
     * Upload a video or PDF for a script card
     * POST /api/admin/media/upload-script-file
     */
    public function uploadScriptFile(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $msgs = [UPLOAD_ERR_INI_SIZE=>'File too large.',UPLOAD_ERR_PARTIAL=>'Upload incomplete.',UPLOAD_ERR_NO_FILE=>'No file.'];
            http_response_code(422);
            echo json_encode(['error' => $msgs[$_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload error.']);
            return;
        }

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $videoTypes = ['video/mp4','video/quicktime','video/webm','video/x-msvideo','video/mpeg'];
        $videoExts  = ['mp4','mov','webm','avi','mpeg'];
        $isPdf      = ($file['type'] === 'application/pdf' || $ext === 'pdf');
        $isVideo    = (in_array($file['type'], $videoTypes) || in_array($ext, $videoExts));

        if (!$isPdf && !$isVideo) {
            http_response_code(422);
            echo json_encode(['error' => 'Only MP4/MOV/WEBM video or PDF accepted.']);
            return;
        }

        $maxBytes = $isVideo ? 500 * 1024 * 1024 : 20 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            http_response_code(422);
            echo json_encode(['error' => $isVideo ? 'Video must be under 500 MB.' : 'PDF must be under 20 MB.']);
            return;
        }

        $settingsDir = UPLOAD_PATH . '/settings';
        if (!is_dir($settingsDir)) mkdir($settingsDir, 0755, true);

        $prefix   = $isVideo ? 'script_video_' : 'script_pdf_';
        $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $settingsDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file.']);
            return;
        }

        $url = '/uploads/settings/' . $filename;
        debug_log("Admin uploaded script " . ($isVideo ? 'video' : 'pdf') . ": {$url}", 'ADMIN');
        echo json_encode(['success' => true, 'url' => $url, 'type' => $isVideo ? 'video' : 'pdf']);
    }

    /**
     * Upload an image specifically for a script card poster
     * POST /api/admin/media/upload-script-image
     * Returns the public URL
     */
    public function uploadScriptImage(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $msgs = [UPLOAD_ERR_INI_SIZE=>'File too large.',UPLOAD_ERR_PARTIAL=>'Upload incomplete.',UPLOAD_ERR_NO_FILE=>'No file.'];
            http_response_code(422);
            echo json_encode(['error' => $msgs[$_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload error.']);
            return;
        }

        $file     = $_FILES['image'];
        $allowed  = ['image/jpeg','image/jpg','image/png','image/webp','image/gif','image/svg+xml','image/bmp'];
        $allowExt = ['jpg','jpeg','png','webp','gif','svg','bmp'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file['type'], $allowed) || !in_array($ext, $allowExt)) {
            http_response_code(422);
            echo json_encode(['error' => 'Only JPG, PNG, WebP, GIF, SVG, or BMP accepted.']);
            return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['error' => 'Image must be under 5 MB.']);
            return;
        }

        $settingsDir = UPLOAD_PATH . '/settings';
        if (!is_dir($settingsDir)) mkdir($settingsDir, 0755, true);

        $filename = 'script_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $settingsDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save image.']);
            return;
        }

        // Compress image automatically (skip SVG as it's already optimized)
        if ($ext !== 'svg') {
            try {
                $imageCompression = new \App\Services\ImageCompressionService();
                $compressionResult = $imageCompression->compressImage($dest, null, true); // Convert to WebP if beneficial
                
                if ($compressionResult['success'] && $compressionResult['output_path'] !== $dest) {
                    // Image was converted (e.g., to WebP), update filename and URL
                    $filename = basename($compressionResult['output_path']);
                    $url = '/uploads/settings/' . $filename;
                    
                    debug_log(sprintf(
                        "Script image compressed: %.1f%% reduction",
                        $compressionResult['compression_ratio']
                    ), 'ADMIN');
                } else if ($compressionResult['success']) {
                    debug_log("Script image compressed in-place", 'ADMIN');
                }
            } catch (\Exception $e) {
                debug_log("Image compression error (continuing with original): " . $e->getMessage(), 'ADMIN');
            }
        }

        $url = '/uploads/settings/' . $filename;
        debug_log("Admin uploaded script image: {$url}", 'ADMIN');
        echo json_encode(['success' => true, 'url' => $url, 'name' => $filename]);
    }

    /**
     * Upload an image or video for a settings field (logo, poster, trailer)
     * POST /api/admin/settings/upload-image
     * Returns the public URL of the saved file.
     */
    public function uploadSettingImage(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $field = trim($_POST['field'] ?? '');

        $imageFields = [
            'site_logo_url',
            'landing_poster_url',  'landing_poster2_url', 'landing_poster3_url',
            'landing_poster4_url', 'landing_poster5_url', 'landing_poster6_url',
            'landing_poster7_url', 'landing_poster8_url', 'landing_poster9_url',
            'landing_poster10_url',
            'actor_script_image_url', 'song_lyrics_image_url',
            'director_script_image_url', 'writer_script_image_url',
        ];
        $videoFields = [
            'landing_trailer_url',  'landing_trailer2_url', 'landing_trailer3_url',
            'landing_trailer4_url', 'landing_trailer5_url', 'landing_trailer6_url',
            'landing_trailer7_url', 'landing_trailer8_url', 'landing_trailer9_url',
            'landing_trailer10_url',
            'landing_hero_trailer_url', // Horizontal auto-play trailer below posters
            'actor_preview_video_url', 'song_preview_video_url',
            'director_preview_video_url', 'writer_preview_video_url',
        ];
        $pdfFields = [
            'actor_script_pdf_url', 'song_lyrics_pdf_url',
            'director_script_pdf_url', 'writer_script_pdf_url',
        ];
        $isVideo = in_array($field, $videoFields);
        $isImage = in_array($field, $imageFields);
        $isPdf   = in_array($field, $pdfFields);

        if (!$isImage && !$isVideo && !$isPdf) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid field']);
            return;
        }
        // Accept either 'image' or 'file' key
        $fileKey = !empty($_FILES['image']) ? 'image' : 'file';
        if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE  => 'File too large (server limit).',
                UPLOAD_ERR_FORM_SIZE => 'File too large.',
                UPLOAD_ERR_PARTIAL   => 'Upload incomplete — please try again.',
                UPLOAD_ERR_NO_FILE   => 'No file received.',
                UPLOAD_ERR_CANT_WRITE => 'Server cannot write file.',
            ];
            $code = $_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE;
            http_response_code(422);
            echo json_encode(['error' => $msgs[$code] ?? 'Upload error.']);
            return;
        }

        $file = $_FILES[$fileKey];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($isImage) {
            $allowed  = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'image/bmp'];
            $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp'];
            $maxBytes = 5 * 1024 * 1024; // 5 MB
            $errMsg   = 'Only JPG, PNG, WebP, GIF, SVG, or BMP images accepted.';
            $sizeMsg  = 'Image must be under 5 MB.';
        } elseif ($isPdf) {
            $allowed  = ['application/pdf'];
            $allowExt = ['pdf'];
            $maxBytes = 20 * 1024 * 1024; // 20 MB
            $errMsg   = 'Only PDF files accepted.';
            $sizeMsg  = 'PDF must be under 20 MB.';
        } else {
            $allowed  = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-msvideo', 'video/mpeg', 'video/avi'];
            $allowExt = ['mp4', 'mov', 'webm', 'avi', 'mpeg'];
            $maxBytes = 500 * 1024 * 1024; // 500 MB
            $errMsg   = 'Only MP4, MOV, or WEBM video files accepted.';
            $sizeMsg  = 'Video must be under 500 MB.';
        }

        if (!in_array($file['type'], $allowed) && !in_array($ext, $allowExt)) {
            http_response_code(422);
            echo json_encode(['error' => $errMsg]);
            return;
        }
        if ($file['size'] > $maxBytes) {
            http_response_code(422);
            echo json_encode(['error' => $sizeMsg]);
            return;
        }

        // Save to uploads/settings/
        $settingsDir = UPLOAD_PATH . '/settings';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        $filename = 'setting_' . $field . '_' . time() . '.' . $ext;
        $dest     = $settingsDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file. Check server permissions.']);
            return;
        }

        // Compress images/videos automatically
        if ($isImage && $ext !== 'svg') {
            try {
                $imageCompression = new \App\Services\ImageCompressionService();
                $compressionResult = $imageCompression->compressImage($dest, null, true);
                
                if ($compressionResult['success'] && $compressionResult['output_path'] !== $dest) {
                    // Image was converted (e.g., to WebP), update filename and URL
                    $filename = basename($compressionResult['output_path']);
                    $publicUrl = '/uploads/settings/' . $filename;
                    
                    debug_log(sprintf(
                        "Setting image compressed: %.1f%% reduction",
                        $compressionResult['compression_ratio']
                    ), 'ADMIN');
                }
            } catch (\Exception $e) {
                debug_log("Image compression error (continuing with original): " . $e->getMessage(), 'ADMIN');
            }
        } else if ($isVideo) {
            try {
                $videoProcessing = new \App\Services\VideoProcessingService();
                $compressionResult = $videoProcessing->compressVideo($dest);
                
                if ($compressionResult['success']) {
                    $compressedPath = $compressionResult['output_path'];
                    if ($compressedPath !== $dest && file_exists($compressedPath)) {
                        // Replace original with compressed
                        unlink($dest);
                        rename($compressedPath, $dest);
                    }
                    
                    debug_log(sprintf(
                        "Setting video compressed: %.1f%% reduction",
                        $compressionResult['compression_ratio']
                    ), 'ADMIN');
                }
            } catch (\Exception $e) {
                debug_log("Video compression error (continuing with original): " . $e->getMessage(), 'ADMIN');
            }
        }

        $publicUrl = '/uploads/settings/' . $filename;

        $settingsModel = new \App\Models\Settings();
        $settingsModel->set($field, $publicUrl);

        debug_log("Admin uploaded " . ($isVideo ? 'video' : ($isPdf ? 'pdf' : 'image')) . " for {$field}: {$publicUrl}", 'ADMIN');
        echo json_encode(['success' => true, 'url' => $publicUrl, 'type' => $isVideo ? 'video' : 'image']);
    }

    /**
     * Save a landing page or audition brief setting
     * POST /api/admin/settings/landing
     */
    public function saveLandingSetting(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $key   = trim($_POST['key']   ?? '');
        $value = trim($_POST['value'] ?? '');

        $allowed = [
            'site_logo_url', 'site_logo_height',
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $key   = trim($_POST['key']   ?? '');
        $value = trim($_POST['value'] ?? '');

        $allowed = [
            'site_logo_url', 'site_logo_height',
            'landing_headline', 'site_tagline',
            'landing_header_content', 'landing_footer_content',
            'landing_roles_heading', 'landing_roles_subheading',
            'landing_poster_url',   'landing_poster_title',  'landing_trailer_url',  'landing_poster_btn_label',
            'landing_poster2_url',  'landing_poster2_title', 'landing_trailer2_url', 'landing_poster2_btn_label',
            'landing_poster3_url',  'landing_poster3_title', 'landing_trailer3_url', 'landing_poster3_btn_label',
            'landing_poster4_url',  'landing_poster4_title', 'landing_trailer4_url', 'landing_poster4_btn_label',
            'landing_poster5_url',  'landing_poster5_title', 'landing_trailer5_url', 'landing_poster5_btn_label',
            'landing_poster6_url',  'landing_poster6_title', 'landing_trailer6_url', 'landing_poster6_btn_label',
            'landing_poster7_url',  'landing_poster7_title', 'landing_trailer7_url', 'landing_poster7_btn_label',
            'landing_poster8_url',  'landing_poster8_title', 'landing_trailer8_url', 'landing_poster8_btn_label',
            'landing_poster9_url',  'landing_poster9_title', 'landing_trailer9_url', 'landing_poster9_btn_label',
            'landing_poster10_url', 'landing_poster10_title', 'landing_trailer10_url', 'landing_poster10_btn_label',
            'landing_hero_trailer_url', // Horizontal auto-play trailer
            'landing_about_text',
            'manifesto_heading', 'manifesto_subheading',
            'manifesto_video1_url', 'manifesto_video1_title',
            'manifesto_video2_url', 'manifesto_video2_title',
            'manifesto_video3_url', 'manifesto_video3_title',
            'manifesto_video4_url', 'manifesto_video4_title',
            'manifesto_video5_url', 'manifesto_video5_title',
            'manifesto_video6_url', 'manifesto_video6_title',
            'actor_dialog_script', 'actor_song_script',
            'director_brief', 'writer_brief',
            'film_song_heading', 'film_song_subtitle', 'film_song_btn_label',
            // Actor page media
            'actor_preview_video_url', 'song_preview_video_url',
            'actor_script_image_url',  'song_lyrics_image_url',
            'song_tune_youtube_url',   'actor_script_pdf_url', 'song_lyrics_pdf_url',
            // Director page media
            'director_preview_video_url', 'director_script_image_url', 'director_script_pdf_url',
            // Writer page media
            'writer_preview_video_url',   'writer_script_image_url',   'writer_script_pdf_url',
            // About section
            'about_section_label', 'about_section_heading',
            // Role cards
            'role_writer_title', 'role_writer_icon', 'role_writer_description',
            'role_writer_badge1', 'role_writer_badge2', 'role_writer_button_text', 'role_writer_button_url',
            'role_director_title', 'role_director_icon', 'role_director_description',
            'role_director_badge1', 'role_director_badge2', 'role_director_button_text', 'role_director_button_url',
            'role_actor_title', 'role_actor_icon', 'role_actor_description',
            'role_actor_badge1', 'role_actor_badge2', 'role_actor_button_text', 'role_actor_button_url',
            // Marquee items
            'marquee_item1', 'marquee_item2', 'marquee_item3', 'marquee_item4', 'marquee_item5',
            'marquee_item6', 'marquee_item7', 'marquee_item8', 'marquee_item9', 'marquee_item10',
            // Role page text settings (hero and form sections for each role)
            'writer_hero_label', 'writer_hero_heading', 'writer_hero_description',
            'writer_form_heading', 'writer_form_description',
            'director_hero_label', 'director_hero_heading', 'director_hero_description',
            'director_form_heading', 'director_form_description',
            'actor_hero_label', 'actor_hero_heading', 'actor_hero_description',
            'actor_form_heading', 'actor_form_description',
            // Header and Footer menu items
            'header_menu_item_1_text', 'header_menu_item_1_page', 'header_menu_item_1_order',
            'header_menu_item_2_text', 'header_menu_item_2_page', 'header_menu_item_2_order',
            'header_menu_item_3_text', 'header_menu_item_3_page', 'header_menu_item_3_order',
            'header_menu_item_4_text', 'header_menu_item_4_page', 'header_menu_item_4_order',
            'footer_menu_item_1_text', 'footer_menu_item_1_page', 'footer_menu_item_1_order',
            'footer_menu_item_2_text', 'footer_menu_item_2_page', 'footer_menu_item_2_order',
            'footer_menu_item_3_text', 'footer_menu_item_3_page', 'footer_menu_item_3_order',
            'footer_menu_item_4_text', 'footer_menu_item_4_page', 'footer_menu_item_4_order',
            // Role submission messages
            'actor_success_heading', 'actor_success_message', 'actor_success_pdf_button',
            'actor_failure_heading', 'actor_failure_message', 'actor_failure_retry_button',
            'director_success_heading', 'director_success_message', 'director_success_pdf_button',
            'director_failure_heading', 'director_failure_message', 'director_failure_retry_button',
            'writer_success_heading', 'writer_success_message', 'writer_success_pdf_button',
            'writer_failure_heading', 'writer_failure_message', 'writer_failure_retry_button',
        ];

        if (!in_array($key, $allowed)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid setting key']);
            return;
        }

        try {
            $settingsModel = new \App\Models\Settings();
            $settingsModel->set($key, $value);
            debug_log("Admin saved landing setting: {$key}", 'ADMIN');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_LANDING_SETTING');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save setting']);
        }
    }

    /**
     * Run pending database migrations
     * POST /api/admin/run-migrations
     */
    public function runMigrations(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $applied = $this->db->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
            $applied = array_flip($applied);

            $migrationDir = __DIR__ . '/../../database/migrations';
            $files = glob($migrationDir . '/*.sql');
            sort($files);

            $ran = [];
            $skipped = [];
            $errors = [];

            foreach ($files as $file) {
                $filename = basename($file);
                if (isset($applied[$filename])) {
                    $skipped[] = $filename;
                    continue;
                }
                $sql = file_get_contents($file);
                $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
                try {
                    foreach ($statements as $statement) {
                        $this->db->exec($statement);
                    }
                    $this->db->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$filename]);
                    $ran[] = $filename;
                    debug_log("Migration applied: {$filename}", 'ADMIN');
                } catch (\Exception $e) {
                    $errors[] = "{$filename}: " . $e->getMessage();
                }
            }

            echo json_encode([
                'success' => true,
                'ran'     => $ran,
                'skipped' => $skipped,
                'errors'  => $errors,
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_RUN_MIGRATIONS');
            http_response_code(500);
            echo json_encode(['error' => 'Migration failed: ' . $e->getMessage()]);
        }
    }

    // ==================== PUBLIC SUBMISSIONS ====================

    /**
     * List all public (guest) submissions with optional filters
     * GET /api/admin/submissions?role=actor&status=new&search=john
     */
    public function listSubmissions(): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin()) return;

        try {
            $submissionModel = new \App\Models\Submission();

            if (!$submissionModel->tableExists()) {
                echo json_encode([
                    'success'     => true,
                    'submissions' => [],
                    'counts'      => ['new' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0],
                    'roles'       => ['actor' => 0, 'director' => 0, 'writer' => 0],
                    'total'       => 0,
                    'table_missing' => true,
                ]);
                return;
            }

            $role   = trim($_GET['role']   ?? '');
            $status = trim($_GET['status'] ?? '');
            $search = trim($_GET['search'] ?? '');

            $submissions = $submissionModel->filter(
                $role   ?: null,
                $status ?: null,
                $search ?: null
            );

            echo json_encode([
                'success'     => true,
                'submissions' => $submissions,
                'counts'      => $submissionModel->countByStatus(),
                'roles'       => $submissionModel->countByRole(),
                'total'       => $submissionModel->totalCount(),
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_LIST_SUBMISSIONS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load submissions: ' . $e->getMessage()]);
        }
    }

    /**
     * Update submission status and/or admin notes
     * POST /api/admin/submissions/{id}/status
     */
    public function updateSubmission(int $id): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $status     = trim($_POST['status']      ?? '');
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        $validStatuses = ['new', 'reviewed', 'shortlisted', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid status']);
            return;
        }

        try {
            $submissionModel = new \App\Models\Submission();
            $sub = $submissionModel->findById($id);
            if (!$sub) {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
                return;
            }

            $submissionModel->updateStatus($id, $status, $adminNotes ?: null);
            debug_log("Admin updated submission #{$id} status to {$status}", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Submission updated']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_SUBMISSION');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update submission']);
        }
    }

    /**
     * Get submission details with AI feedback JSON
     * GET /api/admin/submissions/{id}
     */
    public function getSubmission(int $id): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin()) return;

        try {
            $db = \App\Config\Database::getConnection();
            $stmt = $db->prepare(
                "SELECT s.*, 
                        v1.ai_feedback as video1_ai_feedback, 
                        v1.ai_score as video1_ai_score,
                        v1.ai_status as video1_ai_status,
                        v2.ai_feedback as video2_ai_feedback,
                        v2.ai_score as video2_ai_score,
                        v2.ai_status as video2_ai_status
                 FROM submissions s
                 LEFT JOIN videos v1 ON s.video_id = v1.id
                 LEFT JOIN videos v2 ON s.video_id_2 = v2.id
                 WHERE s.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            $sub = $stmt->fetch();
            
            if (!$sub) {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
                return;
            }

            // Parse AI feedback JSON
            if (!empty($sub['video1_ai_feedback'])) {
                $sub['video1_ai_feedback'] = json_decode($sub['video1_ai_feedback'], true);
            }
            if (!empty($sub['video2_ai_feedback'])) {
                $sub['video2_ai_feedback'] = json_decode($sub['video2_ai_feedback'], true);
            }

            echo json_encode(['success' => true, 'submission' => $sub]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_SUBMISSION');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load submission']);
        }
    }

    /**
     * Delete a submission (and its uploaded file if present)
     * POST /api/admin/submissions/{id}/delete
     */
    public function deleteSubmission(int $id): void
    {
        header('Content-Type: application/json');
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $submissionModel = new \App\Models\Submission();
            $sub = $submissionModel->findById($id);
            if (!$sub) {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
                return;
            }

            // Delete associated files (both video 1 and video 2 for dual submissions)
            if (!empty($sub['file_path'])) {
                $filePath = UPLOAD_PATH . '/' . $sub['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                    debug_log("Deleted submission file: {$filePath}", 'ADMIN');
                }
            }
            
            if (!empty($sub['file_path_2'])) {
                $filePath2 = UPLOAD_PATH . '/' . $sub['file_path_2'];
                if (file_exists($filePath2)) {
                    unlink($filePath2);
                    debug_log("Deleted submission file 2: {$filePath2}", 'ADMIN');
                }
            }

            // Delete linked videos from videos table (cascade delete)
            $videoModel = new \App\Models\Video();
            if (!empty($sub['video_id'])) {
                try {
                    $videoModel->delete($sub['video_id']);
                    debug_log("Cascade deleted video #{$sub['video_id']} from submissions delete", 'ADMIN');
                } catch (\Exception $e) {
                    debug_log("Failed to cascade delete video #{$sub['video_id']}: " . $e->getMessage(), 'ADMIN');
                }
            }
            
            if (!empty($sub['video_id_2'])) {
                try {
                    $videoModel->delete($sub['video_id_2']);
                    debug_log("Cascade deleted video #{$sub['video_id_2']} from submissions delete", 'ADMIN');
                } catch (\Exception $e) {
                    debug_log("Failed to cascade delete video #{$sub['video_id_2']}: " . $e->getMessage(), 'ADMIN');
                }
            }

            // Delete submission record
            $submissionModel->delete($id);
            debug_log("Admin deleted submission #{$id} with cascade delete to videos", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Submission and linked videos deleted']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_SUBMISSION');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete submission']);
        }
    }

    // ==================== ACTIVITY LOGS ====================

    /**
     * Get all activity logs with pagination and filters
     */
    public function getActivityLogs(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $activityLog = new \App\Models\ActivityLog();
            
            if (!$activityLog->tableExists()) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Activity log table not found. Run migration 013.'
                ]);
                return;
            }

            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = max(10, min(100, (int)($_GET['per_page'] ?? 50)));
            $filter = trim($_GET['filter'] ?? '');

            $logs = $activityLog->getAll($page, $perPage, $filter ?: null);
            $total = $activityLog->count($filter ?: null);
            $stats = $activityLog->getStats();

            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'pages' => ceil($total / $perPage)
                ],
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_ACTIVITY_LOGS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch activity logs']);
        }
    }

    /**
     * Delete a single activity log entry
     */
    public function deleteActivityLog(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $activityLog = new \App\Models\ActivityLog();
            
            if (!$activityLog->tableExists()) {
                http_response_code(503);
                echo json_encode(['error' => 'Activity log table not found']);
                return;
            }

            $activityLog->delete($id);
            
            debug_log("Admin deleted activity log #{$id}", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Activity log deleted permanently']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_ACTIVITY_LOG');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete activity log']);
        }
    }

    /**
     * Bulk delete activity logs
     */
    public function bulkDeleteActivityLogs(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $logIds = json_decode($_POST['log_ids'] ?? '[]', true);
        if (empty($logIds) || !is_array($logIds)) {
            http_response_code(422);
            echo json_encode(['error' => 'No logs selected']);
            return;
        }

        try {
            $activityLog = new \App\Models\ActivityLog();
            
            if (!$activityLog->tableExists()) {
                http_response_code(503);
                echo json_encode(['error' => 'Activity log table not found']);
                return;
            }

            $deleted = $activityLog->bulkDelete($logIds);

            debug_log("Admin bulk deleted {$deleted} activity logs", 'ADMIN');
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => "{$deleted} activity log(s) deleted permanently"
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_BULK_DELETE_ACTIVITY_LOGS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to bulk delete activity logs']);
        }
    }

    /**
     * Run activity log cleanup manually (delete logs older than 90 days)
     */
    public function cleanupActivityLogs(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $activityLog = new \App\Models\ActivityLog();
            
            if (!$activityLog->tableExists()) {
                http_response_code(503);
                echo json_encode(['error' => 'Activity log table not found']);
                return;
            }

            $deleted = $activityLog->cleanupOldLogs();

            debug_log("Admin ran activity log cleanup: {$deleted} logs removed", 'ADMIN');
            log_message('info', "Admin cleanup: deleted {$deleted} activity logs older than 90 days");

            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Deleted {$deleted} activity log(s) older than 90 days"
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CLEANUP_ACTIVITY_LOGS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to cleanup activity logs']);
        }
    }

    // ==================== VIDEO STATUS CHECK (AUTO-REFRESH) ====================

    /**
     * Get current status of all videos (for auto-refresh polling)
     * Returns lightweight data with only ID and status
     */
    public function videoStatusCheck(): void
    {
        header('Content-Type: application/json');
        
        // Allow authenticated users (not just admins)
        if (!is_authenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        try {
            $user = auth_user();
            $isAdmin = $user['is_admin'] ?? false;

            // Build query based on user role
            if ($isAdmin) {
                // Admins see all videos
                $sql = "SELECT 
                            id, 
                            title, 
                            status, 
                            rejection_reason,
                            updated_at
                        FROM videos 
                        ORDER BY updated_at DESC 
                        LIMIT 100";
                $stmt = $this->db->query($sql);
            } else {
                // Regular users see only their videos
                $sql = "SELECT 
                            id, 
                            title, 
                            status, 
                            rejection_reason,
                            updated_at
                        FROM videos 
                        WHERE user_id = ?
                        ORDER BY updated_at DESC 
                        LIMIT 50";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$user['id']]);
            }

            $videos = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'videos' => $videos,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'VIDEO_STATUS_CHECK');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to check video status']);
        }
    }

    // ==================== PLAYLISTS ====================

    /**
     * Get all YouTube playlists
     */
    public function getPlaylists(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $youtubeService = new \App\Services\YouTubeService();
            $playlists = $youtubeService->getAllPlaylists();
            
            echo json_encode([
                'success' => true,
                'playlists' => $playlists
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_PLAYLISTS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch playlists: ' . $e->getMessage()]);
        }
    }

    /**
     * Create playlists for all roles
     */
    public function createDefaultPlaylists(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $youtubeService = new \App\Services\YouTubeService();
            $created = [];
            $errors = [];
            $details = [];

            // Create actor playlists (audition and song audition)
            log_message('info', 'Creating Actor Auditions playlist...');
            $auditionPlaylist = $youtubeService->getOrCreatePlaylist('actor', 'audition');
            if ($auditionPlaylist) {
                $created[] = 'Actor Auditions';
                $details[] = "Actor Auditions: {$auditionPlaylist}";
            } else {
                $errors[] = 'Failed to create Actor Auditions playlist';
                log_message('error', 'Failed to create Actor Auditions playlist');
            }
            
            // Small delay to avoid rate limiting
            sleep(1);

            log_message('info', 'Creating Actor Song Auditions playlist...');
            $songPlaylist = $youtubeService->getOrCreatePlaylist('actor', 'song_audition');
            if ($songPlaylist) {
                $created[] = 'Actor Song Auditions';
                $details[] = "Actor Song Auditions: {$songPlaylist}";
            } else {
                $errors[] = 'Failed to create Actor Song Auditions playlist';
                log_message('error', 'Failed to create Actor Song Auditions playlist');
            }
            
            // Small delay to avoid rate limiting
            sleep(1);

            // Create director playlist
            log_message('info', 'Creating Director Submissions playlist...');
            $directorPlaylist = $youtubeService->getOrCreatePlaylist('director');
            if ($directorPlaylist) {
                $created[] = 'Director Submissions';
                $details[] = "Director Submissions: {$directorPlaylist}";
            } else {
                $errors[] = 'Failed to create Director Submissions playlist';
                log_message('error', 'Failed to create Director Submissions playlist');
            }
            
            // Small delay to avoid rate limiting
            sleep(1);

            // Create writer playlist
            log_message('info', 'Creating Writer Submissions playlist...');
            $writerPlaylist = $youtubeService->getOrCreatePlaylist('writer');
            if ($writerPlaylist) {
                $created[] = 'Writer Submissions';
                $details[] = "Writer Submissions: {$writerPlaylist}";
            } else {
                $errors[] = 'Failed to create Writer Submissions playlist';
                log_message('error', 'Failed to create Writer Submissions playlist');
            }

            debug_log("Admin created default playlists: " . implode(', ', $created), 'ADMIN');
            
            if (empty($errors)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Default playlists created successfully',
                    'created' => $created,
                    'details' => $details
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Some playlists failed to create',
                    'created' => $created,
                    'errors' => $errors,
                    'details' => $details
                ]);
            }
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CREATE_DEFAULT_PLAYLISTS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create playlists: ' . $e->getMessage()]);
        }
    }

    /**
     * Update playlist settings
     */
    public function updatePlaylistSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            
            if (isset($_POST['youtube_playlist_enabled'])) {
                $enabled = $_POST['youtube_playlist_enabled'] === '1' ? '1' : '0';
                $settingsModel->set('youtube_playlist_enabled', $enabled, 'youtube', 'Enable automatic playlist organization');
            }
            
            if (isset($_POST['youtube_playlist_per_season'])) {
                $perSeason = $_POST['youtube_playlist_per_season'] === '1' ? '1' : '0';
                $settingsModel->set('youtube_playlist_per_season', $perSeason, 'youtube', 'Create separate playlists for each season');
            }

            debug_log("Admin updated playlist settings", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Playlist settings updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_PLAYLIST_SETTINGS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update playlist settings: ' . $e->getMessage()]);
        }
    }

    /**
     * Get playlist settings
     */
    public function getPlaylistSettings(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $settingsModel = new \App\Models\Settings();
            
            $settings = [
                'youtube_playlist_enabled' => $settingsModel->get('youtube_playlist_enabled') === '1',
                'youtube_playlist_per_season' => $settingsModel->get('youtube_playlist_per_season') === '1',
            ];

            echo json_encode(['success' => true, 'settings' => $settings]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_GET_PLAYLIST_SETTINGS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get playlist settings: ' . $e->getMessage()]);
        }
    }

    /**
     * Organize existing videos into playlists
     */
    public function organizeVideosIntoPlaylists(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $youtubeService = new \App\Services\YouTubeService();
            
            // Get all videos that have been published to YouTube
            $stmt = $this->db->query(
                "SELECT v.*, u.role as user_role, s.title as season_title
                 FROM videos v
                 JOIN users u ON v.user_id = u.id
                 JOIN seasons s ON v.season_id = s.id
                 WHERE v.youtube_id IS NOT NULL AND v.youtube_id != ''
                 AND (v.youtube_playlist_id IS NULL OR v.youtube_playlist_id = '')
                 ORDER BY v.published_at ASC"
            );
            $videos = $stmt->fetchAll();
            
            $organized = 0;
            $errors = [];

            foreach ($videos as $video) {
                try {
                    $playlistId = $youtubeService->determinePlaylistForVideo($video);
                    
                    if ($playlistId && $youtubeService->addVideoToPlaylist($video['youtube_id'], $playlistId)) {
                        // Update video record with playlist ID
                        $updateStmt = $this->db->prepare("UPDATE videos SET youtube_playlist_id = ? WHERE id = ?");
                        $updateStmt->execute([$playlistId, $video['id']]);
                        $organized++;
                    } else {
                        $errors[] = "Failed to add video {$video['id']} to playlist";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error organizing video {$video['id']}: " . $e->getMessage();
                }
            }

            debug_log("Admin organized {$organized} videos into playlists", 'ADMIN');
            
            echo json_encode([
                'success' => true,
                'message' => "{$organized} video(s) organized into playlists",
                'organized' => $organized,
                'total' => count($videos),
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_ORGANIZE_VIDEOS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to organize videos: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a single playlist from database
     */
    public function deletePlaylist(int $playlistId): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            // Remove playlist ID from videos that reference it
            $stmt = $this->db->prepare("UPDATE videos SET youtube_playlist_id = NULL WHERE youtube_playlist_id = (SELECT playlist_id FROM youtube_playlists WHERE id = ?)");
            $stmt->execute([$playlistId]);
            
            // Delete the playlist record
            $stmt = $this->db->prepare("DELETE FROM youtube_playlists WHERE id = ?");
            $stmt->execute([$playlistId]);
            
            debug_log("Admin deleted playlist ID: {$playlistId}", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Playlist deleted successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_PLAYLIST');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete playlist: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete all playlists from database
     */
    public function deleteAllPlaylists(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            // Clear playlist IDs from all videos
            $this->db->exec("UPDATE videos SET youtube_playlist_id = NULL WHERE youtube_playlist_id IS NOT NULL");
            
            // Count playlists before deletion
            $count = $this->db->query("SELECT COUNT(*) FROM youtube_playlists")->fetchColumn();
            
            // Delete all playlists
            $this->db->exec("DELETE FROM youtube_playlists");
            
            debug_log("Admin deleted all playlists ({$count} total)", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'All playlists deleted', 'deleted' => $count]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_ALL_PLAYLISTS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete playlists: ' . $e->getMessage()]);
        }
    }
}


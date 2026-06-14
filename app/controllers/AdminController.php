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
     * Update API keys in .env file
     */
    public function updateAPIKeys(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $envFile = BASE_PATH . '/.env';
        if (!file_exists($envFile)) {
            http_response_code(500);
            echo json_encode(['error' => '.env file not found']);
            return;
        }

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
            
            debug_log("Admin updated API keys: " . implode(', ', array_keys($updates)), 'ADMIN');
            echo json_encode([
                'success' => true, 
                'message' => 'API keys updated successfully. Refresh page to see status changes.',
                'updated' => array_keys($updates)
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

        $keys = [
            'AZURE_CONTENT_SAFETY_ENDPOINT' => $_ENV['AZURE_CONTENT_SAFETY_ENDPOINT'] ?? '',
            'AZURE_CONTENT_SAFETY_KEY' => $_ENV['AZURE_CONTENT_SAFETY_KEY'] ?? '',
            'OPENAI_API_KEY' => $_ENV['OPENAI_API_KEY'] ?? '',
            'SIGHTENGINE_API_USER' => $_ENV['SIGHTENGINE_API_USER'] ?? '',
            'SIGHTENGINE_API_SECRET' => $_ENV['SIGHTENGINE_API_SECRET'] ?? '',
            'RAPIDAPI_KEY' => $_ENV['RAPIDAPI_KEY'] ?? '',
            'GROQ_API_KEY' => $_ENV['GROQ_API_KEY'] ?? '',
            'FFMPEG_PATH' => $_ENV['FFMPEG_PATH'] ?? 'ffmpeg',
            'FFPROBE_PATH' => $_ENV['FFPROBE_PATH'] ?? 'ffprobe',
        ];

        $status = [];
        foreach ($keys as $key => $value) {
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
     * Test an AI provider
     */
    public function testAIProvider(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $provider = trim($_POST['provider'] ?? '');
        $type = trim($_POST['type'] ?? 'text'); // text, image, transcription

        if (!in_array($provider, ['azure', 'openai', 'sightengine', 'rapidapi', 'groq', 'local'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid provider']);
            return;
        }

        try {
            $result = ['provider' => $provider, 'type' => $type, 'status' => 'unknown'];
            
            if ($type === 'text') {
                $moderationService = new \App\Services\ContentModerationService();
                $testResult = $moderationService->moderateText('This is a test message for content moderation.');
                $result['status'] = 'success';
                $result['response'] = $testResult;
            } elseif ($type === 'transcription') {
                $transcriptionService = new \App\Services\TranscriptionService();
                $result['status'] = $transcriptionService->isAvailable() ? 'available' : 'not_configured';
                $result['message'] = $result['status'] === 'available' 
                    ? 'Groq Whisper API is configured and ready'
                    : 'GROQ_API_KEY not set in environment';
            }
            
            debug_log("Admin tested AI provider: $provider ($type) - {$result['status']}", 'ADMIN');
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_TEST_AI_PROVIDER');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Settings
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get a setting by key
     */
    public function get(string $key, ?string $default = null): ?string
    {
        // Try with setting_key column first (new schema)
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            if ($result) {
                return $result['setting_value'];
            }
        } catch (\PDOException $e) {
            // Column might not exist, try old schema
        }
        
        // Fallback to key/value columns (old schema)
        try {
            $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            if ($result) {
                return $result['value'];
            }
        } catch (\PDOException $e) {
            // Neither schema worked
        }
        
        return $default;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, string $value, string $type = 'text', ?string $description = null): bool
    {
        // Try new schema first (setting_key, setting_value)
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO settings (setting_key, setting_value, setting_type, description) 
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?"
            );
            return $stmt->execute([$key, $value, $type, $description, $value, $type]);
        } catch (\PDOException $e) {
            // Fallback to old schema
        }
        
        // Try old schema (key, value)
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO settings (`key`, value, category, description, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()"
            );
            return $stmt->execute([$key, $value, $type, $description, $value]);
        } catch (\PDOException $e) {
            log_message('error', 'Settings set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings
     */
    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM settings ORDER BY setting_key");
        return $stmt->fetchAll();
    }

    /**
     * Get all guide settings
     */
    public function getGuides(): array
    {
        $stmt = $this->db->query("SELECT * FROM settings WHERE setting_key LIKE 'guide_%' ORDER BY setting_key");
        $results = $stmt->fetchAll();
        
        $guides = [];
        foreach ($results as $row) {
            $role = str_replace('guide_', '', $row['setting_key']);
            $guides[$role] = $row['setting_value'];
        }
        
        return $guides;
    }

    /**
     * Update a guide
     */
    public function updateGuide(string $role, string $content): bool
    {
        $key = 'guide_' . $role;
        return $this->set($key, $content, 'text', "Guide text shown to {$role}s on the Create screen");
    }

    /**
     * Get AI provider settings
     */
    public function getAISettings(): array
    {
        $defaults = [
            // Text moderation provider order (comma-separated)
            'ai_text_providers' => 'azure,openai,local',
            // Image moderation provider order
            'ai_image_providers' => 'azure,sightengine,api4ai',
            // Transcription provider
            'ai_transcription_provider' => 'groq',
            // Enable/disable AI processing
            'ai_processing_enabled' => '1',
            // Score thresholds
            'ai_approve_threshold' => '70',
            'ai_flag_threshold' => '40',
            'ai_nsfw_reject_threshold' => '0.7',
            'ai_nsfw_flag_threshold' => '0.4',
            'ai_profanity_reject_threshold' => '0.7',
            'ai_profanity_flag_threshold' => '0.4',
            // Auto-approve if AI score is high enough
            'ai_auto_approve' => '1',
            // Video duration limits (seconds)
            'ai_min_duration' => '10',
            'ai_max_duration' => '180',
            // Video file size limits (MB)
            'video_min_size_mb' => '1',
            'video_max_size_mb' => '100',
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->get($key, $default);
        }

        return $settings;
    }

    /**
     * Get video upload limits
     */
    public function getUploadLimits(): array
    {
        return [
            'min_size_mb' => (int) $this->get('video_min_size_mb', '1'),
            'max_size_mb' => (int) $this->get('video_max_size_mb', '100'),
            'min_duration' => (int) $this->get('ai_min_duration', '10'),
            'max_duration' => (int) $this->get('ai_max_duration', '180'),
        ];
    }

    /**
     * Update AI settings
     */
    public function updateAISetting(string $key, string $value): bool
    {
        $allowedKeys = [
            'ai_text_providers', 'ai_image_providers', 'ai_transcription_provider',
            'ai_processing_enabled', 'ai_approve_threshold', 'ai_flag_threshold',
            'ai_nsfw_reject_threshold', 'ai_nsfw_flag_threshold',
            'ai_profanity_reject_threshold', 'ai_profanity_flag_threshold',
            'ai_auto_approve', 'ai_min_duration', 'ai_max_duration',
            'video_min_size_mb', 'video_max_size_mb'
        ];
        
        if (!in_array($key, $allowedKeys)) {
            return false;
        }

        return $this->set($key, $value, 'ai_config', "AI Configuration: {$key}");
    }

    /**
     * Bulk update AI settings
     */
    public function updateAISettings(array $settings): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $this->updateAISetting($key, (string) $value);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}

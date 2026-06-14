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
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, string $value, string $type = 'text', ?string $description = null): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO settings (setting_key, setting_value, setting_type, description) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?"
        );
        return $stmt->execute([$key, $value, $type, $description, $value, $type]);
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
            // Video duration limits
            'ai_min_duration' => '10',
            'ai_max_duration' => '180',
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $this->get($key, $default);
        }

        return $settings;
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
            'ai_auto_approve', 'ai_min_duration', 'ai_max_duration'
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

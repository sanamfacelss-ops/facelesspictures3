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
        // Ensure type is valid for ENUM
        if (!in_array($type, ['text', 'html', 'json'])) {
            $type = 'text';
        }
        
        // Try new schema first (setting_key, setting_value)
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO settings (setting_key, setting_value, setting_type, description) 
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?"
            );
            $result = $stmt->execute([$key, $value, $type, $description, $value, $type]);
            debug_log("Settings set '$key' = '$value' (type: $type) - result: " . ($result ? 'success' : 'failed'), 'SETTINGS');
            return $result;
        } catch (\PDOException $e) {
            debug_log("Settings set error (new schema): " . $e->getMessage(), 'SETTINGS');
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
            debug_log("Settings set error (old schema): " . $e->getMessage(), 'SETTINGS');
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
            // YouTube auto-publish toggle (1 = enabled, 0 = paused)
            'youtube_auto_publish' => '1',
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
            'video_min_size_mb', 'video_max_size_mb', 'youtube_auto_publish'
        ];
        
        if (!in_array($key, $allowedKeys)) {
            return false;
        }

        return $this->set($key, $value, 'text', "AI Configuration: {$key}");
    }

    /**
     * Check if YouTube auto-publish is enabled
     */
    public function isYouTubeAutoPublishEnabled(): bool
    {
        return $this->get('youtube_auto_publish', '1') === '1';
    }

    /**
     * Toggle YouTube auto-publish
     */
    public function setYouTubeAutoPublish(bool $enabled): bool
    {
        return $this->set('youtube_auto_publish', $enabled ? '1' : '0', 'text', 'YouTube auto-publish toggle');
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

    /**
     * Get SMTP/Email settings
     */
    public function getEmailSettings(): array
    {
        $defaults = [
            'email_provider' => 'smtp', // 'smtp' or 'resend'
            'smtp_host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'smtp_port' => $_ENV['MAIL_PORT'] ?? '587',
            'smtp_username' => $_ENV['MAIL_USERNAME'] ?? '',
            'smtp_password' => '', // Don't expose from env, only from DB
            'smtp_encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'smtp_from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@facelesspictures.com',
            'smtp_from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Faceless Pictures 3',
            // Resend settings
            'resend_api_key' => '',
            'resend_from_address' => '',
            'resend_from_name' => 'Faceless Pictures 3',
            // Notification toggles
            'email_notify_signup' => '1',
            'email_notify_submit' => '1',
            'email_notify_processing' => '1',
            'email_notify_approved' => '1',
            'email_notify_rejected' => '1',
            'email_notify_flagged' => '1',
            // Admin notifications
            'email_admin_address' => '',
            'email_admin_new_video' => '1',
            'email_admin_flagged' => '1',
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $dbValue = $this->get($key);
            // For sensitive keys, only use DB value if set (don't expose env values)
            if ($key === 'smtp_password' || $key === 'resend_api_key') {
                $settings[$key] = $dbValue ?? '';
            } else {
                $settings[$key] = $dbValue ?? $default;
            }
        }

        return $settings;
    }

    /**
     * Update SMTP setting
     */
    public function updateEmailSetting(string $key, string $value): bool
    {
        $allowedKeys = [
            'email_provider',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
            'resend_api_key', 'resend_from_address', 'resend_from_name',
            'email_notify_signup', 'email_notify_submit', 'email_notify_processing',
            'email_notify_approved', 'email_notify_rejected', 'email_notify_flagged',
            'email_admin_address', 'email_admin_new_video', 'email_admin_flagged',
        ];
        
        if (!in_array($key, $allowedKeys)) {
            return false;
        }

        return $this->set($key, $value, 'text', "Email Configuration: {$key}");
    }

    /**
     * Bulk update email/SMTP settings
     */
    public function updateEmailSettings(array $settings): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                // Skip empty passwords/API keys (means don't change them)
                if (($key === 'smtp_password' || $key === 'resend_api_key') && empty($value)) {
                    continue;
                }
                $this->updateEmailSetting($key, (string) $value);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            debug_log("Failed to update email settings: " . $e->getMessage(), 'SETTINGS');
            return false;
        }
    }

    /**
     * Get landing page header content
     */
    public function getHeaderContent(): string
    {
        return $this->get('landing_header_content', '');
    }

    /**
     * Get landing page footer content
     */
    public function getFooterContent(): string
    {
        return $this->get('landing_footer_content', '© 2024 Faceless Pictures. All rights reserved.');
    }

    /**
     * Update header content
     */
    public function updateHeaderContent(string $content): bool
    {
        return $this->set('landing_header_content', $content, 'text', 'Header content text displayed across all pages');
    }

    /**
     * Update footer content
     */
    public function updateFooterContent(string $content): bool
    {
        return $this->set('landing_footer_content', $content, 'text', 'Footer content text with copyright and other information');
    }

    /**
     * Get header menu items
     */
    public function getHeaderMenuItems(): array
    {
        $items = [];
        for ($i = 1; $i <= 4; $i++) {
            $items[] = [
                'text' => $this->get("header_menu_item_{$i}_text", ''),
                'url' => $this->get("header_menu_item_{$i}_url", ''),
                'order' => (int) $this->get("header_menu_item_{$i}_order", $i),
                'number' => $i
            ];
        }
        
        // Sort by order
        usort($items, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $items;
    }

    /**
     * Get header menu items grouped by position (left/right of centered logo)
     */
    public function getHeaderMenuItemsGrouped(): array
    {
        $items = $this->getHeaderMenuItems();
        
        $left = [];
        $right = [];
        
        foreach ($items as $item) {
            if (empty($item['text'])) continue; // Skip empty items
            
            if ($item['order'] <= 2) {
                $left[] = $item;
            } else {
                $right[] = $item;
            }
        }
        
        return [
            'left' => $left,
            'right' => $right
        ];
    }

    /**
     * Update a header menu item
     */
    public function updateHeaderMenuItem(int $itemNumber, string $text, string $url, int $order = null): bool
    {
        if ($itemNumber < 1 || $itemNumber > 4) {
            return false;
        }

        if ($order !== null && ($order < 1 || $order > 4)) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->set("header_menu_item_{$itemNumber}_text", $text, 'text', "Header menu item {$itemNumber} text label");
            $this->set("header_menu_item_{$itemNumber}_url", $url, 'text', "Header menu item {$itemNumber} URL/link");
            if ($order !== null) {
                $this->set("header_menu_item_{$itemNumber}_order", (string)$order, 'text', "Header menu item {$itemNumber} display order");
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            debug_log("Failed to update header menu item {$itemNumber}: " . $e->getMessage(), 'SETTINGS');
            return false;
        }
    }
}

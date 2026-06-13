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
}

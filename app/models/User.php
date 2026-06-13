<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, email, role, content_categories, is_admin, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        if ($result && isset($result['content_categories'])) {
            $result['content_categories'] = json_decode($result['content_categories'], true) ?? [$result['role']];
        }
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role, content_categories) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'],
            json_encode($data['content_categories'] ?? [$data['role']]),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function existsForSeason(int $userId, int $seasonId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM videos WHERE user_id = ? AND season_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $seasonId]);
        return (bool) $stmt->fetch();
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT id, name, email, role, content_categories, is_admin, created_at FROM users ORDER BY created_at DESC");
        $results = $stmt->fetchAll();
        
        // Parse content_categories JSON for each user
        foreach ($results as &$user) {
            if (isset($user['content_categories'])) {
                $user['content_categories'] = json_decode($user['content_categories'], true) ?? [$user['role']];
            } else {
                $user['content_categories'] = [$user['role']];
            }
        }
        
        return $results;
    }
}

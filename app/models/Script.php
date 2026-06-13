<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Script
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM scripts WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function byCategory(string $category): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM scripts WHERE category = ? AND is_active = 1 ORDER BY difficulty ASC, title ASC"
        );
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM scripts WHERE is_active = 1 ORDER BY category, difficulty, title");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO scripts (title, content, category, difficulty, duration_hint) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['category'],
            $data['difficulty'] ?? 'beginner',
            $data['duration_hint'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE scripts SET title = ?, content = ?, category = ?, difficulty = ?, duration_hint = ? WHERE id = ?"
        );
        return $stmt->execute([
            $data['title'],
            $data['content'],
            $data['category'],
            $data['difficulty'] ?? 'beginner',
            $data['duration_hint'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE scripts SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getRandomByCategory(string $category): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM scripts WHERE category = ? AND is_active = 1 ORDER BY RAND() LIMIT 1"
        );
        $stmt->execute([$category]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}

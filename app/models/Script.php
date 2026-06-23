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
        $cols   = $this->getColumns();
        $select = 'id, title, content, category, difficulty, is_active, created_at';
        foreach (['duration_hint', 'image_url', 'audition_type', 'rules'] as $col) {
            if (in_array($col, $cols)) $select .= ', ' . $col;
        }
        $stmt = $this->db->prepare(
            "SELECT {$select} FROM scripts WHERE category = ? AND is_active = 1 ORDER BY difficulty ASC, title ASC"
        );
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $cols   = $this->getColumns();
        $select = 'id, title, content, category, difficulty, is_active, created_at, updated_at';
        foreach (['duration_hint', 'image_url', 'audition_type', 'rules'] as $col) {
            if (in_array($col, $cols)) $select .= ', ' . $col;
        }
        $stmt = $this->db->query("SELECT {$select} FROM scripts WHERE is_active = 1 ORDER BY category, difficulty, title");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $cols = $this->getColumns();

        $columns = ['title', 'content', 'category', 'difficulty'];
        $values  = [
            $data['title'],
            $data['content'],
            $data['category'],
            $data['difficulty'] ?? 'beginner',
        ];

        if (in_array('duration_hint', $cols) && isset($data['duration_hint'])) {
            $columns[] = 'duration_hint';
            $values[]  = $data['duration_hint'] ?: null;
        }
        if (in_array('image_url', $cols)) {
            $columns[] = 'image_url';
            $values[]  = $data['image_url'] ?? null;
        }
        if (in_array('audition_type', $cols)) {
            $columns[] = 'audition_type';
            $values[]  = $data['audition_type'] ?? null;
        }
        if (in_array('rules', $cols)) {
            $columns[] = 'rules';
            $values[]  = $data['rules'] ?? null;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnStr    = implode(', ', $columns);

        $stmt = $this->db->prepare("INSERT INTO scripts ({$columnStr}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $cols = $this->getColumns();

        // Always-present fields
        $sets   = ['title = ?', 'content = ?', 'category = ?', 'difficulty = ?'];
        $values = [
            $data['title'],
            $data['content'],
            $data['category'],
            $data['difficulty'] ?? 'beginner',
        ];

        if (in_array('duration_hint', $cols)) {
            $sets[]   = 'duration_hint = ?';
            $values[] = $data['duration_hint'] ?? null;
        }
        if (in_array('image_url', $cols)) {
            $sets[]   = 'image_url = ?';
            $values[] = $data['image_url'] ?? null;
        }
        if (in_array('audition_type', $cols)) {
            $sets[]   = 'audition_type = ?';
            $values[] = $data['audition_type'] ?? null;
        }
        if (in_array('rules', $cols)) {
            $sets[]   = 'rules = ?';
            $values[] = $data['rules'] ?? null;
        }

        $values[] = $id;
        $sql = 'UPDATE scripts SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Get list of columns in the scripts table (cached)
     */
    private function getColumns(): array
    {
        static $columns = null;
        if ($columns === null) {
            try {
                $stmt    = $this->db->query('DESCRIBE scripts');
                $columns = array_column($stmt->fetchAll(), 'Field');
            } catch (\Exception $e) {
                $columns = ['id', 'title', 'content', 'category', 'difficulty', 'duration_hint', 'is_active'];
            }
        }
        return $columns;
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

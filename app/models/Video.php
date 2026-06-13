<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Video
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, u.name as user_name, u.role as user_role, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO videos (user_id, season_id, title, file_path, status) VALUES (?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $data['user_id'],
            $data['season_id'],
            $data['title'],
            $data['file_path'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $reason = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE videos SET status = ?, rejection_reason = ?, moderated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $reason, $id]);
    }

    public function setYoutubeId(int $id, string $youtubeId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE videos SET youtube_id = ?, published_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$youtubeId, $id]);
    }

    public function pending(): array
    {
        $stmt = $this->db->query(
            "SELECT v.*, u.name as user_name, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.status = 'pending'
             ORDER BY v.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function bySeason(int $seasonId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, u.name as user_name, u.role as user_role
             FROM videos v
             JOIN users u ON v.user_id = u.id
             WHERE v.season_id = ? AND v.status = 'approved'
             ORDER BY v.published_at DESC"
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    public function byUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, s.title as season_title
             FROM videos v
             JOIN seasons s ON v.season_id = s.id
             WHERE v.user_id = ?
             ORDER BY v.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function allApproved(): array
    {
        $stmt = $this->db->query(
            "SELECT v.*, u.name as user_name, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.status = 'approved'
             ORDER BY v.published_at DESC"
        );
        return $stmt->fetchAll();
    }
}

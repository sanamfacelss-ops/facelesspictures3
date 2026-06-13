<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Leaderboard
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByVideoId(int $videoId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM leaderboard WHERE video_id = ? LIMIT 1");
        $stmt->execute([$videoId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createOrUpdate(int $videoId, array $data): bool
    {
        $existing = $this->findByVideoId($videoId);
        $score = $this->calculateScore($data);

        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE leaderboard SET views = ?, likes = ?, comments = ?, score = ? WHERE video_id = ?"
            );
            return $stmt->execute([
                $data['views'] ?? 0,
                $data['likes'] ?? 0,
                $data['comments'] ?? 0,
                $score,
                $videoId,
            ]);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO leaderboard (video_id, views, likes, comments, score) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $videoId,
            $data['views'] ?? 0,
            $data['likes'] ?? 0,
            $data['comments'] ?? 0,
            $score,
        ]);
    }

    public function topBySeason(int $seasonId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, v.title as video_title, v.youtube_id, u.name as user_name, u.role as user_role, s.title as season_title
             FROM leaderboard l
             JOIN videos v ON l.video_id = v.id
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.season_id = ? AND v.status = 'approved'
             ORDER BY l.score DESC
             LIMIT ?"
        );
        $stmt->execute([$seasonId, $limit]);
        return $stmt->fetchAll();
    }

    public function topOverall(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, v.title as video_title, v.youtube_id, u.name as user_name, u.role as user_role, s.title as season_title
             FROM leaderboard l
             JOIN videos v ON l.video_id = v.id
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.status = 'approved'
             ORDER BY l.score DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    private function calculateScore(array $data): float
    {
        $views = $data['views'] ?? 0;
        $likes = $data['likes'] ?? 0;
        $comments = $data['comments'] ?? 0;
        return ($views * 1) + ($likes * 3) + ($comments * 5);
    }
}

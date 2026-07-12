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
        // Check which columns exist in the table to avoid errors from missing migrations
        $existingColumns = $this->getTableColumns();
        
        $columns = ['user_id', 'season_id', 'title', 'file_path', 'status'];
        $values = [
            $data['user_id'],
            $data['season_id'],
            $data['title'],
            $data['file_path'],
            'pending'
        ];
        
        // Add content_type if column exists (migration 002)
        if (in_array('content_type', $existingColumns) && isset($data['content_type'])) {
            $columns[] = 'content_type';
            $values[] = $data['content_type'];
        }
        
        // Add optional fields if present AND column exists (migration 003)
        if (in_array('recording_mode', $existingColumns) && isset($data['recording_mode'])) {
            $columns[] = 'recording_mode';
            $values[] = $data['recording_mode'];
        }
        if (in_array('script_content', $existingColumns) && isset($data['script_content'])) {
            $columns[] = 'script_content';
            $values[] = $data['script_content'];
        }
        if (in_array('video_duration', $existingColumns) && isset($data['video_duration'])) {
            $columns[] = 'video_duration';
            $values[] = $data['video_duration'];
        }
        if (in_array('thumbnail_path', $existingColumns) && isset($data['thumbnail_path'])) {
            $columns[] = 'thumbnail_path';
            $values[] = $data['thumbnail_path'];
        }
        
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnStr = implode(', ', $columns);
        
        $stmt = $this->db->prepare("INSERT INTO videos ({$columnStr}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Get list of column names in the videos table
     */
    private function getTableColumns(): array
    {
        static $columns = null;
        if ($columns === null) {
            $stmt = $this->db->query("DESCRIBE videos");
            $columns = array_column($stmt->fetchAll(), 'Field');
        }
        return $columns;
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

    /**
     * Count how many videos user has uploaded for a specific script in a season
     */
    public function countForSeasonAndScript(int $userId, int $seasonId, ?int $scriptId): int
    {
        if ($scriptId) {
            // Count videos for this specific script
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM videos 
                 WHERE user_id = ? AND season_id = ? AND script_content = (
                     SELECT content FROM scripts WHERE id = ?
                 )"
            );
            $stmt->execute([$userId, $seasonId, $scriptId]);
        } else {
            // For freeform uploads (no script), count all freeform videos
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM videos 
                 WHERE user_id = ? AND season_id = ? AND (script_content IS NULL OR script_content = '')"
            );
            $stmt->execute([$userId, $seasonId]);
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if user has reached the limit for a specific script (2 videos per script)
     */
    public function hasReachedScriptLimit(int $userId, int $seasonId, ?int $scriptId, int $limit = 2): bool
    {
        return $this->countForSeasonAndScript($userId, $seasonId, $scriptId) >= $limit;
    }

    /**
     * Check if user has already submitted a specific content type for a season
     * @deprecated Use hasReachedScriptLimit instead
     */
    public function existsForSeasonAndType(int $userId, int $seasonId, string $contentType): bool
    {
        // This method is kept for backward compatibility but now always returns false
        // to allow multiple uploads per content type
        return false;
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

    /**
     * Update AI moderation status and score
     */
    public function updateAiStatus(int $id, string $status, ?float $score = null, ?array $feedback = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE videos SET ai_status = ?, ai_score = ?, ai_feedback = ?, 
             needs_manual_review = CASE WHEN ? = 'flagged' THEN 1 ELSE needs_manual_review END
             WHERE id = ?"
        );
        return $stmt->execute([
            $status, 
            $score, 
            $feedback ? json_encode($feedback) : null,
            $status,
            $id
        ]);
    }

    /**
     * Update YouTube upload status
     */
    public function updateYoutubeStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE videos SET youtube_status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Get videos pending AI review
     */
    public function pendingAiReview(): array
    {
        $stmt = $this->db->query(
            "SELECT v.*, u.name as user_name, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.ai_status IN ('pending', 'processing')
             ORDER BY v.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get videos flagged for manual review
     */
    public function needsManualReview(): array
    {
        $stmt = $this->db->query(
            "SELECT v.*, u.name as user_name, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.needs_manual_review = 1
             ORDER BY v.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get videos ready for YouTube upload (AI approved but not yet uploaded)
     */
    public function readyForYoutube(): array
    {
        $stmt = $this->db->query(
            "SELECT v.*, u.name as user_name, s.title as season_title
             FROM videos v
             JOIN users u ON v.user_id = u.id
             JOIN seasons s ON v.season_id = s.id
             WHERE v.status = 'approved' AND v.ai_status = 'approved' 
             AND v.youtube_status = 'pending' AND v.youtube_id IS NULL
             ORDER BY v.created_at ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get user's video statistics
     */
    public function getUserStats(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN ai_status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN needs_manual_review = 1 THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN youtube_id IS NOT NULL THEN 1 ELSE 0 END) as published
             FROM videos WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [
            'total' => 0, 'pending' => 0, 'approved' => 0, 
            'rejected' => 0, 'processing' => 0, 'flagged' => 0, 'published' => 0
        ];
    }

    /**
     * Mark video as manually approved by admin
     */
    public function manualApprove(int $id, int $adminId): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE videos SET status = 'approved', ai_status = 'approved', 
                 needs_manual_review = 0, moderated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$id]);
            
            $stmt = $this->db->prepare(
                "INSERT INTO moderation_logs (video_id, action, reason, performed_by) 
                 VALUES (?, 'admin_approved', 'Manually approved by admin', ?)"
            );
            $stmt->execute([$id, $adminId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Mark video as manually rejected by admin
     */
    public function manualReject(int $id, int $adminId, string $reason): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE videos SET status = 'rejected', ai_status = 'rejected', 
                 needs_manual_review = 0, rejection_reason = ?, moderated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$reason, $id]);
            
            $stmt = $this->db->prepare(
                "INSERT INTO moderation_logs (video_id, action, reason, performed_by) 
                 VALUES (?, 'admin_rejected', ?, ?)"
            );
            $stmt->execute([$id, $reason, $adminId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Delete a video record
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM videos WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

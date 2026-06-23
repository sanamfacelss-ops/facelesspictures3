<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Submission model — public guest submissions (no login required)
 * Actors, Directors, Writers submit contact details + optional video file
 */
class Submission
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM submissions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO submissions 
             (role, audition_type, name, email, phone, script_id, script_title, notes, file_path, file_type, file_size_bytes, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['role'],
            $data['audition_type'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['script_id'] ?? null,
            $data['script_title'] ?? null,
            $data['notes'] ?? null,
            $data['file_path'] ?? null,
            $data['file_type'] ?? null,
            $data['file_size_bytes'] ?? null,
            $data['ip_address'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM submissions ORDER BY submitted_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function byRole(string $role): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM submissions WHERE role = ? ORDER BY submitted_at DESC"
        );
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }

    public function byStatus(string $status): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM submissions WHERE status = ? ORDER BY submitted_at DESC"
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    /**
     * Search submissions by name, email, or phone
     */
    public function search(string $q): array
    {
        $like = '%' . $q . '%';
        $stmt = $this->db->prepare(
            "SELECT * FROM submissions 
             WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR audition_type LIKE ?
             ORDER BY submitted_at DESC"
        );
        $stmt->execute([$like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    /**
     * Filter with optional role, status, search
     */
    public function filter(?string $role = null, ?string $status = null, ?string $search = null): array
    {
        $where = [];
        $params = [];

        if (!empty($role)) {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        if (!empty($status)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (!empty($search)) {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR audition_type LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM submissions";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY submitted_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status, ?string $adminNotes = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE submissions SET status = ?, admin_notes = ?, reviewed_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $adminNotes, $id]);
    }

    /**
     * Link a video pipeline record to a submission
     */
    public function linkVideo(int $submissionId, int $videoId): bool
    {
        try {
            // Add video_id column if it exists (migration 006 adds it)
            $stmt = $this->db->prepare(
                "UPDATE submissions SET video_id = ? WHERE id = ?"
            );
            return $stmt->execute([$videoId, $submissionId]);
        } catch (\PDOException $e) {
            // Column may not exist yet — non-fatal
            debug_log("linkVideo failed (column may be missing): " . $e->getMessage(), 'SUBMISSION');
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM submissions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countByStatus(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as count FROM submissions GROUP BY status"
        );
        $rows = $stmt->fetchAll();
        $counts = ['new' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }

    public function countByRole(): array
    {
        $stmt = $this->db->query(
            "SELECT role, COUNT(*) as count FROM submissions GROUP BY role"
        );
        $rows = $stmt->fetchAll();
        $counts = ['actor' => 0, 'director' => 0, 'writer' => 0];
        foreach ($rows as $row) {
            $counts[$row['role']] = (int) $row['count'];
        }
        return $counts;
    }

    public function totalCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    }

    /**
     * Ensure table exists — gracefully handles missing migration
     */
    public function tableExists(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'submissions'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

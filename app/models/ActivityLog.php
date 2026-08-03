<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * ActivityLog Model
 * Handles system-wide activity logging with automatic 90-day cleanup
 */
class ActivityLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Log an activity
     */
    public function log(
        int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        ?array $metadata = null
    ): int {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->db->prepare(
            "INSERT INTO activity_log 
             (user_id, action, entity_type, entity_id, description, ip_address, user_agent, metadata) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $ip,
            $userAgent,
            $metadata ? json_encode($metadata) : null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all activity logs with pagination
     */
    public function getAll(int $page = 1, int $perPage = 50, ?string $filter = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        $where = '';
        $params = [];
        
        if ($filter) {
            $where = "WHERE action LIKE ? OR description LIKE ? OR entity_type = ?";
            $params = ["%{$filter}%", "%{$filter}%", $filter];
        }

        $sql = "SELECT 
                    al.*,
                    u.name as user_name,
                    u.email as user_email,
                    u.role as user_role
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                {$where}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Get total count
     */
    public function count(?string $filter = null): int
    {
        $where = '';
        $params = [];
        
        if ($filter) {
            $where = "WHERE action LIKE ? OR description LIKE ? OR entity_type = ?";
            $params = ["%{$filter}%", "%{$filter}%", $filter];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM activity_log {$where}");
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get activity logs for a specific user
     */
    public function getByUser(int $userId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM activity_log 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll();
    }

    /**
     * Get activity logs for a specific entity
     */
    public function getByEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email
             FROM activity_log al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.entity_type = ? AND al.entity_id = ? 
             ORDER BY al.created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$entityType, $entityId, $limit]);
        
        return $stmt->fetchAll();
    }

    /**
     * Get activity stats
     */
    public function getStats(): array
    {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT user_id) as unique_users,
                MIN(created_at) as oldest_entry,
                MAX(created_at) as newest_entry
             FROM activity_log"
        );
        
        return $stmt->fetch();
    }

    /**
     * Delete a single log entry
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM activity_log WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Bulk delete by IDs
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM activity_log WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        
        return $stmt->rowCount();
    }

    /**
     * Delete logs older than 90 days
     */
    public function cleanupOldLogs(): int
    {
        $stmt = $this->db->query("CALL cleanup_old_activity_logs()");
        $result = $stmt->fetch();
        
        return (int) ($result['deleted_count'] ?? 0);
    }

    /**
     * Delete all logs for a specific user
     */
    public function deleteByUser(int $userId): int
    {
        $stmt = $this->db->prepare("DELETE FROM activity_log WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return $stmt->rowCount();
    }

    /**
     * Check if table exists
     */
    public function tableExists(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM activity_log LIMIT 1");
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}

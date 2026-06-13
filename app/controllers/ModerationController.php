<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Video;
use App\Config\Database;
use PDO;

class ModerationController
{
    private Video $videoModel;
    private PDO $db;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->db = Database::getConnection();
    }

    public function approve(int $videoId): void
    {
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        $reason = trim($_POST['reason'] ?? 'Approved by admin');
        $this->videoModel->updateStatus($videoId, 'approved', $reason);
        $this->logAction($videoId, 'admin_approved', $reason, (int) $user['id']);

        echo json_encode(['success' => true]);
    }

    public function reject(int $videoId): void
    {
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            http_response_code(422);
            echo json_encode(['error' => 'Rejection reason is required.']);
            return;
        }

        $this->videoModel->updateStatus($videoId, 'rejected', $reason);
        $this->logAction($videoId, 'admin_rejected', $reason, (int) $user['id']);

        echo json_encode(['success' => true]);
    }

    public function pendingList(): void
    {
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($this->videoModel->pending());
    }

    private function logAction(int $videoId, string $action, string $reason, int $adminId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason, performed_by) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$videoId, $action, $reason, $adminId]);
    }
}

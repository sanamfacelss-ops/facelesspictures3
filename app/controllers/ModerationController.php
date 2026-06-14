<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Video;
use App\Config\Database;
use App\Services\EmailService;
use PDO;

class ModerationController
{
    private Video $videoModel;
    private PDO $db;
    private EmailService $emailService;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->db = Database::getConnection();
        $this->emailService = new EmailService();
    }

    /**
     * Approve a video (admin manual approval)
     */
    public function approve(int $videoId): void
    {
        header('Content-Type: application/json');
        
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        $reason = trim($_POST['reason'] ?? 'Approved by admin');
        
        // Update both main status and AI status, clear manual review flag
        $stmt = $this->db->prepare(
            "UPDATE videos SET 
                status = 'approved', 
                ai_status = 'approved',
                needs_manual_review = 0,
                rejection_reason = NULL,
                moderated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$videoId]);
        
        $this->logAction($videoId, 'admin_approved', $reason, (int) $user['id']);
        
        // Send approval email to creator
        $this->sendStatusNotification($videoId, 'approved', $reason);
        
        log_message('info', "Admin {$user['id']} approved video {$videoId}");

        // Auto-upload to YouTube after manual approval
        $youtubeResult = null;
        try {
            $youtubeService = new \App\Services\YouTubeService();
            $youtubeResult = $youtubeService->uploadVideo($videoId);
            if (is_string($youtubeResult) && !empty($youtubeResult)) {
                log_message('info', "Video {$videoId} uploaded to YouTube after manual approval: {$youtubeResult}");
            } elseif (is_array($youtubeResult) && isset($youtubeResult['error'])) {
                log_message('warning', "Video {$videoId} YouTube upload failed after manual approval: {$youtubeResult['error']}");
            }
        } catch (\Exception $e) {
            log_message('error', "Video {$videoId} YouTube upload error after manual approval: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Video approved successfully',
            'youtube' => $youtubeResult
        ]);
    }

    /**
     * Reject a video (admin manual rejection)
     */
    public function reject(int $videoId): void
    {
        header('Content-Type: application/json');
        
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

        // Update both main status and AI status, clear manual review flag
        $stmt = $this->db->prepare(
            "UPDATE videos SET 
                status = 'rejected', 
                ai_status = 'rejected',
                needs_manual_review = 0,
                rejection_reason = ?,
                moderated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$reason, $videoId]);
        
        $this->logAction($videoId, 'admin_rejected', $reason, (int) $user['id']);
        
        // Send rejection email to creator
        $this->sendStatusNotification($videoId, 'rejected', $reason);
        
        log_message('info', "Admin {$user['id']} rejected video {$videoId}: {$reason}");

        echo json_encode(['success' => true, 'message' => 'Video rejected']);
    }

    /**
     * Get list of pending videos
     */
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

    /**
     * Get list of videos needing manual review (AI flagged)
     */
    public function flaggedList(): void
    {
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($this->videoModel->needsManualReview());
    }

    /**
     * Get detailed video info for moderation
     */
    public function detail(int $videoId): void
    {
        $user = auth_user();
        if (!$user || empty($user['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        header('Content-Type: application/json');
        
        $video = $this->videoModel->findById($videoId);
        if (!$video) {
            http_response_code(404);
            echo json_encode(['error' => 'Video not found']);
            return;
        }

        // Parse AI feedback
        if (!empty($video['ai_feedback'])) {
            $video['ai_feedback_parsed'] = json_decode($video['ai_feedback'], true);
        }

        // Get moderation history
        $stmt = $this->db->prepare(
            "SELECT ml.*, u.name as moderator_name 
             FROM moderation_logs ml
             LEFT JOIN users u ON ml.performed_by = u.id
             WHERE ml.video_id = ?
             ORDER BY ml.created_at DESC"
        );
        $stmt->execute([$videoId]);
        $video['moderation_history'] = $stmt->fetchAll();

        echo json_encode($video);
    }

    /**
     * Log moderation action
     */
    private function logAction(int $videoId, string $action, string $reason, int $adminId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason, performed_by) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$videoId, $action, $reason, $adminId]);
    }

    /**
     * Send email notification to creator about video status
     */
    private function sendStatusNotification(int $videoId, string $status, string $reason): void
    {
        try {
            $video = $this->videoModel->findById($videoId);
            if (!$video) return;

            $stmt = $this->db->prepare("SELECT email, name FROM users WHERE id = ?");
            $stmt->execute([$video['user_id']]);
            $creator = $stmt->fetch();
            if (!$creator) return;

            $subject = $status === 'approved' 
                ? "✅ Your video '{$video['title']}' has been approved!"
                : "Video Update: '{$video['title']}'";

            $statusColor = $status === 'approved' ? '#10B981' : '#EF4444';
            $statusText = $status === 'approved'
                ? 'Great news! Your video has been approved by our moderation team and will be published to YouTube soon.'
                : 'Your video has been reviewed by our moderation team.';

            $reasonHtml = $status === 'rejected' && !empty($reason)
                ? "<div style='background: #FEF2F2; border-left: 4px solid #EF4444; padding: 15px; margin: 20px 0; border-radius: 4px;'><strong>Feedback:</strong> {$reason}</div>"
                : '';

            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8F5F0; padding: 40px 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header { background: #141414; padding: 25px; text-align: center; }
                    .header h1 { color: white; font-size: 20px; margin: 0; letter-spacing: 2px; }
                    .content { padding: 30px; }
                    .status-badge { display: inline-block; background: {$statusColor}; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; margin: 15px 0; font-size: 12px; }
                    .video-title { font-size: 18px; font-weight: bold; color: #1F2937; margin: 10px 0; }
                    .footer { background: #F3F4F6; padding: 20px; text-align: center; font-size: 12px; color: #6B7280; }
                    .btn { display: inline-block; background: #D92B3A; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>FACELESS PICTURES</h1>
                    </div>
                    <div class='content'>
                        <p style='margin: 0 0 10px;'>Hi {$creator['name']},</p>
                        <div class='status-badge'>" . strtoupper($status) . "</div>
                        <div class='video-title'>{$video['title']}</div>
                        <p style='color: #6B7280;'>{$statusText}</p>
                        {$reasonHtml}
                        <a href='" . APP_URL . "/creator/videos' class='btn'>View My Videos</a>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Faceless Pictures. Keep creating!
                    </div>
                </div>
            </body>
            </html>
            ";

            $this->emailService->send($creator['email'], $subject, $body, true);
            log_message('info', "Sent {$status} notification email to {$creator['email']} for video {$videoId}");
        } catch (\Exception $e) {
            log_message('error', "Failed to send moderation email for video {$videoId}: " . $e->getMessage());
        }
    }
}

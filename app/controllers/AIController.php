<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AIQualityService;
use App\Models\Video;

class AIController
{
    private AIQualityService $aiService;
    private Video $videoModel;

    public function __construct()
    {
        $this->aiService = new AIQualityService();
        $this->videoModel = new Video();
    }

    /**
     * Webhook endpoint for external AI service to send results
     */
    public function webhook(): void
    {
        header('Content-Type: application/json');

        // Verify webhook secret
        $webhookSecret = $_ENV['AI_WEBHOOK_SECRET'] ?? '';
        $providedSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';

        if ($webhookSecret && $providedSecret !== $webhookSecret) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid webhook secret']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['video_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            return;
        }

        $videoId = (int) $input['video_id'];
        $video = $this->videoModel->findById($videoId);

        if (!$video) {
            http_response_code(404);
            echo json_encode(['error' => 'Video not found']);
            return;
        }

        // Process the AI response
        $score = (float) ($input['score'] ?? 50);
        $status = $input['status'] ?? 'flagged';
        $feedback = $input['feedback'] ?? [];

        $this->videoModel->updateAiStatus($videoId, $status, $score, [
            'summary' => implode('. ', $feedback),
            'webhook_data' => $input,
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        // Update main status if approved/rejected
        if ($status === 'approved') {
            $this->videoModel->updateStatus($videoId, 'approved', 'AI approved via webhook');
        } elseif ($status === 'rejected') {
            $this->videoModel->updateStatus($videoId, 'rejected', implode('. ', $feedback));
        }

        log_message('info', "AI webhook processed for video {$videoId}: status={$status}, score={$score}");
        echo json_encode(['success' => true, 'video_id' => $videoId]);
    }

    /**
     * Trigger AI processing for a specific video (admin only)
     */
    public function process(int $videoId): void
    {
        header('Content-Type: application/json');

        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required']);
            return;
        }

        $result = $this->aiService->processVideo($videoId);
        echo json_encode($result);
    }

    /**
     * Process pending queue (for cron job)
     */
    public function processQueue(): void
    {
        header('Content-Type: application/json');

        // Allow either admin or cron key
        $cronKey = $_ENV['CRON_SECRET_KEY'] ?? '';
        $providedKey = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';

        if (!is_admin() && ($cronKey === '' || $providedKey !== $cronKey)) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $limit = min(50, (int) ($_GET['limit'] ?? 10));
        $results = $this->aiService->processQueue($limit);

        echo json_encode([
            'success' => true,
            'processed_count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * Get AI processing status for a video
     */
    public function status(int $videoId): void
    {
        header('Content-Type: application/json');

        $user = auth_user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $video = $this->videoModel->findById($videoId);
        if (!$video) {
            http_response_code(404);
            echo json_encode(['error' => 'Video not found']);
            return;
        }

        // Only allow owner or admin to check status
        if ($video['user_id'] !== $user['id'] && !$user['is_admin']) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        echo json_encode([
            'video_id' => $videoId,
            'status' => $video['status'],
            'ai_status' => $video['ai_status'] ?? 'pending',
            'ai_score' => $video['ai_score'] ?? null,
            'needs_manual_review' => (bool) ($video['needs_manual_review'] ?? false),
            'youtube_status' => $video['youtube_status'] ?? 'pending',
            'youtube_id' => $video['youtube_id'] ?? null,
        ]);
    }
}

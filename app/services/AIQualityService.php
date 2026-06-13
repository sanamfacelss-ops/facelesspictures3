<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;
use App\Config\Database;
use PDO;

/**
 * AI Quality Check Service
 * Integrates with external AI API for video quality assessment
 * Falls back to local checks if API is unavailable
 */
class AIQualityService
{
    private Video $videoModel;
    private PDO $db;
    private string $apiEndpoint;
    private string $apiKey;
    private string $uploadPath;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->db = Database::getConnection();
        $this->apiEndpoint = $_ENV['AI_API_ENDPOINT'] ?? '';
        $this->apiKey = $_ENV['AI_API_KEY'] ?? '';
        $this->uploadPath = UPLOAD_PATH;
    }

    /**
     * Process a video through AI quality check
     */
    public function processVideo(int $videoId): array
    {
        $video = $this->videoModel->findById($videoId);
        if (!$video) {
            return ['success' => false, 'error' => 'Video not found'];
        }

        // Update status to processing
        $this->videoModel->updateAiStatus($videoId, 'processing');
        $startTime = microtime(true);

        $filePath = $this->uploadPath . '/' . $video['file_path'];
        if (!file_exists($filePath)) {
            $this->videoModel->updateAiStatus($videoId, 'rejected', 0, ['error' => 'File not found']);
            return ['success' => false, 'error' => 'Video file not found'];
        }

        try {
            // Try external AI API first
            if ($this->apiEndpoint && $this->apiKey) {
                $result = $this->callExternalAI($filePath, $video);
            } else {
                // Fallback to local processing
                $result = $this->localQualityCheck($filePath, $video);
            }

            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $this->logModeration($videoId, $result, $processingTime);

            return $result;
        } catch (\Exception $e) {
            log_message('error', "AI Quality check failed for video {$videoId}: " . $e->getMessage());
            $this->videoModel->updateAiStatus($videoId, 'flagged', null, [
                'error' => 'Processing error',
                'message' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Call external AI API for quality assessment
     */
    private function callExternalAI(string $filePath, array $video): array
    {
        $ch = curl_init($this->apiEndpoint . '/analyze');
        
        $postFields = [
            'video' => new \CURLFile($filePath),
            'video_id' => $video['id'],
            'content_type' => $video['content_type'] ?? 'general',
            'script_content' => $video['script_content'] ?? '',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300, // 5 minute timeout for video processing
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new \Exception('AI API request failed with status ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['score'])) {
            throw new \Exception('Invalid AI API response');
        }

        return $this->processAIResponse($video['id'], $data);
    }

    /**
     * Local quality check when external API is unavailable
     */
    private function localQualityCheck(string $filePath, array $video): array
    {
        $score = 70.0; // Base score
        $feedback = [];
        $flags = [];

        // Check file size (penalize very small files)
        $fileSize = filesize($filePath);
        if ($fileSize < 1024 * 1024) { // Less than 1MB
            $score -= 10;
            $feedback[] = 'Video file is very small, quality may be low';
        }

        // Check video duration using ffprobe if available
        $duration = $this->getVideoDuration($filePath);
        if ($duration !== null) {
            // Update video with duration
            $stmt = $this->db->prepare("UPDATE videos SET video_duration = ? WHERE id = ?");
            $stmt->execute([$duration, $video['id']]);

            if ($duration < 10) {
                $score -= 15;
                $feedback[] = 'Video is very short (less than 10 seconds)';
            } elseif ($duration > 180) {
                $score -= 5;
                $feedback[] = 'Video exceeds recommended 3-minute length';
            }
        }

        // Check if script was provided for script mode
        if (($video['recording_mode'] ?? '') === 'script' && empty($video['script_content'])) {
            $score -= 5;
            $flags[] = 'Script mode but no script content';
        }

        // Content type specific checks
        $contentType = $video['content_type'] ?? '';
        if (in_array($contentType, ['actor', 'director', 'writer'])) {
            $score += 5; // Bonus for categorized content
        }

        // Ensure score is within bounds
        $score = max(0, min(100, $score));

        // Determine status based on score
        $status = 'approved';
        $needsManualReview = false;

        if ($score < 40) {
            $status = 'rejected';
            $feedback[] = 'Video quality score is below minimum threshold';
        } elseif ($score < 60 || !empty($flags)) {
            $status = 'flagged';
            $needsManualReview = true;
            $feedback[] = 'Video flagged for manual review';
        }

        // Update video status
        $this->videoModel->updateAiStatus($video['id'], $status, $score, [
            'summary' => implode('. ', $feedback) ?: 'Automated quality check passed',
            'score_breakdown' => [
                'base' => 70,
                'adjustments' => $score - 70,
            ],
            'flags' => $flags,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        // If approved, also update main status
        if ($status === 'approved') {
            $this->videoModel->updateStatus($video['id'], 'approved', 'Passed AI quality check');
        } elseif ($status === 'rejected') {
            $this->videoModel->updateStatus($video['id'], 'rejected', implode('. ', $feedback));
        }

        return [
            'success' => true,
            'video_id' => $video['id'],
            'score' => $score,
            'status' => $status,
            'needs_manual_review' => $needsManualReview,
            'feedback' => $feedback,
        ];
    }

    /**
     * Process response from external AI API
     */
    private function processAIResponse(int $videoId, array $data): array
    {
        $score = (float) ($data['score'] ?? 50);
        $flags = $data['flags'] ?? [];
        $feedback = $data['feedback'] ?? [];
        $nsfw = $data['nsfw_score'] ?? 0;
        $quality = $data['quality_score'] ?? 0;
        $audio = $data['audio_score'] ?? 0;

        // Determine status
        $status = 'approved';
        $needsManualReview = false;

        // NSFW content check
        if ($nsfw > 0.7) {
            $status = 'rejected';
            $feedback[] = 'Content flagged as inappropriate';
        } elseif ($nsfw > 0.4) {
            $status = 'flagged';
            $needsManualReview = true;
            $feedback[] = 'Content requires manual review for appropriateness';
        }

        // Overall quality check
        if ($score < 40) {
            $status = 'rejected';
            $feedback[] = 'Video quality below acceptable threshold';
        } elseif ($score < 60 || !empty($flags)) {
            if ($status !== 'rejected') {
                $status = 'flagged';
                $needsManualReview = true;
            }
        }

        // Update video with AI results
        $this->videoModel->updateAiStatus($videoId, $status, $score, [
            'summary' => implode('. ', $feedback) ?: 'AI analysis complete',
            'scores' => [
                'overall' => $score,
                'nsfw' => $nsfw,
                'quality' => $quality,
                'audio' => $audio,
            ],
            'flags' => $flags,
            'raw_response' => $data,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        // Update main status
        if ($status === 'approved') {
            $this->videoModel->updateStatus($videoId, 'approved', 'Passed AI quality check');
        } elseif ($status === 'rejected') {
            $this->videoModel->updateStatus($videoId, 'rejected', implode('. ', $feedback));
        }

        return [
            'success' => true,
            'video_id' => $videoId,
            'score' => $score,
            'status' => $status,
            'needs_manual_review' => $needsManualReview,
            'feedback' => $feedback,
        ];
    }

    /**
     * Get video duration using ffprobe
     */
    private function getVideoDuration(string $filePath): ?int
    {
        $ffprobePath = $_ENV['FFPROBE_PATH'] ?? 'ffprobe';
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellcmd($ffprobePath),
            escapeshellarg($filePath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            return (int) round((float) $output[0]);
        }

        return null;
    }

    /**
     * Log moderation action
     */
    private function logModeration(int $videoId, array $result, int $processingTimeMs): void
    {
        $action = match ($result['status'] ?? 'flagged') {
            'approved' => 'ai_approved',
            'rejected' => 'ai_rejected',
            default => 'ai_flagged',
        };

        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason, ai_processing_time_ms, ai_model_version) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $videoId,
            $action,
            implode('. ', $result['feedback'] ?? ['Automated check']),
            $processingTimeMs,
            'v1.0-local', // or get from API response
        ]);
    }

    /**
     * Process all pending videos in queue
     */
    public function processQueue(int $limit = 10): array
    {
        $pending = $this->videoModel->pendingAiReview();
        $processed = [];

        foreach (array_slice($pending, 0, $limit) as $video) {
            $result = $this->processVideo((int) $video['id']);
            $processed[] = [
                'video_id' => $video['id'],
                'title' => $video['title'],
                'result' => $result,
            ];
        }

        return $processed;
    }
}

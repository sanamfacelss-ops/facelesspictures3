<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;
use App\Models\User;
use App\Config\Database;
use PDO;

/**
 * Video Processing Pipeline
 * 
 * Flow: Upload → ffprobe validation → Extract frames → NSFW check → Transcribe → Profanity check → Score → Result
 */
class VideoProcessingService
{
    private PDO $db;
    private Video $videoModel;
    private ContentModerationService $moderationService;
    private TranscriptionService $transcriptionService;
    private EmailService $emailService;
    
    private string $ffmpegPath;
    private string $ffprobePath;
    private string $uploadPath;

    // Thresholds
    private const MIN_DURATION = 10;      // seconds
    private const MAX_DURATION = 180;     // seconds (3 min)
    private const NSFW_REJECT_THRESHOLD = 0.7;
    private const NSFW_FLAG_THRESHOLD = 0.4;
    private const PROFANITY_REJECT_THRESHOLD = 0.7;
    private const PROFANITY_FLAG_THRESHOLD = 0.4;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->videoModel = new Video();
        $this->moderationService = new ContentModerationService();
        $this->transcriptionService = new TranscriptionService();
        $this->emailService = new EmailService();
        
        $this->ffmpegPath = $_ENV['FFMPEG_PATH'] ?? 'ffmpeg';
        $this->ffprobePath = $_ENV['FFPROBE_PATH'] ?? 'ffprobe';
        $this->uploadPath = UPLOAD_PATH;
    }

    /**
     * Process a video through the full AI quality check pipeline
     */
    public function processVideo(int $videoId): array
    {
        $startTime = microtime(true);
        $video = $this->videoModel->findById($videoId);
        
        if (!$video) {
            return ['success' => false, 'error' => 'Video not found'];
        }

        // Update status to processing
        $this->videoModel->updateAiStatus($videoId, 'processing');
        log_message('info', "Starting AI processing for video {$videoId}");

        $filePath = $this->uploadPath . '/' . $video['file_path'];
        if (!file_exists($filePath)) {
            return $this->reject($videoId, 'Video file not found on server', $startTime);
        }

        $feedback = [];
        $flags = [];
        $score = 100; // Start with perfect score, deduct for issues

        try {
            // Step 1: Validate video with ffprobe (duration, audio track)
            $videoInfo = $this->getVideoInfo($filePath);
            if (!$videoInfo['valid']) {
                return $this->reject($videoId, $videoInfo['error'], $startTime);
            }

            // Update duration in DB
            if ($videoInfo['duration']) {
                $stmt = $this->db->prepare("UPDATE videos SET video_duration = ? WHERE id = ?");
                $stmt->execute([$videoInfo['duration'], $videoId]);
            }

            // Check duration
            if ($videoInfo['duration'] < self::MIN_DURATION) {
                return $this->reject($videoId, "Video too short ({$videoInfo['duration']}s). Minimum is " . self::MIN_DURATION . " seconds.", $startTime);
            }
            if ($videoInfo['duration'] > self::MAX_DURATION) {
                $score -= 10;
                $feedback[] = "Video exceeds recommended length ({$videoInfo['duration']}s > " . self::MAX_DURATION . "s)";
            }

            // Check audio
            if (!$videoInfo['has_audio']) {
                $score -= 20;
                $flags[] = 'no_audio';
                $feedback[] = 'No audio track detected';
            }

            // Step 2: Extract frames for visual analysis
            $framesDir = $this->extractFrames($filePath, $videoId);
            $frameFiles = glob($framesDir . '/*.jpg');
            
            // Step 3: NSFW/Visual content check
            $nsfwResult = $this->checkFramesForNSFW($frameFiles);
            
            // Cleanup frames
            $this->cleanupFrames($framesDir);
            
            if ($nsfwResult['max_score'] >= self::NSFW_REJECT_THRESHOLD) {
                return $this->reject($videoId, 
                    "Content flagged as inappropriate (NSFW score: " . round($nsfwResult['max_score'], 2) . "). " .
                    "Categories: " . implode(', ', $nsfwResult['categories']), 
                    $startTime
                );
            }
            if ($nsfwResult['max_score'] >= self::NSFW_FLAG_THRESHOLD) {
                $score -= 30;
                $flags[] = 'nsfw_warning';
                $feedback[] = "Visual content requires review (score: " . round($nsfwResult['max_score'], 2) . ")";
            }

            // Step 4: Transcribe audio
            $transcript = '';
            if ($videoInfo['has_audio']) {
                $transcriptResult = $this->transcriptionService->transcribe($filePath);
                if ($transcriptResult['success']) {
                    $transcript = $transcriptResult['text'];
                    $feedback[] = "Language detected: " . ($transcriptResult['language'] ?? 'unknown');
                } else {
                    $feedback[] = "Audio transcription skipped: " . ($transcriptResult['error'] ?? 'unknown error');
                }
            }

            // Step 5: Check transcript for profanity/hate speech
            if (!empty($transcript)) {
                $textResult = $this->moderationService->moderateText($transcript);
                
                if (!$textResult['safe'] && $textResult['score'] >= self::PROFANITY_REJECT_THRESHOLD) {
                    return $this->reject($videoId,
                        "Audio content contains prohibited content. Categories: " . implode(', ', $textResult['categories']),
                        $startTime
                    );
                }
                if (!$textResult['safe'] && $textResult['score'] >= self::PROFANITY_FLAG_THRESHOLD) {
                    $score -= 30;
                    $flags[] = 'profanity_warning';
                    $feedback[] = "Audio content flagged for review. Categories: " . implode(', ', $textResult['categories']);
                }
            }

            // Step 6: Calculate final result
            $score = max(0, min(100, $score));
            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            // Determine final status
            if ($score >= 70 && empty($flags)) {
                return $this->approve($videoId, $score, $feedback, $flags, $transcript, $nsfwResult, $processingTime);
            } else {
                return $this->flag($videoId, $score, $feedback, $flags, $transcript, $nsfwResult, $processingTime);
            }

        } catch (\Exception $e) {
            log_message('error', "Video processing error for {$videoId}: " . $e->getMessage());
            return $this->flag($videoId, 50, ["Processing error: " . $e->getMessage()], ['processing_error'], '', [], 
                (int)((microtime(true) - $startTime) * 1000)
            );
        }
    }

    /**
     * Get video information using ffprobe
     */
    private function getVideoInfo(string $filePath): array
    {
        $cmd = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellcmd($this->ffprobePath),
            escapeshellarg($filePath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return ['valid' => false, 'error' => 'Failed to analyze video file'];
        }

        $data = json_decode(implode("\n", $output), true);
        if (!$data) {
            return ['valid' => false, 'error' => 'Invalid video file format'];
        }

        $duration = (int) round((float) ($data['format']['duration'] ?? 0));
        $hasAudio = false;
        $hasVideo = false;

        foreach ($data['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'audio') $hasAudio = true;
            if ($stream['codec_type'] === 'video') $hasVideo = true;
        }

        if (!$hasVideo) {
            return ['valid' => false, 'error' => 'No video stream found'];
        }

        return [
            'valid' => true,
            'duration' => $duration,
            'has_audio' => $hasAudio,
            'has_video' => $hasVideo,
            'format' => $data['format']['format_name'] ?? 'unknown',
        ];
    }

    /**
     * Extract frames from video (1 frame every 5 seconds, max 10 frames)
     */
    private function extractFrames(string $filePath, int $videoId): string
    {
        $framesDir = sys_get_temp_dir() . '/fp3_frames_' . $videoId . '_' . uniqid();
        @mkdir($framesDir, 0777, true);

        $cmd = sprintf(
            '%s -i %s -vf "fps=1/5,scale=640:-1" -frames:v 10 -q:v 3 %s/frame_%%03d.jpg 2>&1',
            escapeshellcmd($this->ffmpegPath),
            escapeshellarg($filePath),
            escapeshellarg($framesDir)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            log_message('warning', "Frame extraction warning for video {$videoId}: " . implode("\n", $output));
        }

        return $framesDir;
    }

    /**
     * Check frames for NSFW content
     */
    private function checkFramesForNSFW(array $frameFiles): array
    {
        $maxScore = 0;
        $allCategories = [];
        $provider = 'none';

        foreach ($frameFiles as $framePath) {
            $result = $this->moderationService->moderateImage($framePath);
            
            if ($result['score'] > $maxScore) {
                $maxScore = $result['score'];
                $provider = $result['provider'];
            }
            
            foreach ($result['categories'] as $cat) {
                if (!in_array($cat, $allCategories)) {
                    $allCategories[] = $cat;
                }
            }
        }

        return [
            'max_score' => $maxScore,
            'categories' => $allCategories,
            'provider' => $provider,
            'frames_checked' => count($frameFiles),
        ];
    }

    /**
     * Cleanup extracted frames
     */
    private function cleanupFrames(string $framesDir): void
    {
        if (is_dir($framesDir)) {
            array_map('unlink', glob($framesDir . '/*'));
            @rmdir($framesDir);
        }
    }

    /**
     * Approve video
     */
    private function approve(int $videoId, float $score, array $feedback, array $flags, string $transcript, array $nsfwResult, int $processingTimeMs): array
    {
        $this->videoModel->updateAiStatus($videoId, 'approved', $score, [
            'score' => $score,
            'summary' => 'Video passed AI quality check',
            'feedback' => $feedback,
            'flags' => $flags,
            'transcript' => substr($transcript, 0, 5000),
            'nsfw_result' => $nsfwResult,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        $this->videoModel->updateStatus($videoId, 'approved', 'Passed AI quality check');
        $this->logModeration($videoId, 'ai_approved', implode('. ', $feedback), $processingTimeMs);
        
        // Send approval email
        $video = $this->videoModel->findById($videoId);
        if ($video) {
            $this->sendStatusEmail($video, 'approved');
        }

        log_message('info', "Video {$videoId} approved with score {$score}");

        return [
            'success' => true,
            'video_id' => $videoId,
            'status' => 'approved',
            'score' => $score,
            'feedback' => $feedback,
        ];
    }

    /**
     * Flag video for manual review
     */
    private function flag(int $videoId, float $score, array $feedback, array $flags, string $transcript, array $nsfwResult, int $processingTimeMs): array
    {
        $this->videoModel->updateAiStatus($videoId, 'flagged', $score, [
            'score' => $score,
            'summary' => 'Video flagged for manual review',
            'feedback' => $feedback,
            'flags' => $flags,
            'transcript' => substr($transcript, 0, 5000),
            'nsfw_result' => $nsfwResult,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        // Set needs_manual_review flag
        $stmt = $this->db->prepare("UPDATE videos SET needs_manual_review = 1 WHERE id = ?");
        $stmt->execute([$videoId]);

        $this->logModeration($videoId, 'ai_flagged', implode('. ', $feedback), $processingTimeMs);
        
        log_message('info', "Video {$videoId} flagged for review with score {$score}");

        return [
            'success' => true,
            'video_id' => $videoId,
            'status' => 'flagged',
            'score' => $score,
            'feedback' => $feedback,
            'needs_manual_review' => true,
        ];
    }

    /**
     * Reject video
     */
    private function reject(int $videoId, string $reason, float $startTime): array
    {
        $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);

        $this->videoModel->updateAiStatus($videoId, 'rejected', 0, [
            'summary' => $reason,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        $this->videoModel->updateStatus($videoId, 'rejected', $reason);
        $this->logModeration($videoId, 'ai_rejected', $reason, $processingTimeMs);
        
        // Send rejection email
        $video = $this->videoModel->findById($videoId);
        if ($video) {
            $this->sendStatusEmail($video, 'rejected', $reason);
        }

        log_message('info', "Video {$videoId} rejected: {$reason}");

        return [
            'success' => true,
            'video_id' => $videoId,
            'status' => 'rejected',
            'score' => 0,
            'feedback' => [$reason],
        ];
    }

    /**
     * Log moderation action
     */
    private function logModeration(int $videoId, string $action, string $reason, int $processingTimeMs): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason, ai_processing_time_ms, ai_model_version) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $videoId,
            $action,
            $reason,
            $processingTimeMs,
            'v2.0-api'
        ]);
    }

    /**
     * Send status email to user
     */
    private function sendStatusEmail(array $video, string $status, string $reason = ''): void
    {
        try {
            $userModel = new User();
            $user = $userModel->findById((int) $video['user_id']);
            if (!$user) return;

            $subject = match($status) {
                'approved' => "✅ Your video '{$video['title']}' has been approved!",
                'rejected' => "❌ Your video '{$video['title']}' was not approved",
                default => "Video status update: {$video['title']}"
            };

            $statusColor = $status === 'approved' ? '#10B981' : '#EF4444';
            $statusIcon = $status === 'approved' ? '✅' : '❌';
            $statusText = $status === 'approved' 
                ? 'Your video has passed our AI quality check and will be published to YouTube soon.'
                : 'Unfortunately, your video did not pass our quality check.';

            $reasonHtml = !empty($reason) 
                ? "<div style='background: #FEF2F2; border-left: 4px solid #EF4444; padding: 15px; margin: 20px 0; border-radius: 4px;'><strong>Reason:</strong> {$reason}</div>" 
                : '';

            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8F5F0; padding: 40px 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header { background: #141414; padding: 25px; text-align: center; }
                    .header h1 { color: white; font-size: 20px; margin: 0; }
                    .content { padding: 30px; }
                    .status-badge { display: inline-block; background: {$statusColor}; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; margin: 15px 0; }
                    .video-title { font-size: 18px; font-weight: bold; color: #1F2937; margin: 10px 0; }
                    .footer { background: #F3F4F6; padding: 20px; text-align: center; font-size: 12px; color: #6B7280; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>FACELESS PICTURES</h1>
                    </div>
                    <div class='content'>
                        <p style='font-size: 24px; margin: 0;'>{$statusIcon}</p>
                        <div class='status-badge'>" . strtoupper($status) . "</div>
                        <div class='video-title'>{$video['title']}</div>
                        <p style='color: #6B7280;'>{$statusText}</p>
                        {$reasonHtml}
                        <a href='" . APP_URL . "/creator/dashboard' style='display: inline-block; background: #141414; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 20px;'>View Dashboard</a>
                    </div>
                    <div class='footer'>
                        &copy; " . date('Y') . " Faceless Pictures. All rights reserved.
                    </div>
                </div>
            </body>
            </html>
            ";

            $this->emailService->send($user['email'], $subject, $body, true);
        } catch (\Exception $e) {
            log_message('error', 'Failed to send status email: ' . $e->getMessage());
        }
    }

    /**
     * Process queue of pending videos
     */
    public function processQueue(int $limit = 5): array
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
            
            // Small delay between videos to respect API rate limits
            usleep(500000); // 0.5 second
        }

        return $processed;
    }
}

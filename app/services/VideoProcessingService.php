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

    // Dynamic thresholds (loaded from database settings)
    private float $minDuration;
    private float $maxDuration;
    private float $nsfwRejectThreshold;
    private float $nsfwFlagThreshold;
    private float $profanityRejectThreshold;
    private float $profanityFlagThreshold;
    private float $approveThreshold;
    private float $flagThreshold;

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
        
        // Load thresholds from database settings
        $this->loadThresholds();
    }
    
    /**
     * Load thresholds from database settings
     */
    private function loadThresholds(): void
    {
        $settingsModel = new \App\Models\Settings();
        $aiSettings = $settingsModel->getAISettings();
        
        $this->minDuration = (float) ($aiSettings['ai_min_duration'] ?? 0);
        $this->maxDuration = (float) ($aiSettings['ai_max_duration'] ?? 300);
        $this->nsfwRejectThreshold = (float) ($aiSettings['ai_nsfw_reject_threshold'] ?? 0.7);
        $this->nsfwFlagThreshold = (float) ($aiSettings['ai_nsfw_flag_threshold'] ?? 0.4);
        $this->profanityRejectThreshold = (float) ($aiSettings['ai_profanity_reject_threshold'] ?? 0.7);
        $this->profanityFlagThreshold = (float) ($aiSettings['ai_profanity_flag_threshold'] ?? 0.4);
        $this->approveThreshold = (float) ($aiSettings['ai_approve_threshold'] ?? 70);
        $this->flagThreshold = (float) ($aiSettings['ai_flag_threshold'] ?? 40);
        
        log_message('debug', sprintf(
            "AI Thresholds loaded: minDuration=%.1f, maxDuration=%.1f, nsfwReject=%.2f, approveThreshold=%.0f",
            $this->minDuration, $this->maxDuration, $this->nsfwRejectThreshold, $this->approveThreshold
        ));
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
            if ($this->minDuration > 0 && $videoInfo['duration'] < $this->minDuration) {
                return $this->reject($videoId, "Video too short ({$videoInfo['duration']}s). Minimum is {$this->minDuration} seconds.", $startTime);
            }
            if ($videoInfo['duration'] > $this->maxDuration) {
                $score -= 10;
                $feedback[] = "Video exceeds recommended length ({$videoInfo['duration']}s > {$this->maxDuration}s)";
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
            
            if ($nsfwResult['max_score'] >= $this->nsfwRejectThreshold) {
                return $this->reject($videoId, 
                    "Content flagged as inappropriate (NSFW score: " . round($nsfwResult['max_score'], 2) . "). " .
                    "Categories: " . implode(', ', $nsfwResult['categories']), 
                    $startTime
                );
            }
            
            // Flag if score is above threshold OR if any SERIOUS concerning categories detected
            // "sexual" at low scores is usually just romantic content - only flag if score >= 0.7
            $seriousCategories = ['violence', 'hate', 'selfharm', 'self-harm', 'nudity', 'gore', 'nsfw'];
            $hasSeriousCategory = !empty(array_intersect(array_map('strtolower', $nsfwResult['categories']), $seriousCategories));
            
            // Sexual only counts if score is high enough (>70%) - romantic content often triggers low sexual scores
            $hasSexualConcern = in_array('sexual', array_map('strtolower', $nsfwResult['categories'])) && $nsfwResult['max_score'] >= 0.7;
            
            if ($nsfwResult['max_score'] >= $this->nsfwFlagThreshold || $hasSeriousCategory || $hasSexualConcern) {
                $deduction = ($hasSeriousCategory || $hasSexualConcern) ? max(20, (int)($nsfwResult['max_score'] * 100 * 0.5)) : 30;
                $score -= $deduction;
                $flags[] = 'nsfw_warning';
                $feedback[] = "Visual content flagged: " . implode(', ', $nsfwResult['categories']) . " (score: " . round($nsfwResult['max_score'] * 100) . "%)";
                log_message('info', "Video {$videoId} flagged for visual content: categories=" . implode(',', $nsfwResult['categories']) . ", score=" . $nsfwResult['max_score']);
            } else if (!empty($nsfwResult['categories'])) {
                // Minor categories detected but not serious - just add feedback without flagging
                $feedback[] = "Note: " . implode(', ', $nsfwResult['categories']) . " detected at low level (" . round($nsfwResult['max_score'] * 100) . "%) - within acceptable range";
            }

            // Step 4: Transcribe audio
            $transcript = '';
            $transcriptUnreliable = false;
            $isNonEnglish = false;
            $detectedLanguage = 'unknown';
            
            if ($videoInfo['has_audio']) {
                $transcriptResult = $this->transcriptionService->transcribe($filePath);
                if ($transcriptResult['success']) {
                    $transcript = $transcriptResult['text'];
                    $transcriptUnreliable = $transcriptResult['unreliable'] ?? false;
                    $detectedLanguage = $transcriptResult['language'] ?? 'unknown';
                    $whisperLanguage = $transcriptResult['whisper_language'] ?? $detectedLanguage;
                    $feedback[] = "Language detected: " . $detectedLanguage;
                    
                    // Check if Whisper detected non-English language
                    $nonEnglishLanguages = ['hindi', 'punjabi', 'panjabi', 'tamil', 'telugu', 'bengali', 
                                           'marathi', 'gujarati', 'kannada', 'malayalam', 'urdu', 'hinglish'];
                    $isNonEnglish = false;
                    foreach ($nonEnglishLanguages as $lang) {
                        if (stripos($detectedLanguage, $lang) !== false || stripos($whisperLanguage, $lang) !== false) {
                            $isNonEnglish = true;
                            break;
                        }
                    }
                    
                    log_message('info', "Video {$videoId}: Language={$detectedLanguage}, NonEnglish={$isNonEnglish}, Unreliable={$transcriptUnreliable}");
                } else {
                    $feedback[] = "Audio transcription skipped: " . ($transcriptResult['error'] ?? 'unknown error');
                }
            }

            // Step 5: Check transcript for profanity/hate speech
            // Different logic for English vs Non-English
            
            if (!empty($transcript)) {
                $textResult = $this->moderationService->moderateText($transcript);
                
                // For English: trust the moderation result fully
                if (!$isNonEnglish && !$transcriptUnreliable) {
                    if (!$textResult['safe'] && $textResult['score'] >= $this->profanityRejectThreshold) {
                        return $this->reject($videoId,
                            "Audio content contains prohibited content. Categories: " . implode(', ', $textResult['categories']),
                            $startTime
                        );
                    }
                    if (!$textResult['safe'] && $textResult['score'] >= $this->profanityFlagThreshold) {
                        $score -= 30;
                        $flags[] = 'profanity_warning';
                        $feedback[] = "Audio content flagged for review. Categories: " . implode(', ', $textResult['categories']);
                    }
                } else {
                    // For Non-English OR Unreliable transcript:
                    // Check for Hindi/Indian profanity patterns - ONLY flag if profanity found
                    $hindiProfanityFound = $this->checkHindiProfanity($transcript);
                    
                    if ($hindiProfanityFound) {
                        // Profanity detected in Hindi content - flag for manual review
                        $score -= 40;
                        $flags[] = 'hindi_profanity_detected';
                        $feedback[] = "⚠️ Hindi/Indian profanity detected - MANUAL REVIEW REQUIRED";
                        log_message('warning', "Video {$videoId}: Hindi profanity found in transcript");
                    } else if ($transcriptUnreliable && $isNonEnglish) {
                        // Transcript is garbage AND non-English - be cautious but don't block
                        // Only add a minor note, don't deduct score heavily
                        $feedback[] = "Note: Non-English audio, transcript may be incomplete";
                        log_message('info', "Video {$videoId}: Non-English with unclear transcript, but no profanity detected - allowing");
                    }
                    // If non-English but clean transcript and no profanity → auto-approve (no flag)
                    
                    // Also check Azure/OpenAI result for any flags
                    // BUT ignore "sexual" category at low scores for non-English - often triggers on romantic content
                    if (!$textResult['safe'] && $textResult['score'] >= $this->profanityFlagThreshold) {
                        // Filter out "sexual" category if it's the only one and score is low
                        $categories = $textResult['categories'] ?? [];
                        $nonSexualCategories = array_filter($categories, fn($c) => strtolower($c) !== 'sexual');
                        
                        if (!empty($nonSexualCategories) || $textResult['score'] >= 0.5) {
                            $score -= 20;
                            $flags[] = 'content_warning';
                            $feedback[] = "Content flagged by AI: " . implode(', ', $textResult['categories']);
                        } else {
                            // Only sexual at low score - just note it, don't flag
                            $feedback[] = "Note: Romantic/mild content detected (" . round($textResult['score'] * 100) . "%) - within acceptable range";
                        }
                    }
                }
            }

            // Step 6: Calculate final result
            $score = max(0, min(100, $score));
            $processingTime = (int)((microtime(true) - $startTime) * 1000);

            // Determine final status - use configurable thresholds
            if ($score >= $this->approveThreshold && empty($flags)) {
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
            
            // Always capture the provider from the first successful result
            if ($provider === 'none' && !empty($result['provider']) && $result['provider'] !== 'none' && $result['provider'] !== 'error') {
                $provider = $result['provider'];
            }
            
            if ($result['score'] > $maxScore) {
                $maxScore = $result['score'];
                // Update provider to the one that found the highest score
                if (!empty($result['provider']) && $result['provider'] !== 'none' && $result['provider'] !== 'error') {
                    $provider = $result['provider'];
                }
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
     * Check for Hindi/Indian profanity in transcript
     * This catches gaalis even in garbage transcripts from Whisper
     * Returns true if ANY hint of profanity is found
     */
    private function checkHindiProfanity(string $text): bool
    {
        $lower = strtolower($text);
        
        // Strong gaalis - always flag if found anywhere (even as substring)
        $strongGaalis = [
            'madarchod', 'maderchod', 'madarchodd', 'madarchot', 'motherchod',
            'bhenchod', 'behenchod', 'banchod', 'benchod', 'bhenchot', 'bahinchod',
            'chutiya', 'chutiye', 'chutia', 'chutiyo', 'chootiya', 'chutiyapa',
            'gaandu', 'gandu',
            'bhosdike', 'bsdk', 'bhosdiwale', 'bhosdika',
            'randi', 'randikhana',
            'harami', 'haramkhor', 'haramzada', 'haramzade',
            'lanjakodaka',
            'thevdiya', 'thevidiya', 'thayoli',
        ];
        
        foreach ($strongGaalis as $gaali) {
            if (str_contains($lower, $gaali)) {
                log_message('info', "Hindi profanity detected (strong): '{$gaali}' in transcript");
                return true;
            }
        }
        
        // Weaker words - only match as whole words (with word boundaries)
        // These can appear in normal English words (e.g., "sale" in "wholesale", "rand" in "random")
        $weakGaalis = [
            'madar', 'behen', 'choot', 'chut', 'gaand', 'gand', 'lund', 'lauda', 'loda',
            'lavda', 'lawda', 'bosdi', 'bhosd', 'raand', 'rand', 'haram',
            'kutte', 'kutta', 'kutiya', 'kutia', 'kutti',
            'kamina', 'kamine', 'kameena', 'kameene',
            'saala', 'sala', 'saale', 'sale', 'sali',  // Common false positives!
            'chod', 'choda', 'chodi', 'chodna', 'chodu',
            'lanja', 'modda', 'gudda', 'punda', 'sunni',
        ];
        
        foreach ($weakGaalis as $gaali) {
            // Use word boundary regex to avoid false positives
            if (preg_match('/\b' . preg_quote($gaali, '/') . '\b/i', $text)) {
                log_message('info', "Hindi profanity detected (word boundary): '{$gaali}' in transcript");
                return true;
            }
        }
        
        // Check phonetic patterns (Whisper might transcribe "madarchod" as "mother chord" etc.)
        $patterns = [
            '/\bmother\s*ch[oa]d/i',          // mother chod/chord
            '/\bmaa\s*ch[oa]d/i',             // maa chod
            '/\bsister\s*f[u]+ck/i',          // sister fuck (bhenchod literal)
            '/\bbehen.*chod/i',               // behen chod
            '/\bbhen.*chod/i',                // bhen chod
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                log_message('info', "Hindi profanity pattern matched in transcript");
                return true;
            }
        }
        
        return false;
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

        // Auto-upload to YouTube for approved videos (if enabled)
        try {
            $settingsModel = new \App\Models\Settings();
            if ($settingsModel->isYouTubeAutoPublishEnabled()) {
                $youtubeService = new YouTubeService();
                $youtubeResult = $youtubeService->uploadVideo($videoId);
                if (is_string($youtubeResult) && !empty($youtubeResult)) {
                    log_message('info', "Video {$videoId} auto-uploaded to YouTube: {$youtubeResult}");
                } elseif (is_array($youtubeResult) && isset($youtubeResult['error'])) {
                    log_message('warning', "Video {$videoId} YouTube auto-upload failed: {$youtubeResult['error']}");
                }
            } else {
                log_message('info', "Video {$videoId} approved but YouTube auto-publish is paused");
            }
        } catch (\Exception $e) {
            log_message('error', "Video {$videoId} YouTube auto-upload error: " . $e->getMessage());
        }

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

        // Send notifications
        $video = $this->videoModel->findById($videoId);
        if ($video) {
            // Notify user that video is under manual review (if enabled)
            if ($this->emailService->isNotificationEnabled('flagged')) {
                $userModel = new User();
                $user = $userModel->findById((int) $video['user_id']);
                if ($user) {
                    $this->emailService->sendVideoManualReviewEmail($user, $video);
                }
            }
            
            // Notify admin about flagged video (if enabled)
            $notificationSettings = $this->emailService->getNotificationSettings();
            $adminEmail = $this->emailService->getAdminEmail();
            if (!empty($adminEmail) && ($notificationSettings['admin_flagged'] ?? '1') === '1') {
                $userModel = $userModel ?? new User();
                $user = $user ?? $userModel->findById((int) $video['user_id']);
                if ($user) {
                    $this->emailService->sendAdminFlaggedVideoEmail($adminEmail, $user, $video, [
                        'score' => $score,
                        'reason' => implode('. ', $feedback),
                    ]);
                }
            }
        }

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

            // Check if notification is enabled for this status
            $notificationKey = match($status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                'processing' => 'processing',
                default => null
            };
            
            if ($notificationKey && !$this->emailService->isNotificationEnabled($notificationKey)) {
                debug_log("Email notification for '{$status}' is disabled, skipping", 'VIDEO_PROCESSING');
                return;
            }

            // Use proper email templates from EmailService
            $sent = match($status) {
                'approved' => $this->emailService->sendVideoApprovedEmail($user, $video),
                'rejected' => $this->emailService->sendVideoRejectedEmail($user, $video, $reason),
                'processing' => $this->emailService->sendVideoProcessingEmail($user, $video),
                default => false
            };
            
            if ($sent) {
                debug_log("Status email sent to {$user['email']} for video {$video['id']} (status: {$status})", 'VIDEO_PROCESSING');
            } else {
                debug_log("Failed to send status email to {$user['email']}: " . $this->emailService->getLastError(), 'VIDEO_PROCESSING');
            }
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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Submission;
use App\Models\Script;
use App\Models\Settings;
use App\Models\Season;
use App\Models\Video;
use App\Services\BackgroundProcessor;

/**
 * SubmissionController — handles public (no-login) audition submissions
 * from /actor, /director, /writer pages.
 *
 * Video is REQUIRED for every submission. Once saved to submissions table,
 * the video is also inserted into the videos table and queued for AI
 * moderation + YouTube publishing via the existing pipeline.
 */
class SubmissionController
{
    private Submission $submissionModel;
    private Script     $scriptModel;
    private Settings   $settingsModel;
    private Season     $seasonModel;
    private Video      $videoModel;

    public function __construct()
    {
        $this->submissionModel = new Submission();
        $this->scriptModel     = new Script();
        $this->settingsModel   = new Settings();
        $this->seasonModel     = new Season();
        $this->videoModel      = new Video();
    }

    /**
     * POST /api/submit — public guest submission (no auth required)
     * Video upload is mandatory; file is fed into the AI/YouTube pipeline.
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        try {
            // ── Rate limiting: max 10 submissions per IP per hour ──────────
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if ($this->isRateLimited($ip)) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many submissions. Please try again later.']);
                return;
            }

            // ── Read form fields ──────────────────────────────────────────
            $role         = trim($_POST['role']          ?? '');
            $auditionType = trim($_POST['audition_type'] ?? '');
            $name         = trim($_POST['name']          ?? '');
            $email        = trim($_POST['email']         ?? '');
            $phone        = trim($_POST['phone']         ?? '');
            $scriptId     = !empty($_POST['script_id']) ? (int) $_POST['script_id'] : null;
            $notes        = strip_tags(trim($_POST['notes'] ?? ''));
            $name         = strip_tags($name);

            // ── Validation ────────────────────────────────────────────────
            $errors = [];

            if (!in_array($role, ['actor', 'director', 'writer'])) {
                $errors[] = 'Invalid role.';
            }
            if (empty($auditionType)) {
                $errors[] = 'Audition type is required.';
            }
            if (empty(trim($name))) {
                $errors[] = 'Full name is required.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            }
            if (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) {
                $errors[] = 'A valid phone number is required (7–20 digits).';
            }

            // ── Video is required ─────────────────────────────────────────
            // Support multiple field names: file, director_video, writer_video
            $fileFieldNames = ['file', 'director_video', 'writer_video'];
            $file = null;
            $filePresent = false;
            $fileOk = false;
            
            foreach ($fileFieldNames as $fieldName) {
                if (!empty($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES[$fieldName];
                    $filePresent = true;
                    $fileOk = $file['error'] === UPLOAD_ERR_OK;
                    break;
                }
            }

            if (!$filePresent || !$fileOk) {
                if (!$filePresent) {
                    $errors[] = 'A video file is required for your submission.';
                } else {
                    // Specific upload error
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
                        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded — please try again.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    ];
                    $errors[] = $uploadErrors[$file['error']] ?? 'Video upload error.';
                }
            }

            // ── Validate script if provided ───────────────────────────────
            $scriptTitle = null;
            if ($scriptId && empty($errors)) {
                $script = $this->scriptModel->findById($scriptId);
                if (!$script) {
                    $errors[] = 'Invalid script selected.';
                } elseif ($script['category'] !== $role) {
                    $errors[] = 'Script does not match your role.';
                } else {
                    $scriptTitle = $script['title'];
                }
            }

            if (!empty($errors)) {
                http_response_code(422);
                echo json_encode(['errors' => $errors]);
                return;
            }

            // ── Validate video file ───────────────────────────────────────
            // $file is already set from the multi-field name check above
            $allowedMimes = [
                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
                'video/mpeg', 'video/avi',
            ];
            $allowedExts  = ['mp4', 'mov', 'avi', 'webm', 'mpeg'];
            $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxBytes     = 500 * 1024 * 1024; // 500 MB

            if (!in_array($file['type'], $allowedMimes) && !in_array($ext, $allowedExts)) {
                http_response_code(422);
                echo json_encode(['errors' => ['Only MP4, MOV, AVI, or WEBM video files are accepted.']]);
                return;
            }

            if ($file['size'] > $maxBytes) {
                http_response_code(422);
                echo json_encode(['errors' => ['Video exceeds the 500 MB size limit.']]);
                return;
            }

            // ── Save to disk ──────────────────────────────────────────────
            if (!is_dir(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0755, true);
            }

            $filename = 'sub_' . uniqid('', true) . '.' . $ext;
            $dest     = UPLOAD_PATH . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                http_response_code(500);
                echo json_encode(['errors' => ['Failed to save video. Please try again.']]);
                return;
            }

            // ── Ensure submissions table exists ───────────────────────────
            if (!$this->submissionModel->tableExists()) {
                // Clean up uploaded file before bailing
                @unlink($dest);
                http_response_code(503);
                echo json_encode(['error' => 'Submissions system not yet set up. Run migration 006.']);
                return;
            }

            // ── Save submission record ────────────────────────────────────
            $submissionId = $this->submissionModel->create([
                'role'            => $role,
                'audition_type'   => $auditionType,
                'name'            => $name,
                'email'           => $email,
                'phone'           => $phone,
                'script_id'       => $scriptId,
                'script_title'    => $scriptTitle,
                'notes'           => $notes ?: null,
                'file_path'       => $filename,
                'file_type'       => $ext,
                'file_size_bytes' => $file['size'],
                'submission_tag'  => $role . '-single',
                'ip_address'      => $ip,
            ]);

            log_message('info',
                "New submission #{$submissionId}: {$name} <{$email}> — {$role} / {$auditionType}"
            );

            // ── Send email notification to submitter ──────────────────────
            try {
                $emailService = new \App\Services\EmailService();
                if ($emailService->isNotificationEnabled('submit')) {
                    $emailService->sendSubmissionReceivedEmail($name, $email, $role, $auditionType);
                }
                // Admin notification
                $adminEmail = $emailService->getAdminEmail();
                if (!empty($adminEmail) && $emailService->isNotificationEnabled('admin_new_video')) {
                    $emailService->sendAdminNewSubmissionEmail($adminEmail, $name, $email, $role, $auditionType, $submissionId);
                }
            } catch (\Throwable $emailErr) {
                log_exception($emailErr, 'SUBMISSION_EMAIL');
            }

            // ── Feed into videos pipeline ─────────────────────────────────
            // Find or create a guest-submissions season so the foreign key is satisfied
            $videoId = null;
            try {
                $season = $this->seasonModel->getActive();

                if (!$season) {
                    // Create a catch-all season for guest submissions
                    $seasonId = $this->seasonModel->create([
                        'title'      => 'Open Auditions',
                        'brief'      => 'Public auditions submitted via the website.',
                        'start_date' => date('Y-01-01'),
                        'end_date'   => date('Y-12-31'),
                        'status'     => 'active',
                    ]);
                    $season = $this->seasonModel->findById($seasonId);
                }

                // Create a synthetic user record for this guest, or reuse one by email
                $guestUserId = $this->getOrCreateGuestUser($name, $email, $role);

                // Title: audition type + role
                $videoTitle = $auditionType . ' — ' . ucfirst($role)
                    . ' (' . $name . ')';

                $videoId = $this->videoModel->create([
                    'user_id'      => $guestUserId,
                    'season_id'    => $season['id'],
                    'title'        => $videoTitle,
                    'content_type' => $role,
                    'file_path'    => $filename,
                    'recording_mode' => 'freeform',
                    'script_content' => $notes ?: ($scriptTitle ?? null),
                ]);

                // Link video_id back to the submission row
                $this->submissionModel->linkVideo($submissionId, $videoId);

                // Queue for AI moderation → YouTube publishing
                BackgroundProcessor::queueVideoProcessing($videoId);

                log_message('info',
                    "Submission #{$submissionId} → video #{$videoId} queued for AI/YouTube pipeline"
                );
            } catch (\Throwable $pipelineErr) {
                // Pipeline failure must NOT block the submission confirmation
                log_exception($pipelineErr, 'SUBMISSION_PIPELINE');
            }

            echo json_encode([
                'success'       => true,
                'id'            => $submissionId,
                'video_id'      => $videoId,
                'submitter_name'=> $name,
                'submitter_email'=> $email,
                'role'          => $role,
                'audition_type' => $auditionType,
                'message'       => "Your submission has been received! "
                    . "We'll review it and be in touch at {$email}.",
            ]);

        } catch (\PDOException $e) {
            log_exception($e, 'SUBMISSION_DB');
            http_response_code(500);
            echo json_encode(['error' => 'Database error. Please try again.']);
        } catch (\Throwable $e) {
            log_exception($e, 'SUBMISSION');
            http_response_code(500);
            echo json_encode(['error' => 'An unexpected error occurred. Please try again.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Find an existing guest user by email or create a minimal one.
     * Guest users have a random password (they never log in).
     */
    private function getOrCreateGuestUser(string $name, string $email, string $role): int
    {
        $db = \App\Config\Database::getConnection();

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        // Create a guest user — no password they can use
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password, role, content_categories, is_admin)
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([
            $name,
            $email,
            $hash,
            $role,
            json_encode([$role]),
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * POST /api/submit/actor — Actor dual-video submission (dialog + song)
     * Both videos required. Creates one submission record, two video pipeline entries.
     */
    public function actorSubmit(): void
    {
        header('Content-Type: application/json');

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            if ($this->isRateLimited($ip)) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many submissions. Please try again later.']);
                return;
            }

            $name  = strip_tags(trim($_POST['name']  ?? ''));
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            $errors = [];
            if (strlen($name) < 2)                               $errors[] = 'Full name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = 'Valid email required.';
            if (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $phone)) $errors[] = 'Valid phone required.';

            // Both videos required
            $dialogFile = $_FILES['dialog_video'] ?? null;
            $songFile   = $_FILES['song_video']   ?? null;

            if (!$dialogFile || $dialogFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Dialog audition video is required.';
            }
            if (!$songFile || $songFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Song audition video is required.';
            }

            if (!empty($errors)) {
                http_response_code(422);
                echo json_encode(['errors' => $errors]);
                return;
            }

            if (!$this->submissionModel->tableExists()) {
                http_response_code(503);
                echo json_encode(['error' => 'Run migration 006 first.']);
                return;
            }

            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);

            // Save both files
            $saved = [];
            $fileMap = ['dialog_video' => $dialogFile, 'song_video' => $songFile];
            foreach ($fileMap as $key => $file) {
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $name_file = 'sub_' . uniqid('', true) . '.' . $ext;
                $dest = UPLOAD_PATH . '/' . $name_file;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    http_response_code(500);
                    echo json_encode(['errors' => ['Failed to save ' . $key . '.']]);
                    return;
                }
                $saved[$key] = ['path' => $name_file, 'ext' => $ext, 'size' => $file['size']];
            }

            // One submission record with both files
            $submissionId = $this->submissionModel->create([
                'role'             => 'actor',
                'audition_type'    => 'Actor Audition (Dialog + Song)',
                'name'             => $name,
                'email'            => $email,
                'phone'            => $phone,
                'file_path'        => $saved['dialog_video']['path'],
                'file_type'        => $saved['dialog_video']['ext'],
                'file_size_bytes'  => $saved['dialog_video']['size'],
                'file_path_2'      => $saved['song_video']['path'],
                'file_type_2'      => $saved['song_video']['ext'],
                'file_size_bytes_2'=> $saved['song_video']['size'],
                'submission_tag'   => 'actor-dual',
                'ip_address'       => $ip,
            ]);

            // Feed both videos into pipeline with correct labels
            $season  = $this->seasonModel->getActive();
            if (!$season) {
                $sid = $this->seasonModel->create(['title'=>'Open Auditions','brief'=>'Public auditions.','start_date'=>date('Y-01-01'),'end_date'=>date('Y-12-31'),'status'=>'active']);
                $season = $this->seasonModel->findById($sid);
            }
            $guestId = $this->getOrCreateGuestUser($name, $email, 'actor');

            // Dialog video
            try {
                $vid1 = $this->videoModel->create([
                    'user_id'        => $guestId,
                    'season_id'      => $season['id'],
                    'title'          => 'Dialog Audition — ' . $name,
                    'content_type'   => 'actor',
                    'file_path'      => $saved['dialog_video']['path'],
                    'recording_mode' => 'freeform',
                    'script_content' => 'Dialog Audition',
                ]);
                $this->submissionModel->linkVideo($submissionId, $vid1);
                BackgroundProcessor::queueVideoProcessing($vid1);
            } catch (\Throwable $e) { log_exception($e, 'ACTOR_DIALOG_PIPELINE'); }

            // Song video
            try {
                $vid2 = $this->videoModel->create([
                    'user_id'        => $guestId,
                    'season_id'      => $season['id'],
                    'title'          => 'Song Audition — ' . $name,
                    'content_type'   => 'actor',
                    'file_path'      => $saved['song_video']['path'],
                    'recording_mode' => 'freeform',
                    'script_content' => 'Song Audition',
                ]);
                $this->submissionModel->linkVideo2($submissionId, $vid2);
                BackgroundProcessor::queueVideoProcessing($vid2);
            } catch (\Throwable $e) { log_exception($e, 'ACTOR_SONG_PIPELINE'); }

            log_message('info', "Actor dual submission #{$submissionId} from {$name} <{$email}>");

            // ── Send email notification to submitter ──────────────────────
            try {
                $emailService = new \App\Services\EmailService();
                if ($emailService->isNotificationEnabled('submit')) {
                    $emailService->sendSubmissionReceivedEmail($name, $email, 'actor', 'Actor Audition (Dialog + Song)');
                }
                // Admin notification
                $adminEmail = $emailService->getAdminEmail();
                if (!empty($adminEmail) && $emailService->isNotificationEnabled('admin_new_video')) {
                    $emailService->sendAdminNewSubmissionEmail($adminEmail, $name, $email, 'actor', 'Actor Audition (Dialog + Song)', $submissionId);
                }
            } catch (\Throwable $emailErr) {
                log_exception($emailErr, 'ACTOR_SUBMIT_EMAIL');
            }

            echo json_encode([
                'success'        => true,
                'id'             => $submissionId,
                'submitter_name' => $name,
                'submitter_email'=> $email,
                'role'           => 'actor',
                'audition_type'  => 'Actor Audition (Dialog + Song)',
                'message'        => "Both auditions received! We'll be in touch at {$email}.",
            ]);

        } catch (\PDOException $e) {
            log_exception($e, 'ACTOR_SUBMIT_DB');
            http_response_code(500);
            echo json_encode(['error' => 'Database error. Please try again.']);
        } catch (\Throwable $e) {
            log_exception($e, 'ACTOR_SUBMIT');
            http_response_code(500);
            echo json_encode(['error' => 'An unexpected error occurred.']);
        }
    }

    /**
     * IP-based rate limiting — max 10 per hour
     */
    private function isRateLimited(string $ip): bool
    {
        try {
            if (!$this->submissionModel->tableExists()) return false;
            $db   = \App\Config\Database::getConnection();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM submissions
                 WHERE ip_address = ? AND submitted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->execute([$ip]);
            return (int) $stmt->fetchColumn() >= 10;
        } catch (\Exception $e) {
            return false;
        }
    }
}

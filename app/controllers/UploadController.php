<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Video;
use App\Models\Season;
use App\Models\User;
use App\Models\Settings;
use App\Services\BackgroundProcessor;

class UploadController
{
    private Video $videoModel;
    private Season $seasonModel;
    private User $userModel;
    private Settings $settingsModel;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->seasonModel = new Season();
        $this->userModel = new User();
        $this->settingsModel = new Settings();
    }

    public function store(): void
    {
        header('Content-Type: application/json');
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                return;
            }

            $user = auth_user();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            $title = trim($_POST['title'] ?? '');
            $seasonId = (int) ($_POST['season_id'] ?? 0);
            $contentType = trim($_POST['content_type'] ?? '');
            $csrf = $_POST['csrf_token'] ?? '';

            debug_log("Upload attempt: user={$user['id']}, title={$title}, season={$seasonId}, type={$contentType}", 'UPLOAD');

            if (!verify_csrf($csrf)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid CSRF token']);
                return;
            }

            // Get upload limits from settings
            $uploadLimits = $this->settingsModel->getUploadLimits();
            $minSizeBytes = $uploadLimits['min_size_mb'] * 1024 * 1024;
            $maxSizeBytes = $uploadLimits['max_size_mb'] * 1024 * 1024;

            // Get user's allowed content categories
            $userCategories = $user['content_categories'] ?? [$user['role']];
            if (is_string($userCategories)) {
                $userCategories = json_decode($userCategories, true) ?? [$user['role']];
            }

            $errors = [];
            if (strlen($title) < 3) $errors[] = 'Title must be at least 3 characters.';
            if (!$seasonId) $errors[] = 'Season is required.';
            if (!$this->seasonModel->findById($seasonId)) $errors[] = 'Invalid season.';
            
            // Validate content type
            $validContentTypes = ['actor', 'director', 'writer'];
            if (empty($contentType)) {
                $errors[] = 'Content type is required.';
            } elseif (!in_array($contentType, $validContentTypes)) {
                $errors[] = 'Invalid content type.';
            } elseif (!in_array($contentType, $userCategories)) {
                $errors[] = 'You are not registered for this content type.';
            }
            
            // Check if already submitted this content type for this season
            if (!empty($contentType) && $seasonId && $this->videoModel->existsForSeasonAndType((int) $user['id'], $seasonId, $contentType)) {
                $errors[] = 'You have already uploaded a ' . $contentType . ' video for this season.';
            }
            
            if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = $_FILES['video']['error'] ?? 'No file';
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit (php.ini upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                    UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension',
                ];
                $errorMsg = $errorMessages[$uploadError] ?? "Upload error code: {$uploadError}";
                $errors[] = "Video file error: {$errorMsg}";
                debug_log("File upload error: {$errorMsg}", 'UPLOAD');
            } else {
                // Validate file size against admin settings
                $fileSize = $_FILES['video']['size'];
                if ($fileSize < $minSizeBytes) {
                    $errors[] = "Video file is too small. Minimum size: {$uploadLimits['min_size_mb']}MB";
                } elseif ($fileSize > $maxSizeBytes) {
                    $errors[] = "Video file is too large. Maximum size: {$uploadLimits['max_size_mb']}MB";
                }
            }

            if (!empty($errors)) {
                http_response_code(422);
                echo json_encode(['errors' => $errors]);
                return;
            }

            $file = $_FILES['video'];
            $allowed = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
            if (!in_array($file['type'], $allowed)) {
                http_response_code(422);
                echo json_encode(['errors' => ['Only MP4, MOV, AVI, and WEBM videos are allowed. Got: ' . $file['type']]]);
                return;
            }

            $maxBytes = $this->parseSize(UPLOAD_MAX_SIZE);
            if ($file['size'] > $maxBytes) {
                http_response_code(422);
                echo json_encode(['errors' => ['Video exceeds maximum allowed size (' . UPLOAD_MAX_SIZE . ').']]);
                return;
            }

            // Check upload directory exists and is writable
            if (!is_dir(UPLOAD_PATH)) {
                if (!mkdir(UPLOAD_PATH, 0755, true)) {
                    log_message('error', "Cannot create upload directory: " . UPLOAD_PATH);
                    http_response_code(500);
                    echo json_encode(['errors' => ['Server configuration error: upload directory']]);
                    return;
                }
            }
            
            if (!is_writable(UPLOAD_PATH)) {
                log_message('error', "Upload directory not writable: " . UPLOAD_PATH);
                http_response_code(500);
                echo json_encode(['errors' => ['Server configuration error: upload permissions']]);
                return;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('fp3_', true) . '.' . $ext;
            $filepath = UPLOAD_PATH . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                log_message('error', "Failed to move uploaded file from {$file['tmp_name']} to {$filepath}");
                http_response_code(500);
                echo json_encode(['errors' => ['Failed to save uploaded file.']]);
                return;
            }

            debug_log("File saved: {$filepath}", 'UPLOAD');

            // Get optional fields
            $recordingMode = trim($_POST['recording_mode'] ?? 'freeform');
            $scriptContent = trim($_POST['script_content'] ?? '');
            
            // Validate recording mode
            if (!in_array($recordingMode, ['script', 'freeform'])) {
                $recordingMode = 'freeform';
            }
            
            // If script_content is provided, set recording_mode to script
            if (!empty($scriptContent)) {
                $recordingMode = 'script';
            }

            $videoId = $this->videoModel->create([
                'user_id' => (int) $user['id'],
                'season_id' => $seasonId,
                'title' => $title,
                'content_type' => $contentType,
                'file_path' => $filename,
                'recording_mode' => $recordingMode,
                'script_content' => $scriptContent ?: null,
            ]);

            log_message('info', "Video uploaded ID {$videoId} by user {$user['id']} (type: {$contentType}, mode: {$recordingMode})");

            // Trigger background AI processing (non-blocking)
            BackgroundProcessor::queueVideoProcessing($videoId);

            flash('success', 'Video uploaded successfully! AI review in progress.');
            echo json_encode([
                'success' => true, 
                'message' => 'Video uploaded! AI quality check in progress...',
                'video_id' => $videoId,
                'redirect' => '/creator/dashboard'
            ]);
            
        } catch (\PDOException $e) {
            log_exception($e, 'UPLOAD_DB');
            
            // Check for duplicate entry error
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                http_response_code(422);
                echo json_encode(['errors' => ['You have already submitted a video for this season. Note: If you want to submit multiple content types, please ensure database migrations are up to date.']]);
            } else {
                http_response_code(500);
                echo json_encode(['errors' => ['Database error. Please try again or contact support. Error: ' . $e->getMessage()]]);
            }
        } catch (\Throwable $e) {
            log_exception($e, 'UPLOAD');
            http_response_code(500);
            echo json_encode(['errors' => ['An unexpected error occurred: ' . $e->getMessage()]]);
        }
    }

    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}

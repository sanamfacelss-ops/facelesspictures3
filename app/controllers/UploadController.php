<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Video;
use App\Models\Season;
use App\Models\User;
use App\Services\BackgroundProcessor;

class UploadController
{
    private Video $videoModel;
    private Season $seasonModel;
    private User $userModel;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->seasonModel = new Season();
        $this->userModel = new User();
    }

    public function store(): void
    {
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

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

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
            $errors[] = 'Video file is required and must be valid.';
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
            echo json_encode(['errors' => ['Only MP4, MOV, AVI, and WEBM videos are allowed.']]);
            return;
        }

        $maxBytes = $this->parseSize(UPLOAD_MAX_SIZE);
        if ($file['size'] > $maxBytes) {
            http_response_code(422);
            echo json_encode(['errors' => ['Video exceeds maximum allowed size.']]);
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('fp3_', true) . '.' . $ext;
        $filepath = UPLOAD_PATH . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['errors' => ['Failed to save uploaded file.']]);
            return;
        }

        // Get optional fields
        $recordingMode = trim($_POST['recording_mode'] ?? 'freeform');
        $scriptContent = trim($_POST['script_content'] ?? '');
        
        // Validate recording mode
        if (!in_array($recordingMode, ['script', 'freeform'])) {
            $recordingMode = 'freeform';
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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Video;
use App\Models\Season;
use App\Models\User;

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
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $errors = [];
        if (strlen($title) < 3) $errors[] = 'Title must be at least 3 characters.';
        if (!$seasonId) $errors[] = 'Season is required.';
        if (!$this->seasonModel->findById($seasonId)) $errors[] = 'Invalid season.';
        if ($this->userModel->existsForSeason((int) $user['id'], $seasonId)) {
            $errors[] = 'You have already uploaded a video for this season.';
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

        $videoId = $this->videoModel->create([
            'user_id' => (int) $user['id'],
            'season_id' => $seasonId,
            'title' => $title,
            'file_path' => $filename,
        ]);

        log_message('info', "Video uploaded ID {$videoId} by user {$user['id']}");
        flash('success', 'Video uploaded successfully and is pending moderation.');
        echo json_encode(['success' => true, 'redirect' => '/dashboard']);
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

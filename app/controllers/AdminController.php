<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\Script;
use App\Models\Season;
use App\Models\User;
use App\Models\Video;
use PDO;

class AdminController
{
    private PDO $db;
    private Script $scriptModel;
    private Season $seasonModel;
    private User $userModel;
    private Video $videoModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->scriptModel = new Script();
        $this->seasonModel = new Season();
        $this->userModel = new User();
        $this->videoModel = new Video();
    }

    /**
     * Check admin access - returns false and sends error response if not admin
     */
    private function requireAdmin(): bool
    {
        if (!is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return false;
        }
        return true;
    }

    /**
     * Verify CSRF token
     */
    private function verifyCsrf(): bool
    {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return false;
        }
        return true;
    }

    // ==================== SCRIPTS ====================

    /**
     * Create a new script
     */
    public function createScript(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = trim($_POST['difficulty'] ?? 'beginner');
        $durationHint = trim($_POST['duration_hint'] ?? '');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (strlen($content) < 10) $errors[] = 'Content must be at least 10 characters';
        if (!in_array($category, ['actor', 'director', 'writer'])) $errors[] = 'Invalid category';
        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) $errors[] = 'Invalid difficulty';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $id = $this->scriptModel->create([
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'difficulty' => $difficulty,
                'duration_hint' => $durationHint ?: null,
            ]);

            debug_log("Admin created script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Script created successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CREATE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create script']);
        }
    }

    /**
     * Update an existing script
     */
    public function updateScript(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $difficulty = trim($_POST['difficulty'] ?? 'beginner');
        $durationHint = trim($_POST['duration_hint'] ?? '');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (strlen($content) < 10) $errors[] = 'Content must be at least 10 characters';
        if (!in_array($category, ['actor', 'director', 'writer'])) $errors[] = 'Invalid category';
        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) $errors[] = 'Invalid difficulty';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $this->scriptModel->update($id, [
                'title' => $title,
                'content' => $content,
                'category' => $category,
                'difficulty' => $difficulty,
                'duration_hint' => $durationHint ?: null,
            ]);

            debug_log("Admin updated script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Script updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update script']);
        }
    }

    /**
     * Soft-delete a script
     */
    public function deleteScript(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        try {
            $this->scriptModel->delete($id);
            debug_log("Admin deleted script ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Script deleted successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_SCRIPT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete script']);
        }
    }

    // ==================== SEASONS ====================

    /**
     * Create a new season
     */
    public function createSeason(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $brief = trim($_POST['brief'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (empty($startDate)) $errors[] = 'Start date is required';
        if (empty($endDate)) $errors[] = 'End date is required';
        if (!in_array($status, ['active', 'closed', 'upcoming'])) $errors[] = 'Invalid status';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $id = $this->seasonModel->create([
                'title' => $title,
                'brief' => $brief,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            debug_log("Admin created season ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Season created successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_CREATE_SEASON');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create season']);
        }
    }

    /**
     * Update an existing season
     */
    public function updateSeason(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $title = trim($_POST['title'] ?? '');
        $brief = trim($_POST['brief'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        $errors = [];
        if (strlen($title) < 2) $errors[] = 'Title is required';
        if (empty($startDate)) $errors[] = 'Start date is required';
        if (empty($endDate)) $errors[] = 'End date is required';
        if (!in_array($status, ['active', 'closed', 'upcoming'])) $errors[] = 'Invalid status';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $this->seasonModel->update($id, [
                'title' => $title,
                'brief' => $brief,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            debug_log("Admin updated season ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Season updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_SEASON');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update season']);
        }
    }

    // ==================== USERS ====================

    /**
     * Delete a user (admin only)
     */
    public function deleteUser(int $id): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        // Don't allow deleting self
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account from admin panel']);
            return;
        }

        try {
            // Delete user's videos first
            $stmt = $this->db->prepare("DELETE FROM videos WHERE user_id = ?");
            $stmt->execute([$id]);

            // Delete the user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            debug_log("Admin deleted user ID: $id", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_DELETE_USER');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    }

    // ==================== VIDEOS ====================

    /**
     * Get all videos (for admin video management)
     */
    public function allVideos(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin()) return;

        try {
            $stmt = $this->db->query(
                "SELECT v.*, u.name as user_name, u.role as user_role, s.title as season_title
                 FROM videos v
                 JOIN users u ON v.user_id = u.id
                 JOIN seasons s ON v.season_id = s.id
                 ORDER BY v.created_at DESC"
            );
            $videos = $stmt->fetchAll();
            echo json_encode($videos);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_ALL_VIDEOS');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch videos']);
        }
    }

    // ==================== GUIDES ====================

    /**
     * Update a guide text for a role
     */
    public function updateGuide(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->requireAdmin() || !$this->verifyCsrf()) return;

        $role = trim($_POST['role'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!in_array($role, ['actor', 'director', 'writer'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid role']);
            return;
        }

        if (strlen($content) < 10) {
            http_response_code(422);
            echo json_encode(['error' => 'Guide content must be at least 10 characters']);
            return;
        }

        try {
            $settingsModel = new \App\Models\Settings();
            $settingsModel->updateGuide($role, $content);
            
            debug_log("Admin updated guide for role: $role", 'ADMIN');
            echo json_encode(['success' => true, 'message' => 'Guide updated successfully']);
        } catch (\Exception $e) {
            log_exception($e, 'ADMIN_UPDATE_GUIDE');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update guide']);
        }
    }
}

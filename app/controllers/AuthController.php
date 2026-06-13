<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\User;
use PDO;

class AuthController
{
    private ?User $userModel = null;
    private ?PDO $db = null;
    private ?string $dbError = null;

    public function __construct()
    {
        try {
            $this->db = Database::getConnection();
            $this->userModel = new User();
        } catch (\Exception $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function register(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Check database connection
        if ($this->dbError || !$this->userModel) {
            http_response_code(503);
            echo json_encode(['error' => 'Database connection failed. Please try again later.']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token. Please refresh and try again.']);
            return;
        }

        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['actor', 'director', 'writer'])) $errors[] = 'Select a valid role.';
        
        if (empty($errors) && $this->userModel->findByEmail($email)) {
            $errors[] = 'Email already registered.';
        }

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            $userId = $this->userModel->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ]);

            $_SESSION['user_id'] = $userId;
            flash('success', 'Account created successfully.');
            echo json_encode(['success' => true, 'redirect' => '/dashboard']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed. Please try again.']);
        }
    }

    public function login(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Check database connection
        if ($this->dbError || !$this->userModel) {
            http_response_code(503);
            echo json_encode(['error' => 'Database connection failed. Please try again later.']);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token. Please refresh and try again.']);
            return;
        }

        try {
            $user = $this->userModel->findByEmail($email);
            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid email or password.']);
                return;
            }

            $_SESSION['user_id'] = $user['id'];
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            echo json_encode(['success' => true, 'redirect' => '/dashboard']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Login failed. Please try again.']);
        }
    }

    public function logout(): void
    {
        session_destroy();
        redirect('/login');
    }
}

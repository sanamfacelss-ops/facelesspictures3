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
            debug_log('AuthController initialized', 'AUTH');
            $this->db = Database::getConnection();
            $this->userModel = new User();
            debug_log('Database connected successfully', 'AUTH');
        } catch (\Exception $e) {
            $this->dbError = $e->getMessage();
            log_exception($e, 'AUTH_INIT');
        }
    }

    public function register(): void
    {
        header('Content-Type: application/json');
        
        debug_log('Register request received', 'REGISTER');
        debug_log($_POST, 'REGISTER_POST');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Check database connection
        if ($this->dbError || !$this->userModel) {
            debug_log('Database error: ' . $this->dbError, 'REGISTER');
            http_response_code(503);
            echo json_encode(['error' => 'Database connection failed. Please try again later.', 'debug' => FP3_DEBUG ? $this->dbError : null]);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        debug_log("Name: $name, Email: $email, Role: $role", 'REGISTER');

        if (!verify_csrf($csrf)) {
            debug_log('CSRF validation failed', 'REGISTER');
            debug_log('Session token: ' . ($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET'), 'REGISTER');
            debug_log('Submitted token: ' . $csrf, 'REGISTER');
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
            debug_log($errors, 'REGISTER_VALIDATION_ERRORS');
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            return;
        }

        try {
            debug_log('Creating user...', 'REGISTER');
            $userId = $this->userModel->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ]);

            debug_log("User created with ID: $userId", 'REGISTER');
            $_SESSION['user_id'] = $userId;
            flash('success', 'Account created successfully.');
            echo json_encode(['success' => true, 'redirect' => '/dashboard']);
        } catch (\Exception $e) {
            log_exception($e, 'REGISTER');
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed. Please try again.', 'debug' => FP3_DEBUG ? $e->getMessage() : null]);
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

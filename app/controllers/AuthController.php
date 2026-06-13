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

    /**
     * Request password reset - sends OTP to email
     */
    public function forgotPassword(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        if ($this->dbError || !$this->userModel) {
            http_response_code(503);
            echo json_encode(['error' => 'Service temporarily unavailable.']);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request. Please refresh and try again.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'Please enter a valid email address.']);
            return;
        }

        try {
            // Check if user exists
            $user = $this->userModel->findByEmail($email);
            
            // Always return success to prevent email enumeration
            if (!$user) {
                debug_log("Password reset requested for non-existent email: $email", 'FORGOT_PASSWORD');
                echo json_encode(['success' => true, 'message' => 'If this email exists, you will receive an OTP shortly.']);
                return;
            }

            // Generate and store OTP
            $resetModel = new \App\Models\PasswordReset();
            $otp = $resetModel->createOTP($email);
            
            debug_log("OTP generated for $email: $otp", 'FORGOT_PASSWORD');

            // Send email with OTP
            $emailService = new \App\Services\EmailService();
            $sent = $emailService->sendPasswordResetOTP($email, $otp);
            
            if (!$sent) {
                debug_log("Failed to send OTP email to $email", 'FORGOT_PASSWORD');
                // Still return success to prevent enumeration, but log the error
            }

            echo json_encode(['success' => true, 'message' => 'If this email exists, you will receive an OTP shortly.']);
            
        } catch (\Exception $e) {
            log_exception($e, 'FORGOT_PASSWORD');
            http_response_code(500);
            echo json_encode(['error' => 'Something went wrong. Please try again.']);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOTP(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request.']);
            return;
        }

        if (empty($email) || empty($otp)) {
            http_response_code(422);
            echo json_encode(['error' => 'Email and OTP are required.']);
            return;
        }

        try {
            $resetModel = new \App\Models\PasswordReset();
            
            if (!$resetModel->verifyOTP($email, $otp)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired OTP.']);
                return;
            }

            // Store verified state in session for password reset
            $_SESSION['password_reset_email'] = $email;
            $_SESSION['password_reset_otp'] = $otp;
            $_SESSION['password_reset_verified'] = true;

            echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
            
        } catch (\Exception $e) {
            log_exception($e, 'VERIFY_OTP');
            http_response_code(500);
            echo json_encode(['error' => 'Verification failed.']);
        }
    }

    /**
     * Reset password with verified OTP
     */
    public function resetPassword(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request.']);
            return;
        }

        // Check if OTP was verified
        if (empty($_SESSION['password_reset_verified']) || empty($_SESSION['password_reset_email'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Please verify OTP first.']);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(422);
            echo json_encode(['error' => 'Password must be at least 6 characters.']);
            return;
        }

        if ($password !== $confirmPassword) {
            http_response_code(422);
            echo json_encode(['error' => 'Passwords do not match.']);
            return;
        }

        try {
            $email = $_SESSION['password_reset_email'];
            $otp = $_SESSION['password_reset_otp'];
            
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);

            // Mark OTP as used
            $resetModel = new \App\Models\PasswordReset();
            $resetModel->markUsed($email, $otp);

            // Clear session
            unset($_SESSION['password_reset_email']);
            unset($_SESSION['password_reset_otp']);
            unset($_SESSION['password_reset_verified']);

            debug_log("Password reset successful for $email", 'RESET_PASSWORD');

            echo json_encode(['success' => true, 'message' => 'Password reset successful!', 'redirect' => '/login']);
            
        } catch (\Exception $e) {
            log_exception($e, 'RESET_PASSWORD');
            http_response_code(500);
            echo json_encode(['error' => 'Password reset failed.']);
        }
    }
}

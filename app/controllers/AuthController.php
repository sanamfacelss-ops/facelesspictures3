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
        
        debug_log('========== REGISTER REQUEST ==========', 'REGISTER');
        debug_log('Method: ' . $_SERVER['REQUEST_METHOD'], 'REGISTER');
        debug_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'), 'REGISTER');
        debug_log('POST data: ' . json_encode($_POST), 'REGISTER');
        debug_log('Raw input: ' . file_get_contents('php://input'), 'REGISTER');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            debug_log('ERROR: Method not POST', 'REGISTER');
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Check database connection
        if ($this->dbError || !$this->userModel) {
            debug_log('ERROR: Database connection failed: ' . $this->dbError, 'REGISTER');
            http_response_code(503);
            echo json_encode(['error' => 'Database connection failed. Please try again later.', 'debug' => FP3_DEBUG ? $this->dbError : null]);
            return;
        }
        debug_log('Database connection OK', 'REGISTER');

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $contentCategories = $_POST['content_categories'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        debug_log("Name: $name, Email: $email, Role: $role, Categories: $contentCategories", 'REGISTER');

        // Parse content categories from JSON
        $categories = [];
        if (!empty($contentCategories)) {
            $categories = json_decode($contentCategories, true);
            if (!is_array($categories)) {
                $categories = [];
            }
        }
        
        // If no categories but role is set, use role as single category (backward compatibility)
        if (empty($categories) && !empty($role)) {
            $categories = [$role];
        }

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
        
        // Validate categories
        $validCategories = ['actor', 'director', 'writer'];
        if (empty($categories)) {
            $errors[] = 'Please select at least one content type.';
        } else {
            foreach ($categories as $cat) {
                if (!in_array($cat, $validCategories)) {
                    $errors[] = 'Invalid content type selected.';
                    break;
                }
            }
        }
        
        // Set role from first category for backward compatibility
        if (!empty($categories) && empty($role)) {
            $role = $categories[0];
        }
        if (!in_array($role, $validCategories)) {
            $role = $categories[0] ?? 'actor';
        }
        
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
                'content_categories' => $categories,
            ]);

            debug_log("User created with ID: $userId", 'REGISTER');
            $_SESSION['user_id'] = $userId;
            flash('success', 'Account created successfully.');
            echo json_encode(['success' => true, 'redirect' => '/onboarding']);
        } catch (\Exception $e) {
            log_exception($e, 'REGISTER');
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed. Please try again.', 'debug' => FP3_DEBUG ? $e->getMessage() : null]);
        }
    }

    public function login(): void
    {
        header('Content-Type: application/json');
        
        debug_log('========== LOGIN REQUEST ==========', 'LOGIN');
        debug_log('Method: ' . $_SERVER['REQUEST_METHOD'], 'LOGIN');
        debug_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'), 'LOGIN');
        debug_log('POST data: ' . json_encode($_POST), 'LOGIN');
        debug_log('Raw input: ' . file_get_contents('php://input'), 'LOGIN');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            debug_log('ERROR: Method not POST', 'LOGIN');
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Check database connection
        if ($this->dbError || !$this->userModel) {
            debug_log('ERROR: Database connection failed: ' . $this->dbError, 'LOGIN');
            http_response_code(503);
            echo json_encode(['error' => 'Database connection failed. Please try again later.']);
            return;
        }
        debug_log('Database connection OK', 'LOGIN');

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';
        
        debug_log("Email: $email", 'LOGIN');
        debug_log("Password length: " . strlen($password), 'LOGIN');
        debug_log("CSRF token received: " . substr($csrf, 0, 20) . '...', 'LOGIN');
        debug_log("CSRF token in session: " . substr($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET', 0, 20) . '...', 'LOGIN');

        if (!verify_csrf($csrf)) {
            debug_log('ERROR: CSRF validation failed', 'LOGIN');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token. Please refresh and try again.']);
            return;
        }
        debug_log('CSRF validation OK', 'LOGIN');

        try {
            debug_log('Looking up user by email...', 'LOGIN');
            $user = $this->userModel->findByEmail($email);
            
            if (!$user) {
                debug_log('ERROR: User not found', 'LOGIN');
                http_response_code(401);
                echo json_encode(['error' => 'Invalid email or password.']);
                return;
            }
            debug_log('User found: ' . json_encode(['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]), 'LOGIN');
            
            $passwordMatch = password_verify($password, $user['password']);
            debug_log('Password verify result: ' . ($passwordMatch ? 'MATCH' : 'NO MATCH'), 'LOGIN');
            
            if (!$passwordMatch) {
                debug_log('ERROR: Password does not match', 'LOGIN');
                http_response_code(401);
                echo json_encode(['error' => 'Invalid email or password.']);
                return;
            }

            $_SESSION['user_id'] = $user['id'];
            debug_log('SUCCESS: User logged in, session user_id = ' . $user['id'], 'LOGIN');
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            
            // Redirect admins to admin dashboard, others to creator dashboard
            $redirect = !empty($user['is_admin']) ? '/admin' : '/creator/dashboard';
            echo json_encode(['success' => true, 'redirect' => $redirect]);
        } catch (\Exception $e) {
            debug_log('EXCEPTION: ' . $e->getMessage(), 'LOGIN');
            log_exception($e, 'LOGIN');
            http_response_code(500);
            echo json_encode(['error' => 'Login failed. Please try again.']);
        }
    }

    public function logout(): void
    {
        header('Content-Type: application/json');
        session_destroy();
        echo json_encode(['success' => true]);
    }

    /**
     * Initiate Google OAuth login
     */
    public function googleAuth(): void
    {
        $googleService = new \App\Services\GoogleAuthService();
        
        if (!$googleService->isConfigured()) {
            flash('error', 'Google sign-in is not configured. Please contact support.');
            redirect('/login');
            return;
        }
        
        // Generate state for CSRF protection
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        
        $authUrl = $googleService->getAuthUrl($state);
        redirect($authUrl);
    }

    /**
     * Handle Google OAuth callback
     */
    public function googleCallback(): void
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        $error = $_GET['error'] ?? '';
        
        // Check for errors from Google
        if ($error) {
            debug_log("Google OAuth error: $error", 'GOOGLE_AUTH');
            flash('error', 'Google sign-in was cancelled or failed.');
            redirect('/login');
            return;
        }
        
        // Validate state
        $savedState = $_SESSION['google_oauth_state'] ?? '';
        unset($_SESSION['google_oauth_state']);
        
        if (!$state || $state !== $savedState) {
            debug_log("Google OAuth state mismatch", 'GOOGLE_AUTH');
            flash('error', 'Invalid request. Please try again.');
            redirect('/login');
            return;
        }
        
        if (!$code) {
            flash('error', 'No authorization code received from Google.');
            redirect('/login');
            return;
        }
        
        $googleService = new \App\Services\GoogleAuthService();
        $result = $googleService->handleCallback($code);
        
        if (!$result['success']) {
            flash('error', $result['error'] ?? 'Google sign-in failed.');
            redirect('/login');
            return;
        }
        
        // Redirect to appropriate page
        $redirect = $result['redirect'] ?? '/dashboard';
        
        if ($result['is_new'] ?? false) {
            flash('success', 'Welcome! Please complete your profile.');
        } else {
            flash('success', 'Welcome back!');
        }
        
        redirect($redirect);
    }

    /**
     * Complete Google signup with role selection
     */
    public function googleComplete(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $role = $_POST['role'] ?? '';
        $categories = $_POST['categories'] ?? [];
        
        if (is_string($categories)) {
            $categories = json_decode($categories, true) ?? [$role];
        }
        
        $googleService = new \App\Services\GoogleAuthService();
        $result = $googleService->completeSignup([
            'role' => $role,
            'categories' => $categories,
        ]);
        
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode(['error' => $result['error']]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'redirect' => $result['redirect'] ?? '/dashboard'
        ]);
    }

    /**
     * Delete user account
     */
    public function deleteAccount(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        try {
            $userId = (int)$_SESSION['user_id'];
            
            // Delete user's videos first (cascade should handle this, but be explicit)
            $stmt = $this->db->prepare("DELETE FROM videos WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete the user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Destroy session
            session_destroy();
            
            debug_log("User account deleted: ID $userId", 'DELETE_ACCOUNT');
            
            echo json_encode(['success' => true, 'message' => 'Account deleted']);
            
        } catch (\Exception $e) {
            log_exception($e, 'DELETE_ACCOUNT');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete account']);
        }
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

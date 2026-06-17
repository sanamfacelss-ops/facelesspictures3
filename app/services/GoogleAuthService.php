<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Google OAuth 2.0 Authentication Service
 */
class GoogleAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
    
    public function __construct()
    {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $appUrl = rtrim(APP_URL ?? 'http://localhost', '/');
        $this->redirectUri = $appUrl . '/api/auth/google/callback';
    }
    
    /**
     * Check if Google OAuth is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }
    
    /**
     * Get configuration status
     */
    public function getConfigStatus(): array
    {
        return [
            'is_configured' => $this->isConfigured(),
            'client_id' => $this->clientId ? substr($this->clientId, 0, 20) . '...' : '',
            'has_secret' => !empty($this->clientSecret),
            'redirect_uri' => $this->redirectUri,
        ];
    }

    /**
     * Get the Google OAuth authorization URL
     */
    public function getAuthUrl(string $state = ''): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google OAuth is not configured');
        }
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code): ?array
    {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->tokenUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            debug_log("Google token exchange failed: HTTP $httpCode", 'GOOGLE_AUTH');
            return null;
        }
        
        $result = json_decode($response, true);
        return $result ?? null;
    }
    
    /**
     * Get user info from Google using access token
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->userInfoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            debug_log("Google user info failed: HTTP $httpCode", 'GOOGLE_AUTH');
            return null;
        }
        
        $result = json_decode($response, true);
        return $result ?? null;
    }

    /**
     * Handle the OAuth callback - authenticate or register user
     */
    public function handleCallback(string $code): array
    {
        // Get access token
        $tokens = $this->getAccessToken($code);
        if (!$tokens || empty($tokens['access_token'])) {
            return ['success' => false, 'error' => 'Failed to get access token from Google'];
        }
        
        // Get user info
        $googleUser = $this->getUserInfo($tokens['access_token']);
        if (!$googleUser || empty($googleUser['email'])) {
            return ['success' => false, 'error' => 'Failed to get user info from Google'];
        }
        
        debug_log("Google user: " . json_encode($googleUser), 'GOOGLE_AUTH');
        
        $email = $googleUser['email'];
        $name = $googleUser['name'] ?? explode('@', $email)[0];
        $googleId = $googleUser['sub'] ?? '';
        $picture = $googleUser['picture'] ?? '';
        
        // Check if user exists
        $userModel = new User();
        $existingUser = $userModel->findByEmail($email);
        
        if ($existingUser) {
            // Update Google ID if not set
            if (empty($existingUser['google_id']) && $googleId) {
                $userModel->updateGoogleId($existingUser['id'], $googleId);
            }
            
            // Login existing user
            $_SESSION['user_id'] = $existingUser['id'];
            debug_log("Google login: existing user ID {$existingUser['id']}", 'GOOGLE_AUTH');
            
            return [
                'success' => true,
                'user' => $existingUser,
                'is_new' => false,
                'redirect' => !empty($existingUser['is_admin']) ? '/admin' : '/dashboard'
            ];
        }
        
        // New user - need to select role
        // Store Google info in session for completion
        $_SESSION['google_signup'] = [
            'email' => $email,
            'name' => $name,
            'google_id' => $googleId,
            'picture' => $picture,
        ];
        
        return [
            'success' => true,
            'is_new' => true,
            'redirect' => '/onboarding?google=1'
        ];
    }
    
    /**
     * Complete Google signup with role selection
     */
    public function completeSignup(array $data): array
    {
        if (empty($_SESSION['google_signup'])) {
            return ['success' => false, 'error' => 'No Google signup in progress'];
        }
        
        $googleData = $_SESSION['google_signup'];
        $role = $data['role'] ?? 'actor';
        $categories = $data['categories'] ?? [$role];
        
        // Validate
        $validRoles = ['actor', 'director', 'writer'];
        if (!in_array($role, $validRoles)) {
            $role = 'actor';
        }
        
        // Create user
        $userModel = new User();
        
        try {
            $userId = $userModel->create([
                'name' => $googleData['name'],
                'email' => $googleData['email'],
                'password' => bin2hex(random_bytes(16)), // Random password
                'role' => $role,
                'content_categories' => $categories,
                'google_id' => $googleData['google_id'],
            ]);
            
            // Clear session signup data
            unset($_SESSION['google_signup']);
            
            // Log in the new user
            $_SESSION['user_id'] = $userId;
            
            debug_log("Google signup complete: new user ID $userId", 'GOOGLE_AUTH');
            
            // Send welcome email
            $user = $userModel->findById($userId);
            if ($user) {
                $emailService = new EmailService();
                $emailService->sendWelcomeEmail($user);
            }
            
            return [
                'success' => true,
                'user_id' => $userId,
                'redirect' => '/dashboard'
            ];
            
        } catch (\Exception $e) {
            debug_log("Google signup failed: " . $e->getMessage(), 'GOOGLE_AUTH');
            return ['success' => false, 'error' => 'Failed to create account'];
        }
    }
}

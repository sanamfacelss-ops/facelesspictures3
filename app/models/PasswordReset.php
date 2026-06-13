<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class PasswordReset
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Generate and store OTP for password reset
     */
    public function createOTP(string $email): string
    {
        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Invalidate any existing OTPs for this email
        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
        $stmt->execute([$email]);
        
        // Create new OTP (expires in 10 minutes)
        $stmt = $this->db->prepare(
            "INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
        );
        $stmt->execute([$email, $otp]);
        
        return $otp;
    }

    /**
     * Verify OTP is valid and not expired
     */
    public function verifyOTP(string $email, string $otp): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM password_resets 
             WHERE email = ? AND otp = ? AND used = 0 AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$email, $otp]);
        
        return (bool) $stmt->fetch();
    }

    /**
     * Mark OTP as used
     */
    public function markUsed(string $email, string $otp): void
    {
        $stmt = $this->db->prepare(
            "UPDATE password_resets SET used = 1 WHERE email = ? AND otp = ?"
        );
        $stmt->execute([$email, $otp]);
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

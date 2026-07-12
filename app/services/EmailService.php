<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

class EmailService
{
    private string $provider = 'smtp'; // 'smtp' or 'resend'
    private string $host = 'localhost';
    private int $port = 587;
    private string $username = '';
    private string $password = '';
    private string $encryption = 'tls';
    private string $fromAddress = 'noreply@facelesspictures.com';
    private string $fromName = 'Faceless Pictures 3';
    private string $resendApiKey = '';
    private string $resendFromAddress = '';
    private string $resendFromName = 'Faceless Pictures 3';
    private ?EmailTemplateService $templateService = null;
    private array $notificationSettings = [];
    private string $lastError = '';

    public function __construct()
    {
        // Load settings from database first, fallback to .env
        $this->loadSettings();
        $this->templateService = new EmailTemplateService();
    }

    /**
     * Load SMTP settings from database, fallback to .env
     */
    private function loadSettings(): void
    {
        try {
            $settingsModel = new Settings();
            $dbSettings = $settingsModel->getEmailSettings();
            
            // Email provider (smtp or resend)
            $this->provider = !empty($dbSettings['email_provider']) ? $dbSettings['email_provider'] : 'smtp';
            
            // SMTP Settings - DB takes priority over .env
            $this->host = !empty($dbSettings['smtp_host']) ? $dbSettings['smtp_host'] : ($_ENV['MAIL_HOST'] ?? 'localhost');
            $this->port = (int) (!empty($dbSettings['smtp_port']) ? $dbSettings['smtp_port'] : ($_ENV['MAIL_PORT'] ?? 587));
            $this->username = !empty($dbSettings['smtp_username']) ? $dbSettings['smtp_username'] : ($_ENV['MAIL_USERNAME'] ?? '');
            $this->password = !empty($dbSettings['smtp_password']) ? $dbSettings['smtp_password'] : ($_ENV['MAIL_PASSWORD'] ?? '');
            $this->encryption = !empty($dbSettings['smtp_encryption']) ? $dbSettings['smtp_encryption'] : ($_ENV['MAIL_ENCRYPTION'] ?? 'tls');
            $this->fromAddress = !empty($dbSettings['smtp_from_address']) ? $dbSettings['smtp_from_address'] : ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@facelesspictures.com');
            $this->fromName = !empty($dbSettings['smtp_from_name']) ? $dbSettings['smtp_from_name'] : ($_ENV['MAIL_FROM_NAME'] ?? 'Faceless Pictures 3');
            
            // Resend Settings
            $this->resendApiKey = $dbSettings['resend_api_key'] ?? '';
            $this->resendFromAddress = $dbSettings['resend_from_address'] ?? '';
            $this->resendFromName = $dbSettings['resend_from_name'] ?? 'Faceless Pictures 3';
            
            // Notification settings
            $this->notificationSettings = [
                'notify_signup' => $dbSettings['email_notify_signup'] ?? '1',
                'notify_submit' => $dbSettings['email_notify_submit'] ?? '1',
                'notify_processing' => $dbSettings['email_notify_processing'] ?? '1',
                'notify_approved' => $dbSettings['email_notify_approved'] ?? '1',
                'notify_rejected' => $dbSettings['email_notify_rejected'] ?? '1',
                'notify_flagged' => $dbSettings['email_notify_flagged'] ?? '1',
                'admin_address' => $dbSettings['email_admin_address'] ?? '',
                'admin_new_video' => $dbSettings['email_admin_new_video'] ?? '1',
                'admin_flagged' => $dbSettings['email_admin_flagged'] ?? '1',
            ];
        } catch (\Exception $e) {
            // Fallback to .env only
            $this->provider = 'smtp';
            $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
            $this->port = (int) ($_ENV['MAIL_PORT'] ?? 587);
            $this->username = $_ENV['MAIL_USERNAME'] ?? '';
            $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
            $this->encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@facelesspictures.com';
            $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Faceless Pictures 3';
            $this->notificationSettings = [];
        }
    }
    
    /**
     * Get email configuration status
     */
    public function getConfigStatus(): array
    {
        return [
            'provider' => $this->provider,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'has_password' => !empty($this->password),
            'encryption' => $this->encryption,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'is_configured' => $this->isConfigured(),
            'resend_configured' => !empty($this->resendApiKey) && !empty($this->resendFromAddress),
        ];
    }
    
    /**
     * Check if email is configured
     */
    private function isConfigured(): bool
    {
        if ($this->provider === 'resend') {
            return !empty($this->resendApiKey) && !empty($this->resendFromAddress);
        }
        return !empty($this->host) && $this->host !== 'localhost' && !empty($this->username) && !empty($this->password);
    }
    
    /**
     * Get last error message
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): array
    {
        return $this->notificationSettings;
    }

    /**
     * Check if a specific notification is enabled
     */
    public function isNotificationEnabled(string $type): bool
    {
        $key = 'notify_' . $type;
        return ($this->notificationSettings[$key] ?? '1') === '1';
    }

    /**
     * Get admin email address
     */
    public function getAdminEmail(): string
    {
        return $this->notificationSettings['admin_address'] ?? '';
    }

    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $headers = "From: {$this->fromName} <{$this->fromAddress}>\r\n";
        $headers .= "Reply-To: {$this->fromAddress}\r\n";
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        $result = mail($to, $subject, $body, $headers);
        if (!$result) {
            log_message('error', "Failed to send email to {$to}: {$subject}");
        }
        return $result;
    }
    
    public function sendSmtp(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $this->lastError = '';
        
        // Always log for debugging (even if FP3_DEBUG is off, write to error log)
        $this->smtpLog("Attempting to send email to {$to}");
        $this->smtpLog("Host={$this->host}, Port={$this->port}, User={$this->username}, Encryption={$this->encryption}");
        
        // Validate settings
        if (empty($this->host) || $this->host === 'localhost') {
            $this->lastError = 'SMTP host not configured';
            $this->smtpLog("ERROR: " . $this->lastError);
            return false;
        }
        
        if (empty($this->username)) {
            $this->lastError = 'SMTP username not configured';
            $this->smtpLog("ERROR: " . $this->lastError);
            return false;
        }
        
        if (empty($this->password)) {
            $this->lastError = 'SMTP password not configured';
            $this->smtpLog("ERROR: " . $this->lastError);
            return false;
        }
        
        try {
            // For SSL, use ssl:// prefix. For TLS (STARTTLS), connect without encryption first
            $socket = ($this->encryption === 'ssl') ? 'ssl://' . $this->host : $this->host;
            $this->smtpLog("Connecting to {$socket}:{$this->port}");
            
            // Set longer timeout and configure context for SSL/TLS
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);
            
            $smtp = @stream_socket_client(
                "{$socket}:{$this->port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$smtp) {
                $this->lastError = "Connection failed: {$errstr} ({$errno})";
                $this->smtpLog("ERROR: " . $this->lastError);
                return false;
            }
            
            // Set stream timeout
            stream_set_timeout($smtp, 30);
            
            // Read greeting
            $greeting = $this->smtpGetResponse($smtp);
            $this->smtpLog("Greeting: " . trim($greeting));
            
            if (!str_starts_with($greeting, '220')) {
                $this->lastError = "Invalid server greeting: " . trim($greeting);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }

            // EHLO
            $resp = $this->smtpCommand($smtp, "EHLO " . gethostname());
            $this->smtpLog("EHLO response: " . trim($resp));
            
            if (!str_starts_with($resp, '250')) {
                $this->lastError = "EHLO failed: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            // STARTTLS for TLS encryption
            if ($this->encryption === 'tls') {
                $resp = $this->smtpCommand($smtp, "STARTTLS");
                $this->smtpLog("STARTTLS response: " . trim($resp));
                
                if (!str_starts_with($resp, '220')) {
                    $this->lastError = "STARTTLS failed: " . trim($resp);
                    $this->smtpLog("ERROR: " . $this->lastError);
                    fclose($smtp);
                    return false;
                }
                
                // Enable TLS - try multiple TLS versions for compatibility
                $crypto = @stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    // Try TLS 1.2 only
                    $crypto = @stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                }
                
                if (!$crypto) {
                    $this->lastError = "TLS handshake failed - server may not support TLS 1.2+";
                    $this->smtpLog("ERROR: " . $this->lastError);
                    fclose($smtp);
                    return false;
                }
                
                $this->smtpLog("TLS enabled successfully");
                
                // Send EHLO again after TLS
                $resp = $this->smtpCommand($smtp, "EHLO " . gethostname());
                $this->smtpLog("EHLO after TLS: " . trim($resp));
            }

            // AUTH LOGIN
            $resp = $this->smtpCommand($smtp, "AUTH LOGIN");
            $this->smtpLog("AUTH LOGIN response: " . trim($resp));
            
            if (!str_starts_with($resp, '334')) {
                $this->lastError = "Server doesn't support AUTH LOGIN: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            // Send username (base64 encoded)
            $resp = $this->smtpCommand($smtp, base64_encode($this->username));
            $this->smtpLog("Username response: " . trim($resp));
            
            if (!str_starts_with($resp, '334')) {
                $this->lastError = "Username rejected: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            // Send password (base64 encoded)
            $resp = $this->smtpCommand($smtp, base64_encode($this->password));
            $this->smtpLog("Password response: " . trim($resp));
            
            if (!str_starts_with($resp, '235')) {
                $this->lastError = "Authentication failed - check username/App Password. Server said: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            $this->smtpLog("Authentication successful");
            
            // MAIL FROM
            $resp = $this->smtpCommand($smtp, "MAIL FROM:<{$this->fromAddress}>");
            $this->smtpLog("MAIL FROM response: " . trim($resp));
            
            if (!str_starts_with($resp, '250')) {
                $this->lastError = "MAIL FROM rejected: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            // RCPT TO
            $resp = $this->smtpCommand($smtp, "RCPT TO:<{$to}>");
            $this->smtpLog("RCPT TO response: " . trim($resp));
            
            if (!str_starts_with($resp, '250') && !str_starts_with($resp, '251')) {
                $this->lastError = "Recipient rejected: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }
            
            // DATA
            $resp = $this->smtpCommand($smtp, "DATA");
            $this->smtpLog("DATA response: " . trim($resp));
            
            if (!str_starts_with($resp, '354')) {
                $this->lastError = "DATA command rejected: " . trim($resp);
                $this->smtpLog("ERROR: " . $this->lastError);
                fclose($smtp);
                return false;
            }

            // Build and send message
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromAddress}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= $isHtml ? "Content-Type: text/html; charset=UTF-8\r\n" : "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "\r\n";

            $message = $headers . $body . "\r\n.\r\n";
            fwrite($smtp, $message);

            $response = $this->smtpGetResponse($smtp);
            $this->smtpLog("Message send response: " . trim($response));

            $this->smtpCommand($smtp, "QUIT");
            fclose($smtp);

            $success = str_starts_with($response, '250');
            
            if (!$success) {
                $this->lastError = "Message not accepted: " . trim($response);
            }
            
            $this->smtpLog("Send " . ($success ? "SUCCESS" : "FAILED: " . $this->lastError));
            
            return $success;
            
        } catch (\Exception $e) {
            $this->lastError = "Exception: " . $e->getMessage();
            $this->smtpLog("ERROR: " . $this->lastError);
            return false;
        }
    }
    
    /**
     * Log SMTP messages to debug.log
     */
    private function smtpLog(string $message): void
    {
        $logMessage = "[SMTP] " . $message;
        
        // Always log to debug
        debug_log($message, 'SMTP');
        
        // Also log to a dedicated SMTP log file
        $file = LOG_PATH . '/smtp.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $logMessage" . PHP_EOL;
        @error_log($line, 3, $file);
    }
    
    private function smtpGetResponse($smtp): string
    {
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    public function sendPasswordResetOTP(string $to, string $otp): bool
    {
        $email = $this->templateService->passwordResetEmail($to, $otp);
        return $this->sendEmail($to, $email['subject'], $email['body']);
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(array $user): bool
    {
        $email = $this->templateService->welcomeEmail($user);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send video submitted notification
     */
    public function sendVideoSubmittedEmail(array $user, array $video): bool
    {
        $email = $this->templateService->videoSubmittedEmail($user, $video);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send video approved notification
     */
    public function sendVideoApprovedEmail(array $user, array $video): bool
    {
        $email = $this->templateService->videoApprovedEmail($user, $video);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send video rejected notification
     */
    public function sendVideoRejectedEmail(array $user, array $video, string $reason = ''): bool
    {
        $email = $this->templateService->videoRejectedEmail($user, $video, $reason);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send video under manual review notification
     */
    public function sendVideoManualReviewEmail(array $user, array $video): bool
    {
        $email = $this->templateService->videoManualReviewEmail($user, $video);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send video processing notification
     */
    public function sendVideoProcessingEmail(array $user, array $video): bool
    {
        $email = $this->templateService->videoProcessingEmail($user, $video);
        return $this->sendEmail($user['email'], $email['subject'], $email['body']);
    }

    /**
     * Send admin notification for new video
     */
    public function sendAdminNewVideoEmail(string $adminEmail, array $user, array $video): bool
    {
        $email = $this->templateService->adminNewVideoEmail($user, $video);
        return $this->sendEmail($adminEmail, $email['subject'], $email['body']);
    }

    /**
     * Send admin notification for flagged video
     */
    public function sendAdminFlaggedVideoEmail(string $adminEmail, array $user, array $video, array $aiResult = []): bool
    {
        $email = $this->templateService->adminFlaggedVideoEmail($user, $video, $aiResult);
        return $this->sendEmail($adminEmail, $email['subject'], $email['body']);
    }

    /**
     * Send submission received confirmation to submitter
     */
    public function sendSubmissionReceivedEmail(string $name, string $email, string $role, string $auditionType): bool
    {
        $emailData = $this->templateService->submissionReceivedEmail($name, $role, $auditionType);
        return $this->sendEmail($email, $emailData['subject'], $emailData['body']);
    }

    /**
     * Send admin notification for new submission
     */
    public function sendAdminNewSubmissionEmail(string $adminEmail, string $name, string $email, string $role, string $auditionType, int $submissionId): bool
    {
        $emailData = $this->templateService->adminNewSubmissionEmail($name, $email, $role, $auditionType, $submissionId);
        return $this->sendEmail($adminEmail, $emailData['subject'], $emailData['body']);
    }

    /**
     * Helper method to send email using best available method
     */
    private function sendEmail(string $to, string $subject, string $body): bool
    {
        // Use Resend if configured and selected
        if ($this->provider === 'resend' && !empty($this->resendApiKey)) {
            return $this->sendResend($to, $subject, $body);
        }
        
        // Use SMTP if configured
        if (!empty($this->username) && !empty($this->password)) {
            return $this->sendSmtp($to, $subject, $body, true);
        }
        
        // Fallback to PHP mail()
        return $this->send($to, $subject, $body, true);
    }
    
    /**
     * Send email using Resend API
     */
    public function sendResend(string $to, string $subject, string $body): bool
    {
        $this->lastError = '';
        
        if (empty($this->resendApiKey)) {
            $this->lastError = 'Resend API key not configured';
            return false;
        }
        
        if (empty($this->resendFromAddress)) {
            $this->lastError = 'Resend from address not configured';
            return false;
        }
        
        $this->smtpLog("Resend: Sending email to {$to}");
        
        $payload = [
            'from' => "{$this->resendFromName} <{$this->resendFromAddress}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $body
        ];
        
        try {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->resendApiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $this->lastError = "Resend connection error: {$curlError}";
                $this->smtpLog("ERROR: " . $this->lastError);
                return false;
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode === 200 && isset($data['id'])) {
                $this->smtpLog("Resend: SUCCESS - Email ID: {$data['id']}");
                return true;
            }
            
            // Handle errors
            $errorMsg = $data['message'] ?? $data['error'] ?? 'Unknown error';
            $this->lastError = "Resend error ({$httpCode}): {$errorMsg}";
            $this->smtpLog("ERROR: " . $this->lastError);
            
            return false;
            
        } catch (\Exception $e) {
            $this->lastError = "Resend exception: " . $e->getMessage();
            $this->smtpLog("ERROR: " . $this->lastError);
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function sendTestEmail(string $to): bool
    {
        $subject = "Test Email from Faceless Pictures 3";
        $body = $this->templateService->buildEmail([
            'logo' => true,
            'heading' => "Test Email",
            'subheading' => "Your email configuration is working!",
            'content' => '<p style="color: #666; font-size: 15px; text-align: center;">If you received this email, your email settings are configured correctly. 🎉</p>',
            'footer_text' => "This is a test email from your Faceless Pictures 3 installation."
        ]);
        return $this->sendEmail($to, $subject, $body);
    }

    private function smtpCommand($smtp, string $command): string
    {
        if (!empty($command)) {
            fwrite($smtp, $command . "\r\n");
        }

        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }
}

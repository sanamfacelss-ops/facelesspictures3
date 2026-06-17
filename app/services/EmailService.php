<?php

declare(strict_types=1);

namespace App\Services;

class EmailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private ?EmailTemplateService $templateService = null;

    public function __construct()
    {
        $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $this->username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@facelesspictures.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Faceless Pictures 3';
        $this->templateService = new EmailTemplateService();
    }
    
    /**
     * Get email configuration status
     */
    public function getConfigStatus(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username ? substr($this->username, 0, 3) . '***' : '',
            'has_password' => !empty($this->password),
            'encryption' => $this->encryption,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'is_configured' => !empty($this->username) && !empty($this->password),
        ];
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
        $smtp = fsockopen(
            ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host,
            $this->port,
            $errno,
            $errstr,
            30
        );

        if (!$smtp) {
            log_message('error', "SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }

        $this->smtpCommand($smtp, "EHLO " . gethostname());
        if ($this->encryption === 'tls') {
            $this->smtpCommand($smtp, "STARTTLS");
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpCommand($smtp, "EHLO " . gethostname());
        }

        $this->smtpCommand($smtp, "AUTH LOGIN");
        $this->smtpCommand($smtp, base64_encode($this->username));
        $this->smtpCommand($smtp, base64_encode($this->password));
        $this->smtpCommand($smtp, "MAIL FROM:<{$this->fromAddress}>");
        $this->smtpCommand($smtp, "RCPT TO:<{$to}>");
        $this->smtpCommand($smtp, "DATA");

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromAddress}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= $isHtml ? "Content-Type: text/html; charset=UTF-8\r\n" : "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $message = $headers . $body . "\r\n.\r\n";
        fwrite($smtp, $message);

        $response = '';
        while ($line = fgets($smtp)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }

        $this->smtpCommand($smtp, "QUIT");
        fclose($smtp);

        return str_starts_with($response, '250');
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
     * Helper method to send email using best available method
     */
    private function sendEmail(string $to, string $subject, string $body): bool
    {
        // Try SMTP first, fallback to mail()
        if (!empty($this->username) && !empty($this->password)) {
            return $this->sendSmtp($to, $subject, $body, true);
        }
        return $this->send($to, $subject, $body, true);
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

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

    public function __construct()
    {
        $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $this->username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@facelesspictures.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Faceless Pictures';
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
        $subject = 'Password Reset OTP - Faceless Pitcher';
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8F5F0; padding: 40px 20px; }
                .container { max-width: 480px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: #141414; padding: 30px; text-align: center; }
                .header h1 { color: white; font-size: 24px; margin: 0; letter-spacing: 2px; }
                .header span { background: #D92B3A; color: white; font-size: 10px; padding: 3px 8px; border-radius: 20px; }
                .content { padding: 40px 30px; text-align: center; }
                .otp-box { background: #F8F5F0; border: 2px dashed #D92B3A; border-radius: 12px; padding: 25px; margin: 25px 0; }
                .otp-code { font-size: 36px; font-weight: bold; color: #D92B3A; letter-spacing: 8px; }
                .expires { color: #666; font-size: 13px; margin-top: 10px; }
                .footer { background: #F8F5F0; padding: 20px; text-align: center; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>FACELESS PITCHER <span>S3</span></h1>
                </div>
                <div class='content'>
                    <h2 style='color: #0D0D0D; margin-bottom: 10px;'>Password Reset</h2>
                    <p style='color: #666; font-size: 14px;'>Use this OTP to reset your password:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$otp}</div>
                        <p class='expires'>Expires in 10 minutes</p>
                    </div>
                    <p style='color: #999; font-size: 13px;'>If you didn't request this, please ignore this email.</p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Faceless Pitcher. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Try SMTP first, fallback to mail()
        if (!empty($this->username) && !empty($this->password)) {
            return $this->sendSmtp($to, $subject, $body, true);
        }
        
        return $this->send($to, $subject, $body, true);
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

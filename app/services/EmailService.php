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

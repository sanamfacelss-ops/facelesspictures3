<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Modern Email Template Service
 * Provides beautiful, responsive email templates for all platform notifications
 */
class EmailTemplateService
{
    private string $brandName = 'Faceless Pictures';
    private string $primaryColor = '#D92B3A';
    private string $goldColor = '#C9943A';
    private string $darkColor = '#0D0D0D';
    private string $creamColor = '#F8F5F0';
    
    /**
     * Get the base email template wrapper
     */
    private function getBaseTemplate(string $content, string $preheader = ''): string
    {
        $year = date('Y');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$this->brandName}</title>
</head>
HTML;
    }

    /**
     * Get the logo HTML (Faceless Pictures with 3 in red circle)
     */
    private function getLogo(): string
    {
        return <<<HTML
<div style="text-align: center; padding: 30px 0;">
    <span style="font-family: 'Bebas Neue', Arial, sans-serif; font-size: 28px; color: {$this->darkColor}; letter-spacing: 2px;">FACELESS PICTURES</span>
    <span style="display: inline-block; background: {$this->primaryColor}; color: white; font-size: 14px; font-weight: bold; width: 24px; height: 24px; line-height: 24px; border-radius: 50%; text-align: center; margin-left: 8px; vertical-align: middle;">3</span>
</div>
HTML;
    }

    /**
     * Welcome email for new user signup
     */
    public function welcomeEmail(array $user): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $email = htmlspecialchars($user['email'] ?? '');
        $role = ucfirst($user['role'] ?? 'creator');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "Welcome to Faceless Pictures 3 - Let's Create Something Amazing! 🎬";
        
        $body = $this->buildEmail([
            'preheader' => "Welcome to Faceless Pictures 3! Your creative journey starts now.",
            'logo' => true,
            'heading' => "Welcome, {$name}!",
            'subheading' => "You're now part of India's first anonymous film competition",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Congratulations on joining <strong>Faceless Pictures 3</strong>! You've registered as a <strong>{$role}</strong>, and we can't wait to see what you create.
</p>
<div style="background: {$this->creamColor}; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 15px; color: {$this->darkColor}; font-size: 16px;">What happens next?</h3>
    <ul style="margin: 0; padding-left: 20px; color: #555; font-size: 14px; line-height: 1.8;">
        <li>Upload your best work (under 3 minutes)</li>
        <li>Our AI reviews your content for quality</li>
        <li>Approved videos go live on YouTube</li>
        <li>The world votes - views become your score</li>
    </ul>
</div>
HTML,
            'cta_text' => 'Go to Dashboard',
            'cta_url' => "{$appUrl}/dashboard",
            'footer_text' => "You received this email because you signed up for Faceless Pictures 3."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Video submitted confirmation email
     */
    public function videoSubmittedEmail(array $user, array $video): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Your Video');
        $contentType = ucfirst($video['content_type'] ?? $user['role'] ?? 'video');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "Video Received! '{$videoTitle}' is Being Reviewed 📹";
        
        $body = $this->buildEmail([
            'preheader' => "We've received your {$contentType} submission and it's now being reviewed.",
            'logo' => true,
            'heading' => "Submission Received!",
            'subheading' => "Your {$contentType} video has been submitted successfully",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Hey {$name}, we've got your video! Here's what you submitted:
</p>
<div style="background: linear-gradient(135deg, {$this->creamColor} 0%, #fff 100%); border: 2px solid #eee; border-radius: 16px; padding: 25px; margin: 25px 0;">
    <div style="display: flex; align-items: center; margin-bottom: 15px;">
        <span style="font-size: 32px; margin-right: 15px;">🎬</span>
        <div>
            <h3 style="margin: 0; color: {$this->darkColor}; font-size: 18px;">{$videoTitle}</h3>
            <p style="margin: 5px 0 0; color: #888; font-size: 13px;">Category: {$contentType}</p>
        </div>
    </div>
    <div style="background: #fff; border-radius: 8px; padding: 12px 15px; border-left: 4px solid {$this->goldColor};">
        <p style="margin: 0; color: #666; font-size: 13px;">
            <strong style="color: {$this->goldColor};">Status:</strong> 
            <span style="background: #FEF3C7; color: #92400E; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">Under Review</span>
        </p>
    </div>
</div>
<p style="color: #666; font-size: 14px; line-height: 1.7;">
    Our AI is reviewing your content for quality and compliance. This usually takes a few minutes to a few hours. We'll email you once it's processed!
</p>
HTML,
            'cta_text' => 'Track Your Submission',
            'cta_url' => "{$appUrl}/dashboard",
            'footer_text' => "You're receiving this because you submitted a video to Faceless Pictures 3."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Video approved email
     */
    public function videoApprovedEmail(array $user, array $video): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Your Video');
        $youtubeId = $video['youtube_id'] ?? '';
        $youtubeUrl = $youtubeId ? "https://youtube.com/watch?v={$youtubeId}" : '';
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "🎉 Congrats! '{$videoTitle}' is Now LIVE on YouTube!";
        
        $youtubeSection = $youtubeId ? <<<HTML
<div style="background: #FF0000; border-radius: 12px; padding: 20px; margin: 25px 0; text-align: center;">
    <p style="color: white; margin: 0 0 15px; font-size: 14px;">Your video is now live!</p>
    <a href="{$youtubeUrl}" style="display: inline-block; background: white; color: #FF0000; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px;">
        ▶ Watch on YouTube
    </a>
</div>
HTML : '';

        $body = $this->buildEmail([
            'preheader' => "Great news! Your video has been approved and is now live.",
            'logo' => true,
            'heading' => "You're Live! 🎉",
            'subheading' => "Your video has been approved and published",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Amazing news, {$name}! Your submission "<strong>{$videoTitle}</strong>" has passed our review and is now published.
</p>
{$youtubeSection}
<div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 10px; color: #065F46; font-size: 15px;">✅ What this means:</h3>
    <ul style="margin: 0; padding-left: 20px; color: #047857; font-size: 14px; line-height: 1.8;">
        <li>Your video is live and collecting views</li>
        <li>Views count as votes in the competition</li>
        <li>Share your video to boost your score!</li>
    </ul>
</div>
<p style="color: #666; font-size: 14px; line-height: 1.7;">
    Remember, in Faceless Pictures 3, the world is your judge. More views = better ranking. Get sharing!
</p>
HTML,
            'cta_text' => 'View My Dashboard',
            'cta_url' => "{$appUrl}/dashboard",
            'footer_text' => "Congratulations on getting published!"
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Video rejected email
     */
    public function videoRejectedEmail(array $user, array $video, string $reason = ''): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Your Video');
        $reason = htmlspecialchars($reason ?: 'Content did not meet our community guidelines.');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "Update on '{$videoTitle}' - Action Required";
        
        $body = $this->buildEmail([
            'preheader' => "Your video needs attention. Here's what happened.",
            'logo' => true,
            'heading' => "Video Not Approved",
            'subheading' => "We couldn't publish this submission",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Hey {$name}, unfortunately your submission "<strong>{$videoTitle}</strong>" didn't pass our review process.
</p>
<div style="background: #FEF2F2; border: 1px solid #FECACA; border-left: 4px solid {$this->primaryColor}; border-radius: 8px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 10px; color: #991B1B; font-size: 14px;">Reason:</h3>
    <p style="margin: 0; color: #7F1D1D; font-size: 14px; line-height: 1.6;">{$reason}</p>
</div>
<div style="background: {$this->creamColor}; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 15px; color: {$this->darkColor}; font-size: 15px;">💡 What you can do:</h3>
    <ul style="margin: 0; padding-left: 20px; color: #555; font-size: 14px; line-height: 1.8;">
        <li>Review our content guidelines</li>
        <li>Edit your video to address the issue</li>
        <li>Upload a new version</li>
    </ul>
</div>
<p style="color: #666; font-size: 14px;">
    Don't give up! Many successful creators had multiple attempts before getting it right.
</p>
HTML,
            'cta_text' => 'Try Again',
            'cta_url' => "{$appUrl}/upload",
            'footer_text' => "Need help? Contact support@facelesspictures.com"
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Video under manual review email
     */
    public function videoManualReviewEmail(array $user, array $video): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Your Video');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "'{$videoTitle}' Needs a Human Touch 👀";
        
        $body = $this->buildEmail([
            'preheader' => "Your video is being reviewed by our team for additional verification.",
            'logo' => true,
            'heading' => "Manual Review in Progress",
            'subheading' => "A human is taking a closer look",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Hey {$name}, your video "<strong>{$videoTitle}</strong>" has been flagged for manual review by our team.
</p>
<div style="background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <div style="display: flex; align-items: center;">
        <span style="font-size: 28px; margin-right: 15px;">⏳</span>
        <div>
            <h3 style="margin: 0 0 5px; color: #92400E; font-size: 15px;">Under Manual Review</h3>
            <p style="margin: 0; color: #B45309; font-size: 13px;">Our team typically responds within 24-48 hours</p>
        </div>
    </div>
</div>
<p style="color: #666; font-size: 14px; line-height: 1.7;">
    <strong>This is normal!</strong> Sometimes our AI needs human backup to make the right call. This doesn't mean anything is wrong with your video.
</p>
<p style="color: #888; font-size: 13px; margin-top: 20px;">
    We'll email you as soon as there's an update on your submission.
</p>
HTML,
            'cta_text' => 'Check Status',
            'cta_url' => "{$appUrl}/dashboard",
            'footer_text' => "Your patience is appreciated while we review your content."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Video processing email (AI is analyzing)
     */
    public function videoProcessingEmail(array $user, array $video): array
    {
        $name = htmlspecialchars($user['name'] ?? 'Creator');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Your Video');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "🔄 Processing '{$videoTitle}' - AI Analysis Started";
        
        $body = $this->buildEmail([
            'preheader' => "Our AI is analyzing your video for quality and compliance.",
            'logo' => true,
            'heading' => "AI Analysis in Progress",
            'subheading' => "Your video is being processed",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px;">
    Hey {$name}, our AI is now analyzing "<strong>{$videoTitle}</strong>". Here's what's happening:
</p>
<div style="background: #EEF2FF; border: 1px solid #C7D2FE; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <div style="margin-bottom: 15px;">
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <span style="color: #4F46E5; margin-right: 10px;">✓</span>
            <span style="color: #3730A3; font-size: 14px;">Video uploaded successfully</span>
        </div>
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <span style="color: #4F46E5; margin-right: 10px;">⏳</span>
            <span style="color: #3730A3; font-size: 14px;">Content analysis in progress</span>
        </div>
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <span style="color: #9CA3AF; margin-right: 10px;">○</span>
            <span style="color: #6B7280; font-size: 14px;">Quality check</span>
        </div>
        <div style="display: flex; align-items: center;">
            <span style="color: #9CA3AF; margin-right: 10px;">○</span>
            <span style="color: #6B7280; font-size: 14px;">Final review</span>
        </div>
    </div>
</div>
<p style="color: #666; font-size: 14px; line-height: 1.7;">
    This process usually takes a few minutes. We'll notify you as soon as it's complete!
</p>
HTML,
            'cta_text' => 'View Status',
            'cta_url' => "{$appUrl}/dashboard",
            'footer_text' => "Sit tight! We're processing your submission."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Admin notification - new video submitted
     */
    public function adminNewVideoEmail(array $user, array $video): array
    {
        $userName = htmlspecialchars($user['name'] ?? 'Unknown');
        $userEmail = htmlspecialchars($user['email'] ?? '');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Untitled');
        $contentType = ucfirst($video['content_type'] ?? $user['role'] ?? 'video');
        $videoId = $video['id'] ?? 0;
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "📥 New Video Submission: '{$videoTitle}' by {$userName}";
        
        $body = $this->buildEmail([
            'preheader' => "New {$contentType} video submitted for review.",
            'logo' => true,
            'heading' => "New Submission",
            'subheading' => "A creator has uploaded new content",
            'content' => <<<HTML
<div style="background: {$this->creamColor}; border-radius: 12px; padding: 20px; margin: 20px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #888; font-size: 13px; width: 120px;">Creator:</td>
            <td style="padding: 8px 0; color: {$this->darkColor}; font-size: 14px; font-weight: 600;">{$userName}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #888; font-size: 13px;">Email:</td>
            <td style="padding: 8px 0; color: {$this->darkColor}; font-size: 14px;">{$userEmail}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #888; font-size: 13px;">Video Title:</td>
            <td style="padding: 8px 0; color: {$this->darkColor}; font-size: 14px; font-weight: 600;">{$videoTitle}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #888; font-size: 13px;">Category:</td>
            <td style="padding: 8px 0; color: {$this->darkColor}; font-size: 14px;">{$contentType}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #888; font-size: 13px;">Video ID:</td>
            <td style="padding: 8px 0; color: {$this->darkColor}; font-size: 14px;">#{$videoId}</td>
        </tr>
    </table>
</div>
HTML,
            'cta_text' => 'Review in Admin Panel',
            'cta_url' => "{$appUrl}/admin?tab=videos",
            'footer_text' => "Admin notification from Faceless Pictures 3"
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Admin notification - video flagged for manual review
     */
    public function adminFlaggedVideoEmail(array $user, array $video, array $aiResult = []): array
    {
        $userName = htmlspecialchars($user['name'] ?? 'Unknown');
        $videoTitle = htmlspecialchars($video['title'] ?? 'Untitled');
        $videoId = $video['id'] ?? 0;
        $aiScore = $aiResult['score'] ?? 'N/A';
        $flagReason = htmlspecialchars($aiResult['reason'] ?? 'Content flagged by AI moderation');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "⚠️ FLAGGED: '{$videoTitle}' Needs Manual Review";
        
        $body = $this->buildEmail([
            'preheader' => "A video has been flagged and requires admin attention.",
            'logo' => true,
            'heading' => "⚠️ Manual Review Required",
            'subheading' => "AI has flagged this content for human review",
            'content' => <<<HTML
<div style="background: #FEF2F2; border: 2px solid {$this->primaryColor}; border-radius: 12px; padding: 20px; margin: 20px 0;">
    <h3 style="margin: 0 0 15px; color: {$this->primaryColor}; font-size: 15px;">Flagged Content</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; color: #7F1D1D; font-size: 13px; width: 120px;">Video:</td>
            <td style="padding: 8px 0; color: #991B1B; font-size: 14px; font-weight: 600;">{$videoTitle}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #7F1D1D; font-size: 13px;">Creator:</td>
            <td style="padding: 8px 0; color: #991B1B; font-size: 14px;">{$userName}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #7F1D1D; font-size: 13px;">AI Score:</td>
            <td style="padding: 8px 0; color: #991B1B; font-size: 14px;">{$aiScore}/100</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #7F1D1D; font-size: 13px;">Flag Reason:</td>
            <td style="padding: 8px 0; color: #991B1B; font-size: 14px;">{$flagReason}</td>
        </tr>
    </table>
</div>
<p style="color: #666; font-size: 14px;">
    Please review this content and take appropriate action (approve or reject).
</p>
HTML,
            'cta_text' => 'Review Now',
            'cta_url' => "{$appUrl}/admin?tab=videos&filter=flagged",
            'footer_text' => "Urgent admin notification"
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Password reset OTP email (updated modern design)
     */
    public function passwordResetEmail(string $email, string $otp): array
    {
        $subject = "🔐 Password Reset OTP - Faceless Pictures 3";
        
        $body = $this->buildEmail([
            'preheader' => "Your OTP for password reset. Valid for 10 minutes.",
            'logo' => true,
            'heading' => "Password Reset",
            'subheading' => "Use this OTP to reset your password",
            'content' => <<<HTML
<p style="color: #666; font-size: 15px; line-height: 1.7; margin: 0 0 20px; text-align: center;">
    We received a request to reset your password. Use the code below:
</p>
<div style="background: {$this->creamColor}; border: 3px dashed {$this->primaryColor}; border-radius: 16px; padding: 30px; margin: 25px 0; text-align: center;">
    <p style="margin: 0 0 10px; color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;">Your OTP Code</p>
    <div style="font-size: 42px; font-weight: bold; color: {$this->primaryColor}; letter-spacing: 10px; font-family: monospace;">{$otp}</div>
    <p style="margin: 15px 0 0; color: #999; font-size: 13px;">Expires in 10 minutes</p>
</div>
<p style="color: #888; font-size: 13px; text-align: center;">
    If you didn't request this, you can safely ignore this email.
</p>
HTML,
            'footer_text' => "For security, do not share this code with anyone."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Build the complete email HTML
     */
    public function buildEmail(array $params): string
    {
        $preheader = $params['preheader'] ?? '';
        $showLogo = $params['logo'] ?? true;
        $heading = $params['heading'] ?? '';
        $subheading = $params['subheading'] ?? '';
        $content = $params['content'] ?? '';
        $ctaText = $params['cta_text'] ?? '';
        $ctaUrl = $params['cta_url'] ?? '';
        $footerText = $params['footer_text'] ?? '';
        $year = date('Y');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $logo = $showLogo ? $this->getLogo() : '';
        
        $ctaButton = $ctaText && $ctaUrl ? <<<HTML
<div style="text-align: center; margin: 30px 0;">
    <a href="{$ctaUrl}" style="display: inline-block; background: {$this->primaryColor}; color: white; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; box-shadow: 0 4px 15px rgba(217, 43, 58, 0.3);">{$ctaText}</a>
</div>
HTML : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Faceless Pictures 3</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {$this->creamColor}; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <!-- Preheader -->
    <div style="display: none; max-height: 0; overflow: hidden;">{$preheader}</div>
    
    <!-- Email Container -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: {$this->creamColor};">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px;">
                    <!-- Logo -->
                    <tr>
                        <td>{$logo}</td>
                    </tr>
                    
                    <!-- Main Content Card -->
                    <tr>
                        <td>
                            <div style="background: white; border-radius: 20px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                                <!-- Heading -->
                                <h1 style="margin: 0 0 8px; color: {$this->darkColor}; font-size: 28px; font-weight: 700; text-align: center;">{$heading}</h1>
                                <p style="margin: 0 0 25px; color: #888; font-size: 14px; text-align: center;">{$subheading}</p>
                                
                                <!-- Content -->
                                {$content}
                                
                                <!-- CTA Button -->
                                {$ctaButton}
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 20px; text-align: center;">
                            <p style="margin: 0 0 10px; color: #999; font-size: 13px;">{$footerText}</p>
                            <p style="margin: 0; color: #bbb; font-size: 12px;">
                                © {$year} Faceless Pictures 3. All rights reserved.
                            </p>
                            <p style="margin: 10px 0 0; color: #ccc; font-size: 11px;">
                                <a href="{$appUrl}" style="color: #999; text-decoration: none;">Visit Website</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Submission received confirmation email (for public submissions from /actor, /director, /writer)
     */
    public function submissionReceivedEmail(string $name, string $role, string $auditionType): array
    {
        $roleEmoji = $role === 'actor' ? '🎭' : ($role === 'director' ? '🎬' : '✍️');
        $roleColor = $role === 'actor' ? '#DC2626' : ($role === 'director' ? '#F59E0B' : '#3B82F6');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "✅ Audition Received — " . ucfirst($role) . " — Faceless Pictures 3";
        
        $body = $this->buildEmail([
            'preheader' => "Your {$role} audition submission has been received successfully.",
            'logo' => true,
            'heading' => "Submission Received!",
            'subheading' => "Thank you for auditioning with us",
            'content' => <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <div style="font-size: 64px; margin-bottom: 15px;">{$roleEmoji}</div>
    <p style="color: #666; font-size: 16px; line-height: 1.7; margin: 0;">
        Thank you, <strong>{$name}</strong>! We've received your <strong>{$role}</strong> audition.
    </p>
</div>

<div style="background: {$this->creamColor}; border-left: 4px solid {$roleColor}; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <p style="margin: 0 0 10px; color: #6B7280; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Submission Type</p>
    <p style="margin: 0; color: #374151; font-size: 16px; font-weight: 600;">{$auditionType}</p>
</div>

<div style="background: #EEF2FF; border: 2px solid #818CF8; border-radius: 12px; padding: 20px; margin: 25px 0;">
    <h3 style="margin: 0 0 12px; color: #4338CA; font-size: 15px;">📋 What happens next?</h3>
    <div style="color: #4338CA; font-size: 14px; line-height: 2;">
        ✅ AI moderation (automatic quality check)<br>
        👀 Creative team review<br>
        📧 We'll email you with updates<br>
        ⭐ Shortlisted candidates will be contacted
    </div>
</div>

<p style="color: #999; font-size: 13px; text-align: center; line-height: 1.6;">
    We review submissions regularly and will be in touch soon.<br>
    Thank you for your interest in Faceless Pictures 3!
</p>
HTML,
            'footer_text' => "You're receiving this because you submitted an audition to Faceless Pictures 3."
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Admin notification for new public submission
     */
    public function adminNewSubmissionEmail(string $name, string $email, string $role, string $auditionType, int $submissionId): array
    {
        $roleEmoji = $role === 'actor' ? '🎭' : ($role === 'director' ? '🎬' : '✍️');
        $roleColor = $role === 'actor' ? '#DC2626' : ($role === 'director' ? '#F59E0B' : '#3B82F6');
        $appUrl = APP_URL ?? 'https://facelesspictures.com';
        
        $subject = "🎬 New " . ucfirst($role) . " Audition (#" . $submissionId . ") — Faceless Pictures 3";
        
        $body = $this->buildEmail([
            'preheader' => "New {$role} audition submission received from {$name}.",
            'logo' => true,
            'heading' => "New Audition 📥",
            'subheading' => "Public submission from /{$role} page",
            'content' => <<<HTML
<div style="text-align: center; margin-bottom: 25px;">
    <div style="font-size: 48px; margin-bottom: 10px;">{$roleEmoji}</div>
    <p style="color: #666; font-size: 14px; margin: 0;">
        Submission ID: <strong style="color: {$this->primaryColor};">#{$submissionId}</strong>
    </p>
</div>

<div style="background: {$this->creamColor}; border: 2px solid {$roleColor}; border-radius: 16px; padding: 25px; margin: 25px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 0; color: #6B7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; width: 120px;">Applicant</td>
            <td style="padding: 10px 0; color: {$this->darkColor}; font-size: 15px; text-align: right; font-weight: 600;">{$name}</td>
        </tr>
        <tr>
            <td style="padding: 10px 0; color: #6B7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Email</td>
            <td style="padding: 10px 0; text-align: right;"><a href="mailto:{$email}" style="color: {$this->primaryColor}; text-decoration: none; font-size: 15px;">{$email}</a></td>
        </tr>
        <tr>
            <td style="padding: 10px 0; color: #6B7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Role</td>
            <td style="padding: 10px 0; color: {$this->darkColor}; font-size: 15px; text-align: right; font-weight: 600;">{$role}</td>
        </tr>
        <tr>
            <td style="padding: 10px 0; color: #6B7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Type</td>
            <td style="padding: 10px 0; color: {$this->darkColor}; font-size: 15px; text-align: right;">{$auditionType}</td>
        </tr>
    </table>
</div>

<p style="color: #999; font-size: 13px; text-align: center; line-height: 1.6;">
    This submission will be automatically processed through AI moderation.<br>
    Log in to your admin dashboard to review and manage submissions.
</p>
HTML,
            'cta_text' => 'Review in Admin Dashboard →',
            'cta_url' => "{$appUrl}/admin?tab=submissions",
            'footer_text' => "Admin notification • Faceless Pictures 3"
        ]);
        
        return ['subject' => $subject, 'body' => $body];
    }
}

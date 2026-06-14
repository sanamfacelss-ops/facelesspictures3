<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;

class YouTubeService
{
    private string $apiKey;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $channelId;
    private Video $videoModel;

    public function __construct()
    {
        // Try environment variables first, then fall back to database settings
        $settingsModel = new \App\Models\Settings();
        
        $this->apiKey = $this->getConfigValue('YOUTUBE_API_KEY', $settingsModel);
        $this->clientId = $this->getConfigValue('YOUTUBE_CLIENT_ID', $settingsModel);
        $this->clientSecret = $this->getConfigValue('YOUTUBE_CLIENT_SECRET', $settingsModel);
        $this->refreshToken = $this->getConfigValue('YOUTUBE_REFRESH_TOKEN', $settingsModel);
        $this->channelId = $this->getConfigValue('YOUTUBE_CHANNEL_ID', $settingsModel);
        $this->videoModel = new Video();
    }
    
    /**
     * Get config value from environment or database
     */
    private function getConfigValue(string $key, $settingsModel): string
    {
        // Check $_ENV first
        if (!empty($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check getenv()
        $envValue = getenv($key);
        if (!empty($envValue)) {
            return $envValue;
        }
        
        // Check database settings (stored with env_ prefix)
        try {
            $dbValue = $settingsModel->get('env_' . $key);
            if (!empty($dbValue)) {
                return $dbValue;
            }
        } catch (\Exception $e) {
            // Settings table might not exist
        }
        
        return '';
    }

    public function uploadVideo(int $videoId): array|string|null
    {
        $video = $this->videoModel->findById($videoId);
        
        // Only upload videos that are fully approved (both admin and AI)
        if (!$video) {
            log_message('warning', "Upload skipped: Video {$videoId} not found");
            return ['error' => 'Video not found'];
        }
        
        if ($video['status'] !== 'approved') {
            log_message('info', "Upload skipped: Video {$videoId} status is '{$video['status']}' (not approved)");
            return ['error' => "Video status is '{$video['status']}', must be 'approved'"];
        }
        
        // Check AI status - must be approved OR flagged (for manually approved flagged videos)
        $aiStatus = $video['ai_status'] ?? 'pending';
        if (!in_array($aiStatus, ['approved', 'flagged'])) {
            log_message('info', "Upload skipped: Video {$videoId} AI status is '{$aiStatus}' (not approved/flagged)");
            return ['error' => "AI status is '{$aiStatus}', must be 'approved' or 'flagged' (manually approved)"];
        }
        
        if (!empty($video['youtube_id'])) {
            log_message('info', "Upload skipped: Video {$videoId} already published to YouTube");
            return $video['youtube_id']; // Already published, return the ID
        }
        
        // Note: We removed the needs_manual_review check because if status is 'approved', 
        // the admin has already reviewed and approved it

        // Update YouTube status to uploading
        $this->videoModel->updateYoutubeStatus($videoId, 'uploading');

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', "Failed to get YouTube access token for video {$videoId}. Check YOUTUBE_CLIENT_ID, YOUTUBE_CLIENT_SECRET, and YOUTUBE_REFRESH_TOKEN.");
            $this->videoModel->updateYoutubeStatus($videoId, 'failed');
            return ['error' => 'Failed to get YouTube access token. Check OAuth credentials (Client ID, Client Secret, Refresh Token).'];
        }

        $filePath = UPLOAD_PATH . '/' . $video['file_path'];
        if (!file_exists($filePath)) {
            log_message('error', "Video file not found: {$filePath}");
            $this->videoModel->updateYoutubeStatus($videoId, 'failed');
            return ['error' => "Video file not found on server: {$video['file_path']}"];
        }

        $metadata = [
            'snippet' => [
                'title' => $video['title'],
                'description' => "Entry for season: {$video['season_title']}\n\nCreator: {$video['user_name']}\nCategory: " . ucfirst($video['content_type'] ?? 'General'),
                'tags' => ['facelesspictures', 'competition', $video['season_title'], $video['content_type'] ?? 'video'],
                'categoryId' => '1',
            ],
            'status' => [
                'privacyStatus' => 'public',
            ],
        ];

        $boundary = uniqid('fp3_');
        $body = $this->buildMultipartBody($metadata, $filePath, $boundary);

        $ch = curl_init('https://www.googleapis.com/upload/youtube/v3/videos?part=snippet,status&uploadType=multipart');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Content-Type: multipart/related; boundary={$boundary}",
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $data = json_decode($response, true);
            $youtubeId = $data['id'] ?? null;
            if ($youtubeId) {
                $this->videoModel->setYoutubeId($videoId, $youtubeId);
                $this->videoModel->updateYoutubeStatus($videoId, 'published');
                log_message('info', "Video {$videoId} published to YouTube: {$youtubeId}");
                return $youtubeId;
            }
        }

        // Parse error response from YouTube API
        $errorMsg = 'Unknown error';
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['error']['message'])) {
                $errorMsg = $data['error']['message'];
            } elseif (isset($data['error'])) {
                $errorMsg = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
            } else {
                $errorMsg = substr($response, 0, 200);
            }
        }

        log_message('error', "YouTube upload failed for video {$videoId} (HTTP {$httpCode}): " . $errorMsg);
        $this->videoModel->updateYoutubeStatus($videoId, 'failed');
        return ['error' => "YouTube API error (HTTP {$httpCode}): {$errorMsg}"];
    }

    public function syncStats(): void
    {
        // Stats sync removed - leaderboard functionality removed
    }

    private function syncChunk(array $youtubeIds): void
    {
        // Stats sync removed - leaderboard functionality removed
    }

    private function getAccessToken(): ?string
    {
        if (empty($this->refreshToken)) {
            log_message('error', 'YouTube refresh token is empty. Set YOUTUBE_REFRESH_TOKEN in .env');
            return null;
        }
        
        if (empty($this->clientId)) {
            log_message('error', 'YouTube client ID is empty. Set YOUTUBE_CLIENT_ID in .env');
            return null;
        }
        
        if (empty($this->clientSecret)) {
            log_message('error', 'YouTube client secret is empty. Set YOUTUBE_CLIENT_SECRET in .env');
            return null;
        }
        
        // Log what we're using (masked)
        log_message('info', 'YouTube OAuth: Using client_id=' . substr($this->clientId, 0, 20) . '..., refresh_token=' . substr($this->refreshToken, 0, 10) . '...');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            log_message('error', 'YouTube OAuth curl error: ' . $curlError);
            return null;
        }
        
        if (!$response) {
            log_message('error', 'YouTube OAuth request failed - no response (HTTP ' . $httpCode . ')');
            return null;
        }

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            $errorMsg = $data['error_description'] ?? $data['error'];
            log_message('error', 'YouTube OAuth error: ' . $errorMsg . ' (Full response: ' . $response . ')');
            return null;
        }
        
        if (empty($data['access_token'])) {
            log_message('error', 'YouTube OAuth: No access_token in response: ' . $response);
            return null;
        }
        
        log_message('info', 'YouTube OAuth: Successfully got access token');
        return $data['access_token'];
    }

    private function buildMultipartBody(array $metadata, string $filePath, string $boundary): string
    {
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: video/mp4\r\n";
        $body .= "Content-Transfer-Encoding: binary\r\n\r\n";
        $body .= file_get_contents($filePath) . "\r\n";
        $body .= "--{$boundary}--";
        return $body;
    }

    private function findVideoByYoutubeId(string $youtubeId): ?array
    {
        $videos = $this->videoModel->allApproved();
        foreach ($videos as $video) {
            if ($video['youtube_id'] === $youtubeId) {
                return $video;
            }
        }
        return null;
    }
}

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
    private \PDO $db;

    public function __construct()
    {
        // Try environment variables first, then fall back to database settings
        $settingsModel = new \App\Models\Settings();
        $this->db = \App\Config\Database::getConnection();
        
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
                
                // Add video to appropriate playlist
                $playlistId = $this->determinePlaylistForVideo($video);
                if ($playlistId) {
                    if ($this->addVideoToPlaylist($youtubeId, $playlistId)) {
                        // Update video record with playlist ID
                        $stmt = $this->db->prepare("UPDATE videos SET youtube_playlist_id = ? WHERE id = ?");
                        $stmt->execute([$playlistId, $videoId]);
                        log_message('info', "Video {$videoId} added to playlist {$playlistId}");
                    }
                }
                
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

    /**
     * Get or create a YouTube playlist for a specific role and audition type
     * 
     * @param string $role User role (actor, director, writer)
     * @param string|null $auditionType For actors: 'audition' or 'song_audition'
     * @param int|null $seasonId Optional season ID for per-season playlists
     * @return string|null Playlist ID or null on failure
     */
    public function getOrCreatePlaylist(string $role, ?string $auditionType = null, ?int $seasonId = null): ?string
    {
        // Check if playlist already exists in database
        $existingPlaylist = $this->findPlaylist($role, $auditionType, $seasonId);
        if ($existingPlaylist) {
            log_message('info', "Using existing playlist: {$existingPlaylist['playlist_id']} for {$role}" . ($auditionType ? " - {$auditionType}" : ""));
            return $existingPlaylist['playlist_id'];
        }

        // Create new playlist on YouTube
        $playlistId = $this->createYouTubePlaylist($role, $auditionType, $seasonId);
        if (!$playlistId) {
            return null;
        }

        // Save to database
        $this->savePlaylist($playlistId, $role, $auditionType, $seasonId);
        
        return $playlistId;
    }

    /**
     * Find existing playlist in database
     */
    private function findPlaylist(string $role, ?string $auditionType, ?int $seasonId): ?array
    {
        $query = "SELECT * FROM youtube_playlists WHERE role = ?";
        $params = [$role];

        if ($auditionType) {
            $query .= " AND audition_type = ?";
            $params[] = $auditionType;
        } else {
            $query .= " AND audition_type IS NULL";
        }

        // Check if we need season-specific playlists
        $settingsModel = new \App\Models\Settings();
        $perSeason = $settingsModel->get('youtube_playlist_per_season') === '1';
        
        if ($perSeason && $seasonId) {
            $query .= " AND season_id = ?";
            $params[] = $seasonId;
        } else {
            $query .= " AND season_id IS NULL";
        }

        $query .= " LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Create a new playlist on YouTube
     */
    private function createYouTubePlaylist(string $role, ?string $auditionType, ?int $seasonId): ?string
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', 'Failed to get access token for playlist creation');
            return null;
        }

        // Build playlist title and description
        $title = $this->buildPlaylistTitle($role, $auditionType, $seasonId);
        $description = $this->buildPlaylistDescription($role, $auditionType, $seasonId);

        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'tags' => ['facelesspictures', $role],
                'defaultLanguage' => 'en',
            ],
            'status' => [
                'privacyStatus' => 'public',
            ],
        ];

        $ch = curl_init('https://www.googleapis.com/youtube/v3/playlists?part=snippet,status');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($metadata),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $data = json_decode($response, true);
            $playlistId = $data['id'] ?? null;
            if ($playlistId) {
                log_message('info', "Created YouTube playlist: {$playlistId} - {$title}");
                return $playlistId;
            }
        }

        $errorMsg = 'Unknown error';
        if ($response) {
            $data = json_decode($response, true);
            $errorMsg = $data['error']['message'] ?? json_encode($data);
        }

        log_message('error', "Failed to create YouTube playlist '{$title}' (HTTP {$httpCode}): {$errorMsg}");
        log_message('error', "Full response: " . $response);
        return null;
    }

    /**
     * Build playlist title based on role and type
     */
    private function buildPlaylistTitle(string $role, ?string $auditionType, ?int $seasonId): string
    {
        $title = 'Faceless Pictures - ';
        
        switch ($role) {
            case 'actor':
                if ($auditionType === 'song_audition') {
                    $title .= 'Actor Song Auditions';
                } else {
                    $title .= 'Actor Auditions';
                }
                break;
            case 'director':
                $title .= 'Director Submissions';
                break;
            case 'writer':
                $title .= 'Writer Submissions';
                break;
        }

        // Add season info if enabled
        if ($seasonId) {
            $stmt = $this->db->prepare("SELECT title FROM seasons WHERE id = ?");
            $stmt->execute([$seasonId]);
            $season = $stmt->fetch();
            if ($season) {
                $title .= ' - ' . $season['title'];
            }
        }

        return $title;
    }

    /**
     * Build playlist description
     */
    private function buildPlaylistDescription(string $role, ?string $auditionType, ?int $seasonId): string
    {
        $description = "Official competition submissions for Faceless Pictures.\n\n";
        
        switch ($role) {
            case 'actor':
                if ($auditionType === 'song_audition') {
                    $description .= "This playlist contains song audition videos submitted by actors.";
                } else {
                    $description .= "This playlist contains audition videos submitted by actors.";
                }
                break;
            case 'director':
                $description .= "This playlist contains video submissions from directors.";
                break;
            case 'writer':
                $description .= "This playlist contains submissions from writers.";
                break;
        }

        if ($seasonId) {
            $stmt = $this->db->prepare("SELECT title, brief FROM seasons WHERE id = ?");
            $stmt->execute([$seasonId]);
            $season = $stmt->fetch();
            if ($season) {
                $description .= "\n\nSeason: " . $season['title'];
                if (!empty($season['brief'])) {
                    $description .= "\n" . $season['brief'];
                }
            }
        }

        return $description;
    }

    /**
     * Save playlist to database
     */
    private function savePlaylist(string $playlistId, string $role, ?string $auditionType, ?int $seasonId): bool
    {
        $title = $this->buildPlaylistTitle($role, $auditionType, $seasonId);
        $description = $this->buildPlaylistDescription($role, $auditionType, $seasonId);

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO youtube_playlists (playlist_id, title, description, role, audition_type, season_id) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$playlistId, $title, $description, $role, $auditionType, $seasonId]);
            log_message('info', "Saved playlist to database: {$playlistId}");
            return true;
        } catch (\Exception $e) {
            log_message('error', "Failed to save playlist to database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add video to a YouTube playlist
     */
    public function addVideoToPlaylist(string $videoId, string $playlistId): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', 'Failed to get access token for adding video to playlist');
            return false;
        }

        $metadata = [
            'snippet' => [
                'playlistId' => $playlistId,
                'resourceId' => [
                    'kind' => 'youtube#video',
                    'videoId' => $videoId,
                ],
            ],
        ];

        $ch = curl_init('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($metadata),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            log_message('info', "Added video {$videoId} to playlist {$playlistId}");
            return true;
        }

        $errorMsg = 'Unknown error';
        if ($response) {
            $data = json_decode($response, true);
            $errorMsg = $data['error']['message'] ?? json_encode($data);
        }

        log_message('error', "Failed to add video to playlist (HTTP {$httpCode}): {$errorMsg}");
        return false;
    }

    /**
     * Determine which playlist a video should go to based on user role and video metadata
     */
    public function determinePlaylistForVideo(array $video): ?string
    {
        $settingsModel = new \App\Models\Settings();
        $playlistEnabled = $settingsModel->get('youtube_playlist_enabled') === '1';
        
        if (!$playlistEnabled) {
            return null;
        }

        $role = $video['user_role'] ?? null;
        $seasonId = $video['season_id'] ?? null;
        
        // Check if we need per-season playlists
        $perSeason = $settingsModel->get('youtube_playlist_per_season') === '1';
        $seasonIdToUse = $perSeason ? $seasonId : null;

        // For actors, determine audition type
        $auditionType = null;
        if ($role === 'actor') {
            // Check script audition_type if available
            if (!empty($video['script_id'])) {
                $stmt = $this->db->prepare("SELECT audition_type FROM scripts WHERE id = ?");
                $stmt->execute([$video['script_id']]);
                $script = $stmt->fetch();
                $auditionType = $script['audition_type'] ?? null;
            }
            
            // Fall back to checking title or content type
            if (!$auditionType) {
                $title = strtolower($video['title'] ?? '');
                if (strpos($title, 'song') !== false || strpos($title, 'singing') !== false) {
                    $auditionType = 'song_audition';
                } else {
                    $auditionType = 'audition';
                }
            }
        }

        return $this->getOrCreatePlaylist($role, $auditionType, $seasonIdToUse);
    }

    /**
     * Get all playlists from database
     */
    public function getAllPlaylists(): array
    {
        $stmt = $this->db->query(
            "SELECT p.*, s.title as season_title 
             FROM youtube_playlists p 
             LEFT JOIN seasons s ON p.season_id = s.id 
             ORDER BY p.created_at DESC"
        );
        return $stmt->fetchAll();
    }
}


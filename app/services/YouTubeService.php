<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;
use App\Models\Leaderboard;

class YouTubeService
{
    private string $apiKey;
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $channelId;
    private Video $videoModel;
    private Leaderboard $leaderboardModel;

    public function __construct()
    {
        $this->apiKey = $_ENV['YOUTUBE_API_KEY'] ?? '';
        $this->clientId = $_ENV['YOUTUBE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['YOUTUBE_CLIENT_SECRET'] ?? '';
        $this->refreshToken = $_ENV['YOUTUBE_REFRESH_TOKEN'] ?? '';
        $this->channelId = $_ENV['YOUTUBE_CHANNEL_ID'] ?? '';
        $this->videoModel = new Video();
        $this->leaderboardModel = new Leaderboard();
    }

    public function uploadVideo(int $videoId): ?string
    {
        $video = $this->videoModel->findById($videoId);
        if (!$video || $video['status'] !== 'approved' || !empty($video['youtube_id'])) {
            return null;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            log_message('error', "Failed to get YouTube access token for video {$videoId}");
            return null;
        }

        $filePath = UPLOAD_PATH . '/' . $video['file_path'];
        if (!file_exists($filePath)) {
            log_message('error', "Video file not found: {$filePath}");
            return null;
        }

        $metadata = [
            'snippet' => [
                'title' => $video['title'],
                'description' => "Entry for season: {$video['season_title']}",
                'tags' => ['facelesspictures', 'competition', $video['season_title']],
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
                log_message('info', "Video {$videoId} published to YouTube: {$youtubeId}");
                return $youtubeId;
            }
        }

        log_message('error', "YouTube upload failed for video {$videoId}: " . ($response ?: 'Unknown error'));
        return null;
    }

    public function syncStats(): void
    {
        $videos = $this->videoModel->allApproved();
        $ids = array_filter(array_column($videos, 'youtube_id'));
        if (empty($ids)) return;

        $chunks = array_chunk($ids, 50);
        foreach ($chunks as $chunk) {
            $this->syncChunk($chunk);
        }
    }

    private function syncChunk(array $youtubeIds): void
    {
        $idString = implode(',', $youtubeIds);
        $url = "https://www.googleapis.com/youtube/v3/videos?part=statistics&id={$idString}&key=" . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return;
        $data = json_decode($response, true);
        if (empty($data['items'])) return;

        foreach ($data['items'] as $item) {
            $youtubeId = $item['id'];
            $stats = $item['statistics'] ?? [];
            $video = $this->findVideoByYoutubeId($youtubeId);
            if ($video) {
                $this->leaderboardModel->createOrUpdate((int) $video['id'], [
                    'views' => (int) ($stats['viewCount'] ?? 0),
                    'likes' => (int) ($stats['likeCount'] ?? 0),
                    'comments' => (int) ($stats['commentCount'] ?? 0),
                ]);
            }
        }
    }

    private function getAccessToken(): ?string
    {
        if (empty($this->refreshToken)) return null;

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
        curl_close($ch);
        if (!$response) return null;

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
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

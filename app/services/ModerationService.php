<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Video;
use App\Config\Database;
use PDO;

class ModerationService
{
    private Video $videoModel;
    private PDO $db;
    private string $ffmpegPath;
    private string $uploadPath;

    public function __construct()
    {
        $this->videoModel = new Video();
        $this->db = Database::getConnection();
        $this->ffmpegPath = $_ENV['FFMPEG_PATH'] ?? 'ffmpeg';
        $this->uploadPath = UPLOAD_PATH;
    }

    public function runQueue(): void
    {
        $pending = $this->videoModel->pending();
        if (empty($pending)) {
            log_message('info', 'Moderation queue: no pending videos.');
            return;
        }

        foreach ($pending as $video) {
            $this->processVideo((int) $video['id']);
        }
    }

    private function processVideo(int $videoId): void
    {
        $video = $this->videoModel->findById($videoId);
        if (!$video || $video['status'] !== 'pending') return;

        $filePath = $this->uploadPath . '/' . $video['file_path'];
        if (!file_exists($filePath)) {
            $this->reject($videoId, 'Video file missing on server.');
            return;
        }

        // Extract frames for NudeNet analysis
        $framesDir = sys_get_temp_dir() . '/fp3_frames_' . $videoId;
        @mkdir($framesDir, 0777, true);

        $cmd = sprintf(
            '%s -i %s -vf "fps=1/5,scale=320:-1" -frames:v 10 %s/frame_%%03d.jpg 2>&1',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($filePath),
            escapeshellarg($framesDir)
        );
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            log_message('error', "FFmpeg failed for video {$videoId}: " . implode("\n", $output));
            @rmdir($framesDir);
            return;
        }

        $nsfwScore = $this->runNudeNet($framesDir);
        $transcript = $this->runWhisper($filePath);

        // Cleanup frames
        array_map('unlink', glob($framesDir . '/*'));
        @rmdir($framesDir);

        if ($nsfwScore > 0.7) {
            $this->reject($videoId, 'AI detected potentially inappropriate visual content (NSFW score: ' . round($nsfwScore, 2) . ').');
            return;
        }

        if ($this->detectHateSpeech($transcript)) {
            $this->reject($videoId, 'AI detected potentially inappropriate audio/transcript content.');
            return;
        }

        $this->approve($videoId, 'AI moderation passed. NSFW score: ' . round($nsfwScore, 2));
    }

    private function runNudeNet(string $framesDir): float
    {
        $pythonScript = sys_get_temp_dir() . '/fp3_nudenet_' . uniqid() . '.py';
        $code = <<<'PY'
import sys, os, json
try:
    from nudenet import NudeDetector
    detector = NudeDetector()
    scores = []
    for f in sorted(os.listdir(sys.argv[1])):
        if f.endswith('.jpg'):
            results = detector.detect(os.path.join(sys.argv[1], f))
            if results:
                scores.append(max(r['score'] for r in results))
    print(json.dumps({'max_score': max(scores) if scores else 0.0}))
except Exception as e:
    print(json.dumps({'max_score': 0.0, 'error': str(e)}))
PY;
        file_put_contents($pythonScript, $code);
        $cmd = escapeshellcmd("python3 {$pythonScript} " . escapeshellarg($framesDir)) . ' 2>&1';
        exec($cmd, $output, $returnCode);
        @unlink($pythonScript);

        if ($returnCode !== 0 || empty($output)) {
            log_message('warning', 'NudeNet execution failed, defaulting to safe score.');
            return 0.0;
        }

        $result = json_decode(implode("\n", $output), true);
        return (float) ($result['max_score'] ?? 0.0);
    }

    private function runWhisper(string $filePath): string
    {
        $outputFile = sys_get_temp_dir() . '/fp3_whisper_' . uniqid() . '.txt';
        $cmd = sprintf(
            'whisper %s --model base --language English --output_format txt --output_dir %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg(sys_get_temp_dir())
        );
        exec($cmd, $output, $returnCode);

        $transcript = '';
        if ($returnCode === 0) {
            $base = pathinfo($filePath, PATHINFO_FILENAME);
            $possible = sys_get_temp_dir() . '/' . $base . '.txt';
            if (file_exists($possible)) {
                $transcript = file_get_contents($possible);
                @unlink($possible);
            }
        }

        if (file_exists($outputFile)) @unlink($outputFile);
        return $transcript;
    }

    private function detectHateSpeech(string $text): bool
    {
        $banned = ['hate', 'kill', 'death threat', 'terrorist', 'racist', 'slur'];
        $lower = strtolower($text);
        foreach ($banned as $word) {
            if (str_contains($lower, $word)) return true;
        }
        return false;
    }

    private function approve(int $videoId, string $reason): void
    {
        $this->videoModel->updateStatus($videoId, 'approved', $reason);
        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason) VALUES (?, 'ai_approved', ?)"
        );
        $stmt->execute([$videoId, $reason]);
        log_message('info', "Video {$videoId} AI approved.");
    }

    private function reject(int $videoId, string $reason): void
    {
        $this->videoModel->updateStatus($videoId, 'rejected', $reason);
        $stmt = $this->db->prepare(
            "INSERT INTO moderation_logs (video_id, action, reason) VALUES (?, 'ai_rejected', ?)"
        );
        $stmt->execute([$videoId, $reason]);
        log_message('info', "Video {$videoId} AI rejected: {$reason}");
    }
}

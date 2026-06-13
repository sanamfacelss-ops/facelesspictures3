<?php
require_once __DIR__ . '/../../app/config/config.php';

if (!is_authenticated()) redirect('/login');
$user = auth_user();
$title = 'My Videos - ' . APP_NAME;

$videoModel = new App\Models\Video();
$userVideos = $videoModel->byUser((int)$user['id']);

$categoryInfo = [
    'actor' => ['icon' => '🎭', 'label' => 'Acting', 'bg' => '#FFF1F2'],
    'director' => ['icon' => '🎬', 'label' => 'Directing', 'bg' => '#FFFBEB'],
    'writer' => ['icon' => '✍️', 'label' => 'Writing', 'bg' => '#EFF6FF'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', system-ui, sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/creator/dashboard" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="hidden sm:inline font-medium">Back</span>
            </a>
            <h1 class="font-semibold text-gray-900">My Videos</h1>
            <a href="/creator/record" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition">+ New</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 pb-24">
        <?php if (empty($userVideos)): ?>
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">No videos yet</h2>
            <p class="text-gray-500 mb-6">Start creating to see your videos here</p>
            <a href="/creator/record" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 text-white rounded-lg font-medium hover:bg-gray-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Video
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($userVideos as $video): 
                $cat = $categoryInfo[$video['content_type'] ?? 'actor'] ?? ['icon' => '📹', 'bg' => '#F3F4F6'];
                $statusStyles = [
                    'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Processing'],
                    'approved' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Approved'],
                    'rejected' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'label' => 'Rejected'],
                ];
                $status = $statusStyles[$video['status']] ?? $statusStyles['pending'];
            ?>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-lg flex items-center justify-center text-2xl flex-shrink-0" style="background: <?= $cat['bg'] ?>">
                        <?= $cat['icon'] ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-semibold text-gray-900"><?= e($video['title']) ?></h3>
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0 <?= $status['bg'] ?> <?= $status['text'] ?>"><?= $status['label'] ?></span>
                        </div>
                        <p class="text-sm text-gray-500 mb-3"><?= e($video['season_title']) ?> • <?= date('M j, Y', strtotime($video['created_at'])) ?></p>
                        
                        <!-- Progress -->
                        <div class="flex items-center gap-2 text-xs">
                            <span class="flex items-center gap-1 text-emerald-600"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Uploaded</span>
                            <span class="text-gray-300">→</span>
                            <span class="flex items-center gap-1 <?= in_array($video['ai_status'] ?? '', ['approved','flagged']) ? 'text-emerald-600' : 'text-gray-400' ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= in_array($video['ai_status'] ?? '', ['approved','flagged']) ? 'bg-emerald-500' : ($video['ai_status'] === 'processing' ? 'bg-blue-500 animate-pulse' : 'bg-gray-300') ?>"></span>
                                AI Check
                            </span>
                            <span class="text-gray-300">→</span>
                            <span class="flex items-center gap-1 <?= $video['status'] === 'approved' ? 'text-emerald-600' : 'text-gray-400' ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $video['status'] === 'approved' ? 'bg-emerald-500' : 'bg-gray-300' ?>"></span>
                                Approved
                            </span>
                            <span class="text-gray-300">→</span>
                            <span class="flex items-center gap-1 <?= !empty($video['youtube_id']) ? 'text-red-500' : 'text-gray-400' ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= !empty($video['youtube_id']) ? 'bg-red-500' : 'bg-gray-300' ?>"></span>
                                Published
                            </span>
                        </div>
                        
                        <?php if (!empty($video['youtube_id'])): ?>
                        <a href="https://youtube.com/watch?v=<?= e($video['youtube_id']) ?>" target="_blank" class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 bg-red-50 text-red-600 text-sm font-medium rounded-lg hover:bg-red-100 transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            Watch on YouTube
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($video['needs_manual_review'] ?? false): ?>
                <div class="mt-4 p-3 bg-amber-50 border border-amber-100 rounded-lg">
                    <p class="text-sm text-amber-700 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Under manual review
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($video['status'] === 'rejected' && $video['rejection_reason']): ?>
                <div class="mt-4 p-3 bg-rose-50 border border-rose-100 rounded-lg">
                    <p class="text-sm text-rose-700"><strong>Reason:</strong> <?= e($video['rejection_reason']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Mobile Nav -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
        <div class="flex justify-around py-2">
            <a href="/creator/dashboard" class="flex flex-col items-center py-2 px-3 text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="/creator/record" class="flex flex-col items-center py-2 px-3 text-gray-500">
                <div class="w-10 h-10 -mt-5 bg-gray-900 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <span class="text-xs mt-1">Create</span>
            </a>
            <a href="/creator/videos" class="flex flex-col items-center py-2 px-3 text-rose-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span class="text-xs mt-1">Videos</span>
            </a>
        </div>
    </nav>
</body>
</html>

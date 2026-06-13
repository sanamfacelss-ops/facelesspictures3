<?php
require_once __DIR__ . '/../../app/config/config.php';

if (!is_authenticated()) redirect('/login');
$user = auth_user();
$title = 'My Videos - ' . APP_NAME;

$videoModel = new App\Models\Video();
$userVideos = $videoModel->byUser((int)$user['id']);

$categoryInfo = [
    'actor' => ['icon' => '🎭', 'label' => 'Acting', 'gradient' => 'from-rose-500 to-pink-600'],
    'director' => ['icon' => '🎬', 'label' => 'Directing', 'gradient' => 'from-amber-500 to-orange-600'],
    'writer' => ['icon' => '✍️', 'label' => 'Writing', 'gradient' => 'from-blue-500 to-indigo-600'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        dark: { 900: '#0f172a', 950: '#020617' },
                        brand: { 500: '#6366f1', 600: '#4f46e5' }
                    }
                }
            }
        }
    </script>
    <style>[x-cloak] { display: none !important; } .glass { backdrop-filter: blur(12px); }</style>
</head>
<body class="min-h-screen bg-dark-950 text-white font-sans">
    <!-- Header -->
    <header class="sticky top-0 z-50 glass bg-dark-950/90 border-b border-white/5">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/creator/dashboard" class="flex items-center gap-2 text-white/60 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="hidden sm:inline">Back</span>
            </a>
            <h1 class="font-semibold">My Videos</h1>
            <a href="/creator/record" class="flex items-center gap-2 px-4 py-2 bg-brand-500 rounded-lg text-sm font-medium hover:bg-brand-600 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="hidden sm:inline">New Video</span>
            </a>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 pb-24">
        <?php if (empty($userVideos)): ?>
        <!-- Empty State -->
        <div class="text-center py-20">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/5 flex items-center justify-center">
                <svg class="w-12 h-12 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold mb-2">No videos yet</h2>
            <p class="text-white/50 mb-8">Create your first video to get started</p>
            <a href="/creator/record" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-500 rounded-xl font-semibold hover:bg-brand-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create First Video
            </a>
        </div>
        <?php else: ?>
        <!-- Videos Grid -->
        <div class="grid gap-4">
            <?php foreach ($userVideos as $video): 
                $catInfo = $categoryInfo[$video['content_type'] ?? 'actor'] ?? ['icon' => '📹', 'gradient' => 'from-gray-500 to-gray-600'];
                
                $statusConfig = [
                    'pending' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'label' => 'Pending Review', 'icon' => '⏳'],
                    'approved' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'label' => 'Approved', 'icon' => '✓'],
                    'rejected' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'label' => 'Rejected', 'icon' => '✗'],
                ];
                
                $aiStatusConfig = [
                    'pending' => ['bg' => 'bg-slate-500/20', 'text' => 'text-slate-400', 'label' => 'Queued for AI', 'desc' => 'Waiting in queue'],
                    'processing' => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'label' => 'AI Reviewing', 'desc' => 'AI is analyzing your video'],
                    'approved' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'label' => 'AI Passed', 'desc' => 'Passed automated checks'],
                    'flagged' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'label' => 'Manual Review', 'desc' => 'Flagged for human review'],
                    'rejected' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'label' => 'AI Rejected', 'desc' => 'Did not pass automated checks'],
                ];
                
                $youtubeConfig = [
                    'pending' => ['bg' => 'bg-slate-500/20', 'text' => 'text-slate-400', 'label' => 'Not Published'],
                    'uploading' => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'label' => 'Uploading to YouTube'],
                    'published' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400', 'label' => 'Live on YouTube'],
                    'failed' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'label' => 'Upload Failed'],
                ];
                
                $status = $statusConfig[$video['status']] ?? $statusConfig['pending'];
                $aiStatus = $aiStatusConfig[$video['ai_status'] ?? 'pending'] ?? $aiStatusConfig['pending'];
                $ytStatus = $youtubeConfig[$video['youtube_status'] ?? 'pending'] ?? $youtubeConfig['pending'];
            ?>
            <div class="bg-dark-900 rounded-2xl border border-white/5 overflow-hidden hover:border-white/10 transition">
                <div class="p-5">
                    <div class="flex items-start gap-4">
                        <!-- Thumbnail/Icon -->
                        <div class="w-20 h-20 md:w-28 md:h-20 rounded-xl bg-gradient-to-br <?= $catInfo['gradient'] ?> flex items-center justify-center text-3xl flex-shrink-0">
                            <?= $catInfo['icon'] ?>
                        </div>
                        
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="font-semibold text-lg truncate"><?= e($video['title']) ?></h3>
                            </div>
                            <p class="text-sm text-white/50 mb-3">
                                <?= e($video['season_title']) ?> • 
                                <?= ucfirst(e($video['content_type'] ?? 'Video')) ?> •
                                <?= date('M j, Y', strtotime($video['created_at'])) ?>
                            </p>
                            
                            <!-- Status Pills -->
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $status['bg'] ?> <?= $status['text'] ?>">
                                    <?= $status['label'] ?>
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $aiStatus['bg'] ?> <?= $aiStatus['text'] ?>">
                                    🤖 <?= $aiStatus['label'] ?>
                                </span>
                                <?php if (!empty($video['youtube_id'])): ?>
                                <a href="https://youtube.com/watch?v=<?= e($video['youtube_id']) ?>" target="_blank" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400 hover:bg-red-500/30 transition">
                                    ▶ Watch on YouTube
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- AI Score -->
                        <?php if (isset($video['ai_score']) && $video['ai_score'] !== null): ?>
                        <div class="hidden md:block text-center px-4">
                            <div class="text-2xl font-bold <?= $video['ai_score'] >= 70 ? 'text-emerald-400' : ($video['ai_score'] >= 40 ? 'text-amber-400' : 'text-rose-400') ?>">
                                <?= number_format($video['ai_score'], 0) ?>
                            </div>
                            <div class="text-xs text-white/40">AI Score</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rejection Reason or Flagged Notice -->
                    <?php if ($video['status'] === 'rejected' && !empty($video['rejection_reason'])): ?>
                    <div class="mt-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/20">
                        <p class="text-sm text-rose-300">
                            <span class="font-medium">Rejection reason:</span> <?= e($video['rejection_reason']) ?>
                        </p>
                    </div>
                    <?php elseif ($video['needs_manual_review'] ?? false): ?>
                    <div class="mt-4 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20">
                        <p class="text-sm text-amber-300">
                            🚩 This video has been flagged for manual review. Our team will check it shortly.
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- AI Feedback -->
                    <?php if (!empty($video['ai_feedback'])): 
                        $feedback = is_string($video['ai_feedback']) ? json_decode($video['ai_feedback'], true) : $video['ai_feedback'];
                    ?>
                    <?php if ($feedback && !empty($feedback['summary'])): ?>
                    <div class="mt-4 p-3 rounded-lg bg-white/5">
                        <p class="text-sm text-white/70">
                            <span class="font-medium text-white/90">AI Feedback:</span> <?= e($feedback['summary']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Progress Timeline -->
                <div class="px-5 pb-5">
                    <div class="flex items-center gap-2 text-xs">
                        <div class="flex items-center gap-1 <?= $video['status'] !== 'rejected' ? 'text-emerald-400' : 'text-white/40' ?>">
                            <span class="w-2 h-2 rounded-full <?= $video['status'] !== 'rejected' ? 'bg-emerald-400' : 'bg-white/20' ?>"></span>
                            Uploaded
                        </div>
                        <div class="flex-1 h-px bg-white/10"></div>
                        <div class="flex items-center gap-1 <?= in_array($video['ai_status'] ?? '', ['approved', 'flagged']) ? 'text-emerald-400' : 'text-white/40' ?>">
                            <span class="w-2 h-2 rounded-full <?= in_array($video['ai_status'] ?? '', ['approved', 'flagged']) ? 'bg-emerald-400' : ($video['ai_status'] === 'processing' ? 'bg-blue-400 animate-pulse' : 'bg-white/20') ?>"></span>
                            AI Check
                        </div>
                        <div class="flex-1 h-px bg-white/10"></div>
                        <div class="flex items-center gap-1 <?= $video['status'] === 'approved' ? 'text-emerald-400' : 'text-white/40' ?>">
                            <span class="w-2 h-2 rounded-full <?= $video['status'] === 'approved' ? 'bg-emerald-400' : 'bg-white/20' ?>"></span>
                            Approved
                        </div>
                        <div class="flex-1 h-px bg-white/10"></div>
                        <div class="flex items-center gap-1 <?= !empty($video['youtube_id']) ? 'text-red-400' : 'text-white/40' ?>">
                            <span class="w-2 h-2 rounded-full <?= !empty($video['youtube_id']) ? 'bg-red-400' : 'bg-white/20' ?>"></span>
                            Published
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Mobile Nav -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 glass bg-dark-900/90 border-t border-white/10">
        <div class="flex justify-around py-2">
            <a href="/creator/dashboard" class="flex flex-col items-center p-2 text-white/60">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-[10px] mt-1">Home</span>
            </a>
            <a href="/creator/record" class="flex flex-col items-center p-2 text-white/60">
                <div class="w-12 h-12 -mt-6 bg-gradient-to-br from-brand-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <span class="text-[10px] mt-1">Create</span>
            </a>
            <a href="/creator/videos" class="flex flex-col items-center p-2 text-brand-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span class="text-[10px] mt-1">Videos</span>
            </a>
        </div>
    </nav>
</body>
</html>

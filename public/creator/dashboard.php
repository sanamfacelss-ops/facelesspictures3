<?php
require_once __DIR__ . '/../../app/config/config.php';

// Wrap everything in try-catch to capture errors
try {
    debug_log('Creator dashboard loading...', 'CREATOR_DASH');
    
    if (!is_authenticated()) {
        debug_log('User not authenticated, redirecting to login', 'CREATOR_DASH');
        redirect('/login');
    }
    
    $user = auth_user();
    debug_log('User authenticated: ' . ($user['email'] ?? 'unknown'), 'CREATOR_DASH');
    
    $title = 'Creator Studio - ' . APP_NAME;

    // Get user data
    debug_log('Loading Video model...', 'CREATOR_DASH');
    $videoModel = new App\Models\Video();
    
    debug_log('Fetching user videos...', 'CREATOR_DASH');
    $userVideos = $videoModel->byUser((int)$user['id']);
    debug_log('Found ' . count($userVideos) . ' videos', 'CREATOR_DASH');
    
    debug_log('Fetching user stats...', 'CREATOR_DASH');
    $stats = $videoModel->getUserStats((int)$user['id']);
    debug_log('Stats loaded', 'CREATOR_DASH');
} catch (Exception $e) {
    debug_log('CREATOR DASHBOARD ERROR: ' . $e->getMessage(), 'CREATOR_DASH');
    debug_log('Stack trace: ' . $e->getTraceAsString(), 'CREATOR_DASH');
    log_exception($e, 'CREATOR_DASH');
    die('Dashboard error: ' . ($e->getMessage()));
}

// Get user's content categories
$userCategories = $user['content_categories'] ?? [$user['role']];
if (is_string($userCategories)) {
    $userCategories = json_decode($userCategories, true) ?? [$user['role']];
}

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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        dark: { 50: '#f8fafc', 100: '#f1f5f9', 800: '#1e293b', 900: '#0f172a', 950: '#020617' },
                        brand: { 500: '#6366f1', 600: '#4f46e5' }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .glass { backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .gradient-border { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #d946ef 100%); }
        @keyframes pulse-slow { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .pulse-slow { animation: pulse-slow 2s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-dark-950 text-white font-sans" x-data="creatorDashboard()">
    <!-- Mobile Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 glass bg-dark-900/90 border-t border-white/10">
        <div class="flex justify-around py-2">
            <a href="/creator/dashboard" class="flex flex-col items-center p-2 text-brand-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-[10px] mt-1">Home</span>
            </a>
            <a href="/creator/record" class="flex flex-col items-center p-2 text-white/60 hover:text-white">
                <div class="w-12 h-12 -mt-6 bg-gradient-to-br from-brand-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg shadow-brand-500/30">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <span class="text-[10px] mt-1">Create</span>
            </a>
            <a href="/creator/videos" class="flex flex-col items-center p-2 text-white/60 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span class="text-[10px] mt-1">Videos</span>
            </a>
        </div>
    </nav>

    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex fixed left-0 top-0 bottom-0 w-72 flex-col bg-dark-900 border-r border-white/5">
        <div class="p-6 border-b border-white/5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-purple-600 flex items-center justify-center font-bold text-lg">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 class="font-semibold text-white"><?= e($user['name']) ?></h2>
                    <p class="text-xs text-white/50">Creator Studio</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="/creator/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="/creator/record" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/60 hover:bg-white/5 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Record Video
            </a>
            <a href="/creator/videos" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/60 hover:bg-white/5 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                My Videos
            </a>
            <a href="/leaderboard" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/60 hover:bg-white/5 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Leaderboard
            </a>
        </nav>
        <div class="p-4 border-t border-white/5">
            <a href="/api/logout" onclick="event.preventDefault(); fetch('/api/logout', {method:'POST'}).then(() => window.location.href='/login');" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-white/40 hover:bg-red-500/10 hover:text-red-400 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-72 min-h-screen pb-24 lg:pb-8">
        <!-- Top Bar -->
        <header class="sticky top-0 z-40 glass bg-dark-950/80 border-b border-white/5">
            <div class="px-4 lg:px-8 py-4 flex items-center justify-between">
                <div class="lg:hidden">
                    <span class="font-bold text-lg">Creator Studio</span>
                </div>
                <div class="hidden lg:block">
                    <h1 class="text-2xl font-bold">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>! 👋</h1>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($user['is_admin']): ?>
                    <a href="/admin" class="p-2 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    <button class="p-2 rounded-lg bg-white/5 text-white/60 hover:bg-white/10 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="px-4 lg:px-8 py-6 space-y-6">
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-brand-500/20 to-purple-600/20 rounded-2xl p-4 border border-brand-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white/60 text-sm">Total Videos</span>
                        <span class="text-2xl">📹</span>
                    </div>
                    <p class="text-3xl font-bold"><?= $stats['total'] ?></p>
                </div>
                <div class="bg-gradient-to-br from-emerald-500/20 to-green-600/20 rounded-2xl p-4 border border-emerald-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white/60 text-sm">Published</span>
                        <span class="text-2xl">🚀</span>
                    </div>
                    <p class="text-3xl font-bold text-emerald-400"><?= $stats['published'] ?></p>
                </div>
                <div class="bg-gradient-to-br from-amber-500/20 to-orange-600/20 rounded-2xl p-4 border border-amber-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white/60 text-sm">Processing</span>
                        <span class="text-2xl">⏳</span>
                    </div>
                    <p class="text-3xl font-bold text-amber-400"><?= $stats['processing'] + $stats['pending'] ?></p>
                </div>
                <div class="bg-gradient-to-br from-rose-500/20 to-pink-600/20 rounded-2xl p-4 border border-rose-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white/60 text-sm">Flagged</span>
                        <span class="text-2xl">🚩</span>
                    </div>
                    <p class="text-3xl font-bold text-rose-400"><?= $stats['flagged'] ?></p>
                </div>
            </div>

            <!-- Create New CTA -->
            <div class="gradient-border p-[1px] rounded-2xl">
                <div class="bg-dark-900 rounded-2xl p-6 lg:p-8">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-2 h-2 bg-emerald-400 rounded-full pulse-slow"></span>
                                <span class="text-xs font-medium text-emerald-400 uppercase tracking-wider">Season 3 — Open for submissions</span>
                            </div>
                            <h2 class="text-2xl lg:text-3xl font-bold mb-2">Ready to create something amazing?</h2>
                            <p class="text-white/60">Choose a script or go freeform. Record your best take and let our AI handle the rest.</p>
                        </div>
                        <a href="/creator/record" class="flex-shrink-0 inline-flex items-center justify-center gap-2 px-6 py-4 bg-gradient-to-r from-brand-500 to-purple-600 rounded-xl font-semibold text-white hover:shadow-lg hover:shadow-brand-500/25 transition-all hover:-translate-y-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Start Recording
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Categories -->
            <div>
                <h3 class="text-lg font-semibold mb-4">Your Content Categories</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($userCategories as $cat): 
                        $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'gradient' => 'from-gray-500 to-gray-600'];
                        $categoryVideos = array_filter($userVideos, fn($v) => ($v['content_type'] ?? $user['role']) === $cat);
                    ?>
                    <div class="bg-dark-900 rounded-2xl p-5 border border-white/5 hover:border-white/10 transition">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $info['gradient'] ?> flex items-center justify-center text-2xl">
                                <?= $info['icon'] ?>
                            </div>
                            <div>
                                <h4 class="font-semibold"><?= $info['label'] ?></h4>
                                <p class="text-sm text-white/50"><?= count($categoryVideos) ?> video<?= count($categoryVideos) !== 1 ? 's' : '' ?></p>
                            </div>
                        </div>
                        <a href="/creator/record?category=<?= e($cat) ?>" class="block w-full text-center py-2.5 rounded-lg bg-white/5 text-sm font-medium hover:bg-white/10 transition">
                            Create <?= $info['label'] ?> Video →
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Videos -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Recent Submissions</h3>
                    <?php if (count($userVideos) > 3): ?>
                    <a href="/creator/videos" class="text-sm text-brand-500 hover:text-brand-400">View all →</a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($userVideos)): ?>
                <div class="bg-dark-900 rounded-2xl p-12 text-center border border-white/5">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
                        <svg class="w-10 h-10 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h4 class="text-xl font-semibold mb-2">No videos yet</h4>
                    <p class="text-white/50 mb-6">Start by creating your first submission</p>
                    <a href="/creator/record" class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-500 rounded-lg font-medium hover:bg-brand-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Create First Video
                    </a>
                </div>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($userVideos, 0, 5) as $video): 
                        $catInfo = $categoryInfo[$video['content_type'] ?? $user['role']] ?? ['icon' => '📹', 'gradient' => 'from-gray-500 to-gray-600'];
                        $statusConfig = [
                            'pending' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'label' => 'Processing', 'icon' => '⏳'],
                            'approved' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'label' => 'Approved', 'icon' => '✓'],
                            'rejected' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'label' => 'Rejected', 'icon' => '✗'],
                        ];
                        $aiStatusConfig = [
                            'pending' => ['bg' => 'bg-slate-500/20', 'text' => 'text-slate-400', 'label' => 'Queued'],
                            'processing' => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'label' => 'AI Reviewing'],
                            'approved' => ['bg' => 'bg-emerald-500/20', 'text' => 'text-emerald-400', 'label' => 'AI Passed'],
                            'flagged' => ['bg' => 'bg-amber-500/20', 'text' => 'text-amber-400', 'label' => 'Under Review'],
                            'rejected' => ['bg' => 'bg-rose-500/20', 'text' => 'text-rose-400', 'label' => 'AI Rejected'],
                        ];
                        $status = $statusConfig[$video['status']] ?? $statusConfig['pending'];
                        $aiStatus = $aiStatusConfig[$video['ai_status'] ?? 'pending'] ?? $aiStatusConfig['pending'];
                    ?>
                    <div class="bg-dark-900 rounded-xl p-4 border border-white/5 hover:border-white/10 transition">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-lg bg-gradient-to-br <?= $catInfo['gradient'] ?> flex items-center justify-center text-2xl flex-shrink-0">
                                <?= $catInfo['icon'] ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium truncate"><?= e($video['title']) ?></h4>
                                <p class="text-sm text-white/50"><?= e($video['season_title']) ?> • <?= date('M j', strtotime($video['created_at'])) ?></p>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium <?= $status['bg'] ?> <?= $status['text'] ?>">
                                    <?= $status['label'] ?>
                                </span>
                                <?php if (isset($video['ai_status']) && $video['ai_status'] !== 'pending'): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium <?= $aiStatus['bg'] ?> <?= $aiStatus['text'] ?>">
                                    <?= $aiStatus['label'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($video['needs_manual_review'] ?? false): ?>
                        <div class="mt-3 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20">
                            <p class="text-xs text-amber-300">🚩 This video has been flagged for manual review by our team.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- How It Works -->
            <div class="bg-dark-900 rounded-2xl p-6 border border-white/5">
                <h3 class="text-lg font-semibold mb-6">How It Works</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-brand-500/20 flex items-center justify-center">
                            <span class="text-xl">📝</span>
                        </div>
                        <h4 class="font-medium mb-1">1. Choose Mode</h4>
                        <p class="text-sm text-white/50">Pick a script or go freeform with your own content</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-purple-500/20 flex items-center justify-center">
                            <span class="text-xl">🎬</span>
                        </div>
                        <h4 class="font-medium mb-1">2. Record</h4>
                        <p class="text-sm text-white/50">Record your performance directly in browser or upload</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-500/20 flex items-center justify-center">
                            <span class="text-xl">🤖</span>
                        </div>
                        <h4 class="font-medium mb-1">3. AI Review</h4>
                        <p class="text-sm text-white/50">Our AI checks quality and content guidelines</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-rose-500/20 flex items-center justify-center">
                            <span class="text-xl">🚀</span>
                        </div>
                        <h4 class="font-medium mb-1">4. Publish</h4>
                        <p class="text-sm text-white/50">Approved videos go live on our YouTube channel</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function creatorDashboard() {
            return {
                // Add any interactive functionality here
            }
        }
    </script>
</body>
</html>

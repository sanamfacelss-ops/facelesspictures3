<?php
require_once __DIR__ . '/../app/config/config.php';

if (!is_authenticated()) redirect('/login');
$user = auth_user();
$title = 'Dashboard - ' . APP_NAME;

// Get user's content categories
$userCategories = $user['content_categories'] ?? [$user['role']];
if (is_string($userCategories)) {
    $userCategories = json_decode($userCategories, true) ?? [$user['role']];
}

// Category display info
$categoryDisplay = [
    'actor' => ['icon' => '🎭', 'color' => 'text-red-600'],
    'director' => ['icon' => '🎬', 'color' => 'text-amber-600'],
    'writer' => ['icon' => '✍️', 'color' => 'text-blue-600'],
];

// Get user's videos
$videoModel = new App\Models\Video();
$userVideos = $videoModel->byUser((int)$user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: '#F8F5F0',
                        crimson: '#D92B3A',
                        gold: '#C9943A',
                        dark: '#0D0D0D',
                        charcoal: '#141414',
                    },
                    fontFamily: {
                        display: ['Bebas Neue', 'sans-serif'],
                        body: ['DM Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
    </style>
</head>
<body class="min-h-screen bg-cream">
    <!-- Header -->
    <header class="bg-charcoal text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="flex items-center gap-2">
                    <span class="font-display text-xl">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[9px] font-semibold px-2 py-0.5 rounded-full">S3</span>
                </a>
                <div class="flex items-center gap-6">
                    <span class="text-sm text-white/60">Welcome, <?= e($user['name']) ?></span>
                    <?php if ($user['is_admin']): ?>
                        <a href="/admin" class="text-sm text-gold hover:text-gold/80">Admin</a>
                    <?php endif; ?>
                    <a href="/api/logout" class="text-sm text-white/60 hover:text-white" onclick="event.preventDefault(); fetch('/api/logout', {method:'POST'}).then(() => window.location.href='/login');">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <?php $msg = flash('success'); if ($msg): ?>
            <div class="mb-6 bg-green-100 border border-green-200 text-green-700 p-4 rounded-xl text-sm"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="mb-8">
            <h1 class="font-display text-4xl text-dark mb-2">WELCOME BACK, <?= strtoupper(e($user['name'])) ?>!</h1>
            <p class="text-dark/50">Your Season 3 dashboard</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-dark/5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-crimson/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-dark/50">My Submissions</p>
                        <p class="text-3xl font-display text-dark"><?= count($userVideos) ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-dark/5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gold/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-dark/50">Content Types</p>
                        <div class="flex items-center gap-2 mt-1">
                            <?php foreach ($userCategories as $cat): 
                                $display = $categoryDisplay[$cat] ?? ['icon' => '📹', 'color' => 'text-gray-600'];
                            ?>
                                <span class="text-xl" title="<?= ucfirst(e($cat)) ?>"><?= $display['icon'] ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-dark/5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-[#4A6CF7]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#4A6CF7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-dark/50">Member Since</p>
                        <p class="text-xl font-display text-dark"><?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Card -->
        <div class="bg-charcoal rounded-2xl p-8 text-white">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 mb-4">
                    <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                    <span class="text-[11px] font-semibold tracking-[3px] uppercase text-white/40">Season 3 — Now Open</span>
                </span>
                <h2 class="font-display text-3xl mb-4">READY TO SUBMIT YOUR ENTRY?</h2>
                <p class="text-white/50 mb-6">Upload your best work and compete with talent from across India. One video, under 3 minutes, shot on any device.</p>
                <a href="/upload" class="inline-flex items-center gap-2 bg-crimson text-white px-6 py-3 rounded-xl font-semibold hover:bg-crimson/90 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload Video
                </a>
            </div>
        </div>

        <!-- My Submissions -->
        <div class="mt-8 bg-white rounded-2xl shadow-sm border border-dark/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-dark/5">
                <h2 class="font-semibold text-dark">My Submissions</h2>
            </div>
            <?php if (empty($userVideos)): ?>
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-dark/5 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-dark/50 mb-4">No submissions yet</p>
                <a href="/upload" class="text-crimson font-semibold hover:underline">Upload your first video →</a>
            </div>
            <?php else: ?>
            <div class="divide-y divide-dark/5">
                <?php foreach ($userVideos as $video): 
                    $catDisplay = $categoryDisplay[$video['content_type'] ?? $user['role']] ?? ['icon' => '📹', 'color' => 'text-gray-600'];
                    $statusColors = [
                        'pending' => 'bg-amber-100 text-amber-700',
                        'approved' => 'bg-green-100 text-green-700',
                        'rejected' => 'bg-red-100 text-red-700',
                    ];
                    $statusColor = $statusColors[$video['status']] ?? 'bg-gray-100 text-gray-700';
                ?>
                <div class="px-6 py-4 flex items-center gap-4">
                    <div class="text-2xl"><?= $catDisplay['icon'] ?></div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-dark truncate"><?= e($video['title']) ?></h3>
                        <p class="text-sm text-dark/50"><?= e($video['season_title']) ?> • <?= ucfirst(e($video['content_type'] ?? $user['role'])) ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $statusColor ?>">
                        <?= ucfirst(e($video['status'])) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

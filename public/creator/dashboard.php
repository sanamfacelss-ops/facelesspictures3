<?php
require_once __DIR__ . '/../../app/config/config.php';

try {
    if (!is_authenticated()) redirect('/login');
    $user = auth_user();
    $title = 'Creator Studio - ' . APP_NAME;
    
    $videoModel = new App\Models\Video();
    $userVideos = $videoModel->byUser((int)$user['id']);
    $stats = $videoModel->getUserStats((int)$user['id']);
    
    $userCategories = $user['content_categories'] ?? [$user['role']];
    if (is_string($userCategories)) {
        $userCategories = json_decode($userCategories, true) ?? [$user['role']];
    }
} catch (Exception $e) {
    log_exception($e, 'CREATOR_DASH');
    die('Error loading dashboard');
}

$categoryInfo = [
    'actor' => ['icon' => '🎭', 'label' => 'Acting', 'color' => '#E11D48', 'bg' => '#FFF1F2', 'desc' => 'Perform scripts on camera'],
    'director' => ['icon' => '🎬', 'label' => 'Directing', 'color' => '#D97706', 'bg' => '#FFFBEB', 'desc' => 'Direct and explain scenes'],
    'writer' => ['icon' => '✍️', 'label' => 'Writing', 'color' => '#2563EB', 'bg' => '#EFF6FF', 'desc' => 'Continue stories creatively'],
];

// Get script counts for each category
$scriptModel = new App\Models\Script();
$scriptCounts = [];
foreach ($userCategories as $cat) {
    $scripts = $scriptModel->byCategory($cat);
    $scriptCounts[$cat] = count($scripts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        
        /* Skeleton loading animation */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton 1.5s ease-in-out infinite;
        }
        @keyframes skeleton {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Smooth transitions */
        .card {
            transition: all 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen" x-data="{ mobileMenu: false, loading: false }">

    <!-- Top Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gradient-to-br from-rose-500 to-orange-500 rounded-lg flex items-center justify-center">
                            <span class="text-white text-sm font-bold">FP</span>
                        </div>
                        <span class="font-semibold text-gray-900 hidden sm:block">Creator Studio</span>
                    </a>
                </div>
                
                <!-- Desktop Nav -->
                <div class="hidden md:flex items-center gap-1">
                    <a href="/creator/dashboard" class="px-4 py-2 text-sm font-medium text-gray-900 bg-gray-100 rounded-lg">Dashboard</a>
                    <a href="/creator/record" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">Create</a>
                    <a href="/creator/videos" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">My Videos</a>
                    <a href="/leaderboard" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">Leaderboard</a>
                </div>
                
                <div class="flex items-center gap-3">
                    <?php if ($user['is_admin']): ?>
                    <a href="/admin" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Admin">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </a>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-2 pl-3 border-l border-gray-200">
                        <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= e(explode(' ', $user['name'])[0]) ?></span>
                        <a href="#" onclick="event.preventDefault(); fetch('/api/logout', {method:'POST'}).then(() => window.location.href='/login');" 
                           class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition" title="Logout">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </a>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div x-show="mobileMenu" x-cloak class="md:hidden border-t border-gray-200 bg-white">
            <div class="px-4 py-3 space-y-1">
                <a href="/creator/dashboard" class="block px-4 py-2 text-sm font-medium text-gray-900 bg-gray-100 rounded-lg">Dashboard</a>
                <a href="/creator/record" class="block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">Create</a>
                <a href="/creator/videos" class="block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">My Videos</a>
                <a href="/leaderboard" class="block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">Leaderboard</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
            <p class="text-gray-500 mt-1">Here's what's happening with your content</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Videos</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Published</p>
                        <p class="text-3xl font-bold text-emerald-600"><?= $stats['published'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Processing</p>
                        <p class="text-3xl font-bold text-amber-600"><?= $stats['processing'] + $stats['pending'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Needs Review</p>
                        <p class="text-3xl font-bold text-rose-600"><?= $stats['flagged'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-rose-50 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Banner -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 sm:p-8 mb-8 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"30\" height=\"30\" viewBox=\"0 0 30 30\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cpath d=\"M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z\" fill=\"rgba(255,255,255,0.05)\"%3E%3C/path%3E%3C/svg%3E')] opacity-50"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                        <span class="text-xs font-medium text-emerald-400 uppercase tracking-wider">Season 3 Open</span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">Ready to create something amazing?</h2>
                    <p class="text-gray-400 text-sm sm:text-base">Choose a script or go freeform. Our AI handles the quality check.</p>
                </div>
                <a href="/creator/record" class="inline-flex items-center justify-center gap-2 bg-white text-gray-900 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 transition shadow-lg flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Video
                </a>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Content Categories -->
            <div class="lg:col-span-1">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Modes</h3>
                <div class="space-y-3">
                    <?php foreach ($userCategories as $cat): 
                        $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'color' => '#6B7280', 'bg' => '#F3F4F6', 'desc' => ''];
                        $catVideos = array_filter($userVideos, fn($v) => ($v['content_type'] ?? '') === $cat);
                        $availableScripts = $scriptCounts[$cat] ?? 0;
                    ?>
                    <a href="/creator/record?tab=<?= e($cat) ?>" class="block bg-white rounded-xl p-4 border border-gray-100 shadow-sm card">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl" style="background: <?= $info['bg'] ?>">
                                <?= $info['icon'] ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900"><?= $info['label'] ?></h4>
                                <p class="text-xs text-gray-500 truncate"><?= $info['desc'] ?? '' ?></p>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs text-gray-400"><?= count($catVideos) ?> video<?= count($catVideos) !== 1 ? 's' : '' ?></span>
                                    <span class="text-xs" style="color: <?= $info['color'] ?>"><?= $availableScripts ?> script<?= $availableScripts !== 1 ? 's' : '' ?> available</span>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                </div>
                
                <!-- Quick Tips -->
                <div class="mt-6 bg-amber-50 border border-amber-100 rounded-xl p-4">
                    <h4 class="font-medium text-amber-900 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        Quick Tips
                    </h4>
                    <ul class="text-sm text-amber-800 space-y-1">
                        <li>• Keep videos under 3 minutes</li>
                        <li>• Good lighting makes a difference</li>
                        <li>• Clear audio is essential</li>
                    </ul>
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Submissions</h3>
                    <?php if (count($userVideos) > 5): ?>
                    <a href="/creator/videos" class="text-sm font-medium text-rose-600 hover:text-rose-700">View all →</a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($userVideos)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">No videos yet</h4>
                    <p class="text-gray-500 mb-6">Create your first video to get started</p>
                    <a href="/creator/record" class="inline-flex items-center gap-2 bg-gray-900 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-gray-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Create First Video
                    </a>
                </div>
                <?php else: ?>
                <!-- Video List -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="divide-y divide-gray-100">
                        <?php foreach (array_slice($userVideos, 0, 5) as $video): 
                            $catInfo = $categoryInfo[$video['content_type'] ?? 'actor'] ?? ['icon' => '📹', 'color' => '#6B7280', 'bg' => '#F3F4F6'];
                            
                            $statusStyles = [
                                'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Processing'],
                                'approved' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Approved'],
                                'rejected' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'label' => 'Rejected'],
                            ];
                            $status = $statusStyles[$video['status']] ?? $statusStyles['pending'];
                        ?>
                        <div class="p-4 hover:bg-gray-50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl flex-shrink-0" style="background: <?= $catInfo['bg'] ?>">
                                    <?= $catInfo['icon'] ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 truncate"><?= e($video['title']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= e($video['season_title']) ?> • <?= date('M j, Y', strtotime($video['created_at'])) ?></p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $status['bg'] ?> <?= $status['text'] ?>">
                                        <?= $status['label'] ?>
                                    </span>
                                    <?php if (!empty($video['youtube_id'])): ?>
                                    <a href="https://youtube.com/watch?v=<?= e($video['youtube_id']) ?>" target="_blank" class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition" title="Watch on YouTube">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($video['needs_manual_review'] ?? false): ?>
                            <div class="mt-3 p-2.5 bg-amber-50 border border-amber-100 rounded-lg">
                                <p class="text-xs text-amber-700 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    Under manual review by our team
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Mobile Bottom Nav -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
        <div class="flex justify-around py-2">
            <a href="/creator/dashboard" class="flex flex-col items-center py-2 px-3 text-rose-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="/creator/record" class="flex flex-col items-center py-2 px-3 text-gray-500">
                <div class="w-10 h-10 -mt-5 bg-gray-900 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <span class="text-xs mt-1">Create</span>
            </a>
            <a href="/creator/videos" class="flex flex-col items-center py-2 px-3 text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span class="text-xs mt-1">Videos</span>
            </a>
        </div>
    </nav>
    
    <div class="h-20 md:hidden"></div>
</body>
</html>

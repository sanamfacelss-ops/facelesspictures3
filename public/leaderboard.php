<?php
require_once __DIR__ . '/../app/config/config.php';

$title = 'Leaderboard - ' . APP_NAME;
$seasonModel = new App\Models\Season();
$seasons = $seasonModel->all();
$user = auth_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .podium-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
        .podium-2 { background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%); }
        .podium-3 { background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gradient-to-br from-rose-500 to-orange-500 rounded-lg flex items-center justify-center">
                            <span class="text-white text-sm font-bold">FP</span>
                        </div>
                        <span class="font-semibold text-gray-900 hidden sm:block">Faceless Pitcher</span>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center gap-1">
                    <a href="/leaderboard" class="px-4 py-2 text-sm font-medium text-gray-900 bg-gray-100 rounded-lg">Leaderboard</a>
                    <?php if ($user): ?>
                    <a href="/creator/dashboard" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">Dashboard</a>
                    <a href="/creator/record" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">Create</a>
                    <?php else: ?>
                    <a href="/login" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition">Login</a>
                    <a href="/register" class="px-4 py-2 text-sm font-medium bg-rose-600 text-white hover:bg-rose-700 rounded-lg transition">Join Now</a>
                    <?php endif; ?>
                </div>
                
                <?php if ($user): ?>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= e(explode(' ', $user['name'])[0]) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="leaderboard()" x-init="load()">

        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-2">🏆 Leaderboard</h1>
            <p class="text-gray-500">Top performers ranked by YouTube engagement</p>
        </div>

        <!-- Season Filter -->
        <div class="flex justify-center mb-8">
            <div class="inline-flex bg-white rounded-xl p-1 shadow-sm border border-gray-200">
                <button @click="seasonId = ''; load()" 
                        :class="seasonId === '' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition">
                    All Time
                </button>
                <?php foreach ($seasons as $s): ?>
                <button @click="seasonId = '<?= $s['id'] ?>'; load()" 
                        :class="seasonId === '<?= $s['id'] ?>' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition">
                    <?= e($s['title']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="flex justify-center py-20">
            <div class="w-10 h-10 border-4 border-gray-200 border-t-rose-500 rounded-full animate-spin"></div>
        </div>

        <!-- Empty State -->
        <div x-show="!loading && rows.length === 0" x-cloak class="text-center py-20">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No rankings yet</h3>
            <p class="text-gray-500 mb-6">Be the first to upload a video and get ranked!</p>
            <a href="/creator/record" class="inline-flex items-center gap-2 bg-rose-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-rose-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Video
            </a>
        </div>

        <!-- Top 3 Podium (Desktop) -->
        <div x-show="!loading && rows.length >= 3" x-cloak class="hidden md:flex justify-center items-end gap-4 mb-10">
            <!-- 2nd Place -->
            <div class="text-center">
                <div class="w-20 h-20 podium-2 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                    <span class="text-3xl font-bold text-white">2</span>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 w-48">
                    <p class="font-semibold text-gray-900 truncate" x-text="rows[1]?.video_title || ''"></p>
                    <p class="text-sm text-gray-500" x-text="rows[1]?.user_name || ''"></p>
                    <p class="text-lg font-bold text-gray-900 mt-2" x-text="Number(rows[1]?.score || 0).toLocaleString() + ' pts'"></p>
                </div>
            </div>
            
            <!-- 1st Place -->
            <div class="text-center -mt-6">
                <div class="w-24 h-24 podium-1 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg ring-4 ring-yellow-200">
                    <span class="text-4xl font-bold text-white">1</span>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-md border-2 border-yellow-200 w-56">
                    <span class="text-2xl">👑</span>
                    <p class="font-bold text-gray-900 truncate text-lg" x-text="rows[0]?.video_title || ''"></p>
                    <p class="text-sm text-gray-500" x-text="rows[0]?.user_name || ''"></p>
                    <p class="text-xl font-bold text-yellow-600 mt-2" x-text="Number(rows[0]?.score || 0).toLocaleString() + ' pts'"></p>
                </div>
            </div>
            
            <!-- 3rd Place -->
            <div class="text-center">
                <div class="w-20 h-20 podium-3 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                    <span class="text-3xl font-bold text-white">3</span>
                </div>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 w-48">
                    <p class="font-semibold text-gray-900 truncate" x-text="rows[2]?.video_title || ''"></p>
                    <p class="text-sm text-gray-500" x-text="rows[2]?.user_name || ''"></p>
                    <p class="text-lg font-bold text-gray-900 mt-2" x-text="Number(rows[2]?.score || 0).toLocaleString() + ' pts'"></p>
                </div>
            </div>
        </div>

        <!-- Rankings List -->
        <div x-show="!loading && rows.length > 0" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Mobile Cards -->
            <div class="md:hidden divide-y divide-gray-100">
                <template x-for="(row, index) in rows" :key="row.id">
                    <div class="p-4">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <div :class="{
                                    'podium-1 text-white': index === 0,
                                    'podium-2 text-white': index === 1,
                                    'podium-3 text-white': index === 2,
                                    'bg-gray-100 text-gray-600': index > 2
                                }" class="w-10 h-10 rounded-full flex items-center justify-center font-bold">
                                    <span x-text="index + 1"></span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <a :href="row.youtube_id ? 'https://youtube.com/watch?v=' + row.youtube_id : '#'" 
                                   target="_blank"
                                   class="font-semibold text-gray-900 hover:text-rose-600 truncate block" 
                                   x-text="row.video_title"></a>
                                <p class="text-sm text-gray-500" x-text="row.user_name"></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900" x-text="Number(row.score).toLocaleString()"></p>
                                <p class="text-xs text-gray-500">points</p>
                            </div>
                        </div>
                        <div class="flex gap-4 mt-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span x-text="Number(row.views).toLocaleString()"></span>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span x-text="Number(row.likes).toLocaleString()"></span>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                <span x-text="Number(row.comments).toLocaleString()"></span>
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-16">Rank</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Video</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Creator</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Season</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Views</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Likes</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(row, index) in rows" :key="row.id">
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div :class="{
                                        'podium-1 text-white': index === 0,
                                        'podium-2 text-white': index === 1,
                                        'podium-3 text-white': index === 2,
                                        'bg-gray-100 text-gray-600': index > 2
                                    }" class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm">
                                        <span x-text="index + 1"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <a :href="row.youtube_id ? 'https://youtube.com/watch?v=' + row.youtube_id : '#'" 
                                       target="_blank"
                                       class="font-medium text-gray-900 hover:text-rose-600 transition flex items-center gap-2">
                                        <span x-text="row.video_title"></span>
                                        <svg x-show="row.youtube_id" class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 bg-gradient-to-br from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-medium">
                                            <span x-text="row.user_name?.charAt(0)?.toUpperCase() || '?'"></span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="row.user_name"></p>
                                            <p class="text-xs text-gray-500 capitalize" x-text="row.user_role"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium" x-text="row.season_title"></span>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-600" x-text="Number(row.views).toLocaleString()"></td>
                                <td class="px-6 py-4 text-right text-gray-600" x-text="Number(row.likes).toLocaleString()"></td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-bold text-gray-900" x-text="Number(row.score).toLocaleString()"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function leaderboard() {
            return {
                seasonId: '',
                rows: [],
                loading: true,
                
                load() {
                    this.loading = true;
                    const params = this.seasonId ? '?season_id=' + this.seasonId : '';
                    fetch('/api/leaderboard' + params)
                        .then(r => r.json())
                        .then(data => {
                            this.rows = data.data || [];
                            this.loading = false;
                        })
                        .catch(() => {
                            this.rows = [];
                            this.loading = false;
                        });
                }
            };
        }
    </script>
</body>
</html>

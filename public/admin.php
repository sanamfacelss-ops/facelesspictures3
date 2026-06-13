<?php
require_once __DIR__ . '/../app/config/config.php';

if (!is_admin()) redirect('/dashboard');

$videoModel = new App\Models\Video();
$pending = $videoModel->pending();
$flagged = $videoModel->needsManualReview();
$title = 'Admin Dashboard — ' . APP_NAME;

// Handle debug toggle (secure - admin only, POST with CSRF)
$debugMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_debug'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $envFile = BASE_PATH . '/.env';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            $currentDebug = FP3_DEBUG;
            $newDebug = $currentDebug ? 'false' : 'true';
            
            if (preg_match('/^FP3_DEBUG=.*/m', $content)) {
                $content = preg_replace('/^FP3_DEBUG=.*/m', "FP3_DEBUG=$newDebug", $content);
            } else {
                $content .= "\nFP3_DEBUG=$newDebug";
            }
            
            file_put_contents($envFile, $content);
            $debugMessage = $newDebug === 'true' ? 'Debug mode ENABLED' : 'Debug mode DISABLED';
            
            // Reload the config
            header('Location: /admin?debug_updated=1');
            exit;
        }
    }
}

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $logFiles = glob(LOG_PATH . '/*.log');
        foreach ($logFiles as $file) {
            unlink($file);
        }
        header('Location: /admin?logs_cleared=1');
        exit;
    }
}

// Get log files info
$logFiles = [];
if (is_dir(LOG_PATH)) {
    foreach (glob(LOG_PATH . '/*.log') as $file) {
        $logFiles[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'modified' => filemtime($file)
        ];
    }
    usort($logFiles, fn($a, $b) => $b['modified'] - $a['modified']);
}

// Read debug log content (last 100 lines)
$debugLogContent = '';
$debugLogFile = LOG_PATH . '/debug.log';
if (file_exists($debugLogFile)) {
    $lines = file($debugLogFile);
    $debugLogContent = implode('', array_slice($lines, -100));
}

$errorLogContent = '';
$errorLogFile = LOG_PATH . '/error.log';
if (file_exists($errorLogFile)) {
    $lines = file($errorLogFile);
    $errorLogContent = implode('', array_slice($lines, -50));
}
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        [x-cloak] { display: none !important; }
        .log-viewer { font-family: 'Monaco', 'Menlo', monospace; font-size: 11px; }
    </style>
</head>
<body class="bg-cream min-h-screen">
    <!-- Admin Nav -->
    <nav class="bg-charcoal text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-4">
                    <a href="/" class="flex items-center gap-2">
                        <span class="font-display text-[18px]">FACELESS PITCHER</span>
                        <span class="bg-crimson text-[9px] px-2 py-0.5 rounded-full font-semibold">ADMIN</span>
                    </a>
                </div>
                <div class="flex items-center gap-6">
                    <a href="/dashboard" class="text-[13px] text-white/60 hover:text-white transition">Dashboard</a>
                    <a href="/leaderboard" class="text-[13px] text-white/60 hover:text-white transition">Leaderboard</a>
                    <span class="text-[13px] text-white/40">Hi, <?= e(auth_user()['name']) ?></span>
                    <form action="/api/logout" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button class="text-[13px] text-white/60 hover:text-white transition">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8" x-data="adminPanel()">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-display text-[36px] text-dark">ADMIN DASHBOARD</h1>
                <p class="text-[14px] text-dark/50">Manage videos, users, and system settings</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if (isset($_GET['debug_updated'])): ?>
                <span class="text-[12px] bg-green-100 text-green-700 px-3 py-1 rounded-full">Debug setting updated!</span>
                <?php endif; ?>
                <?php if (isset($_GET['logs_cleared'])): ?>
                <span class="text-[12px] bg-green-100 text-green-700 px-3 py-1 rounded-full">Logs cleared!</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="flex gap-2 mb-6 border-b border-dark/10">
            <button @click="activeTab = 'moderation'" 
                    :class="activeTab === 'moderation' ? 'border-crimson text-crimson' : 'border-transparent text-dark/50 hover:text-dark'"
                    class="px-4 py-3 text-[14px] font-medium border-b-2 -mb-px transition">
                Moderation <span class="bg-crimson/10 text-crimson text-[11px] px-2 py-0.5 rounded-full ml-1"><?= count($pending) ?></span>
            </button>
            <button @click="activeTab = 'debug'" 
                    :class="activeTab === 'debug' ? 'border-crimson text-crimson' : 'border-transparent text-dark/50 hover:text-dark'"
                    class="px-4 py-3 text-[14px] font-medium border-b-2 -mb-px transition">
                Debug & Logs
            </button>
            <button @click="activeTab = 'system'" 
                    :class="activeTab === 'system' ? 'border-crimson text-crimson' : 'border-transparent text-dark/50 hover:text-dark'"
                    class="px-4 py-3 text-[14px] font-medium border-b-2 -mb-px transition">
                System Info
            </button>
        </div>

        <!-- MODERATION TAB -->
        <div x-show="activeTab === 'moderation'" x-cloak>
            <!-- AI Flagged Videos (needs manual review) -->
            <?php if (!empty($flagged)): ?>
            <div class="bg-amber-50 rounded-2xl shadow-sm border border-amber-200 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-amber-200 flex items-center justify-between">
                    <h2 class="font-semibold text-amber-800 flex items-center gap-2">
                        🚩 AI Flagged - Manual Review Required
                        <span class="bg-amber-500 text-white text-[11px] px-2 py-0.5 rounded-full"><?= count($flagged) ?></span>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-amber-100/50 text-amber-800">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">ID</th>
                                <th class="px-6 py-3 text-left font-medium">Title</th>
                                <th class="px-6 py-3 text-left font-medium">User</th>
                                <th class="px-6 py-3 text-left font-medium">AI Score</th>
                                <th class="px-6 py-3 text-left font-medium">Reason</th>
                                <th class="px-6 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            <?php foreach ($flagged as $v): 
                                $aiFeedback = is_string($v['ai_feedback'] ?? '') ? json_decode($v['ai_feedback'], true) : ($v['ai_feedback'] ?? []);
                            ?>
                            <tr class="bg-white hover:bg-amber-50 transition">
                                <td class="px-6 py-4 text-dark/50"><?= $v['id'] ?></td>
                                <td class="px-6 py-4 font-medium text-dark"><?= e($v['title']) ?></td>
                                <td class="px-6 py-4 text-dark/70"><?= e($v['user_name']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?= ($v['ai_score'] ?? 0) >= 60 ? 'bg-emerald-100 text-emerald-700' : (($v['ai_score'] ?? 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') ?>">
                                        <?= $v['ai_score'] ?? 'N/A' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-dark/70 max-w-xs truncate" title="<?= e($aiFeedback['summary'] ?? 'Flagged by AI') ?>">
                                    <?= e($aiFeedback['summary'] ?? 'Flagged by AI') ?>
                                </td>
                                <td class="px-6 py-4 space-x-2">
                                    <button @click="approve(<?= $v['id'] ?>)" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-medium hover:bg-green-700 transition">Approve</button>
                                    <button @click="reject(<?= $v['id'] ?>)" class="bg-crimson text-white px-3 py-1.5 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Regular Pending Videos -->
            <div class="bg-white rounded-2xl shadow-sm border border-dark/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-dark/5 flex items-center justify-between">
                    <h2 class="font-semibold text-dark">Pending Videos</h2>
                    <button @click="fetchPending()" class="text-[13px] text-crimson hover:underline">Refresh</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-cream/50 text-dark/60">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">ID</th>
                                <th class="px-6 py-3 text-left font-medium">Title</th>
                                <th class="px-6 py-3 text-left font-medium">User</th>
                                <th class="px-6 py-3 text-left font-medium">Season</th>
                                <th class="px-6 py-3 text-left font-medium">Date</th>
                                <th class="px-6 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-dark/5">
                            <template x-for="v in pendingVideos" :key="v.id">
                                <tr class="hover:bg-cream/30 transition">
                                    <td class="px-6 py-4 text-dark/50" x-text="v.id"></td>
                                    <td class="px-6 py-4 font-medium text-dark" x-text="v.title"></td>
                                    <td class="px-6 py-4 text-dark/70" x-text="v.user_name"></td>
                                    <td class="px-6 py-4 text-dark/70" x-text="v.season_title"></td>
                                    <td class="px-6 py-4 text-dark/50" x-text="new Date(v.created_at).toLocaleDateString()"></td>
                                    <td class="px-6 py-4 space-x-2">
                                        <button @click="approve(v.id)" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-[12px] font-medium hover:bg-green-700 transition">Approve</button>
                                        <button @click="reject(v.id)" class="bg-crimson text-white px-3 py-1.5 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="pendingVideos.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-dark/40">No pending videos to review.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DEBUG TAB -->
        <div x-show="activeTab === 'debug'" x-cloak>
            <div class="grid lg:grid-cols-3 gap-6">
                
                <!-- Debug Controls -->
                <div class="bg-white rounded-2xl shadow-sm border border-dark/5 p-6">
                    <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Debug Mode
                    </h3>
                    
                    <div class="flex items-center justify-between p-4 bg-cream rounded-xl mb-4">
                        <div>
                            <p class="font-medium text-dark">Debug Logging</p>
                            <p class="text-[12px] text-dark/50">Logs detailed info to /logs/debug.log</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/40' ?>">
                                <?= FP3_DEBUG ? 'ENABLED' : 'DISABLED' ?>
                            </span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="toggle_debug" value="1">
                                <button type="submit" class="relative w-12 h-6 rounded-full transition-colors <?= FP3_DEBUG ? 'bg-green-500' : 'bg-dark/20' ?>">
                                    <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= FP3_DEBUG ? 'translate-x-6' : '' ?>"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                        <p class="text-[12px] text-amber-800">
                            <strong>⚠️ Security Note:</strong> Debug mode logs sensitive data. Only enable when troubleshooting. Disable in production.
                        </p>
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Clear all log files?')">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="clear_logs" value="1">
                        <button type="submit" class="w-full bg-crimson/10 text-crimson font-medium py-2.5 rounded-xl hover:bg-crimson/20 transition text-[13px]">
                            Clear All Logs
                        </button>
                    </form>
                </div>

                <!-- Log Files List -->
                <div class="bg-white rounded-2xl shadow-sm border border-dark/5 p-6">
                    <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Log Files
                    </h3>
                    
                    <?php if (empty($logFiles)): ?>
                    <p class="text-[13px] text-dark/40 text-center py-8">No log files yet.</p>
                    <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($logFiles as $log): ?>
                        <div class="flex items-center justify-between p-3 bg-cream rounded-lg">
                            <div>
                                <p class="font-medium text-dark text-[13px]"><?= e($log['name']) ?></p>
                                <p class="text-[11px] text-dark/40"><?= date('M j, H:i', $log['modified']) ?></p>
                            </div>
                            <span class="text-[11px] text-dark/50 bg-white px-2 py-1 rounded">
                                <?= number_format($log['size'] / 1024, 1) ?> KB
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-dark/5 p-6">
                    <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Environment
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between text-[13px]">
                            <span class="text-dark/50">Environment</span>
                            <span class="font-medium text-dark"><?= APP_ENV ?></span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-dark/50">PHP Version</span>
                            <span class="font-medium text-dark"><?= PHP_VERSION ?></span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-dark/50">Debug Mode</span>
                            <span class="font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/40' ?>"><?= FP3_DEBUG ? 'ON' : 'OFF' ?></span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-dark/50">Memory Limit</span>
                            <span class="font-medium text-dark"><?= ini_get('memory_limit') ?></span>
                        </div>
                        <div class="flex justify-between text-[13px]">
                            <span class="text-dark/50">Max Upload</span>
                            <span class="font-medium text-dark"><?= ini_get('upload_max_filesize') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Viewers -->
            <?php if ($debugLogContent || $errorLogContent): ?>
            <div class="grid lg:grid-cols-2 gap-6 mt-6">
                <?php if ($debugLogContent): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-dark/5 overflow-hidden">
                    <div class="px-6 py-4 border-b border-dark/5 flex items-center justify-between bg-blue-50">
                        <h3 class="font-semibold text-dark flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                            Debug Log (last 100 lines)
                        </h3>
                    </div>
                    <div class="p-4 bg-charcoal max-h-[400px] overflow-auto">
                        <pre class="log-viewer text-green-400 whitespace-pre-wrap"><?= e($debugLogContent) ?></pre>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($errorLogContent): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-dark/5 overflow-hidden">
                    <div class="px-6 py-4 border-b border-dark/5 flex items-center justify-between bg-red-50">
                        <h3 class="font-semibold text-dark flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                            Error Log (last 50 lines)
                        </h3>
                    </div>
                    <div class="p-4 bg-charcoal max-h-[400px] overflow-auto">
                        <pre class="log-viewer text-red-400 whitespace-pre-wrap"><?= e($errorLogContent) ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- SYSTEM TAB -->
        <div x-show="activeTab === 'system'" x-cloak>
            <div class="bg-white rounded-2xl shadow-sm border border-dark/5 p-6">
                <h3 class="font-semibold text-dark mb-6">System Information</h3>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">App Name</p>
                        <p class="font-semibold text-dark"><?= APP_NAME ?></p>
                    </div>
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">App URL</p>
                        <p class="font-semibold text-dark text-[13px]"><?= APP_URL ?></p>
                    </div>
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">Environment</p>
                        <p class="font-semibold <?= APP_ENV === 'production' ? 'text-green-600' : 'text-gold' ?>"><?= strtoupper(APP_ENV) ?></p>
                    </div>
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">Server Time</p>
                        <p class="font-semibold text-dark"><?= date('Y-m-d H:i:s') ?></p>
                    </div>
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">PHP Version</p>
                        <p class="font-semibold text-dark"><?= PHP_VERSION ?></p>
                    </div>
                    <div class="p-4 bg-cream rounded-xl">
                        <p class="text-[12px] text-dark/50 uppercase tracking-wider mb-1">Server Software</p>
                        <p class="font-semibold text-dark text-[12px]"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Approve/Reject -->
        <div x-show="modalOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="modalOpen = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
                <h3 class="font-display text-[24px] text-dark mb-2" x-text="modalTitle"></h3>
                <p class="text-[13px] text-dark/50 mb-4">Provide a reason for this action.</p>
                <textarea x-model="modalReason" rows="3" class="w-full border-2 border-dark/10 rounded-xl px-4 py-3 text-[14px] focus:outline-none focus:border-crimson transition mb-4" placeholder="Reason..."></textarea>
                <div class="flex justify-end gap-3">
                    <button @click="modalOpen = false" class="px-5 py-2.5 text-[13px] text-dark/60 hover:text-dark transition">Cancel</button>
                    <button @click="confirmAction()" class="px-5 py-2.5 text-[13px] bg-crimson text-white rounded-xl hover:bg-crimson/90 transition font-medium" x-text="modalAction === 'approve' ? 'Approve' : 'Reject'"></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function adminPanel() {
        return {
            activeTab: 'moderation',
            pendingVideos: <?= json_encode($pending) ?>,
            modalOpen: false,
            modalAction: '',
            modalVideoId: null,
            modalTitle: '',
            modalReason: '',
            
            fetchPending() {
                fetch('/api/moderation/pending')
                    .then(r => r.json())
                    .then(data => this.pendingVideos = data)
                    .catch(() => alert('Failed to load'));
            },
            
            approve(id) {
                this.modalAction = 'approve';
                this.modalVideoId = id;
                this.modalTitle = 'Approve Video';
                this.modalReason = 'Approved by admin';
                this.modalOpen = true;
            },
            
            reject(id) {
                this.modalAction = 'reject';
                this.modalVideoId = id;
                this.modalTitle = 'Reject Video';
                this.modalReason = '';
                this.modalOpen = true;
            },
            
            confirmAction() {
                const url = this.modalAction === 'approve'
                    ? '/api/moderation/approve/' + this.modalVideoId
                    : '/api/moderation/reject/' + this.modalVideoId;
                const formData = new FormData();
                formData.append('reason', this.modalReason);
                formData.append('csrf_token', '<?= csrf_token() ?>');
                fetch(url, { method: 'POST', body: formData })
                    .then(r => {
                        if (!r.ok) throw new Error('Failed');
                        this.modalOpen = false;
                        this.fetchPending();
                    })
                    .catch(() => alert('Action failed'));
            }
        };
    }
    </script>
</body>
</html>

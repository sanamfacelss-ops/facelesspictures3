<?php
require_once __DIR__ . '/../app/config/config.php';

if (!is_admin()) redirect('/dashboard');

// Load models and data
$videoModel = new App\Models\Video();
$userModel = new App\Models\User();
$seasonModel = new App\Models\Season();
$scriptModel = new App\Models\Script();
$settingsModel = new App\Models\Settings();

$pending = $videoModel->pending();
$flagged = $videoModel->needsManualReview();
$allUsers = $userModel->all();
$allSeasons = $seasonModel->all();
$allScripts = $scriptModel->all();
$activeSeason = $seasonModel->getActive();

// Load guides (with fallbacks if table doesn't exist yet)
$guides = ['actor' => '', 'director' => '', 'writer' => ''];
try {
    $guides = array_merge($guides, $settingsModel->getGuides());
} catch (\Exception $e) {
    // Table might not exist yet - use defaults
    $guides = [
        'actor' => "As an actor, you'll perform dramatic monologues, character scenes, and emotional pieces.\n\n**Tips:**\n• Find a quiet space with good lighting\n• Practice your script before recording\n• Focus on emotion and delivery",
        'director' => "As a director, you'll pitch your creative vision for scenes and explain how you'd bring stories to life.\n\n**Tips:**\n• Be specific about your creative vision\n• Explain your choices with confidence\n• Keep pitches under 2 minutes",
        'writer' => "As a writer, you'll present original work, pitch story concepts, or perform script readings.\n\n**Tips:**\n• Read your work with conviction\n• Vary your pacing to maintain interest\n• Let your unique voice come through"
    ];
}

// Load AI settings and provider status
$aiSettings = [];
$aiProviders = [];
try {
    $aiSettings = $settingsModel->getAISettings();
    
    // Get provider status
    $moderationService = new App\Services\ContentModerationService();
    $aiProviders = $moderationService->getProviderStatus();
    
    // Check transcription service
    $transcriptionService = new App\Services\TranscriptionService();
    $aiProviders['groq'] = $transcriptionService->isAvailable();
} catch (\Exception $e) {
    // Default values if services not available
    $aiSettings = [
        'ai_text_providers' => 'azure,openai,local',
        'ai_image_providers' => 'azure,sightengine,api4ai',
        'ai_transcription_provider' => 'groq',
        'ai_processing_enabled' => '1',
        'ai_approve_threshold' => '70',
        'ai_flag_threshold' => '40',
        'ai_auto_approve' => '1',
    ];
    $aiProviders = ['azure' => false, 'openai' => false, 'sightengine' => false, 'rapidapi' => false, 'groq' => false];
}

// Get all videos for video management tab
$db = App\Config\Database::getConnection();
$stmt = $db->query(
    "SELECT v.*, u.name as user_name, u.role as user_role, s.title as season_title
     FROM videos v
     JOIN users u ON v.user_id = u.id
     JOIN seasons s ON v.season_id = s.id
     ORDER BY v.created_at DESC"
);
$allVideos = $stmt->fetchAll();

// Count stats
$totalUsers = count($allUsers);
$pendingCount = count($pending);
$flaggedCount = count($flagged);
$processingCount = count(array_filter($allVideos, fn($v) => ($v['ai_status'] ?? '') === 'processing'));
$approvedCount = count(array_filter($allVideos, fn($v) => $v['status'] === 'approved'));
$publishedCount = count(array_filter($allVideos, fn($v) => !empty($v['youtube_id'])));

$title = 'Admin Dashboard — ' . APP_NAME;


// Handle debug toggle
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

// Read log content
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
        .sidebar-link { transition: all 0.15s ease; }
        .sidebar-link:hover { background: #FEF2F2; }
        .sidebar-link.active { background: #FEF2F2; border-left: 3px solid #D92B3A; color: #D92B3A; }
        .stat-card { background: #F1EFE8; }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F8F5F0; }
        ::-webkit-scrollbar-thumb { background: #D92B3A33; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #D92B3A66; }
    </style>
</head>
<body class="bg-cream min-h-screen" x-data="adminDashboard()" x-init="init()">

    <!-- Mobile Bottom Tab Bar -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-dark/10 z-50 md:hidden">
        <div class="flex justify-around items-center h-16">
            <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="text-[10px] font-medium">Overview</span>
            </button>
            <button @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span class="text-[10px] font-medium">Videos</span>
            </button>
            <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                <span class="text-[10px] font-medium">Users</span>
            </button>
            <button @click="activeTab = 'scripts'" :class="activeTab === 'scripts' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="text-[10px] font-medium">Scripts</span>
            </button>
            <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="text-[10px] font-medium">Settings</span>
            </button>
        </div>
    </nav>

    <!-- Mobile Drawer Overlay -->
    <div x-show="mobileMenuOpen" x-cloak @click="mobileMenuOpen = false" class="fixed inset-0 bg-black/30 z-40 md:hidden" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

    <!-- Mobile Sidebar Drawer -->
    <aside x-show="mobileMenuOpen" x-cloak class="fixed top-0 left-0 bottom-0 w-[280px] bg-white z-50 md:hidden shadow-xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
        <div class="flex flex-col h-full">
            <!-- Logo -->
            <div class="p-5 border-b border-dark/5">
                <div class="flex items-center gap-3">
                    <span class="font-display text-[20px] text-dark">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[9px] px-2 py-0.5 rounded-full font-semibold">ADMIN</span>
                </div>
            </div>
            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 mb-2"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">Main</span></div>
                <button @click="activeTab = 'overview'; mobileMenuOpen = false" :class="activeTab === 'overview' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Overview
                </button>
                <button @click="activeTab = 'videos'; mobileMenuOpen = false" :class="activeTab === 'videos' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Videos
                </button>
                <button @click="activeTab = 'users'; mobileMenuOpen = false" :class="activeTab === 'users' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                    Users
                </button>
                <div class="px-4 mt-6 mb-2"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">Content</span></div>
                <button @click="activeTab = 'seasons'; mobileMenuOpen = false" :class="activeTab === 'seasons' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Seasons
                </button>
                <button @click="activeTab = 'scripts'; mobileMenuOpen = false" :class="activeTab === 'scripts' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Scripts
                </button>
                <div class="px-4 mt-6 mb-2"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">System</span></div>
                <button @click="activeTab = 'aiconfig'; mobileMenuOpen = false" :class="activeTab === 'aiconfig' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    AI Config
                </button>
                <button @click="activeTab = 'youtube'; mobileMenuOpen = false" :class="activeTab === 'youtube' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    YouTube
                </button>
                <button @click="activeTab = 'settings'; mobileMenuOpen = false" :class="activeTab === 'settings' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Settings
                </button>
            </nav>
            <!-- User -->
            <div class="p-4 border-t border-dark/5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 bg-crimson/10 rounded-full flex items-center justify-center">
                        <span class="text-crimson font-semibold text-[14px]"><?= strtoupper(substr(auth_user()['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                        <p class="text-[13px] font-medium text-dark"><?= e(auth_user()['name']) ?></p>
                        <p class="text-[11px] text-dark/40">Administrator</p>
                    </div>
                </div>
                <form action="/api/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button class="w-full text-[13px] text-dark/50 hover:text-crimson py-2 flex items-center justify-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Desktop Layout Container -->
    <div class="flex min-h-screen">
        <!-- Desktop Sidebar -->
        <aside class="hidden md:flex flex-col w-[220px] bg-white border-r border-dark/5 fixed left-0 top-0 bottom-0 lg:w-[220px] md:w-[52px] group z-30" :class="sidebarCollapsed ? 'md:w-[52px]' : 'lg:w-[220px]'">
            <!-- Logo -->
            <div class="p-4 border-b border-dark/5 lg:block" :class="sidebarCollapsed ? 'hidden' : 'lg:block'">
                <div class="flex items-center gap-2">
                    <span class="font-display text-[18px] text-dark whitespace-nowrap">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[8px] px-1.5 py-0.5 rounded-full font-semibold">ADMIN</span>
                </div>
            </div>
            <!-- Collapsed Logo -->
            <div class="p-3 border-b border-dark/5 lg:hidden flex items-center justify-center" :class="sidebarCollapsed ? 'flex' : 'lg:hidden'">
                <span class="font-display text-[16px] text-crimson">FP</span>
            </div>
            
            <!-- Nav Items -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 mb-2 lg:block" :class="sidebarCollapsed ? 'hidden' : ''"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">Main</span></div>
                
                <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item" :title="sidebarCollapsed ? 'Overview' : ''">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Overview</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Overview</span>
                </button>
                
                <button @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Videos</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Videos</span>
                </button>
                
                <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Users</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Users</span>
                </button>

                <div class="px-4 mt-5 mb-2 lg:block" :class="sidebarCollapsed ? 'hidden' : ''"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">Content</span></div>
                
                <button @click="activeTab = 'seasons'" :class="activeTab === 'seasons' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Seasons</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Seasons</span>
                </button>
                
                <button @click="activeTab = 'scripts'" :class="activeTab === 'scripts' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Scripts</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Scripts</span>
                </button>
                
                <div class="px-4 mt-5 mb-2 lg:block" :class="sidebarCollapsed ? 'hidden' : ''"><span class="text-[10px] font-semibold text-dark/30 uppercase tracking-wider">System</span></div>
                
                <button @click="activeTab = 'aiconfig'" :class="activeTab === 'aiconfig' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">AI Config</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">AI Config</span>
                </button>
                
                <button @click="activeTab = 'youtube'" :class="activeTab === 'youtube' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">YouTube</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">YouTube</span>
                </button>
                
                <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Settings</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Settings</span>
                </button>
            </nav>
            
            <!-- User Info -->
            <div class="p-3 border-t border-dark/5">
                <div class="flex items-center gap-2 lg:flex" :class="sidebarCollapsed ? 'justify-center' : ''">
                    <div class="w-8 h-8 bg-crimson/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-crimson font-semibold text-[12px]"><?= strtoupper(substr(auth_user()['name'], 0, 1)) ?></span>
                    </div>
                    <div class="lg:block" :class="sidebarCollapsed ? 'hidden' : ''">
                        <p class="text-[12px] font-medium text-dark truncate max-w-[120px]"><?= e(auth_user()['name']) ?></p>
                        <p class="text-[10px] text-dark/40">Admin</p>
                    </div>
                </div>
                <form action="/api/logout" method="POST" class="mt-2 lg:block" :class="sidebarCollapsed ? 'hidden' : ''">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button class="w-full text-[11px] text-dark/40 hover:text-crimson py-1.5 flex items-center justify-center gap-1 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign Out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 md:ml-[52px] lg:ml-[220px] pb-20 md:pb-0">
            <!-- Top Bar -->
            <header class="bg-white border-b border-dark/5 sticky top-0 z-20">
                <div class="flex items-center justify-between px-4 md:px-6 h-14">
                    <div class="flex items-center gap-3">
                        <!-- Mobile menu button -->
                        <button @click="mobileMenuOpen = true" class="md:hidden p-2 -ml-2 text-dark/60 hover:text-dark">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <h1 class="font-display text-[20px] md:text-[24px] text-dark" x-text="tabTitles[activeTab]"></h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (isset($_GET['debug_updated'])): ?>
                        <span class="text-[11px] bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Debug updated!</span>
                        <?php endif; ?>
                        <?php if (isset($_GET['logs_cleared'])): ?>
                        <span class="text-[11px] bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Logs cleared!</span>
                        <?php endif; ?>
                        <a href="/creator/dashboard" class="hidden sm:flex items-center gap-1.5 text-[12px] text-dark/50 hover:text-crimson transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Creator Studio
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="p-4 md:p-6">

                <!-- ==================== OVERVIEW TAB ==================== -->
                <div x-show="activeTab === 'overview'" x-cloak>
                    <!-- Stat Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="stat-card rounded-xl p-4">
                            <p class="text-[11px] text-dark/40 uppercase tracking-wider mb-1">Total Users</p>
                            <p class="font-display text-[32px] text-dark leading-none"><?= $totalUsers ?></p>
                        </div>
                        <div class="stat-card rounded-xl p-4">
                            <p class="text-[11px] text-dark/40 uppercase tracking-wider mb-1">Pending Videos</p>
                            <p class="font-display text-[32px] text-amber-600 leading-none"><?= $pendingCount ?></p>
                        </div>
                        <div class="stat-card rounded-xl p-4">
                            <p class="text-[11px] text-dark/40 uppercase tracking-wider mb-1">AI Flagged</p>
                            <p class="font-display text-[32px] text-crimson leading-none"><?= $flaggedCount ?></p>
                        </div>
                        <div class="stat-card rounded-xl p-4">
                            <p class="text-[11px] text-dark/40 uppercase tracking-wider mb-1">Active Season</p>
                            <p class="font-display text-[18px] text-teal-600 leading-tight truncate"><?= $activeSeason ? e($activeSeason['title']) : 'None' ?></p>
                        </div>
                    </div>

                    <!-- AI Flagged Videos -->
                    <?php if (!empty($flagged)): ?>
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-dark/5 bg-red-50/50">
                            <h2 class="font-semibold text-dark flex items-center gap-2">
                                <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                                AI Flagged — Manual Review
                                <span class="bg-crimson text-white text-[10px] px-2 py-0.5 rounded-full"><?= count($flagged) ?></span>
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium">Creator</th>
                                        <th class="px-5 py-3 text-left font-medium">Title</th>
                                        <th class="px-5 py-3 text-left font-medium">Type</th>
                                        <th class="px-5 py-3 text-left font-medium">AI Score</th>
                                        <th class="px-5 py-3 text-left font-medium">Flags</th>
                                        <th class="px-5 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <?php foreach ($flagged as $v): 
                                        $fb = is_string($v['ai_feedback'] ?? '') ? json_decode($v['ai_feedback'], true) : ($v['ai_feedback'] ?? []);
                                        $flags = $fb['flags'] ?? [];
                                    ?>
                                    <tr class="hover:bg-cream/30 transition">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-crimson/10 rounded-full flex items-center justify-center">
                                                    <span class="text-crimson font-semibold text-[10px]"><?= strtoupper(substr($v['user_name'], 0, 1)) ?></span>
                                                </div>
                                                <span class="font-medium text-dark"><?= e($v['user_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-dark/70"><?= e($v['title']) ?></span>
                                            <?php if (!empty($v['video_duration'])): ?>
                                            <span class="text-[10px] text-dark/40 ml-1">(<?= gmdate('i:s', $v['video_duration']) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60"><?= e($v['content_type'] ?? $v['user_role'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium <?= ($v['ai_score'] ?? 0) >= 60 ? 'bg-green-100 text-green-700' : (($v['ai_score'] ?? 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                                                <?= $v['ai_score'] !== null ? round($v['ai_score']) : 'N/A' ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex flex-wrap gap-1 max-w-[200px]">
                                                <?php if (!empty($flags)): foreach ($flags as $flag): ?>
                                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700"><?= e($flag) ?></span>
                                                <?php endforeach; else: ?>
                                                <span class="text-[10px] text-dark/40"><?= e($fb['summary'] ?? 'Review required') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex gap-2">
                                                <button @click="openVideoDetail(<?= $v['id'] ?>, '<?= e(addslashes($v['title'])) ?>', '<?= e($v['file_path'] ?? '') ?>', <?= htmlspecialchars(json_encode($fb), ENT_QUOTES, 'UTF-8') ?>)" class="bg-dark/10 text-dark/60 px-2 py-1 rounded text-[10px] font-medium hover:bg-dark/20 transition">Details</button>
                                                <button @click="approveVideo(<?= $v['id'] ?>)" class="bg-green-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-green-700 transition">✓</button>
                                                <button @click="rejectVideo(<?= $v['id'] ?>)" class="bg-crimson text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-crimson/90 transition">✗</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pending AI Processing -->
                    <?php 
                    $processingVideos = array_filter($allVideos, fn($v) => ($v['ai_status'] ?? '') === 'processing');
                    if (!empty($processingVideos)): 
                    ?>
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-dark/5 bg-blue-50/50">
                            <h2 class="font-semibold text-dark flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                AI Processing
                                <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded-full"><?= count($processingVideos) ?></span>
                            </h2>
                        </div>
                        <div class="p-4">
                            <div class="grid gap-2">
                                <?php foreach (array_slice($processingVideos, 0, 5) as $v): ?>
                                <div class="flex items-center justify-between p-3 bg-cream/50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                        </div>
                                        <div>
                                            <p class="text-[13px] font-medium text-dark"><?= e($v['title']) ?></p>
                                            <p class="text-[11px] text-dark/40">by <?= e($v['user_name']) ?> • <?= e($v['content_type'] ?? 'video') ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-blue-600 font-medium">Analyzing...</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pending Videos -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-dark/5 flex items-center justify-between">
                            <h2 class="font-semibold text-dark">Pending Videos</h2>
                            <span class="bg-amber-100 text-amber-700 text-[10px] px-2 py-0.5 rounded-full font-medium"><?= count($pending) ?> pending</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium">Creator</th>
                                        <th class="px-5 py-3 text-left font-medium">Title</th>
                                        <th class="px-5 py-3 text-left font-medium">Type</th>
                                        <th class="px-5 py-3 text-left font-medium">AI Status</th>
                                        <th class="px-5 py-3 text-left font-medium">Submitted</th>
                                        <th class="px-5 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <?php if (empty($pending)): ?>
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-dark/30">No pending videos</td></tr>
                                    <?php else: foreach ($pending as $v): ?>
                                    <tr class="hover:bg-cream/30 transition">
                                        <td class="px-5 py-3 font-medium text-dark"><?= e($v['user_name']) ?></td>
                                        <td class="px-5 py-3 text-dark/70"><?= e($v['title']) ?></td>
                                        <td class="px-5 py-3">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60"><?= e($v['content_type'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <?php 
                                            $aiStatus = $v['ai_status'] ?? 'pending';
                                            $aiStatusClass = match($aiStatus) {
                                                'approved' => 'bg-green-100 text-green-700',
                                                'processing' => 'bg-blue-100 text-blue-700',
                                                'flagged' => 'bg-amber-100 text-amber-700',
                                                'rejected' => 'bg-red-100 text-red-700',
                                                default => 'bg-dark/5 text-dark/40'
                                            };
                                            ?>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full <?= $aiStatusClass ?>"><?= ucfirst($aiStatus) ?></span>
                                        </td>
                                        <td class="px-5 py-3 text-dark/40"><?= date('M j, g:i A', strtotime($v['created_at'])) ?></td>
                                        <td class="px-5 py-3">
                                            <div class="flex gap-2">
                                                <button @click="approveVideo(<?= $v['id'] ?>)" class="bg-green-600 text-white px-3 py-1 rounded-lg text-[11px] font-medium hover:bg-green-700 transition">Approve</button>
                                                <button @click="rejectVideo(<?= $v['id'] ?>)" class="bg-crimson text-white px-3 py-1 rounded-lg text-[11px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Quick Widgets Row -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Seasons Widget -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Recent Seasons
                            </h3>
                            <div class="space-y-2">
                                <?php foreach (array_slice($allSeasons, 0, 3) as $s): ?>
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-cream/50 transition">
                                    <span class="text-[13px] text-dark"><?= e($s['title']) ?></span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full <?= $s['status'] === 'active' ? 'bg-teal-100 text-teal-700' : 'bg-dark/10 text-dark/50' ?>"><?= ucfirst($s['status']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button @click="activeTab = 'seasons'" class="text-[12px] text-crimson hover:underline mt-3">View all seasons →</button>
                        </div>

                        <!-- Scripts by Role Widget -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-3 flex items-center gap-2">
                                <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Scripts by Role
                            </h3>
                            <div class="grid grid-cols-3 gap-3">
                                <?php 
                                $scriptsByRole = ['actor' => 0, 'director' => 0, 'writer' => 0];
                                foreach ($allScripts as $sc) {
                                    if (isset($scriptsByRole[$sc['category']])) $scriptsByRole[$sc['category']]++;
                                }
                                ?>
                                <div class="text-center p-3 bg-cream/50 rounded-lg">
                                    <p class="font-display text-[24px] text-dark"><?= $scriptsByRole['actor'] ?></p>
                                    <p class="text-[10px] text-dark/40 uppercase">Actor</p>
                                </div>
                                <div class="text-center p-3 bg-cream/50 rounded-lg">
                                    <p class="font-display text-[24px] text-dark"><?= $scriptsByRole['director'] ?></p>
                                    <p class="text-[10px] text-dark/40 uppercase">Director</p>
                                </div>
                                <div class="text-center p-3 bg-cream/50 rounded-lg">
                                    <p class="font-display text-[24px] text-dark"><?= $scriptsByRole['writer'] ?></p>
                                    <p class="text-[10px] text-dark/40 uppercase">Writer</p>
                                </div>
                            </div>
                            <button @click="activeTab = 'scripts'" class="text-[12px] text-crimson hover:underline mt-3">Manage scripts →</button>
                        </div>
                    </div>
                </div>

                <!-- ==================== VIDEOS TAB ==================== -->
                <div x-show="activeTab === 'videos'" x-cloak>
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button @click="videoFilter = 'all'" :class="videoFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">All</button>
                        <button @click="videoFilter = 'pending'" :class="videoFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Pending</button>
                        <button @click="videoFilter = 'approved'" :class="videoFilter === 'approved' ? 'bg-green-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Approved</button>
                        <button @click="videoFilter = 'rejected'" :class="videoFilter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Rejected</button>
                        <button @click="videoFilter = 'flagged'" :class="videoFilter === 'flagged' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">AI Flagged</button>
                    </div>

                    <!-- Videos Table -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium">Creator</th>
                                        <th class="px-5 py-3 text-left font-medium">Role</th>
                                        <th class="px-5 py-3 text-left font-medium">Season</th>
                                        <th class="px-5 py-3 text-left font-medium">AI Score</th>
                                        <th class="px-5 py-3 text-left font-medium">YouTube</th>
                                        <th class="px-5 py-3 text-left font-medium">Status</th>
                                        <th class="px-5 py-3 text-left font-medium">Submitted</th>
                                        <th class="px-5 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <template x-for="v in filteredVideos" :key="v.id">
                                        <tr class="hover:bg-cream/30 transition">
                                            <td class="px-5 py-3 font-medium text-dark" x-text="v.user_name"></td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="v.user_role"></span>
                                            </td>
                                            <td class="px-5 py-3 text-dark/50" x-text="v.season_title"></td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full" :class="(v.ai_score || 0) >= 60 ? 'bg-green-100 text-green-700' : ((v.ai_score || 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')" x-text="v.ai_score || 'N/A'"></span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full" :class="v.youtube_id ? 'bg-red-100 text-red-700' : 'bg-dark/5 text-dark/40'" x-text="v.youtube_id ? 'Published' : (v.youtube_status || 'Pending')"></span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full" :class="{'bg-amber-100 text-amber-700': v.status === 'pending', 'bg-green-100 text-green-700': v.status === 'approved', 'bg-red-100 text-red-700': v.status === 'rejected'}" x-text="v.status"></span>
                                            </td>
                                            <td class="px-5 py-3 text-dark/40" x-text="new Date(v.created_at).toLocaleDateString()"></td>
                                            <td class="px-5 py-3">
                                                <div class="flex gap-2">
                                                    <template x-if="v.status === 'pending'">
                                                        <button @click="approveVideo(v.id)" class="bg-green-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-green-700 transition">Approve</button>
                                                    </template>
                                                    <template x-if="v.status === 'pending'">
                                                        <button @click="rejectVideo(v.id)" class="bg-crimson text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                                    </template>
                                                    <template x-if="v.status === 'approved' && !v.youtube_id">
                                                        <button @click="publishToYouTube(v.id)" class="bg-red-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-red-700 transition flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                                            Publish
                                                        </button>
                                                    </template>
                                                    <template x-if="v.youtube_id">
                                                        <a :href="'https://youtube.com/watch?v=' + v.youtube_id" target="_blank" class="bg-red-100 text-red-700 px-2 py-1 rounded text-[10px] font-medium hover:bg-red-200 transition flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                                            Watch
                                                        </a>
                                                    </template>
                                                    <template x-if="v.file_path">
                                                        <a :href="'/uploads/' + v.file_path" target="_blank" class="bg-dark/10 text-dark/60 px-2 py-1 rounded text-[10px] font-medium hover:bg-dark/20 transition">Preview</a>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredVideos.length === 0">
                                        <td colspan="8" class="px-5 py-10 text-center text-dark/30">No videos found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== USERS TAB ==================== -->
                <div x-show="activeTab === 'users'" x-cloak>
                    <!-- Filter -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button @click="userFilter = 'all'" :class="userFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">All Users</button>
                        <button @click="userFilter = 'actor'" :class="userFilter === 'actor' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Actors</button>
                        <button @click="userFilter = 'director'" :class="userFilter === 'director' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Directors</button>
                        <button @click="userFilter = 'writer'" :class="userFilter === 'writer' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Writers</button>
                    </div>

                    <!-- Users Table -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium">Name</th>
                                        <th class="px-5 py-3 text-left font-medium">Email</th>
                                        <th class="px-5 py-3 text-left font-medium">Role</th>
                                        <th class="px-5 py-3 text-left font-medium">Categories</th>
                                        <th class="px-5 py-3 text-left font-medium">Joined</th>
                                        <th class="px-5 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <template x-for="u in filteredUsers" :key="u.id">
                                        <tr class="hover:bg-cream/30 transition">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-7 h-7 bg-crimson/10 rounded-full flex items-center justify-center">
                                                        <span class="text-crimson font-semibold text-[11px]" x-text="u.name.charAt(0).toUpperCase()"></span>
                                                    </div>
                                                    <span class="font-medium text-dark" x-text="u.name"></span>
                                                    <template x-if="u.is_admin == 1">
                                                        <span class="text-[9px] bg-crimson text-white px-1.5 py-0.5 rounded-full">ADMIN</span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-dark/60" x-text="u.email"></td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="u.role"></span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-wrap gap-1">
                                                    <template x-for="cat in (Array.isArray(u.content_categories) ? u.content_categories : [u.role])" :key="cat">
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-cream text-dark/50" x-text="cat"></span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 text-dark/40" x-text="new Date(u.created_at).toLocaleDateString()"></td>
                                            <td class="px-5 py-3">
                                                <template x-if="u.is_admin != 1">
                                                    <button @click="deleteUser(u.id, u.name)" class="text-crimson hover:underline text-[12px]">Delete</button>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredUsers.length === 0">
                                        <td colspan="6" class="px-5 py-10 text-center text-dark/30">No users found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== SEASONS TAB ==================== -->
                <div x-show="activeTab === 'seasons'" x-cloak>
                    <div class="grid lg:grid-cols-3 gap-6">
                        <!-- Create Season Form -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4">Create New Season</h3>
                            <form @submit.prevent="createSeason()">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Title</label>
                                        <input type="text" x-model="newSeason.title" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="Season 1: The Beginning">
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Brief</label>
                                        <textarea x-model="newSeason.brief" rows="2" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-none" placeholder="Season description..."></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[12px] text-dark/50 mb-1">Start Date</label>
                                            <input type="date" x-model="newSeason.start_date" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                        </div>
                                        <div>
                                            <label class="block text-[12px] text-dark/50 mb-1">End Date</label>
                                            <input type="date" x-model="newSeason.end_date" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Status</label>
                                        <select x-model="newSeason.status" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                            <option value="active">Active</option>
                                            <option value="upcoming">Upcoming</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="w-full bg-crimson text-white py-2.5 rounded-lg text-[13px] font-medium hover:bg-crimson/90 transition">Create Season</button>
                                </div>
                            </form>
                        </div>

                        <!-- Seasons List -->
                        <div class="lg:col-span-2 bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-5 py-4 border-b border-dark/5">
                                <h3 class="font-semibold text-dark">All Seasons</h3>
                            </div>
                            <div class="divide-y divide-dark/5">
                                <template x-for="s in seasons" :key="s.id">
                                    <div class="p-5 hover:bg-cream/30 transition">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="font-medium text-dark" x-text="s.title"></h4>
                                                <p class="text-[12px] text-dark/50 mt-1" x-text="s.brief || 'No description'"></p>
                                                <div class="flex items-center gap-3 mt-2 text-[11px] text-dark/40">
                                                    <span x-text="s.start_date + ' → ' + s.end_date"></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] px-2 py-0.5 rounded-full" :class="{'bg-teal-100 text-teal-700': s.status === 'active', 'bg-amber-100 text-amber-700': s.status === 'upcoming', 'bg-dark/10 text-dark/50': s.status === 'closed'}" x-text="s.status"></span>
                                                <button @click="editSeason(s)" class="text-[11px] text-dark/50 hover:text-crimson">Edit</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="seasons.length === 0" class="p-10 text-center text-dark/30 text-[13px]">No seasons yet</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SCRIPTS TAB ==================== -->
                <div x-show="activeTab === 'scripts'" x-cloak>
                    <!-- Guide Editor Section -->
                    <div class="bg-white rounded-xl border border-dark/5 p-5 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-dark flex items-center gap-2">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                Role Guides (Shown on Create Screen)
                            </h3>
                            <div class="flex gap-2">
                                <button @click="guideTab = 'actor'" :class="guideTab === 'actor' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">🎭 Actor</button>
                                <button @click="guideTab = 'director'" :class="guideTab === 'director' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">🎬 Director</button>
                                <button @click="guideTab = 'writer'" :class="guideTab === 'writer' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">✍️ Writer</button>
                            </div>
                        </div>
                        
                        <!-- Actor Guide -->
                        <div x-show="guideTab === 'actor'" x-cloak>
                            <textarea x-model="guides.actor" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for actors..."></textarea>
                            <div class="flex items-center justify-between mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('actor')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">Save Actor Guide</button>
                            </div>
                        </div>
                        
                        <!-- Director Guide -->
                        <div x-show="guideTab === 'director'" x-cloak>
                            <textarea x-model="guides.director" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for directors..."></textarea>
                            <div class="flex items-center justify-between mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('director')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">Save Director Guide</button>
                            </div>
                        </div>
                        
                        <!-- Writer Guide -->
                        <div x-show="guideTab === 'writer'" x-cloak>
                            <textarea x-model="guides.writer" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for writers..."></textarea>
                            <div class="flex items-center justify-between mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('writer')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">Save Writer Guide</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid lg:grid-cols-3 gap-6">
                        <!-- Create Script Form -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4" x-text="editingScript ? 'Edit Script' : 'Create New Script'"></h3>
                            <form @submit.prevent="editingScript ? updateScript() : createScript()">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Title</label>
                                        <input type="text" x-model="scriptForm.title" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="Dramatic Monologue #1">
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Content</label>
                                        <textarea x-model="scriptForm.content" rows="6" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-none" placeholder="The script content goes here..."></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[12px] text-dark/50 mb-1">Category</label>
                                            <select x-model="scriptForm.category" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                                <option value="actor">Actor</option>
                                                <option value="director">Director</option>
                                                <option value="writer">Writer</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[12px] text-dark/50 mb-1">Difficulty</label>
                                            <select x-model="scriptForm.difficulty" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                                <option value="beginner">Beginner</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="advanced">Advanced</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Duration Hint</label>
                                        <input type="text" x-model="scriptForm.duration_hint" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="60-90 seconds">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="flex-1 bg-crimson text-white py-2.5 rounded-lg text-[13px] font-medium hover:bg-crimson/90 transition" x-text="editingScript ? 'Update Script' : 'Create Script'"></button>
                                        <template x-if="editingScript">
                                            <button type="button" @click="cancelEditScript()" class="px-4 py-2.5 rounded-lg text-[13px] text-dark/50 hover:bg-cream transition">Cancel</button>
                                        </template>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Scripts List -->
                        <div class="lg:col-span-2">
                            <!-- Filter -->
                            <div class="flex flex-wrap gap-2 mb-4">
                                <button @click="scriptFilter = 'all'" :class="scriptFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">All</button>
                                <button @click="scriptFilter = 'actor'" :class="scriptFilter === 'actor' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Actor</button>
                                <button @click="scriptFilter = 'director'" :class="scriptFilter === 'director' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Director</button>
                                <button @click="scriptFilter = 'writer'" :class="scriptFilter === 'writer' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Writer</button>
                            </div>

                            <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                                <div class="divide-y divide-dark/5 max-h-[600px] overflow-y-auto">
                                    <template x-for="sc in filteredScripts" :key="sc.id">
                                        <div class="p-4 hover:bg-cream/30 transition">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <h4 class="font-medium text-dark truncate" x-text="sc.title"></h4>
                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-dark/5 text-dark/50" x-text="sc.category"></span>
                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full" :class="{'bg-green-100 text-green-700': sc.difficulty === 'beginner', 'bg-amber-100 text-amber-700': sc.difficulty === 'intermediate', 'bg-red-100 text-red-700': sc.difficulty === 'advanced'}" x-text="sc.difficulty"></span>
                                                    </div>
                                                    <p class="text-[12px] text-dark/50 line-clamp-2" x-text="sc.content"></p>
                                                    <p class="text-[10px] text-dark/30 mt-1" x-show="sc.duration_hint" x-text="'⏱ ' + sc.duration_hint"></p>
                                                </div>
                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    <button @click="editScript(sc)" class="text-[11px] text-dark/50 hover:text-crimson">Edit</button>
                                                    <button @click="openDeleteModal('script', sc.id, sc.title)" class="text-[11px] text-crimson hover:underline">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="filteredScripts.length === 0" class="p-10 text-center text-dark/30 text-[13px]">No scripts found</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== AI CONFIG TAB ==================== -->
                <div x-show="activeTab === 'aiconfig'" x-cloak>
                    <!-- Provider Status Overview -->
                    <div class="bg-white rounded-xl border border-dark/5 p-5 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-dark flex items-center gap-2">
                                <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                AI Provider Status
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-dark/40">AI Processing:</span>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full" :class="aiSettings.ai_processing_enabled === '1' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" x-text="aiSettings.ai_processing_enabled === '1' ? 'Enabled' : 'Disabled'"></span>
                            </div>
                        </div>
                        
                        <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-4">
                            <!-- Azure -->
                            <div class="p-4 rounded-xl" :class="aiProviders.azure ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full" :class="aiProviders.azure ? 'bg-green-500' : 'bg-red-500'"></span>
                                    <span class="font-medium text-[13px]">Azure AI</span>
                                </div>
                                <p class="text-[10px] text-dark/50">Text + Image</p>
                                <p class="text-[10px] text-dark/30">5K/month free</p>
                            </div>
                            
                            <!-- OpenAI -->
                            <div class="p-4 rounded-xl" :class="aiProviders.openai ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full" :class="aiProviders.openai ? 'bg-green-500' : 'bg-red-500'"></span>
                                    <span class="font-medium text-[13px]">OpenAI</span>
                                </div>
                                <p class="text-[10px] text-dark/50">Text Only</p>
                                <p class="text-[10px] text-dark/30">Unlimited free</p>
                            </div>
                            
                            <!-- SightEngine -->
                            <div class="p-4 rounded-xl" :class="aiProviders.sightengine ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full" :class="aiProviders.sightengine ? 'bg-green-500' : 'bg-red-500'"></span>
                                    <span class="font-medium text-[13px]">SightEngine</span>
                                </div>
                                <p class="text-[10px] text-dark/50">Image Only</p>
                                <p class="text-[10px] text-dark/30">500/month free</p>
                            </div>
                            
                            <!-- RapidAPI -->
                            <div class="p-4 rounded-xl" :class="aiProviders.rapidapi ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full" :class="aiProviders.rapidapi ? 'bg-green-500' : 'bg-red-500'"></span>
                                    <span class="font-medium text-[13px]">API4AI</span>
                                </div>
                                <p class="text-[10px] text-dark/50">Image NSFW</p>
                                <p class="text-[10px] text-dark/30">100/day free</p>
                            </div>
                            
                            <!-- Groq -->
                            <div class="p-4 rounded-xl" :class="aiProviders.groq ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2 h-2 rounded-full" :class="aiProviders.groq ? 'bg-green-500' : 'bg-red-500'"></span>
                                    <span class="font-medium text-[13px]">Groq Whisper</span>
                                </div>
                                <p class="text-[10px] text-dark/50">Transcription</p>
                                <p class="text-[10px] text-dark/30">Unlimited (30 RPM)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid lg:grid-cols-2 gap-6">
                        <!-- Provider Priority Configuration -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                Provider Priority (Fallback Order)
                            </h3>
                            
                            <div class="space-y-4">
                                <!-- Text Moderation Providers -->
                                <div>
                                    <label class="block text-[12px] text-dark/50 mb-2">Text Moderation</label>
                                    <select x-model="aiSettings.ai_text_providers" @change="saveAISetting('ai_text_providers', aiSettings.ai_text_providers)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                        <option value="azure,openai,local">Azure → OpenAI → Local</option>
                                        <option value="openai,azure,local">OpenAI → Azure → Local</option>
                                        <option value="azure,local">Azure → Local (skip OpenAI)</option>
                                        <option value="openai,local">OpenAI → Local (skip Azure)</option>
                                        <option value="local">Local Only (no API)</option>
                                    </select>
                                    <p class="text-[10px] text-dark/30 mt-1">System tries each provider in order until one succeeds</p>
                                </div>
                                
                                <!-- Image Moderation Providers -->
                                <div>
                                    <label class="block text-[12px] text-dark/50 mb-2">Image Moderation</label>
                                    <select x-model="aiSettings.ai_image_providers" @change="saveAISetting('ai_image_providers', aiSettings.ai_image_providers)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                        <option value="azure,sightengine,api4ai">Azure → SightEngine → API4AI</option>
                                        <option value="sightengine,azure,api4ai">SightEngine → Azure → API4AI</option>
                                        <option value="azure,api4ai">Azure → API4AI (skip SightEngine)</option>
                                        <option value="sightengine,api4ai">SightEngine → API4AI (skip Azure)</option>
                                        <option value="azure">Azure Only</option>
                                        <option value="sightengine">SightEngine Only</option>
                                    </select>
                                </div>
                                
                                <!-- Transcription Provider -->
                                <div>
                                    <label class="block text-[12px] text-dark/50 mb-2">Audio Transcription</label>
                                    <select x-model="aiSettings.ai_transcription_provider" @change="saveAISetting('ai_transcription_provider', aiSettings.ai_transcription_provider)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                        <option value="groq">Groq Whisper (50+ languages)</option>
                                        <option value="none">Disabled (skip transcription)</option>
                                    </select>
                                    <p class="text-[10px] text-dark/30 mt-1">Supports Hindi, Hinglish, English, and more</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Threshold Settings -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                Quality Thresholds
                            </h3>
                            
                            <div class="space-y-4">
                                <!-- Approve Threshold -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[12px] text-dark/50">Auto-Approve Score</label>
                                        <span class="text-[12px] font-medium text-green-600" x-text="aiSettings.ai_approve_threshold + '%'"></span>
                                    </div>
                                    <input type="range" min="50" max="100" step="5" x-model="aiSettings.ai_approve_threshold" @change="saveAISetting('ai_approve_threshold', aiSettings.ai_approve_threshold)" class="w-full h-2 bg-dark/10 rounded-lg appearance-none cursor-pointer accent-green-600">
                                    <p class="text-[10px] text-dark/30 mt-1">Videos scoring above this are auto-approved</p>
                                </div>
                                
                                <!-- Flag Threshold -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[12px] text-dark/50">Flag for Review Score</label>
                                        <span class="text-[12px] font-medium text-amber-600" x-text="aiSettings.ai_flag_threshold + '%'"></span>
                                    </div>
                                    <input type="range" min="20" max="70" step="5" x-model="aiSettings.ai_flag_threshold" @change="saveAISetting('ai_flag_threshold', aiSettings.ai_flag_threshold)" class="w-full h-2 bg-dark/10 rounded-lg appearance-none cursor-pointer accent-amber-500">
                                    <p class="text-[10px] text-dark/30 mt-1">Videos below this but above reject are flagged for manual review</p>
                                </div>
                                
                                <!-- NSFW Threshold -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[12px] text-dark/50">NSFW Reject Threshold</label>
                                        <span class="text-[12px] font-medium text-red-600" x-text="(aiSettings.ai_nsfw_reject_threshold * 100) + '%'"></span>
                                    </div>
                                    <input type="range" min="0.3" max="0.9" step="0.1" x-model="aiSettings.ai_nsfw_reject_threshold" @change="saveAISetting('ai_nsfw_reject_threshold', aiSettings.ai_nsfw_reject_threshold)" class="w-full h-2 bg-dark/10 rounded-lg appearance-none cursor-pointer accent-red-600">
                                    <p class="text-[10px] text-dark/30 mt-1">NSFW score above this = auto-reject</p>
                                </div>
                                
                                <!-- Duration Limits -->
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-dark/5">
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Min Duration (sec)</label>
                                        <input type="number" x-model="aiSettings.ai_min_duration" @change="saveAISetting('ai_min_duration', aiSettings.ai_min_duration)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" min="5" max="60">
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Max Duration (sec)</label>
                                        <input type="number" x-model="aiSettings.ai_max_duration" @change="saveAISetting('ai_max_duration', aiSettings.ai_max_duration)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" min="60" max="600">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Master Controls -->
                    <div class="bg-white rounded-xl border border-dark/5 p-5 mt-6">
                        <h3 class="font-semibold text-dark mb-4">Master Controls</h3>
                        <div class="grid md:grid-cols-3 gap-4">
                            <!-- Enable/Disable AI Processing -->
                            <div class="flex items-center justify-between p-4 bg-cream rounded-xl">
                                <div>
                                    <p class="font-medium text-dark text-[13px]">AI Processing</p>
                                    <p class="text-[11px] text-dark/40">Process uploaded videos through AI</p>
                                </div>
                                <button @click="toggleAISetting('ai_processing_enabled')" class="relative w-11 h-6 rounded-full transition-colors" :class="aiSettings.ai_processing_enabled === '1' ? 'bg-green-500' : 'bg-dark/20'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform" :class="aiSettings.ai_processing_enabled === '1' ? 'translate-x-5' : ''"></span>
                                </button>
                            </div>
                            
                            <!-- Auto-Approve -->
                            <div class="flex items-center justify-between p-4 bg-cream rounded-xl">
                                <div>
                                    <p class="font-medium text-dark text-[13px]">Auto-Approve</p>
                                    <p class="text-[11px] text-dark/40">High-score videos approved automatically</p>
                                </div>
                                <button @click="toggleAISetting('ai_auto_approve')" class="relative w-11 h-6 rounded-full transition-colors" :class="aiSettings.ai_auto_approve === '1' ? 'bg-green-500' : 'bg-dark/20'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform" :class="aiSettings.ai_auto_approve === '1' ? 'translate-x-5' : ''"></span>
                                </button>
                            </div>
                            
                            <!-- Test API Connection -->
                            <div class="p-4 bg-cream rounded-xl">
                                <p class="font-medium text-dark text-[13px] mb-2">Test Connection</p>
                                <button @click="testAIConnection()" class="w-full bg-crimson text-white py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">
                                    Test AI Providers
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- API Keys Configuration -->
                    <div class="bg-white rounded-xl border border-dark/5 p-5 mt-6" x-data="{ apiKeysLoaded: false }" x-init="loadAPIKeys()">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-dark flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                API Keys Configuration
                            </h3>
                            <button @click="loadAPIKeys()" class="text-[11px] text-dark/40 hover:text-crimson flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh
                            </button>
                        </div>
                        
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                            <p class="text-[11px] text-amber-800">
                                <strong>⚠️ Security Note:</strong> API keys are stored in your .env file. Changes take effect immediately after save. 
                                Keys are masked for security - enter a new value to update.
                            </p>
                        </div>
                        
                        <form @submit.prevent="saveAPIKeys()">
                            <div class="grid lg:grid-cols-2 gap-6">
                                <!-- Azure Content Safety -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.AZURE_CONTENT_SAFETY_KEY?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">Azure AI Content Safety</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">Endpoint URL</label>
                                        <input type="text" x-model="apiKeyForm.AZURE_CONTENT_SAFETY_ENDPOINT" 
                                            :placeholder="apiKeyStatus.AZURE_CONTENT_SAFETY_ENDPOINT?.masked || 'https://your-resource.cognitiveservices.azure.com'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">API Key</label>
                                        <input type="password" x-model="apiKeyForm.AZURE_CONTENT_SAFETY_KEY" 
                                            :placeholder="apiKeyStatus.AZURE_CONTENT_SAFETY_KEY?.configured ? '••••••••••••' : 'Enter Azure key'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <a href="https://portal.azure.com" target="_blank" class="text-[10px] text-blue-600 hover:underline">→ Get API key from Azure Portal</a>
                                </div>
                                
                                <!-- OpenAI -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.OPENAI_API_KEY?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">OpenAI Moderation</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">API Key</label>
                                        <input type="password" x-model="apiKeyForm.OPENAI_API_KEY" 
                                            :placeholder="apiKeyStatus.OPENAI_API_KEY?.configured ? '••••••••••••' : 'sk-...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <p class="text-[10px] text-dark/40">Free unlimited moderation API</p>
                                    <a href="https://platform.openai.com/api-keys" target="_blank" class="text-[10px] text-blue-600 hover:underline">→ Get API key from OpenAI</a>
                                </div>
                                
                                <!-- SightEngine -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.SIGHTENGINE_API_SECRET?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">SightEngine (Images)</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">API User ID</label>
                                        <input type="text" x-model="apiKeyForm.SIGHTENGINE_API_USER" 
                                            :placeholder="apiKeyStatus.SIGHTENGINE_API_USER?.configured ? apiKeyStatus.SIGHTENGINE_API_USER.masked : 'User ID'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">API Secret</label>
                                        <input type="password" x-model="apiKeyForm.SIGHTENGINE_API_SECRET" 
                                            :placeholder="apiKeyStatus.SIGHTENGINE_API_SECRET?.configured ? '••••••••••••' : 'Secret key'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <a href="https://sightengine.com" target="_blank" class="text-[10px] text-blue-600 hover:underline">→ Get keys from SightEngine</a>
                                </div>
                                
                                <!-- RapidAPI -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.RAPIDAPI_KEY?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">RapidAPI / API4AI NSFW</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">RapidAPI Key</label>
                                        <input type="password" x-model="apiKeyForm.RAPIDAPI_KEY" 
                                            :placeholder="apiKeyStatus.RAPIDAPI_KEY?.configured ? '••••••••••••' : 'RapidAPI key'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <p class="text-[10px] text-dark/40">~100 requests/day free tier</p>
                                    <a href="https://rapidapi.com/api4ai-api4ai-default/api/nsfw3" target="_blank" class="text-[10px] text-blue-600 hover:underline">→ Get key from RapidAPI</a>
                                </div>
                                
                                <!-- Groq -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.GROQ_API_KEY?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">Groq Whisper (Transcription)</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">Groq API Key</label>
                                        <input type="password" x-model="apiKeyForm.GROQ_API_KEY" 
                                            :placeholder="apiKeyStatus.GROQ_API_KEY?.configured ? '••••••••••••' : 'gsk_...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <p class="text-[10px] text-dark/40">Unlimited free, 30 RPM • 50+ languages</p>
                                    <a href="https://console.groq.com/keys" target="_blank" class="text-[10px] text-blue-600 hover:underline">→ Get key from Groq Console</a>
                                </div>
                                
                                <!-- FFmpeg Paths -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <h4 class="font-medium text-[13px] text-dark">Local Tools (FFmpeg)</h4>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">FFmpeg Path</label>
                                        <input type="text" x-model="apiKeyForm.FFMPEG_PATH" 
                                            :placeholder="apiKeyStatus.FFMPEG_PATH?.masked || '/usr/bin/ffmpeg'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1">FFprobe Path</label>
                                        <input type="text" x-model="apiKeyForm.FFPROBE_PATH" 
                                            :placeholder="apiKeyStatus.FFPROBE_PATH?.masked || '/usr/bin/ffprobe'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    <p class="text-[10px] text-dark/40">Used for frame extraction & audio processing</p>
                                </div>
                                
                                <!-- YouTube API -->
                                <div class="space-y-3 p-4 bg-cream/50 rounded-xl lg:col-span-2">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <h4 class="font-medium text-[13px] text-dark">YouTube API (Auto-Publish)</h4>
                                    </div>
                                    <div class="grid md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] text-dark/50 mb-1">YouTube API Key</label>
                                            <input type="password" x-model="apiKeyForm.YOUTUBE_API_KEY" 
                                                :placeholder="apiKeyStatus.YOUTUBE_API_KEY?.configured ? '••••••••••••' : 'AIza...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-dark/50 mb-1">Channel ID</label>
                                            <input type="text" x-model="apiKeyForm.YOUTUBE_CHANNEL_ID" 
                                                :placeholder="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? apiKeyStatus.YOUTUBE_CHANNEL_ID.masked : 'UC...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-dark/50 mb-1">OAuth Client ID</label>
                                            <input type="text" x-model="apiKeyForm.YOUTUBE_CLIENT_ID" 
                                                :placeholder="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured ? apiKeyStatus.YOUTUBE_CLIENT_ID.masked : 'xxxx.apps.googleusercontent.com'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-dark/50 mb-1">OAuth Client Secret</label>
                                            <input type="password" x-model="apiKeyForm.YOUTUBE_CLIENT_SECRET" 
                                                :placeholder="apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured ? '••••••••••••' : 'GOCSPX-...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-[11px] text-dark/50 mb-1">OAuth Refresh Token</label>
                                            <input type="password" x-model="apiKeyForm.YOUTUBE_REFRESH_TOKEN" 
                                                :placeholder="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? '••••••••••••' : '1//0g...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2 mt-2">
                                        <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <p class="text-[10px] text-dark/50">Approved videos are automatically published to your YouTube channel. <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Get credentials from Google Cloud Console</a></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Save Button -->
                            <div class="flex items-center justify-between mt-6 pt-4 border-t border-dark/10">
                                <p class="text-[11px] text-dark/40">Only filled fields will be updated. Leave empty to keep current value.</p>
                                <button type="submit" 
                                    class="px-6 py-2.5 bg-crimson text-white rounded-xl text-[13px] font-medium hover:bg-crimson/90 transition flex items-center gap-2"
                                    :disabled="savingKeys"
                                    :class="savingKeys ? 'opacity-50 cursor-not-allowed' : ''">
                                    <svg x-show="savingKeys" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <svg x-show="!savingKeys" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                    <span x-text="savingKeys ? 'Saving...' : 'Save API Keys'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- How It Works Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mt-6">
                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            How AI Moderation Works
                        </h4>
                        <div class="text-[12px] text-blue-800 space-y-1">
                            <p>1. <strong>Video Upload</strong> → User uploads video, background processing starts</p>
                            <p>2. <strong>Frame Extraction</strong> → FFmpeg extracts frames every 5 seconds</p>
                            <p>3. <strong>NSFW Check</strong> → Images sent to configured providers (Azure/SightEngine/API4AI)</p>
                            <p>4. <strong>Audio Transcription</strong> → Groq Whisper transcribes audio (Hindi/English/50+ languages)</p>
                            <p>5. <strong>Text Moderation</strong> → Transcript checked for profanity/hate speech</p>
                            <p>6. <strong>Score & Status</strong> → Video scored and either approved, flagged, or rejected</p>
                        </div>
                    </div>
                </div>

                <!-- ==================== YOUTUBE TAB ==================== -->
                <div x-show="activeTab === 'youtube'" x-cloak>
                    <div class="grid lg:grid-cols-3 gap-6">
                        <!-- Connection Status Card -->
                        <div class="lg:col-span-3">
                            <div class="bg-white rounded-xl border border-dark/5 p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                                            <svg class="w-7 h-7 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-dark text-[16px]">YouTube Integration</h3>
                                            <p class="text-[12px] text-dark/50">Auto-publish approved videos to your channel</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                        <span class="text-[12px] font-medium" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'text-green-600' : 'text-red-600'" x-text="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'Connected' : 'Not Connected'"></span>
                                    </div>
                                </div>
                                
                                <!-- Test Button -->
                                <button @click="testProviders()" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-[12px] font-medium hover:bg-red-700 transition flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Test YouTube Connection
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step-by-Step Guide -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl border border-dark/5 p-5">
                                <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                    Setup Guide
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Step 1 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">1</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Enable YouTube Data API v3</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">Go to Google Cloud Console and enable the YouTube Data API v3 for your project.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/library" target="_blank" class="text-blue-600 hover:underline">Google Cloud API Library</a></li>
                                                    <li>Search for "YouTube Data API v3"</li>
                                                    <li>Click on it and press "Enable"</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 2 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">2</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Create OAuth 2.0 Credentials</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">Create OAuth credentials to authorize video uploads.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials page</a></li>
                                                    <li>Click "Create Credentials" → "OAuth client ID"</li>
                                                    <li>Application type: <strong>Web application</strong></li>
                                                    <li>Name: "Faceless Pitcher" (or anything)</li>
                                                    <li>Authorized redirect URIs: Add <code class="bg-dark/5 px-1.5 py-0.5 rounded text-[10px]">https://developers.google.com/oauthplayground</code></li>
                                                    <li>Click "Create" and copy <strong>Client ID</strong> and <strong>Client Secret</strong></li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 3 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">3</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Add Test User (Required for Testing)</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">If your app is in "Testing" mode, you must add your email as a test user.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" class="text-blue-600 hover:underline">OAuth consent screen</a></li>
                                                    <li>Scroll down to "Test users" section</li>
                                                    <li>Click "Add Users"</li>
                                                    <li>Enter your YouTube channel's email address</li>
                                                    <li>Click "Save"</li>
                                                </ol>
                                                <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded-lg">
                                                    <p class="text-[10px] text-yellow-800"><strong>Important:</strong> Use the Gmail account that owns your YouTube channel!</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 4 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">4</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Get Refresh Token from OAuth Playground</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">Use Google's OAuth Playground to generate a refresh token.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://developers.google.com/oauthplayground" target="_blank" class="text-blue-600 hover:underline">OAuth 2.0 Playground</a></li>
                                                    <li>Click the ⚙️ gear icon (top-right)</li>
                                                    <li>Check "Use your own OAuth credentials"</li>
                                                    <li>Enter your Client ID and Client Secret</li>
                                                    <li>Close the settings</li>
                                                    <li>In the left panel, find "YouTube Data API v3"</li>
                                                    <li>Select <code class="bg-dark/5 px-1.5 py-0.5 rounded text-[10px]">https://www.googleapis.com/auth/youtube.upload</code></li>
                                                    <li>Click "Authorize APIs"</li>
                                                    <li>Sign in with your YouTube channel's Google account</li>
                                                    <li>Allow access</li>
                                                    <li>Click "Exchange authorization code for tokens"</li>
                                                    <li>Copy the <strong>Refresh token</strong></li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 5 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">5</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Create API Key</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">Create an API key for public data access.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials page</a></li>
                                                    <li>Click "Create Credentials" → "API key"</li>
                                                    <li>Copy the API key (starts with AIza...)</li>
                                                    <li>Optional: Click "Edit" to restrict the key to YouTube Data API only</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 6 -->
                                    <div class="border border-dark/10 rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[12px] font-bold flex-shrink-0">6</div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-dark text-[13px] mb-1">Get Your Channel ID</h4>
                                                <p class="text-[11px] text-dark/60 mb-2">Find your YouTube channel ID.</p>
                                                <ol class="text-[11px] text-dark/70 space-y-1 ml-4 list-decimal">
                                                    <li>Go to <a href="https://www.youtube.com" target="_blank" class="text-blue-600 hover:underline">YouTube</a></li>
                                                    <li>Click your profile picture → "Your channel"</li>
                                                    <li>Look at the URL: <code class="bg-dark/5 px-1.5 py-0.5 rounded text-[10px]">youtube.com/channel/<strong>UC...</strong></code></li>
                                                    <li>Copy the ID that starts with "UC"</li>
                                                </ol>
                                                <div class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                                                    <p class="text-[10px] text-blue-800">If your URL shows a custom handle (@username), go to <a href="https://www.youtube.com/account_advanced" target="_blank" class="underline">YouTube Advanced Settings</a> to find your Channel ID.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- API Configuration Form -->
                        <div class="lg:col-span-1">
                            <form @submit.prevent="saveAPIKeys()" class="bg-white rounded-xl border border-dark/5 p-5">
                                <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    API Configuration
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- API Key -->
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                            YouTube API Key
                                            <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_API_KEY?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        </label>
                                        <input type="password" x-model="apiKeyForm.YOUTUBE_API_KEY" 
                                            :placeholder="apiKeyStatus.YOUTUBE_API_KEY?.configured ? '••••••••••••' : 'AIza...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    
                                    <!-- Client ID -->
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                            OAuth Client ID
                                            <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        </label>
                                        <input type="text" x-model="apiKeyForm.YOUTUBE_CLIENT_ID" 
                                            :placeholder="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured ? apiKeyStatus.YOUTUBE_CLIENT_ID.masked : 'xxxx.apps.googleusercontent.com'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    
                                    <!-- Client Secret -->
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                            OAuth Client Secret
                                            <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        </label>
                                        <input type="password" x-model="apiKeyForm.YOUTUBE_CLIENT_SECRET" 
                                            :placeholder="apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured ? '••••••••••••' : 'GOCSPX-...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    
                                    <!-- Refresh Token -->
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                            OAuth Refresh Token
                                            <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        </label>
                                        <input type="password" x-model="apiKeyForm.YOUTUBE_REFRESH_TOKEN" 
                                            :placeholder="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? '••••••••••••' : '1//0g...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                    
                                    <!-- Channel ID -->
                                    <div>
                                        <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                            Channel ID
                                            <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        </label>
                                        <input type="text" x-model="apiKeyForm.YOUTUBE_CHANNEL_ID" 
                                            :placeholder="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? apiKeyStatus.YOUTUBE_CHANNEL_ID.masked : 'UC...'"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                    </div>
                                </div>
                                
                                <div class="mt-6 pt-4 border-t border-dark/10">
                                    <p class="text-[10px] text-dark/40 mb-3">Only filled fields will be updated. Leave empty to keep current value.</p>
                                    <button type="submit" 
                                        class="w-full px-4 py-2.5 bg-crimson text-white rounded-xl text-[13px] font-medium hover:bg-crimson/90 transition flex items-center justify-center gap-2"
                                        :disabled="savingKeys"
                                        :class="savingKeys ? 'opacity-50 cursor-not-allowed' : ''">
                                        <svg x-show="savingKeys" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <svg x-show="!savingKeys" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                        <span x-text="savingKeys ? 'Saving...' : 'Save YouTube Settings'"></span>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Quick Links -->
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-4">
                                <h4 class="font-semibold text-blue-900 text-[12px] mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    Quick Links
                                </h4>
                                <div class="space-y-2">
                                    <a href="https://console.cloud.google.com/apis/library" target="_blank" class="block text-[11px] text-blue-700 hover:underline">→ API Library</a>
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="block text-[11px] text-blue-700 hover:underline">→ Credentials</a>
                                    <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" class="block text-[11px] text-blue-700 hover:underline">→ OAuth Consent Screen</a>
                                    <a href="https://developers.google.com/oauthplayground" target="_blank" class="block text-[11px] text-blue-700 hover:underline">→ OAuth Playground</a>
                                    <a href="https://www.youtube.com/account_advanced" target="_blank" class="block text-[11px] text-blue-700 hover:underline">→ YouTube Channel ID</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SETTINGS TAB ==================== -->
                <div x-show="activeTab === 'settings'" x-cloak>
                    <div class="grid lg:grid-cols-3 gap-6">
                        <!-- Debug Controls -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Debug Mode
                            </h3>
                            
                            <div class="flex items-center justify-between p-4 bg-cream rounded-xl mb-4">
                                <div>
                                    <p class="font-medium text-dark text-[13px]">Debug Logging</p>
                                    <p class="text-[11px] text-dark/40">Logs to /logs/debug.log</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/30' ?>">
                                        <?= FP3_DEBUG ? 'ON' : 'OFF' ?>
                                    </span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="toggle_debug" value="1">
                                        <button type="submit" class="relative w-10 h-5 rounded-full transition-colors <?= FP3_DEBUG ? 'bg-green-500' : 'bg-dark/20' ?>">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform <?= FP3_DEBUG ? 'translate-x-5' : '' ?>"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4">
                                <p class="text-[11px] text-amber-800">
                                    <strong>⚠️</strong> Debug mode logs sensitive data. Disable in production.
                                </p>
                            </div>
                            
                            <form method="POST" @submit.prevent="openConfirmModal('logs', null, 'Clear all log files?')">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="clear_logs" value="1">
                                <button type="submit" class="w-full bg-crimson/10 text-crimson font-medium py-2 rounded-xl hover:bg-crimson/20 transition text-[12px]">
                                    Clear All Logs
                                </button>
                            </form>
                        </div>

                        <!-- Log Files -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Log Files
                            </h3>
                            
                            <?php if (empty($logFiles)): ?>
                            <p class="text-[12px] text-dark/30 text-center py-6">No log files</p>
                            <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($logFiles as $log): ?>
                                <div class="flex items-center justify-between p-3 bg-cream rounded-lg">
                                    <div>
                                        <p class="font-medium text-dark text-[12px]"><?= e($log['name']) ?></p>
                                        <p class="text-[10px] text-dark/40"><?= date('M j, H:i', $log['modified']) ?></p>
                                    </div>
                                    <span class="text-[10px] text-dark/40"><?= number_format($log['size'] / 1024, 1) ?> KB</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Environment -->
                        <div class="bg-white rounded-xl border border-dark/5 p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Environment
                            </h3>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between text-[12px] p-2 bg-cream rounded">
                                    <span class="text-dark/50">Environment</span>
                                    <span class="font-medium text-dark"><?= APP_ENV ?></span>
                                </div>
                                <div class="flex justify-between text-[12px] p-2 bg-cream rounded">
                                    <span class="text-dark/50">PHP Version</span>
                                    <span class="font-medium text-dark"><?= PHP_VERSION ?></span>
                                </div>
                                <div class="flex justify-between text-[12px] p-2 bg-cream rounded">
                                    <span class="text-dark/50">Debug Mode</span>
                                    <span class="font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/40' ?>"><?= FP3_DEBUG ? 'ON' : 'OFF' ?></span>
                                </div>
                                <div class="flex justify-between text-[12px] p-2 bg-cream rounded">
                                    <span class="text-dark/50">Memory Limit</span>
                                    <span class="font-medium text-dark"><?= ini_get('memory_limit') ?></span>
                                </div>
                                <div class="flex justify-between text-[12px] p-2 bg-cream rounded">
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
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-5 py-3 border-b border-dark/5 bg-blue-50">
                                <h3 class="font-semibold text-dark text-[13px] flex items-center gap-2">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    Debug Log (last 100 lines)
                                </h3>
                            </div>
                            <div class="p-3 bg-dark max-h-[300px] overflow-auto">
                                <pre class="log-viewer text-green-400 whitespace-pre-wrap text-[10px]"><?= e($debugLogContent) ?></pre>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($errorLogContent): ?>
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-5 py-3 border-b border-dark/5 bg-red-50">
                                <h3 class="font-semibold text-dark text-[13px] flex items-center gap-2">
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    Error Log (last 50 lines)
                                </h3>
                            </div>
                            <div class="p-3 bg-dark max-h-[300px] overflow-auto">
                                <pre class="log-viewer text-red-400 whitespace-pre-wrap text-[10px]"><?= e($errorLogContent) ?></pre>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <!-- Modal for Video Approve/Reject -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="modalOpen = false"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop 
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            
            <!-- Header with icon -->
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="modalAction === 'approve' ? 'bg-green-100' : 'bg-red-100'">
                    <svg x-show="modalAction === 'approve'" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="modalAction === 'reject'" class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <div>
                    <h3 class="font-display text-[22px] text-dark" x-text="modalTitle"></h3>
                    <p class="text-[12px] text-dark/50" x-text="modalAction === 'approve' ? 'Video will be queued for YouTube upload' : 'User will be notified via email'"></p>
                </div>
            </div>
            
            <!-- Reason input -->
            <div class="mb-4">
                <label class="block text-[12px] text-dark/60 mb-2 font-medium" x-text="modalAction === 'approve' ? 'Approval note (optional)' : 'Rejection reason (required)'"></label>
                <textarea x-model="modalReason" rows="3" 
                    class="w-full border rounded-xl px-4 py-3 text-[13px] focus:outline-none transition resize-none"
                    :class="modalAction === 'approve' ? 'border-green-200 focus:border-green-500 bg-green-50/30' : 'border-red-200 focus:border-red-500 bg-red-50/30'"
                    :placeholder="modalAction === 'approve' ? 'Great performance! (optional)' : 'Please explain why this video was rejected...'"></textarea>
                <p x-show="modalAction === 'reject' && !modalReason.trim()" class="text-[11px] text-red-500 mt-1">* Rejection reason is required</p>
            </div>
            
            <!-- Quick rejection reasons -->
            <div x-show="modalAction === 'reject'" class="mb-4">
                <p class="text-[11px] text-dark/50 mb-2">Quick reasons:</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="modalReason = 'Video contains inappropriate content'" class="text-[10px] px-2.5 py-1 bg-dark/5 hover:bg-dark/10 rounded-full transition">Inappropriate content</button>
                    <button type="button" @click="modalReason = 'Poor audio/video quality'" class="text-[10px] px-2.5 py-1 bg-dark/5 hover:bg-dark/10 rounded-full transition">Poor quality</button>
                    <button type="button" @click="modalReason = 'Video is too short or does not meet requirements'" class="text-[10px] px-2.5 py-1 bg-dark/5 hover:bg-dark/10 rounded-full transition">Too short</button>
                    <button type="button" @click="modalReason = 'Content does not match the selected category'" class="text-[10px] px-2.5 py-1 bg-dark/5 hover:bg-dark/10 rounded-full transition">Wrong category</button>
                    <button type="button" @click="modalReason = 'Contains copyrighted material'" class="text-[10px] px-2.5 py-1 bg-dark/5 hover:bg-dark/10 rounded-full transition">Copyright issue</button>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2 border-t border-dark/5">
                <button @click="modalOpen = false" class="px-4 py-2.5 text-[12px] text-dark/50 hover:text-dark hover:bg-dark/5 rounded-xl transition">Cancel</button>
                <button @click="confirmVideoAction()" 
                    :disabled="modalAction === 'reject' && !modalReason.trim()"
                    :class="modalAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                    class="px-5 py-2.5 text-[12px] text-white rounded-xl transition font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="modalAction === 'approve'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="modalAction === 'reject'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span x-text="modalAction === 'approve' ? 'Approve & Queue for YouTube' : 'Reject Video'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Generic Confirm Modal (replaces browser confirm) -->
    <div x-show="confirmModalOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="confirmModalOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6" @click.stop>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-crimson/10 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-dark text-[15px]">Confirm Action</h3>
                    <p class="text-[12px] text-dark/50" x-text="confirmMessage"></p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button @click="confirmModalOpen = false" class="px-4 py-2 text-[12px] text-dark/50 hover:text-dark transition rounded-lg">Cancel</button>
                <button @click="executeConfirm()" class="px-5 py-2 text-[12px] bg-crimson text-white rounded-xl hover:bg-crimson/90 transition font-medium">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="deleteModalOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6" @click.stop>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-dark text-[15px]">Delete <span x-text="deleteType"></span></h3>
                    <p class="text-[12px] text-dark/50">This action cannot be undone.</p>
                </div>
            </div>
            <p class="text-[13px] text-dark/70 mb-4 bg-cream rounded-lg p-3" x-text="deleteName"></p>
            <div class="flex justify-end gap-3">
                <button @click="deleteModalOpen = false" class="px-4 py-2 text-[12px] text-dark/50 hover:text-dark transition rounded-lg">Cancel</button>
                <button @click="executeDelete()" class="px-5 py-2 text-[12px] bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-medium">Delete</button>
            </div>
        </div>
    </div>

    <!-- AI Test Results Modal -->
    <div x-show="testResultsOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="testResultsOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-dark text-[16px]">AI Provider Test Results</h3>
                    <p class="text-[12px] text-dark/50">Connection status for each provider</p>
                </div>
            </div>
            <div class="space-y-3 mb-5">
                <template x-for="(status, provider) in testResults" :key="provider">
                    <div class="flex items-center justify-between p-3 rounded-xl" :class="status === 'OK' || status.startsWith('OK') ? 'bg-green-50' : status === 'Not configured' ? 'bg-gray-50' : status.includes('429') ? 'bg-yellow-50' : 'bg-red-50'">
                        <span class="font-medium text-[13px] uppercase" x-text="provider"></span>
                        <span class="text-[12px] px-2 py-1 rounded-full font-medium" 
                            :class="status === 'OK' || status.startsWith('OK') ? 'bg-green-100 text-green-700' : status === 'Not configured' ? 'bg-gray-200 text-gray-600' : status.includes('429') ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'"
                            x-text="status"></span>
                    </div>
                </template>
            </div>
            <div class="flex justify-end">
                <button @click="testResultsOpen = false" class="px-5 py-2.5 text-[13px] bg-dark text-white rounded-xl hover:bg-dark/90 transition font-medium">Close</button>
            </div>
        </div>
    </div>

    <!-- Video Detail Modal -->
    <div x-show="videoDetailOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="videoDetailOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden" @click.stop>
            <!-- Header -->
            <div class="px-6 py-4 border-b border-dark/10 flex items-center justify-between">
                <h3 class="font-display text-[20px] text-dark" x-text="videoDetail.title"></h3>
                <button @click="videoDetailOpen = false" class="text-dark/40 hover:text-dark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <!-- Content -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Video Preview -->
                <template x-if="videoDetail.filePath">
                    <div class="mb-6">
                        <video :src="'/uploads/' + videoDetail.filePath" controls class="w-full rounded-xl bg-dark max-h-[300px]"></video>
                    </div>
                </template>
                
                <!-- AI Analysis Summary -->
                <div class="bg-cream rounded-xl p-4 mb-6">
                    <h4 class="font-semibold text-dark text-[14px] mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        AI Analysis
                    </h4>
                    
                    <!-- Score -->
                    <div class="flex items-center gap-4 mb-4">
                        <div class="text-center">
                            <div class="text-[32px] font-display" :class="videoDetail.feedback?.score >= 70 ? 'text-green-600' : (videoDetail.feedback?.score >= 40 ? 'text-amber-600' : 'text-red-600')" x-text="videoDetail.feedback?.score || 'N/A'"></div>
                            <div class="text-[10px] text-dark/40 uppercase">Quality Score</div>
                        </div>
                        <div class="flex-1">
                            <div class="h-3 bg-dark/10 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all" 
                                     :class="videoDetail.feedback?.score >= 70 ? 'bg-green-500' : (videoDetail.feedback?.score >= 40 ? 'bg-amber-500' : 'bg-red-500')"
                                     :style="'width: ' + (videoDetail.feedback?.score || 0) + '%'"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <template x-if="videoDetail.feedback?.summary">
                        <p class="text-[13px] text-dark/70 mb-3" x-text="videoDetail.feedback.summary"></p>
                    </template>
                    
                    <!-- Flags -->
                    <template x-if="videoDetail.feedback?.flags?.length > 0">
                        <div class="mb-3">
                            <span class="text-[11px] text-dark/40 uppercase">Flags:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <template x-for="flag in videoDetail.feedback.flags" :key="flag">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-700" x-text="flag"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Feedback Items -->
                    <template x-if="videoDetail.feedback?.feedback?.length > 0">
                        <div>
                            <span class="text-[11px] text-dark/40 uppercase">Details:</span>
                            <ul class="mt-1 space-y-1">
                                <template x-for="item in videoDetail.feedback.feedback" :key="item">
                                    <li class="text-[12px] text-dark/60 flex items-start gap-2">
                                        <span class="text-dark/30">•</span>
                                        <span x-text="item"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    
                    <!-- Transcript Preview (if available) -->
                    <template x-if="videoDetail.feedback?.transcript">
                        <div class="mt-4 pt-4 border-t border-dark/10">
                            <span class="text-[11px] text-dark/40 uppercase">Transcript Preview:</span>
                            <p class="text-[12px] text-dark/50 mt-1 line-clamp-3" x-text="videoDetail.feedback.transcript"></p>
                        </div>
                    </template>
                </div>
                
                <!-- Actions -->
                <div class="flex gap-3">
                    <button @click="videoDetailOpen = false; approveVideo(videoDetail.id)" class="flex-1 bg-green-600 text-white py-3 rounded-xl text-[13px] font-medium hover:bg-green-700 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve Video
                    </button>
                    <button @click="videoDetailOpen = false; rejectVideo(videoDetail.id)" class="flex-1 bg-crimson text-white py-3 rounded-xl text-[13px] font-medium hover:bg-crimson/90 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject Video
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div x-show="toastShow" x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-24 md:bottom-6 right-6 z-50">
        <div class="bg-dark text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3">
            <svg x-show="toastType === 'success'" class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg x-show="toastType === 'error'" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="text-[13px]" x-text="toastMessage"></span>
        </div>
    </div>

    <!-- Modal for Season Edit -->
    <div x-show="seasonModalOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="seasonModalOpen = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
            <h3 class="font-display text-[22px] text-dark mb-4">Edit Season</h3>
            <form @submit.prevent="updateSeason()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-[12px] text-dark/50 mb-1">Title</label>
                        <input type="text" x-model="editSeasonForm.title" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                    </div>
                    <div>
                        <label class="block text-[12px] text-dark/50 mb-1">Brief</label>
                        <textarea x-model="editSeasonForm.brief" rows="2" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[12px] text-dark/50 mb-1">Start Date</label>
                            <input type="date" x-model="editSeasonForm.start_date" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                        </div>
                        <div>
                            <label class="block text-[12px] text-dark/50 mb-1">End Date</label>
                            <input type="date" x-model="editSeasonForm.end_date" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[12px] text-dark/50 mb-1">Status</label>
                        <select x-model="editSeasonForm.status" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                            <option value="active">Active</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="seasonModalOpen = false" class="px-4 py-2 text-[12px] text-dark/50 hover:text-dark transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-[12px] bg-crimson text-white rounded-xl hover:bg-crimson/90 transition font-medium">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function adminDashboard() {
        return {
            activeTab: 'overview',
            mobileMenuOpen: false,
            sidebarCollapsed: window.innerWidth < 1024,
            
            // Tab titles
            tabTitles: {
                overview: 'OVERVIEW',
                videos: 'VIDEOS',
                users: 'USERS',
                seasons: 'SEASONS',
                scripts: 'SCRIPTS',
                aiconfig: 'AI CONFIGURATION',
                youtube: 'YOUTUBE',
                settings: 'SETTINGS'
            },
            
            // Data from PHP
            videos: <?= json_encode($allVideos) ?>,
            users: <?= json_encode($allUsers) ?>,
            seasons: <?= json_encode($allSeasons) ?>,
            scripts: <?= json_encode($allScripts) ?>,
            
            // AI Settings
            aiSettings: <?= json_encode($aiSettings) ?>,
            aiProviders: <?= json_encode($aiProviders) ?>,
            
            // API Keys
            apiKeyStatus: {},
            apiKeyForm: {
                AZURE_CONTENT_SAFETY_ENDPOINT: '',
                AZURE_CONTENT_SAFETY_KEY: '',
                OPENAI_API_KEY: '',
                SIGHTENGINE_API_USER: '',
                SIGHTENGINE_API_SECRET: '',
                RAPIDAPI_KEY: '',
                GROQ_API_KEY: '',
                FFMPEG_PATH: '',
                FFPROBE_PATH: '',
                YOUTUBE_API_KEY: '',
                YOUTUBE_CLIENT_ID: '',
                YOUTUBE_CLIENT_SECRET: '',
                YOUTUBE_REFRESH_TOKEN: '',
                YOUTUBE_CHANNEL_ID: ''
            },
            savingKeys: false,
            
            // Test Results Modal
            testResults: {},
            testResultsOpen: false,
            
            // Filters
            videoFilter: 'all',
            userFilter: 'all',
            scriptFilter: 'all',
            
            // Video modal
            modalOpen: false,
            modalAction: '',
            modalVideoId: null,
            modalTitle: '',
            modalReason: '',
            
            // Video detail modal
            videoDetailOpen: false,
            videoDetail: { id: null, title: '', filePath: '', feedback: null },
            
            // Season edit
            seasonModalOpen: false,
            editSeasonForm: { id: null, title: '', brief: '', start_date: '', end_date: '', status: 'active' },
            
            // New season form
            newSeason: { title: '', brief: '', start_date: '', end_date: '', status: 'active' },
            
            // Scripts
            scriptForm: { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '' },
            editingScript: null,
            
            // Guides
            guides: <?= json_encode($guides) ?>,
            guideTab: 'actor',
            
            // Delete modal
            deleteModalOpen: false,
            deleteType: '',
            deleteId: null,
            deleteName: '',
            
            // Confirm modal
            confirmModalOpen: false,
            confirmMessage: '',
            confirmCallback: null,
            
            // Toast
            toastShow: false,
            toastMessage: '',
            toastType: 'success',
            
            csrf: '<?= csrf_token() ?>',
            
            init() {
                window.addEventListener('resize', () => {
                    this.sidebarCollapsed = window.innerWidth < 1024;
                });
            },
            
            // Toast notification
            showToast(message, type = 'success') {
                this.toastMessage = message;
                this.toastType = type;
                this.toastShow = true;
                setTimeout(() => { this.toastShow = false; }, 3000);
            },
            
            // Open delete modal
            openDeleteModal(type, id, name) {
                this.deleteType = type;
                this.deleteId = id;
                this.deleteName = name;
                this.deleteModalOpen = true;
            },
            
            // Execute delete
            async executeDelete() {
                if (this.deleteType === 'script') {
                    await this.doDeleteScript();
                } else if (this.deleteType === 'user') {
                    await this.doDeleteUser();
                }
                this.deleteModalOpen = false;
            },
            
            // Open confirm modal
            openConfirmModal(action, id, message) {
                this.confirmMessage = message;
                this.confirmCallback = { action, id };
                this.confirmModalOpen = true;
            },
            
            // Execute confirm action
            executeConfirm() {
                if (this.confirmCallback?.action === 'logs') {
                    document.querySelector('form[method="POST"] input[name="clear_logs"]')?.closest('form')?.submit();
                }
                this.confirmModalOpen = false;
            },
            
            // Computed: filtered videos
            get filteredVideos() {
                if (this.videoFilter === 'all') return this.videos;
                if (this.videoFilter === 'flagged') return this.videos.filter(v => v.needs_manual_review == 1);
                return this.videos.filter(v => v.status === this.videoFilter);
            },
            
            // Computed: filtered users
            get filteredUsers() {
                if (this.userFilter === 'all') return this.users;
                return this.users.filter(u => u.role === this.userFilter);
            },
            
            // Computed: filtered scripts
            get filteredScripts() {
                if (this.scriptFilter === 'all') return this.scripts;
                return this.scripts.filter(s => s.category === this.scriptFilter);
            },

            // Open video detail modal
            openVideoDetail(id, title, filePath, feedback) {
                this.videoDetail = {
                    id: id,
                    title: title,
                    filePath: filePath,
                    feedback: feedback || {}
                };
                this.videoDetailOpen = true;
            },

            // Video actions
            approveVideo(id) {
                this.modalAction = 'approve';
                this.modalVideoId = id;
                this.modalTitle = 'Approve Video';
                this.modalReason = 'Approved by admin';
                this.modalOpen = true;
            },
            
            rejectVideo(id) {
                this.modalAction = 'reject';
                this.modalVideoId = id;
                this.modalTitle = 'Reject Video';
                this.modalReason = '';
                this.modalOpen = true;
            },
            
            async confirmVideoAction() {
                const url = this.modalAction === 'approve'
                    ? '/api/moderation/approve/' + this.modalVideoId
                    : '/api/moderation/reject/' + this.modalVideoId;
                const formData = new FormData();
                formData.append('reason', this.modalReason);
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch(url, { method: 'POST', body: formData });
                    if (!res.ok) throw new Error('Failed');
                    this.modalOpen = false;
                    location.reload();
                } catch (e) {
                    alert('Action failed');
                }
            },
            
            // User actions
            deleteUser(id, name) {
                this.openDeleteModal('user', id, `Delete user "${name}"? This will also delete all their videos.`);
            },
            
            async doDeleteUser() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/users/delete/' + this.deleteId, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.users = this.users.filter(u => u.id !== this.deleteId);
                        this.showToast('User deleted successfully');
                    } else {
                        this.showToast(data.error || 'Failed to delete user', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete user', 'error');
                }
            },
            
            // Season actions
            editSeason(s) {
                this.editSeasonForm = { ...s };
                this.seasonModalOpen = true;
            },
            
            async createSeason() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                Object.keys(this.newSeason).forEach(k => formData.append(k, this.newSeason[k]));
                try {
                    const res = await fetch('/api/admin/seasons/create', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Season created successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to create season', 'error');
                }
            },
            
            async updateSeason() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                Object.keys(this.editSeasonForm).forEach(k => {
                    if (k !== 'id') formData.append(k, this.editSeasonForm[k]);
                });
                try {
                    const res = await fetch('/api/admin/seasons/update/' + this.editSeasonForm.id, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.seasonModalOpen = false;
                        this.showToast('Season updated successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to update season', 'error');
                }
            },

            // Script actions
            editScript(sc) {
                this.editingScript = sc.id;
                this.scriptForm = {
                    title: sc.title,
                    content: sc.content,
                    category: sc.category,
                    difficulty: sc.difficulty,
                    duration_hint: sc.duration_hint || ''
                };
            },
            
            cancelEditScript() {
                this.editingScript = null;
                this.scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '' };
            },
            
            async createScript() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                Object.keys(this.scriptForm).forEach(k => formData.append(k, this.scriptForm[k]));
                try {
                    const res = await fetch('/api/admin/scripts/create', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Script created successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to create script', 'error');
                }
            },
            
            async updateScript() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                Object.keys(this.scriptForm).forEach(k => formData.append(k, this.scriptForm[k]));
                try {
                    const res = await fetch('/api/admin/scripts/update/' + this.editingScript, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Script updated successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to update script', 'error');
                }
            },
            
            async doDeleteScript() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/scripts/delete/' + this.deleteId, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.scripts = this.scripts.filter(s => s.id !== this.deleteId);
                        this.showToast('Script deleted successfully');
                    } else {
                        this.showToast(data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete script', 'error');
                }
            },
            
            // Guide actions
            async saveGuide(role) {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('role', role);
                formData.append('content', this.guides[role]);
                try {
                    const res = await fetch('/api/admin/guides/update', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(`${role.charAt(0).toUpperCase() + role.slice(1)} guide saved!`);
                    } else {
                        this.showToast(data.error || 'Failed to save guide', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to save guide', 'error');
                }
            },
            
            // AI Settings actions
            async saveAISetting(key, value) {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append(key, value);
                try {
                    const res = await fetch('/api/admin/ai/config/update', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('AI setting saved');
                    } else {
                        this.showToast(data.error || 'Failed to save setting', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to save AI setting', 'error');
                }
            },
            
            async toggleAISetting(key) {
                const newValue = this.aiSettings[key] === '1' ? '0' : '1';
                this.aiSettings[key] = newValue;
                await this.saveAISetting(key, newValue);
            },
            
            async testAIConnection() {
                this.showToast('Testing AI providers...');
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/ai/test', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        const details = data.result.details;
                        this.testResults = details;
                        this.testResultsOpen = true;
                        this.showToast(data.result.status);
                    } else {
                        this.showToast('AI test failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to test AI connection', 'error');
                }
            },
            
            // YouTube publish
            async publishToYouTube(videoId) {
                this.showToast('Publishing to YouTube...');
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/youtube/publish/' + videoId, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Video published to YouTube!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.showToast('YouTube publish failed: ' + (data.error || 'Check YouTube API settings'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to publish to YouTube', 'error');
                }
            },
            
            // API Keys management
            async loadAPIKeys() {
                try {
                    const res = await fetch('/api/admin/ai/keys');
                    const data = await res.json();
                    if (data.success) {
                        this.apiKeyStatus = data.keys;
                    }
                } catch (e) {
                    console.error('Failed to load API key status', e);
                }
            },
            
            async saveAPIKeys() {
                // Filter out empty values
                const keysToUpdate = {};
                for (const [key, value] of Object.entries(this.apiKeyForm)) {
                    if (value && value.trim() !== '') {
                        keysToUpdate[key] = value.trim();
                    }
                }
                
                if (Object.keys(keysToUpdate).length === 0) {
                    this.showToast('No changes to save', 'error');
                    return;
                }
                
                this.savingKeys = true;
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                for (const [key, value] of Object.entries(keysToUpdate)) {
                    formData.append(key, value);
                }
                
                try {
                    const res = await fetch('/api/admin/ai/keys/update', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('API keys saved! Refreshing...', 'success');
                        // Clear form
                        this.apiKeyForm = {
                            AZURE_CONTENT_SAFETY_ENDPOINT: '',
                            AZURE_CONTENT_SAFETY_KEY: '',
                            OPENAI_API_KEY: '',
                            SIGHTENGINE_API_USER: '',
                            SIGHTENGINE_API_SECRET: '',
                            RAPIDAPI_KEY: '',
                            GROQ_API_KEY: '',
                            FFMPEG_PATH: '',
                            FFPROBE_PATH: '',
                            YOUTUBE_API_KEY: '',
                            YOUTUBE_CLIENT_ID: '',
                            YOUTUBE_CLIENT_SECRET: '',
                            YOUTUBE_REFRESH_TOKEN: '',
                            YOUTUBE_CHANNEL_ID: ''
                        };
                        // Auto refresh after short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showToast(data.error || 'Failed to save API keys', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to save API keys', 'error');
                } finally {
                    this.savingKeys = false;
                }
            }
        };
    }
    </script>
</body>
</html>

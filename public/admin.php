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

// Load public submissions (new no-login system)
$allSubmissions = [];
$submissionCounts = ['new' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
$submissionRoles  = ['actor' => 0, 'director' => 0, 'writer' => 0];
$submissionTotal  = 0;
$submissionTableMissing = false;
try {
    $submissionModel = new App\Models\Submission();
    if ($submissionModel->tableExists()) {
        $allSubmissions   = $submissionModel->all();
        $submissionCounts = $submissionModel->countByStatus();
        $submissionRoles  = $submissionModel->countByRole();
        $submissionTotal  = $submissionModel->totalCount();
    } else {
        $submissionTableMissing = true;
    }
} catch (\Exception $e) {
    $submissionTableMissing = true;
}

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
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Plyr Video Player -->
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
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
    <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        [x-cloak] { display: none !important; }
        .log-viewer { font-family: 'Monaco', 'Menlo', monospace; font-size: 11px; }
        .sidebar-link { transition: all 0.15s ease; }
        .sidebar-link:hover { background: #FEF2F2; }
        .sidebar-link.active { background: #FEF2F2; border-left: 3px solid #D92B3A; color: #D92B3A; }
        .stat-card { background: #F1EFE8; }
        /* Custom Plyr colors */
        :root {
            --plyr-color-main: #D92B3A;
        }
        .plyr { border-radius: 12px; }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F8F5F0; }
        ::-webkit-scrollbar-thumb { background: #D92B3A33; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #D92B3A66; }
        
        /* Mobile-first responsive fixes */
        html { overflow-x: hidden; }
        body { overflow-x: hidden; max-width: 100vw; }
        
        /* Prevent horizontal scroll on mobile */
        .mobile-safe { max-width: 100%; overflow-x: hidden; }
        
        /* Mobile table cards */
        @media (max-width: 767px) {
            .mobile-card-view tbody tr {
                display: block;
                padding: 1rem;
                margin-bottom: 0.75rem;
                background: white;
                border-radius: 0.75rem;
                border: 1px solid rgba(13, 13, 13, 0.05);
            }
            .mobile-card-view thead { display: none; }
            .mobile-card-view tbody { display: block; }
            .mobile-card-view td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.5rem 0;
                border: none !important;
                gap: 0.5rem;
            }
            .mobile-card-view td::before {
                content: attr(data-label);
                font-weight: 500;
                font-size: 11px;
                color: rgba(13, 13, 13, 0.5);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                flex-shrink: 0;
                min-width: 80px;
            }
            .mobile-card-view td:last-child {
                border-top: 1px solid rgba(13, 13, 13, 0.05);
                padding-top: 0.75rem;
                margin-top: 0.25rem;
            }
            
            /* Fix text overflow */
            .mobile-truncate {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 100%;
            }
            
            /* Stack action buttons */
            .mobile-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            /* Fix stat cards text */
            .stat-card p {
                word-break: break-word;
            }
            
            /* Ensure proper spacing for bottom nav */
            main { padding-bottom: 5rem !important; }
        }
        
        /* Tablet adjustments */
        @media (min-width: 768px) and (max-width: 1023px) {
            .tablet-scroll-x {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* Form input mobile fixes */
        @media (max-width: 640px) {
            input, select, textarea {
                font-size: 16px !important; /* Prevent iOS zoom */
            }
        }
        
        /* YouTube tab mobile fixes */
        @media (max-width: 767px) {
            /* Make code blocks wrap properly */
            .youtube-guide code {
                word-break: break-all;
                white-space: pre-wrap;
                display: inline-block;
                max-width: 100%;
            }
            
            /* Fix long URLs in steps */
            .youtube-guide a {
                word-break: break-all;
            }
            
            /* Ensure guide steps don't overflow */
            .youtube-guide ol {
                padding-left: 1rem;
            }
            
            /* Stack Connection Status header on mobile */
            .youtube-status-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 0.75rem !important;
            }
            
            /* Full width test button on mobile */
            .youtube-test-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Tablet adjustments for YouTube */
        @media (min-width: 768px) and (max-width: 1023px) {
            .youtube-guide code {
                word-break: break-all;
                white-space: pre-wrap;
            }
        }
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
            <button @click="activeTab = 'submissions'" :class="activeTab === 'submissions' ? 'text-crimson' : 'text-dark/40'" class="flex flex-col items-center gap-1 px-3 py-2 relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span class="text-[10px] font-medium">Auditions</span>
                <span x-show="submissionCounts.new > 0" class="absolute top-1 right-1 bg-crimson text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center" x-text="submissionCounts.new > 9 ? '9+' : submissionCounts.new"></span>
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
                    <span class="font-display text-[20px] text-dark">FACELESS PICTURES</span>
                    <span style="display: inline-flex; align-items: center; justify-content: center; background: #D92B3A; color: white; font-size: 10px; font-weight: bold; width: 20px; height: 20px; border-radius: 50%;">3</span>
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
                <button @click="activeTab = 'submissions'; mobileMenuOpen = false" :class="activeTab === 'submissions' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Auditions
                    <span x-show="submissionCounts.new > 0" class="ml-auto bg-crimson text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full" x-text="submissionCounts.new"></span>
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
                <button @click="activeTab = 'email'; mobileMenuOpen = false" :class="activeTab === 'email' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Email
                </button>
                <button @click="activeTab = 'google'; mobileMenuOpen = false" :class="activeTab === 'google' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-5 py-3 text-[14px] text-dark/70">
                    <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    Google Login
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
                    <span class="font-display text-[18px] text-dark whitespace-nowrap">FACELESS PICTURES</span>
                    <span style="display: inline-flex; align-items: center; justify-content: center; background: #D92B3A; color: white; font-size: 9px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%;">3</span>
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

                <button @click="activeTab = 'submissions'" :class="activeTab === 'submissions' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Auditions</span>
                    <span x-show="submissionCounts.new > 0" class="ml-auto bg-crimson text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full lg:flex hidden" :class="sidebarCollapsed ? 'hidden' : 'lg:flex'" x-text="submissionCounts.new"></span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Auditions</span>
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
                
                <button @click="activeTab = 'email'" :class="activeTab === 'email' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Email</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Email</span>
                </button>
                
                <button @click="activeTab = 'google'" :class="activeTab === 'google' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Google</span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Google</span>
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
                        <!-- Live refresh indicator -->
                        <span class="hidden sm:inline-flex items-center gap-1.5 text-[10px] bg-green-50 text-green-600 px-2 py-0.5 rounded-full" :class="{'opacity-50': isRefreshing}">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            <span x-text="isRefreshing ? 'Refreshing...' : 'Live'"></span>
                        </span>
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
            <div class="p-4 md:p-6 mobile-safe">

                <!-- ==================== OVERVIEW TAB ==================== -->
                <div x-show="activeTab === 'overview'" x-cloak>
                    <!-- Stat Cards - Mobile: 2 columns, Desktop: 4 columns (REACTIVE) -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
                        <div class="stat-card rounded-xl p-3 md:p-4">
                            <p class="text-[10px] md:text-[11px] text-dark/40 uppercase tracking-wider mb-1">Total Users</p>
                            <p class="font-display text-[24px] md:text-[32px] text-dark leading-none" x-text="users.length"></p>
                        </div>
                        <div class="stat-card rounded-xl p-3 md:p-4">
                            <p class="text-[10px] md:text-[11px] text-dark/40 uppercase tracking-wider mb-1">Pending</p>
                            <p class="font-display text-[24px] md:text-[32px] text-amber-600 leading-none" x-text="videos.filter(v => v.status === 'pending').length"></p>
                        </div>
                        <div class="stat-card rounded-xl p-3 md:p-4">
                            <p class="text-[10px] md:text-[11px] text-dark/40 uppercase tracking-wider mb-1">AI Flagged</p>
                            <p class="font-display text-[24px] md:text-[32px] text-crimson leading-none" x-text="videos.filter(v => v.needs_manual_review == 1).length"></p>
                        </div>
                        <div class="stat-card rounded-xl p-3 md:p-4">
                            <p class="text-[10px] md:text-[11px] text-dark/40 uppercase tracking-wider mb-1">Active Season</p>
                            <p class="font-display text-[14px] md:text-[18px] text-teal-600 leading-tight truncate" x-text="activeSeason ? activeSeason.title : 'None'"></p>
                        </div>
                    </div>

                    <!-- New Auditions Banner -->
                    <template x-if="submissionCounts.new > 0">
                        <div class="mb-5 bg-gold/10 border border-gold/20 rounded-xl px-4 py-3 flex items-center gap-3 cursor-pointer hover:bg-gold/15 transition" @click="activeTab = 'submissions'">
                            <span class="bg-gold text-dark text-xs font-bold px-2 py-0.5 rounded-full" x-text="submissionCounts.new + ' New'"></span>
                            <p class="text-sm text-dark/70">Audition submissions are waiting for review. <span class="text-gold font-semibold">View Auditions →</span></p>
                        </div>
                    </template>

                    <!-- AI Flagged Videos - Reactive (LIVE UPDATES) -->
                    <template x-if="videos.filter(v => v.needs_manual_review == 1).length > 0">
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-4 md:px-5 py-3 md:py-4 border-b border-dark/5 bg-red-50/50">
                            <h2 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                                <span class="hidden sm:inline">AI Flagged — Manual Review</span>
                                <span class="sm:hidden">Flagged</span>
                                <span class="bg-crimson text-white text-[10px] px-2 py-0.5 rounded-full" x-text="videos.filter(v => v.needs_manual_review == 1).length"></span>
                            </h2>
                        </div>
                        
                        <!-- Mobile Card View -->
                        <div class="md:hidden p-3 space-y-3">
                            <template x-for="v in videos.filter(v => v.needs_manual_review == 1)" :key="v.id">
                            <div class="bg-cream/30 rounded-xl p-4 border border-dark/5">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 bg-crimson/10 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-crimson font-semibold text-[10px]" x-text="(v.user_name || 'U').charAt(0).toUpperCase()"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-dark text-[13px] truncate" x-text="v.user_name"></p>
                                            <p class="text-[11px] text-dark/50 truncate" x-text="v.title"></p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0" 
                                          :class="(v.ai_score || 0) >= 60 ? 'bg-green-100 text-green-700' : ((v.ai_score || 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')"
                                          x-text="v.ai_score !== null ? Math.round(v.ai_score) : 'N/A'"></span>
                                </div>
                                
                                <template x-if="v.ai_feedback && (typeof v.ai_feedback === 'object' ? v.ai_feedback.flags : (JSON.parse(v.ai_feedback || '{}').flags || [])).length > 0">
                                <div class="flex flex-wrap gap-1 mb-3">
                                    <template x-for="flag in (typeof v.ai_feedback === 'object' ? v.ai_feedback.flags : (JSON.parse(v.ai_feedback || '{}').flags || [])).slice(0, 3)" :key="flag">
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700" x-text="flag"></span>
                                    </template>
                                </div>
                                </template>
                                
                                <div class="flex gap-2">
                                    <button @click="openVideoDetail(v.id, v.title, v.file_path, v.ai_feedback, v.ai_score)" class="flex-1 bg-dark/10 text-dark/60 px-3 py-2 rounded-lg text-[11px] font-medium hover:bg-dark/20 transition text-center">Details</button>
                                    <button @click="approveVideo(v.id)" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg text-[11px] font-medium hover:bg-green-700 transition">Approve</button>
                                    <button @click="rejectVideo(v.id)" class="flex-1 bg-crimson text-white px-3 py-2 rounded-lg text-[11px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                </div>
                            </div>
                            </template>
                        </div>
                        
                        <!-- Desktop Table View -->
                        <div class="hidden md:block overflow-x-auto">
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
                                    <template x-for="v in videos.filter(v => v.needs_manual_review == 1)" :key="v.id">
                                    <tr class="hover:bg-cream/30 transition">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-crimson/10 rounded-full flex items-center justify-center">
                                                    <span class="text-crimson font-semibold text-[10px]" x-text="(v.user_name || 'U').charAt(0).toUpperCase()"></span>
                                                </div>
                                                <span class="font-medium text-dark" x-text="v.user_name"></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-dark/70" x-text="v.title"></span>
                                            <template x-if="v.video_duration">
                                                <span class="text-[10px] text-dark/40 ml-1" x-text="'(' + formatDuration(v.video_duration) + ')'"></span>
                                            </template>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="v.content_type || v.user_role || 'N/A'"></span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium" 
                                                  :class="(v.ai_score || 0) >= 60 ? 'bg-green-100 text-green-700' : ((v.ai_score || 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')"
                                                  x-text="v.ai_score !== null ? Math.round(v.ai_score) : 'N/A'"></span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex flex-wrap gap-1 max-w-[200px]">
                                                <template x-if="v.ai_feedback && (typeof v.ai_feedback === 'object' ? v.ai_feedback.flags : (JSON.parse(v.ai_feedback || '{}').flags || [])).length > 0">
                                                    <template x-for="flag in (typeof v.ai_feedback === 'object' ? v.ai_feedback.flags : (JSON.parse(v.ai_feedback || '{}').flags || []))" :key="flag">
                                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700" x-text="flag"></span>
                                                    </template>
                                                </template>
                                                <template x-if="!v.ai_feedback || (typeof v.ai_feedback === 'object' ? !v.ai_feedback.flags : !(JSON.parse(v.ai_feedback || '{}').flags || []).length)">
                                                    <span class="text-[10px] text-dark/40" x-text="(typeof v.ai_feedback === 'object' ? v.ai_feedback?.summary : (JSON.parse(v.ai_feedback || '{}').summary)) || 'Review required'"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex gap-2">
                                                <button @click="openVideoDetail(v.id, v.title, v.file_path, v.ai_feedback, v.ai_score)" class="bg-dark/10 text-dark/60 px-2 py-1 rounded text-[10px] font-medium hover:bg-dark/20 transition">Details</button>
                                                <button @click="approveVideo(v.id)" class="bg-green-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-green-700 transition">✓</button>
                                                <button @click="rejectVideo(v.id)" class="bg-crimson text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-crimson/90 transition">✗</button>
                                            </div>
                                        </td>
                                    </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </template>

                    <!-- AI Processing - Reactive (LIVE UPDATES) -->
                    <template x-if="videos.filter(v => (v.ai_status || '') === 'processing').length > 0">
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-5 py-4 border-b border-dark/5 bg-blue-50/50">
                            <h2 class="font-semibold text-dark flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                AI Processing
                                <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded-full" x-text="videos.filter(v => (v.ai_status || '') === 'processing').length"></span>
                            </h2>
                        </div>
                        <div class="p-4">
                            <div class="grid gap-2">
                                <template x-for="v in videos.filter(v => (v.ai_status || '') === 'processing').slice(0, 5)" :key="v.id">
                                <div class="flex items-center justify-between p-3 bg-cream/50 rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                        </div>
                                        <div>
                                            <p class="text-[13px] font-medium text-dark" x-text="v.title"></p>
                                            <p class="text-[11px] text-dark/40">by <span x-text="v.user_name"></span> • <span x-text="v.content_type || 'video'"></span></p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-blue-600 font-medium">Analyzing...</span>
                                </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    </template>

                    <!-- Pending Videos - Reactive (LIVE UPDATES) -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mb-6">
                        <div class="px-4 md:px-5 py-3 md:py-4 border-b border-dark/5 flex items-center justify-between">
                            <h2 class="font-semibold text-dark text-[14px] md:text-base">Pending Videos</h2>
                            <span class="bg-amber-100 text-amber-700 text-[10px] px-2 py-0.5 rounded-full font-medium" x-text="videos.filter(v => v.status === 'pending').length"></span>
                        </div>
                        
                        <!-- Mobile Card View -->
                        <div class="md:hidden p-3 space-y-3">
                            <template x-if="videos.filter(v => v.status === 'pending').length === 0">
                                <p class="text-center text-dark/30 py-8 text-[13px]">No pending videos</p>
                            </template>
                            <template x-for="v in videos.filter(v => v.status === 'pending')" :key="v.id">
                            <div class="bg-cream/30 rounded-xl p-4 border border-dark/5">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-dark text-[13px] truncate" x-text="v.user_name"></p>
                                        <p class="text-[12px] text-dark/70 truncate" x-text="v.title"></p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="v.content_type || 'N/A'"></span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full" 
                                                  :class="{
                                                      'bg-green-100 text-green-700': (v.ai_status || 'pending') === 'approved',
                                                      'bg-blue-100 text-blue-700': (v.ai_status || 'pending') === 'processing',
                                                      'bg-amber-100 text-amber-700': (v.ai_status || 'pending') === 'flagged',
                                                      'bg-red-100 text-red-700': (v.ai_status || 'pending') === 'rejected',
                                                      'bg-dark/5 text-dark/40': !['approved','processing','flagged','rejected'].includes(v.ai_status || 'pending')
                                                  }"
                                                  x-text="(v.ai_status || 'pending').charAt(0).toUpperCase() + (v.ai_status || 'pending').slice(1)"></span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-3" x-text="formatDate(v.created_at)"></p>
                                <div class="flex gap-2">
                                    <button @click="approveVideo(v.id)" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg text-[11px] font-medium hover:bg-green-700 transition">Approve</button>
                                    <button @click="rejectVideo(v.id)" class="flex-1 bg-crimson text-white px-3 py-2 rounded-lg text-[11px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                </div>
                            </div>
                            </template>
                        </div>
                        
                        <!-- Desktop Table View -->
                        <div class="hidden md:block overflow-x-auto">
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
                                    <template x-if="videos.filter(v => v.status === 'pending').length === 0">
                                        <tr><td colspan="6" class="px-5 py-10 text-center text-dark/30">No pending videos</td></tr>
                                    </template>
                                    <template x-for="v in videos.filter(v => v.status === 'pending')" :key="v.id">
                                    <tr class="hover:bg-cream/30 transition">
                                        <td class="px-5 py-3 font-medium text-dark" x-text="v.user_name"></td>
                                        <td class="px-5 py-3 text-dark/70" x-text="v.title"></td>
                                        <td class="px-5 py-3">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="v.content_type || 'N/A'"></span>
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="text-[10px] px-2 py-0.5 rounded-full" 
                                                  :class="{
                                                      'bg-green-100 text-green-700': (v.ai_status || 'pending') === 'approved',
                                                      'bg-blue-100 text-blue-700': (v.ai_status || 'pending') === 'processing',
                                                      'bg-amber-100 text-amber-700': (v.ai_status || 'pending') === 'flagged',
                                                      'bg-red-100 text-red-700': (v.ai_status || 'pending') === 'rejected',
                                                      'bg-dark/5 text-dark/40': !['approved','processing','flagged','rejected'].includes(v.ai_status || 'pending')
                                                  }"
                                                  x-text="(v.ai_status || 'pending').charAt(0).toUpperCase() + (v.ai_status || 'pending').slice(1)"></span>
                                        </td>
                                        <td class="px-5 py-3 text-dark/40" x-text="formatDate(v.created_at)"></td>
                                        <td class="px-5 py-3">
                                            <div class="flex gap-2">
                                                <button @click="approveVideo(v.id)" class="bg-green-600 text-white px-3 py-1 rounded-lg text-[11px] font-medium hover:bg-green-700 transition">Approve</button>
                                                <button @click="rejectVideo(v.id)" class="bg-crimson text-white px-3 py-1 rounded-lg text-[11px] font-medium hover:bg-crimson/90 transition">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Quick Widgets Row - Stack on mobile -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                <div x-show="activeTab === 'videos'" x-cloak x-init="silentRefreshVideos()">
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-2 mb-4 items-center">
                        <button @click="videoFilter = 'all'" :class="videoFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">All</button>
                        <button @click="videoFilter = 'pending'" :class="videoFilter === 'pending' ? 'bg-amber-500 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Pending</button>
                        <button @click="videoFilter = 'approved'" :class="videoFilter === 'approved' ? 'bg-green-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Approved</button>
                        <button @click="videoFilter = 'rejected'" :class="videoFilter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Rejected</button>
                        <button @click="videoFilter = 'flagged'" :class="videoFilter === 'flagged' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition hidden sm:block">Flagged</button>
                        
                        <!-- Manual Refresh Button -->
                        <button @click="silentRefreshVideos()" class="text-dark/40 hover:text-dark p-1.5 rounded-lg hover:bg-white transition" title="Refresh">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                        
                        <!-- Auto-refresh indicator -->
                        <span class="text-[10px] text-dark/30 hidden sm:inline">Auto-refresh: 10s</span>
                        
                        <!-- Bulk Delete Button -->
                        <template x-if="selectedVideos.length > 0">
                            <button @click="bulkDeleteVideos()" class="ml-auto bg-red-600 text-white px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium hover:bg-red-700 transition flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                (<span x-text="selectedVideos.length"></span>)
                            </button>
                        </template>
                    </div>

                    <!-- Mobile Card View for Videos -->
                    <div class="md:hidden space-y-3">
                        <template x-for="v in filteredVideos" :key="v.id">
                            <div class="bg-white rounded-xl border border-dark/5 p-4">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <input type="checkbox" :value="v.id" x-model="selectedVideos" class="rounded border-dark/20 flex-shrink-0">
                                        <div class="min-w-0">
                                            <p class="font-medium text-dark text-[13px] truncate" x-text="v.user_name"></p>
                                            <p class="text-[11px] text-dark/50 truncate" x-text="v.season_title"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="(v.ai_score || 0) >= 60 ? 'bg-green-100 text-green-700' : ((v.ai_score || 0) >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')" x-text="v.ai_score || 'N/A'"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full" :class="{'bg-amber-100 text-amber-700': v.status === 'pending', 'bg-green-100 text-green-700': v.status === 'approved', 'bg-red-100 text-red-700': v.status === 'rejected'}" x-text="v.status"></span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-if="v.status === 'pending'">
                                        <button @click="approveVideo(v.id)" class="flex-1 bg-green-600 text-white px-3 py-2 rounded-lg text-[11px] font-medium">Approve</button>
                                    </template>
                                    <template x-if="v.status === 'pending'">
                                        <button @click="rejectVideo(v.id)" class="flex-1 bg-crimson text-white px-3 py-2 rounded-lg text-[11px] font-medium">Reject</button>
                                    </template>
                                    <button @click="openVideoDetail(v.id, v.title, v.file_path, v.ai_feedback ? (typeof v.ai_feedback === 'string' ? JSON.parse(v.ai_feedback) : v.ai_feedback) : {}, v.ai_score)" class="bg-blue-100 text-blue-700 px-3 py-2 rounded-lg text-[11px] font-medium">Details</button>
                                </div>
                            </div>
                        </template>
                        <div x-show="filteredVideos.length === 0" class="bg-white rounded-xl border border-dark/5 p-8 text-center text-dark/30 text-[13px]">No videos found</div>
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden md:block bg-white rounded-xl border border-dark/5 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-3 py-3 text-left font-medium">
                                            <input type="checkbox" @change="toggleAllVideos($event)" class="rounded border-dark/20">
                                        </th>
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
                                            <td class="px-3 py-3">
                                                <input type="checkbox" :value="v.id" x-model="selectedVideos" class="rounded border-dark/20">
                                            </td>
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
                                                <div class="flex gap-2 flex-wrap">
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
                                                    <button @click="openVideoDetail(v.id, v.title, v.file_path, v.ai_feedback ? (typeof v.ai_feedback === 'string' ? JSON.parse(v.ai_feedback) : v.ai_feedback) : {}, v.ai_score)" class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-[10px] font-medium hover:bg-blue-200 transition">Details</button>
                                                    <button @click="deleteVideo(v.id, v.title)" class="bg-red-100 text-red-700 px-2 py-1 rounded text-[10px] font-medium hover:bg-red-200 transition" title="Delete video permanently">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredVideos.length === 0">
                                        <td colspan="9" class="px-5 py-10 text-center text-dark/30">No videos found</td>
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
                        <button @click="userFilter = 'all'" :class="userFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">All</button>
                        <button @click="userFilter = 'actor'" :class="userFilter === 'actor' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Actors</button>
                        <button @click="userFilter = 'director'" :class="userFilter === 'director' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Directors</button>
                        <button @click="userFilter = 'writer'" :class="userFilter === 'writer' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition">Writers</button>
                    </div>

                    <!-- Mobile Card View for Users -->
                    <div class="md:hidden space-y-3">
                        <template x-for="u in filteredUsers" :key="u.id">
                            <div class="bg-white rounded-xl border border-dark/5 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-10 h-10 bg-crimson/10 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-crimson font-semibold text-[14px]" x-text="u.name.charAt(0).toUpperCase()"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium text-dark text-[14px] truncate" x-text="u.name"></p>
                                                <template x-if="u.is_admin == 1">
                                                    <span class="text-[9px] bg-crimson text-white px-1.5 py-0.5 rounded-full flex-shrink-0">ADMIN</span>
                                                </template>
                                            </div>
                                            <p class="text-[12px] text-dark/50 truncate" x-text="u.email"></p>
                                        </div>
                                    </div>
                                    <template x-if="u.is_admin != 1">
                                        <button @click="deleteUser(u.id, u.name)" class="text-crimson text-[12px] flex-shrink-0">Delete</button>
                                    </template>
                                </div>
                                <div class="flex items-center gap-2 mt-3">
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="u.role"></span>
                                    <span class="text-[11px] text-dark/40" x-text="'Joined ' + new Date(u.created_at).toLocaleDateString()"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="filteredUsers.length === 0" class="bg-white rounded-xl border border-dark/5 p-8 text-center text-dark/30 text-[13px]">No users found</div>
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden md:block bg-white rounded-xl border border-dark/5 overflow-hidden">
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

                <!-- ==================== SUBMISSIONS TAB ==================== -->
                <div x-show="activeTab === 'submissions'" x-cloak>
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
                        <div>
                            <h2 class="font-display text-[28px] text-dark">AUDITION SUBMISSIONS</h2>
                            <p class="text-dark/40 text-sm">Public submissions from /actor, /director, /writer pages</p>
                        </div>
                        <!-- Stats pills -->
                        <div class="flex flex-wrap gap-2">
                            <span class="bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1 rounded-full">New: <span x-text="submissionCounts.new"></span></span>
                            <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">Reviewed: <span x-text="submissionCounts.reviewed"></span></span>
                            <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">Shortlisted: <span x-text="submissionCounts.shortlisted"></span></span>
                            <span class="bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full">Rejected: <span x-text="submissionCounts.rejected"></span></span>
                        </div>
                    </div>

                    <?php if ($submissionTableMissing): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                        <div class="text-3xl mb-3">⚠️</div>
                        <h3 class="font-semibold text-amber-800 mb-2">Database Migration Required</h3>
                        <p class="text-amber-700 text-sm">Run <code class="bg-amber-100 px-2 py-0.5 rounded">database/migrations/006_add_submissions_table.sql</code> to enable the Auditions system.</p>
                    </div>
                    <?php else: ?>

                    <!-- Filters -->
                    <div class="bg-white rounded-xl border border-dark/5 p-4 mb-5">
                        <div class="flex flex-wrap gap-3">
                            <input type="text" x-model="submissionSearch" @input.debounce.300ms="filterSubmissions()"
                                placeholder="Search name, email, phone..."
                                class="flex-1 min-w-[180px] border border-dark/15 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30">
                            <select x-model="submissionRoleFilter" @change="filterSubmissions()" class="border border-dark/15 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none">
                                <option value="">All Roles</option>
                                <option value="actor">Actor</option>
                                <option value="director">Director</option>
                                <option value="writer">Writer</option>
                            </select>
                            <select x-model="submissionStatusFilter" @change="filterSubmissions()" class="border border-dark/15 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none">
                                <option value="">All Status</option>
                                <option value="new">New</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="shortlisted">Shortlisted</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden mobile-safe">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm mobile-card-view">
                                <thead class="bg-dark/[0.03] border-b border-dark/5">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider">Applicant</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider">Role / Type</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider hidden md:table-cell">Contact</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider hidden lg:table-cell">File</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider">Status</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold text-dark/40 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <template x-for="sub in filteredSubmissions" :key="sub.id">
                                        <tr class="hover:bg-dark/[0.02] transition">
                                            <td class="px-5 py-3" data-label="Applicant">
                                                <div>
                                                    <p class="font-medium text-dark truncate max-w-[160px]" x-text="sub.name"></p>
                                                    <p class="text-xs text-dark/40" x-text="formatDate(sub.submitted_at)"></p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3" data-label="Role">
                                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full mb-1"
                                                    :class="{
                                                        'bg-red-100 text-red-700': sub.role==='actor',
                                                        'bg-amber-100 text-amber-700': sub.role==='director',
                                                        'bg-blue-100 text-blue-700': sub.role==='writer'
                                                    }" x-text="sub.role"></span>
                                                <p class="text-xs text-dark/50" x-text="sub.audition_type"></p>
                                            </td>
                                            <td class="px-5 py-3 hidden md:table-cell" data-label="Contact">
                                                <p class="text-xs text-dark/70" x-text="sub.email"></p>
                                                <p class="text-xs text-dark/50" x-text="sub.phone"></p>
                                            </td>
                                            <td class="px-5 py-3 hidden lg:table-cell" data-label="File">
                                                <template x-if="sub.file_path">
                                                    <a :href="'/uploads/'+sub.file_path" target="_blank"
                                                        class="text-xs text-crimson hover:underline flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        <span x-text="sub.file_type ? sub.file_type.toUpperCase() : 'File'"></span>
                                                    </a>
                                                </template>
                                                <template x-if="!sub.file_path">
                                                    <span class="text-xs text-dark/30">No file</span>
                                                </template>
                                            </td>
                                            <td class="px-5 py-3" data-label="Status">
                                                <select :value="sub.status" @change="updateSubmissionStatus(sub.id, $event.target.value)"
                                                    class="text-xs border border-dark/15 rounded px-2 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-gold/40">
                                                    <option value="new">New</option>
                                                    <option value="reviewed">Reviewed</option>
                                                    <option value="shortlisted">Shortlisted ⭐</option>
                                                    <option value="rejected">Rejected</option>
                                                </select>
                                            </td>
                                            <td class="px-5 py-3" data-label="Actions">
                                                <div class="flex items-center gap-2">
                                                    <button @click="viewSubmission(sub)"
                                                        class="text-xs text-dark/50 hover:text-dark bg-dark/5 hover:bg-dark/10 px-2 py-1 rounded transition">
                                                        View
                                                    </button>
                                                    <button @click="confirmDeleteSubmission(sub.id)"
                                                        class="text-xs text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1 rounded transition">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredSubmissions.length === 0">
                                        <td colspan="6" class="px-5 py-12 text-center text-dark/30">
                                            <div class="text-3xl mb-2">📋</div>
                                            <p>No submissions found</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- View Submission Modal -->
                    <div x-show="viewingSubmission" x-cloak @click.self="viewingSubmission=null"
                        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100">
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
                            <div class="p-6 border-b border-dark/5 flex items-center justify-between">
                                <h3 class="font-semibold text-dark">Submission Details</h3>
                                <button @click="viewingSubmission=null" class="text-dark/40 hover:text-dark">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <template x-if="viewingSubmission">
                                <div class="p-6 space-y-4 text-sm">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Name</p><p class="font-medium" x-text="viewingSubmission.name"></p></div>
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Role</p><p class="font-medium capitalize" x-text="viewingSubmission.role+' — '+viewingSubmission.audition_type"></p></div>
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Email</p><p x-text="viewingSubmission.email"></p></div>
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Phone</p><p x-text="viewingSubmission.phone"></p></div>
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Submitted</p><p x-text="formatDate(viewingSubmission.submitted_at)"></p></div>
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Status</p>
                                            <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                                :class="{'bg-amber-100 text-amber-700':viewingSubmission.status==='new','bg-blue-100 text-blue-700':viewingSubmission.status==='reviewed','bg-green-100 text-green-700':viewingSubmission.status==='shortlisted','bg-red-100 text-red-700':viewingSubmission.status==='rejected'}"
                                                x-text="viewingSubmission.status"></span>
                                        </div>
                                    </div>
                                    <template x-if="viewingSubmission.notes">
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Notes</p><p class="bg-dark/[0.03] rounded p-3" x-text="viewingSubmission.notes"></p></div>
                                    </template>
                                    <template x-if="viewingSubmission.script_title">
                                        <div><p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Script Used</p><p x-text="viewingSubmission.script_title"></p></div>
                                    </template>
                                    <template x-if="viewingSubmission.file_path">
                                        <div>
                                            <p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Uploaded File</p>
                                            <a :href="'/uploads/'+viewingSubmission.file_path" target="_blank"
                                                class="inline-flex items-center gap-2 bg-crimson/10 text-crimson px-3 py-2 rounded-lg text-sm font-medium hover:bg-crimson/20 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Download / View File
                                            </a>
                                        </div>
                                    </template>
                                    <div>
                                        <p class="text-dark/40 text-xs uppercase tracking-wider mb-1">Admin Notes</p>
                                        <textarea x-model="submissionAdminNotes" rows="3"
                                            class="w-full border border-dark/15 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30"
                                            placeholder="Internal notes for this submission..."></textarea>
                                        <button @click="saveSubmissionNotes(viewingSubmission.id)"
                                            class="mt-2 bg-dark text-white text-xs font-semibold px-4 py-2 rounded-lg hover:bg-dark/80 transition">
                                            Save Notes
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <?php endif; ?>
                </div>

                <!-- ==================== SEASONS TAB ==================== -->
                <div x-show="activeTab === 'seasons'" x-cloak>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Create Season Form -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 text-[14px] md:text-base">Create New Season</h3>
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
                            <div class="px-4 md:px-5 py-3 md:py-4 border-b border-dark/5">
                                <h3 class="font-semibold text-dark text-[14px] md:text-base">All Seasons</h3>
                            </div>
                            <div class="divide-y divide-dark/5">
                                <template x-for="s in seasons" :key="s.id">
                                    <div class="p-4 md:p-5 hover:bg-cream/30 transition">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                            <div class="min-w-0">
                                                <h4 class="font-medium text-dark truncate" x-text="s.title"></h4>
                                                <p class="text-[12px] text-dark/50 mt-1 line-clamp-2" x-text="s.brief || 'No description'"></p>
                                                <div class="flex items-center gap-3 mt-2 text-[11px] text-dark/40">
                                                    <span x-text="s.start_date + ' → ' + s.end_date"></span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
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
                    <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5 mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                <span class="hidden sm:inline">Role Guides (Shown on Create Screen)</span>
                                <span class="sm:hidden">Role Guides</span>
                            </h3>
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                <button @click="guideTab = 'actor'" :class="guideTab === 'actor' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition whitespace-nowrap">🎭 Actor</button>
                                <button @click="guideTab = 'director'" :class="guideTab === 'director' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition whitespace-nowrap">🎬 Director</button>
                                <button @click="guideTab = 'writer'" :class="guideTab === 'writer' ? 'bg-crimson text-white' : 'bg-cream text-dark/60'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition whitespace-nowrap">✍️ Writer</button>
                            </div>
                        </div>
                        
                        <!-- Actor Guide -->
                        <div x-show="guideTab === 'actor'" x-cloak>
                            <textarea x-model="guides.actor" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for actors..."></textarea>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('actor')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition w-full sm:w-auto">Save Actor Guide</button>
                            </div>
                        </div>
                        
                        <!-- Director Guide -->
                        <div x-show="guideTab === 'director'" x-cloak>
                            <textarea x-model="guides.director" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for directors..."></textarea>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('director')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition w-full sm:w-auto">Save Director Guide</button>
                            </div>
                        </div>
                        
                        <!-- Writer Guide -->
                        <div x-show="guideTab === 'writer'" x-cloak>
                            <textarea x-model="guides.writer" rows="5" class="w-full border border-dark/10 rounded-lg px-4 py-3 text-[13px] focus:outline-none focus:border-crimson resize-none font-mono" placeholder="Guide text for writers..."></textarea>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-3">
                                <p class="text-[11px] text-dark/40">Use **text** for bold. New lines create paragraphs.</p>
                                <button @click="saveGuide('writer')" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition w-full sm:w-auto">Save Writer Guide</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Create Script Form -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 text-[14px] md:text-base" x-text="editingScript ? 'Edit Script' : 'Create New Script'"></h3>
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
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Audition Type Label</label>
                                        <input type="text" x-model="scriptForm.audition_type" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="Dialog Audition / Song Audition / Scene Direction…">
                                        <p class="text-[11px] text-dark/30 mt-1">Shown as the badge on the script card</p>
                                    </div>
                                    <div x-data="scriptImagePicker()" x-init="init()">
                                        <label class="block text-[12px] text-dark/50 mb-1.5">Card Poster Image</label>

                                        <!-- Current image preview -->
                                        <div x-show="scriptForm.image_url" class="mb-2 relative rounded-lg overflow-hidden border border-dark/10" style="aspect-ratio:16/9;max-height:120px">
                                            <img :src="scriptForm.image_url" class="w-full h-full object-cover">
                                            <button type="button" @click="scriptForm.image_url=''"
                                                class="absolute top-1.5 right-1.5 w-6 h-6 bg-white/90 rounded-full flex items-center justify-center shadow hover:bg-white transition border border-dark/10">
                                                <svg class="w-3 h-3 text-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>

                                        <!-- Tabs: Upload / Gallery -->
                                        <div class="flex border border-dark/10 rounded-lg overflow-hidden mb-2 text-[12px]">
                                            <button type="button" @click="pickerTab='upload'"
                                                :class="pickerTab==='upload' ? 'bg-dark text-white' : 'bg-white text-dark/50 hover:bg-cream'"
                                                class="flex-1 py-2 font-medium transition flex items-center justify-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                                Upload New
                                            </button>
                                            <button type="button" @click="pickerTab='gallery'; loadGallery()"
                                                :class="pickerTab==='gallery' ? 'bg-dark text-white' : 'bg-white text-dark/50 hover:bg-cream'"
                                                class="flex-1 py-2 font-medium transition flex items-center justify-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                Pick Existing
                                                <span x-show="galleryImages.length" class="bg-white/20 text-white text-[10px] px-1.5 py-0.5 rounded-full" x-text="galleryImages.length"></span>
                                            </button>
                                        </div>

                                        <!-- UPLOAD TAB -->
                                        <div x-show="pickerTab==='upload'">
                                            <div
                                                class="border-2 rounded-xl transition-all cursor-pointer overflow-hidden"
                                                :class="uploadDragging ? 'border-dark bg-dark/5' : 'border-dashed border-dark/15 hover:border-dark/30 bg-dark/[.02]'"
                                                style="min-height:90px"
                                                @dragover.prevent="uploadDragging=true"
                                                @dragleave.prevent="uploadDragging=false"
                                                @drop.prevent="onDrop($event)"
                                                @click="$refs.imgPick.click()">
                                                <input type="file" x-ref="imgPick" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" @change="onFile($event)">
                                                <template x-if="!uploadProgress && !uploadError">
                                                    <div class="flex flex-col items-center justify-center gap-1.5 p-4 text-center">
                                                        <svg class="w-7 h-7 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                        <p class="text-[12px] text-dark/40">Drop image or <span class="text-dark underline cursor-pointer">browse</span></p>
                                                        <p class="text-[11px] text-dark/25">PNG · JPG · WEBP · max 5 MB</p>
                                                    </div>
                                                </template>
                                                <template x-if="uploadProgress > 0 && uploadProgress < 100">
                                                    <div class="flex flex-col items-center justify-center gap-2 p-4">
                                                        <div class="w-full bg-dark/10 rounded-full h-1.5 overflow-hidden">
                                                            <div class="h-full bg-dark rounded-full transition-all" :style="'width:'+uploadProgress+'%'"></div>
                                                        </div>
                                                        <p class="text-[11px] text-dark/40" x-text="uploadProgress+'% uploading...'"></p>
                                                    </div>
                                                </template>
                                            </div>
                                            <template x-if="uploadError">
                                                <p class="text-[11px] text-red-500 mt-1" x-text="uploadError"></p>
                                            </template>
                                        </div>

                                        <!-- GALLERY TAB -->
                                        <div x-show="pickerTab==='gallery'">
                                            <template x-if="galleryLoading">
                                                <p class="text-[12px] text-dark/30 text-center py-4">Loading images...</p>
                                            </template>
                                            <template x-if="!galleryLoading && galleryImages.length === 0">
                                                <p class="text-[12px] text-dark/30 text-center py-4">No uploaded images yet. Upload one first.</p>
                                            </template>
                                            <div x-show="!galleryLoading && galleryImages.length > 0"
                                                class="grid grid-cols-4 gap-1.5 max-h-48 overflow-y-auto rounded-xl border border-dark/10 p-2 bg-dark/[.015]">
                                                <template x-for="img in galleryImages" :key="img.url">
                                                    <button type="button" @click="selectFromGallery(img.url)"
                                                        class="relative rounded-lg overflow-hidden border-2 transition aspect-square"
                                                        :class="scriptForm.image_url === img.url ? 'border-dark' : 'border-transparent hover:border-dark/30'">
                                                        <img :src="img.url" :alt="img.name" class="w-full h-full object-cover">
                                                        <div x-show="scriptForm.image_url === img.url"
                                                            class="absolute inset-0 bg-dark/40 flex items-center justify-center">
                                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                        </div>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        <p class="text-[11px] text-dark/30 mt-1.5">16:9 image shown at top of the audition card</p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Rules &amp; Limits</label>
                                        <textarea x-model="scriptForm.rules" rows="5" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-y" placeholder="One rule per line:&#10;Video under 3 minutes&#10;Shoot on any device&#10;Face must not be visible&#10;Clear audio required"></textarea>
                                        <p class="text-[11px] text-dark/30 mt-1">One rule per line — shown as bullet list on the card</p>
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
                                <div class="divide-y divide-dark/5 max-h-[500px] md:max-h-[600px] overflow-y-auto">
                                    <template x-for="sc in filteredScripts" :key="sc.id">
                                        <div class="p-4 hover:bg-cream/30 transition">
                                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                                        <h4 class="font-medium text-dark truncate text-[13px] md:text-[14px]" x-text="sc.title"></h4>
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
                                
                                <!-- File Size Limits -->
                                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-dark/5">
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Min File Size (MB)</label>
                                        <input type="number" x-model="aiSettings.video_min_size_mb" @change="saveAISetting('video_min_size_mb', aiSettings.video_min_size_mb)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" min="0" max="50">
                                        <p class="text-[10px] text-dark/30 mt-1">Ensures minimum quality</p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] text-dark/50 mb-1">Max File Size (MB)</label>
                                        <input type="number" x-model="aiSettings.video_max_size_mb" @change="saveAISetting('video_max_size_mb', aiSettings.video_max_size_mb)" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" min="10" max="500">
                                        <p class="text-[10px] text-dark/30 mt-1">For 60s 1080p: ~50MB is enough</p>
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
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
                        <!-- Connection Status Card -->
                        <div class="lg:col-span-3">
                            <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3 youtube-status-header">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 md:w-12 md:h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 md:w-7 md:h-7 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-dark text-[15px] md:text-[16px]">YouTube Integration</h3>
                                            <p class="text-[11px] md:text-[12px] text-dark/50">Auto-publish approved videos</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <!-- Auto-Publish Toggle -->
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] text-dark/50">Auto-Publish:</span>
                                            <button @click="toggleYouTubeAutoPublish()" 
                                                class="relative w-12 h-6 rounded-full transition-colors duration-200"
                                                :class="youtubeAutoPublish ? 'bg-green-500' : 'bg-dark/20'">
                                                <span class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                                    :class="youtubeAutoPublish ? 'translate-x-6' : ''"></span>
                                            </button>
                                            <span class="text-[11px] font-medium" :class="youtubeAutoPublish ? 'text-green-600' : 'text-amber-600'" x-text="youtubeAutoPublish ? 'ON' : 'PAUSED'"></span>
                                        </div>
                                        <!-- Connection Status -->
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500' : 'bg-red-500'"></span>
                                            <span class="text-[11px] md:text-[12px] font-medium" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'text-green-600' : 'text-red-600'" x-text="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'Connected' : 'Not Connected'"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Info when paused -->
                                <div x-show="!youtubeAutoPublish" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <p class="text-[12px] text-amber-700 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span><strong>Auto-publish paused.</strong> Videos will be judged by AI but won't go to YouTube. Use the Publish Queue below to manually push videos.</span>
                                    </p>
                                </div>
                                
                                <!-- Test Button -->
                                <button @click="testYouTubeConnection()" 
                                    :disabled="testingYouTube"
                                    class="w-full sm:w-auto px-4 py-2.5 bg-red-600 text-white rounded-lg text-[12px] font-medium hover:bg-red-700 transition flex items-center justify-center gap-2 youtube-test-btn"
                                    :class="testingYouTube ? 'opacity-50 cursor-not-allowed' : ''">
                                    <svg x-show="testingYouTube" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <svg x-show="!testingYouTube" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span x-text="testingYouTube ? 'Testing...' : 'Test YouTube + AI Connection'"></span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Publish Queue Card -->
                        <div class="lg:col-span-3">
                            <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        Publish Queue
                                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700" x-text="publishQueue.length + ' videos'"></span>
                                    </h3>
                                    <div class="flex items-center gap-2">
                                        <button @click="refreshPublishQueue()" class="text-dark/40 hover:text-dark p-1" title="Refresh">
                                            <svg class="w-4 h-4" :class="loadingPublishQueue ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        </button>
                                        <button @click="publishAllQueue()" 
                                            :disabled="publishQueue.length === 0 || publishingAll"
                                            class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-[11px] font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center gap-1">
                                            <svg x-show="publishingAll" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            <svg x-show="!publishingAll" class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                            <span x-text="publishingAll ? 'Publishing...' : 'Publish All'"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Queue Description -->
                                <p class="text-[12px] text-dark/50 mb-4">Videos that are approved but not yet published to YouTube. These accumulate when auto-publish is paused.</p>
                                
                                <!-- Queue List -->
                                <div x-show="publishQueue.length === 0" class="text-center py-8 text-dark/30 text-[13px]">
                                    <svg class="w-12 h-12 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    No videos waiting to be published
                                </div>
                                
                                <div x-show="publishQueue.length > 0" class="space-y-2 max-h-[300px] overflow-y-auto">
                                    <template x-for="video in publishQueue" :key="video.id">
                                        <div class="flex items-center justify-between p-3 bg-cream/50 rounded-lg hover:bg-cream transition">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <input type="checkbox" :value="video.id" x-model="selectedQueueVideos" class="rounded border-dark/20">
                                                <div class="min-w-0">
                                                    <p class="font-medium text-dark text-[13px] truncate" x-text="video.title"></p>
                                                    <p class="text-[11px] text-dark/50">
                                                        <span x-text="video.user_name"></span> • 
                                                        <span x-text="video.content_type"></span> • 
                                                        Score: <span x-text="video.ai_score || 'N/A'"></span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] text-dark/40" x-text="video.moderated_at ? new Date(video.moderated_at).toLocaleDateString() : ''"></span>
                                                <button @click="publishSingleVideo(video.id)" 
                                                    :disabled="publishingVideoId === video.id"
                                                    class="px-2 py-1 bg-red-600 text-white rounded text-[10px] font-medium hover:bg-red-700 transition disabled:opacity-50 flex items-center gap-1">
                                                    <svg x-show="publishingVideoId === video.id" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                    <span x-text="publishingVideoId === video.id ? '...' : 'Publish'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Bulk publish selected -->
                                <div x-show="selectedQueueVideos.length > 0" class="mt-4 pt-4 border-t border-dark/10 flex items-center justify-between">
                                    <span class="text-[12px] text-dark/50"><span x-text="selectedQueueVideos.length"></span> videos selected</span>
                                    <button @click="publishSelectedQueue()" 
                                        :disabled="publishingAll"
                                        class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-[11px] font-medium hover:bg-red-700 transition disabled:opacity-50">
                                        Publish Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test Results Panel -->
                        <div x-show="youtubeTestResults" x-cloak class="lg:col-span-3">
                            <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        Test Results
                                    </h3>
                                    <button @click="youtubeTestResults = null" class="text-dark/30 hover:text-dark">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                    <!-- YouTube Results -->
                                    <div>
                                        <h4 class="font-medium text-[13px] text-dark mb-3 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                            YouTube (<span x-text="youtubeTestResults?.youtube_passed || 0"></span>/<span x-text="youtubeTestResults?.youtube_total || 7"></span>)
                                        </h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">API Key</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.api_key ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">Client ID</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.client_id ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">Client Secret</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.client_secret ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">Refresh Token</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.refresh_token ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">Channel ID</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.channel_id ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">OAuth Token Test</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.oauth_test ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 bg-cream rounded-lg">
                                                <span class="text-[12px] text-dark/70">Channel Access</span>
                                                <span class="w-2 h-2 rounded-full" :class="youtubeTestResults?.results?.channel_access ? 'bg-green-500' : 'bg-red-500'"></span>
                                            </div>
                                            <template x-if="youtubeTestResults?.results?.channel_name">
                                                <div class="p-2 bg-green-50 border border-green-200 rounded-lg">
                                                    <span class="text-[11px] text-green-700">Channel: <strong x-text="youtubeTestResults.results.channel_name"></strong></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- AI Results -->
                                    <div>
                                        <h4 class="font-medium text-[13px] text-dark mb-3 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                            AI Services (<span x-text="youtubeTestResults?.results?.ai_services || 0"></span>/4 configured)
                                        </h4>
                                        <div class="p-3 rounded-lg" :class="youtubeTestResults?.results?.ai_ready ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                                            <div class="flex items-center gap-2">
                                                <span class="w-3 h-3 rounded-full" :class="youtubeTestResults?.results?.ai_ready ? 'bg-green-500' : 'bg-red-500'"></span>
                                                <span class="text-[12px] font-medium" :class="youtubeTestResults?.results?.ai_ready ? 'text-green-700' : 'text-red-700'" x-text="youtubeTestResults?.results?.ai_ready ? 'AI Ready for Processing' : 'AI Not Ready - Configure more services'"></span>
                                            </div>
                                            <p class="text-[10px] mt-1" :class="youtubeTestResults?.results?.ai_ready ? 'text-green-600' : 'text-red-600'">
                                                Videos will be processed by AI before approval
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overall Status -->
                                <div class="mt-4 p-4 rounded-xl" :class="youtubeTestResults?.all_ready ? 'bg-green-100 border border-green-300' : 'bg-yellow-100 border border-yellow-300'">
                                    <div class="flex items-center gap-3">
                                        <template x-if="youtubeTestResults?.all_ready">
                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </template>
                                        <template x-if="!youtubeTestResults?.all_ready">
                                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        </template>
                                        <div>
                                            <p class="font-semibold" :class="youtubeTestResults?.all_ready ? 'text-green-800' : 'text-yellow-800'" x-text="youtubeTestResults?.all_ready ? '✓ System Ready!' : '⚠ Some issues detected'"></p>
                                            <p class="text-[11px]" :class="youtubeTestResults?.all_ready ? 'text-green-700' : 'text-yellow-700'" x-text="youtubeTestResults?.all_ready ? 'Videos can be uploaded, processed by AI, and published to YouTube' : 'Check failed items above and fix credentials'"></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <template x-if="youtubeTestResults?.error">
                                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-[11px] text-red-700"><strong>Error:</strong> <span x-text="youtubeTestResults.error"></span></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                        <!-- Step-by-Step Guide -->
                        <div class="lg:col-span-2">
                            <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5 youtube-guide">
                                <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                    Setup Guide
                                </h3>
                                
                                <div class="space-y-3 md:space-y-4">
                                    <!-- Step 1 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">1</div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Enable YouTube Data API v3</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Go to Google Cloud Console and enable the API.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/library" target="_blank" class="text-blue-600 hover:underline break-all">API Library</a></li>
                                                    <li>Search for "YouTube Data API v3"</li>
                                                    <li>Click and press "Enable"</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 2 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">2</div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Create OAuth 2.0 Credentials</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Create OAuth credentials for uploads.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials</a></li>
                                                    <li>"Create Credentials" → "OAuth client ID"</li>
                                                    <li>Type: <strong>Web application</strong></li>
                                                    <li>Name: "Faceless Pictures 3"</li>
                                                    <li class="break-all">Redirect URI: <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px] break-all">developers.google.com/oauthplayground</code></li>
                                                    <li>Copy <strong>Client ID</strong> & <strong>Secret</strong></li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 3 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">3</div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Add Test User</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Required if app is in "Testing" mode.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" class="text-blue-600 hover:underline">OAuth consent</a></li>
                                                    <li>Scroll to "Test users"</li>
                                                    <li>Click "Add Users"</li>
                                                    <li>Enter your YouTube email</li>
                                                    <li>Click "Save"</li>
                                                </ol>
                                                <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded-lg">
                                                    <p class="text-[9px] md:text-[10px] text-yellow-800"><strong>Note:</strong> Use the Gmail that owns your YouTube channel!</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 4 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">4</div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Get Refresh Token</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Use OAuth Playground to generate token.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://developers.google.com/oauthplayground" target="_blank" class="text-blue-600 hover:underline">OAuth Playground</a></li>
                                                    <li>Click ⚙️ gear icon (top-right)</li>
                                                    <li>Check "Use your own OAuth credentials"</li>
                                                    <li>Enter Client ID & Secret</li>
                                                    <li>Find "YouTube Data API v3"</li>
                                                    <li class="break-all">Select: <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px] break-all">.../auth/youtube.upload</code></li>
                                                    <li>Click "Authorize APIs"</li>
                                                    <li>Sign in with YouTube account</li>
                                                    <li>Allow access</li>
                                                    <li>"Exchange authorization code for tokens"</li>
                                                    <li>Copy the <strong>Refresh token</strong></li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 5 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">5</div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Create API Key</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Create an API key for public data access.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials</a></li>
                                                    <li>"Create Credentials" → "API key"</li>
                                                    <li>Copy the API key (AIza...)</li>
                                                    <li>Optional: Restrict to YouTube API</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 6 -->
                                    <div class="border border-dark/10 rounded-xl p-3 md:p-4">
                                        <div class="flex items-start gap-2 md:gap-3">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">6</div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px] mb-1">Get Your Channel ID</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/60 mb-2">Find your YouTube channel ID.</p>
                                                <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                    <li>Go to <a href="https://www.youtube.com" target="_blank" class="text-blue-600 hover:underline">YouTube</a></li>
                                                    <li>Click profile → "Your channel"</li>
                                                    <li class="break-all">URL shows: <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px]">channel/<strong>UC...</strong></code></li>
                                                    <li>Copy the ID (starts with "UC")</li>
                                                </ol>
                                                <div class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                                                    <p class="text-[9px] md:text-[10px] text-blue-800">If URL shows @username, go to <a href="https://www.youtube.com/account_advanced" target="_blank" class="underline">Advanced Settings</a></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- API Configuration Form -->
                        <div class="lg:col-span-1">
                            <form @submit.prevent="saveAPIKeys()" class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                                <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    API Configuration
                                </h3>
                                
                                <div class="space-y-3 md:space-y-4">
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
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 md:p-4 mt-4">
                                <h4 class="font-semibold text-blue-900 text-[11px] md:text-[12px] mb-2 md:mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    Quick Links
                                </h4>
                                <div class="space-y-1.5 md:space-y-2">
                                    <a href="https://console.cloud.google.com/apis/library" target="_blank" class="block text-[10px] md:text-[11px] text-blue-700 hover:underline">→ API Library</a>
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="block text-[10px] md:text-[11px] text-blue-700 hover:underline">→ Credentials</a>
                                    <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" class="block text-[10px] md:text-[11px] text-blue-700 hover:underline">→ OAuth Consent</a>
                                    <a href="https://developers.google.com/oauthplayground" target="_blank" class="block text-[10px] md:text-[11px] text-blue-700 hover:underline">→ OAuth Playground</a>
                                    <a href="https://www.youtube.com/account_advanced" target="_blank" class="block text-[10px] md:text-[11px] text-blue-700 hover:underline">→ Channel ID</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== EMAIL TAB ==================== -->
                <div x-show="activeTab === 'email'" x-cloak x-data="emailSettings()" x-init="loadEmailSettings()">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
                        
                        <!-- Email Provider Selection -->
                        <div class="lg:col-span-3 bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Email Provider
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" @click="emailProvider = 'smtp'" 
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl border-2 transition"
                                    :class="emailProvider === 'smtp' ? 'border-crimson bg-crimson/5' : 'border-dark/10 hover:border-dark/20'">
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-semibold text-[13px]" :class="emailProvider === 'smtp' ? 'text-crimson' : 'text-dark'">SMTP</p>
                                        <p class="text-[10px] text-dark/50">Gmail, Zoho, Outlook, etc.</p>
                                    </div>
                                </button>
                                <button type="button" @click="emailProvider = 'resend'" 
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl border-2 transition"
                                    :class="emailProvider === 'resend' ? 'border-crimson bg-crimson/5' : 'border-dark/10 hover:border-dark/20'">
                                    <div class="w-10 h-10 rounded-lg bg-black flex items-center justify-center">
                                        <span class="text-white font-bold text-[14px]">R</span>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-semibold text-[13px]" :class="emailProvider === 'resend' ? 'text-crimson' : 'text-dark'">Resend</p>
                                        <p class="text-[10px] text-dark/50">3,000 free/month • Best deliverability</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Resend Configuration (shown when resend selected) -->
                        <div x-show="emailProvider === 'resend'" x-cloak class="lg:col-span-2 bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                    <div class="w-6 h-6 rounded bg-black flex items-center justify-center">
                                        <span class="text-white font-bold text-[11px]">R</span>
                                    </div>
                                    Resend Configuration
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                      :class="resendStatus.is_configured ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                      x-text="resendStatus.is_configured ? 'Connected' : 'Not Configured'"></span>
                            </div>
                            
                            <form @submit.prevent="saveResendSettings()" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">Resend API Key</label>
                                        <div class="relative">
                                            <input :type="showResendKey ? 'text' : 'password'" x-model="resend.api_key" :placeholder="resendStatus.has_api_key ? '••••••••••••••••••••' : 're_xxxxxxxxxx'" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] pr-10 focus:border-crimson focus:ring-1 focus:ring-crimson/20 font-mono">
                                            <button type="button" @click="showResendKey = !showResendKey" class="absolute right-3 top-1/2 -translate-y-1/2 text-dark/40 hover:text-dark">
                                                <svg x-show="!showResendKey" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <svg x-show="showResendKey" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            </button>
                                        </div>
                                        <p class="text-[10px] mt-1" :class="resendStatus.has_api_key ? 'text-green-600' : 'text-dark/40'">
                                            <span x-show="resendStatus.has_api_key">✓ API key configured. Leave blank to keep current key.</span>
                                            <span x-show="!resendStatus.has_api_key">Get your API key from <a href="https://resend.com/api-keys" target="_blank" class="text-crimson underline">resend.com/api-keys</a></span>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">From Email Address</label>
                                        <input type="email" x-model="resend.from_address" placeholder="noreply@yourdomain.com" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                        <p class="text-[10px] text-dark/40 mt-1">Must use verified domain in Resend</p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">From Name</label>
                                        <input type="text" x-model="resend.from_name" placeholder="Faceless Pictures 3" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                    </div>
                                </div>
                                
                                <!-- Setup Guide -->
                                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mt-4">
                                    <h4 class="font-semibold text-blue-900 text-[12px] mb-2 flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Setup Guide
                                    </h4>
                                    <ol class="text-[11px] text-blue-800 space-y-1 list-decimal list-inside">
                                        <li>Create free account at <a href="https://resend.com" target="_blank" class="underline">resend.com</a></li>
                                        <li>Add your domain in Resend → Domains</li>
                                        <li>Add DNS records to Cloudflare (SPF, DKIM)</li>
                                        <li>Create API key and paste above</li>
                                    </ol>
                                </div>
                                
                                <div class="flex items-center gap-3 pt-2">
                                    <button type="submit" :disabled="savingResend" class="bg-crimson text-white px-6 py-2.5 rounded-lg text-[13px] font-semibold hover:bg-crimson/90 disabled:opacity-50">
                                        <span x-text="savingResend ? 'Saving...' : 'Save Resend Settings'"></span>
                                    </button>
                                    <span x-show="resendMessage" x-text="resendMessage" class="text-[12px]" :class="resendSuccess ? 'text-green-600' : 'text-red-600'"></span>
                                </div>
                            </form>
                        </div>
                        
                        <!-- SMTP Configuration Card (shown when smtp selected) -->
                        <div x-show="emailProvider === 'smtp'" x-cloak class="lg:col-span-2 bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    SMTP Configuration
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                      :class="smtpStatus.is_configured ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                      x-text="smtpStatus.is_configured ? 'Connected' : 'Not Configured'"></span>
                            </div>
                            
                            <form @submit.prevent="saveSmtpSettings()" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">SMTP Host</label>
                                        <input type="text" x-model="smtp.host" placeholder="smtp.gmail.com" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                        <p class="text-[10px] text-dark/40 mt-1">e.g., smtp.gmail.com, smtp.zoho.com</p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">Port</label>
                                        <select x-model="smtp.port" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson">
                                            <option value="587">587 (TLS - Recommended)</option>
                                            <option value="465">465 (SSL)</option>
                                            <option value="25">25 (Unencrypted)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">Username / Email</label>
                                        <input type="email" x-model="smtp.username" placeholder="your@email.com" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">Password / App Password</label>
                                        <div class="relative">
                                            <input :type="showPassword ? 'text' : 'password'" x-model="smtp.password" placeholder="Leave blank to keep existing" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] pr-10 focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-dark/40 hover:text-dark">
                                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            </button>
                                        </div>
                                        <p class="text-[10px] text-dark/40 mt-1">For Gmail, use <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-crimson underline">App Password</a></p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">Encryption</label>
                                        <select x-model="smtp.encryption" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson">
                                            <option value="tls">TLS (Recommended)</option>
                                            <option value="ssl">SSL</option>
                                            <option value="">None</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">From Email Address</label>
                                        <input type="email" x-model="smtp.from_address" placeholder="noreply@yourdomain.com" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[12px] font-medium text-dark/70 mb-1">From Name</label>
                                        <input type="text" x-model="smtp.from_name" placeholder="Faceless Pictures 3" class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-[13px] focus:border-crimson focus:ring-1 focus:ring-crimson/20">
                                    </div>
                                </div>
                                
                                <!-- Quick Setup Buttons -->
                                <div class="bg-cream rounded-xl p-4 mt-4">
                                    <p class="text-[11px] font-semibold text-dark/50 uppercase mb-3">Quick Setup - Select Provider</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="setProvider('gmail')" class="px-3 py-1.5 text-[11px] font-medium bg-white border border-dark/10 rounded-lg hover:border-crimson hover:text-crimson transition">Gmail</button>
                                        <button type="button" @click="setProvider('zoho')" class="px-3 py-1.5 text-[11px] font-medium bg-white border border-dark/10 rounded-lg hover:border-crimson hover:text-crimson transition">Zoho</button>
                                        <button type="button" @click="setProvider('outlook')" class="px-3 py-1.5 text-[11px] font-medium bg-white border border-dark/10 rounded-lg hover:border-crimson hover:text-crimson transition">Outlook</button>
                                        <button type="button" @click="setProvider('sendgrid')" class="px-3 py-1.5 text-[11px] font-medium bg-white border border-dark/10 rounded-lg hover:border-crimson hover:text-crimson transition">SendGrid</button>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3 pt-2">
                                    <button type="submit" :disabled="savingSmtp" class="bg-crimson text-white px-6 py-2.5 rounded-lg text-[13px] font-semibold hover:bg-crimson/90 disabled:opacity-50">
                                        <span x-text="savingSmtp ? 'Saving...' : 'Save SMTP Settings'"></span>
                                    </button>
                                    <span x-show="smtpMessage" x-text="smtpMessage" class="text-[12px]" :class="smtpSuccess ? 'text-green-600' : 'text-red-600'"></span>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Test Email & Status Card -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Connection Status
                            </h3>
                            
                            <div class="space-y-3 mb-4">
                                <div class="flex items-center justify-between py-2 border-b border-dark/5">
                                    <span class="text-[12px] text-dark/50">Host</span>
                                    <span class="text-[12px] font-medium" x-text="smtpStatus.host || 'Not set'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-dark/5">
                                    <span class="text-[12px] text-dark/50">Port</span>
                                    <span class="text-[12px] font-medium" x-text="smtpStatus.port || '-'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-dark/5">
                                    <span class="text-[12px] text-dark/50">Username</span>
                                    <span class="text-[12px] font-medium truncate max-w-[120px]" x-text="smtpStatus.username || 'Not set'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-dark/5">
                                    <span class="text-[12px] text-dark/50">Password</span>
                                    <span class="text-[12px] font-medium" x-text="smtpStatus.has_password ? '••••••••' : 'Not set'"></span>
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-[12px] text-dark/50">From</span>
                                    <span class="text-[12px] font-medium truncate max-w-[120px]" x-text="smtpStatus.from_address || '-'"></span>
                                </div>
                            </div>
                            
                            <!-- Test Email -->
                            <div class="pt-4 border-t border-dark/5">
                                <label class="block text-[11px] font-semibold text-dark/50 uppercase mb-2">Send Test Email</label>
                                <div class="flex gap-2">
                                    <input type="email" x-model="testEmail" placeholder="test@example.com" class="flex-1 border border-dark/10 rounded-lg px-3 py-2 text-[13px]">
                                    <button @click="sendTestEmail()" :disabled="testingEmail || !testEmail" class="bg-crimson text-white px-4 py-2 rounded-lg text-[12px] font-semibold hover:bg-crimson/90 disabled:opacity-50">
                                        <span x-show="!testingEmail">Send</span>
                                        <span x-show="testingEmail">...</span>
                                    </button>
                                </div>
                                <p x-show="testResult" class="text-[12px] mt-2 p-2 rounded-lg" 
                                   :class="testResult.includes('success') ? 'text-green-700 bg-green-50 border border-green-200' : 'text-red-700 bg-red-50 border border-red-200'" 
                                   x-text="testResult"></p>
                            </div>
                        </div>
                        
                        <!-- Email Notifications Settings -->
                        <div class="lg:col-span-3 bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                Email Notification Settings
                            </h3>
                            
                            <form @submit.prevent="saveNotificationSettings()">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- User Notification Toggles -->
                                    <div class="space-y-3">
                                        <h4 class="text-[12px] font-semibold text-dark/50 uppercase">Send Emails to Users:</h4>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.signup" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">User signs up</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.submit" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video submitted</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.processing" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video processing</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.approved" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video approved</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.rejected" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video rejected</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.flagged" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video flagged</span></label>
                                    </div>
                                    
                                    <!-- Admin Notifications -->
                                    <div class="space-y-3">
                                        <h4 class="text-[12px] font-semibold text-dark/50 uppercase">Admin Notifications</h4>
                                        <div>
                                            <label class="block text-[12px] text-dark/70 mb-1">Admin Email</label>
                                            <input type="email" x-model="notifications.admin_address" placeholder="admin@example.com" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px]">
                                        </div>
                                        <label class="flex items-center gap-3 cursor-pointer mt-4"><input type="checkbox" x-model="notifications.admin_new_video" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">New video submitted</span></label>
                                        <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" x-model="notifications.admin_flagged" class="w-4 h-4 rounded text-crimson border-dark/20"><span class="text-[13px] text-dark">Video flagged</span></label>
                                    </div>
                                    
                                    <!-- Tips -->
                                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                                        <h4 class="text-[12px] font-semibold text-blue-800 uppercase mb-2">💡 Quick Tips</h4>
                                        <ul class="text-[11px] text-blue-700 space-y-1.5">
                                            <li><strong>Gmail:</strong> Use <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline">App Password</a></li>
                                            <li><strong>Zoho:</strong> smtp.zoho.com:587</li>
                                            <li><strong>SendGrid:</strong> apikey as username</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mt-6 pt-4 border-t border-dark/5 flex items-center gap-3">
                                    <button type="submit" :disabled="savingNotifications" class="bg-crimson text-white px-6 py-2.5 rounded-lg text-[13px] font-semibold hover:bg-crimson/90 disabled:opacity-50">
                                        <span x-text="savingNotifications ? 'Saving...' : 'Save Notification Settings'"></span>
                                    </button>
                                    <span x-show="notifMessage" x-text="notifMessage" class="text-[12px]" :class="notifSuccess ? 'text-green-600' : 'text-red-600'"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ==================== GOOGLE LOGIN TAB ==================== -->
                <div x-show="activeTab === 'google'" x-cloak x-data="googleSettings()" x-init="loadGoogleSettings()">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Configuration Card -->
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-5 py-4 border-b border-dark/5">
                                <h3 class="font-semibold text-dark flex items-center gap-2">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                    </svg>
                                    Google OAuth Settings
                                </h3>
                            </div>
                            
                            <div class="p-5">
                                <!-- Status -->
                                <div class="flex items-center justify-between p-4 rounded-xl mb-5" :class="isConfigured ? 'bg-green-50' : 'bg-amber-50'">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="isConfigured ? 'bg-green-100' : 'bg-amber-100'">
                                            <svg x-show="isConfigured" class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <svg x-show="!isConfigured" class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        </div>
                                        <div>
                                            <p class="font-medium text-dark text-[13px]" x-text="isConfigured ? 'Google Login Enabled' : 'Not Configured'"></p>
                                            <p class="text-[11px]" :class="isConfigured ? 'text-green-600' : 'text-amber-600'" x-text="isConfigured ? 'Users can sign in with Google' : 'Add credentials to enable'"></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <form @submit.prevent="saveGoogleSettings()">
                                    <div class="space-y-4">
                                        <!-- Client ID -->
                                        <div>
                                            <label class="block text-[12px] font-medium text-dark/70 mb-1.5">Client ID</label>
                                            <input type="text" x-model="clientId" :placeholder="hasClientId ? '••••••••••••••••••••...' : 'Enter Google Client ID'" class="w-full px-4 py-2.5 text-[13px] border border-dark/10 rounded-lg focus:outline-none focus:ring-2 focus:ring-crimson/20 focus:border-crimson">
                                            <p x-show="hasClientId && !clientId" class="text-[10px] text-green-600 mt-1">✓ Client ID configured (enter new value to change)</p>
                                        </div>
                                        
                                        <!-- Client Secret -->
                                        <div>
                                            <label class="block text-[12px] font-medium text-dark/70 mb-1.5">Client Secret</label>
                                            <input type="password" x-model="clientSecret" :placeholder="hasClientSecret ? '••••••••••••••••••••' : 'Enter Google Client Secret'" class="w-full px-4 py-2.5 text-[13px] border border-dark/10 rounded-lg focus:outline-none focus:ring-2 focus:ring-crimson/20 focus:border-crimson">
                                            <p x-show="hasClientSecret && !clientSecret" class="text-[10px] text-green-600 mt-1">✓ Client Secret configured (enter new value to change)</p>
                                        </div>
                                        
                                        <!-- Redirect URI (read-only) -->
                                        <div>
                                            <label class="block text-[12px] font-medium text-dark/70 mb-1.5">Redirect URI (copy this to Google Console)</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" :value="redirectUri" readonly class="flex-1 px-4 py-2.5 text-[13px] border border-dark/10 rounded-lg bg-cream/50 font-mono">
                                                <button type="button" @click="copyRedirectUri()" class="p-2.5 bg-dark/5 rounded-lg hover:bg-dark/10 transition">
                                                    <svg class="w-4 h-4 text-dark/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Message -->
                                    <div x-show="message" x-cloak class="mt-4 p-3 rounded-lg text-[12px]" :class="messageType === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'" x-text="message"></div>
                                    
                                    <div class="mt-5 flex items-center gap-3">
                                        <button type="submit" :disabled="saving" class="bg-crimson text-white px-6 py-2.5 rounded-lg text-[13px] font-semibold hover:bg-crimson/90 disabled:opacity-50 flex items-center gap-2">
                                            <span x-text="saving ? 'Saving...' : 'Save Settings'"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Setup Guide -->
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-5 py-4 border-b border-dark/5">
                                <h3 class="font-semibold text-dark flex items-center gap-2">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Setup Guide
                                </h3>
                            </div>
                            
                            <div class="p-5 space-y-4 text-[13px]">
                                <div class="flex gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] font-bold">1</span>
                                    <div>
                                        <p class="font-medium text-dark">Go to Google Cloud Console</p>
                                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-[12px] text-crimson hover:underline">console.cloud.google.com/apis/credentials →</a>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] font-bold">2</span>
                                    <div>
                                        <p class="font-medium text-dark">Create OAuth 2.0 Client ID</p>
                                        <p class="text-[12px] text-dark/50">Click "+ Create Credentials" → "OAuth client ID" → "Web application"</p>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] font-bold">3</span>
                                    <div>
                                        <p class="font-medium text-dark">Add Authorized Origins</p>
                                        <code class="block mt-1 bg-cream p-2 rounded text-[11px] font-mono"><?= get_base_url() ?></code>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] font-bold">4</span>
                                    <div>
                                        <p class="font-medium text-dark">Add Authorized Redirect URI</p>
                                        <code class="block mt-1 bg-cream p-2 rounded text-[11px] font-mono break-all"><?= get_base_url() ?>/api/auth/google/callback</code>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <span class="flex-shrink-0 w-6 h-6 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] font-bold">5</span>
                                    <div>
                                        <p class="font-medium text-dark">Copy Client ID & Secret</p>
                                        <p class="text-[12px] text-dark/50">Paste them in the form on the left</p>
                                    </div>
                                </div>
                                
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-4">
                                    <p class="text-[11px] text-amber-800"><strong>⚠️ Important:</strong> You may need to configure the OAuth Consent Screen first. Choose "External" and fill in app name & email.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SETTINGS TAB ==================== -->
                <div x-show="activeTab === 'settings'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">

                        <!-- Landing Page Settings -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-6 md:col-span-2 lg:col-span-3" x-data="landingSettings()">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="font-semibold text-dark flex items-center gap-2 text-[15px]">
                                    <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                                    Landing Page &amp; Site Settings
                                </h3>
                                <div class="flex items-center gap-3">
                                    <span x-show="saved" class="text-green-600 text-sm font-medium flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Saved
                                    </span>
                                    <a href="/" target="_blank" class="text-dark/40 text-xs hover:text-dark transition">Preview site →</a>
                                </div>
                            </div>

                            <!-- SECTION: Brand -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <p class="text-[11px] font-semibold tracking-widest uppercase text-dark/30 mb-3">Brand</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <!-- Logo uploader -->
                                    <div x-data="imageUploader('site_logo_url', '<?= addslashes(htmlspecialchars($settingsModel->get('site_logo_url',''))) ?>')">
                                        <label class="block text-xs font-medium text-dark/60 mb-2">Logo Image</label>
                                        <div
                                            class="relative border-2 rounded-xl transition-all cursor-pointer overflow-hidden"
                                            :class="dragging ? 'border-dark bg-dark/5' : 'border-dashed border-dark/15 hover:border-dark/30 bg-dark/[.02]'"
                                            style="min-height:120px"
                                            @dragover.prevent="dragging=true"
                                            @dragleave.prevent="dragging=false"
                                            @drop.prevent="onDrop($event)"
                                            @click="$refs.imgInput.click()">
                                            <input type="file" x-ref="imgInput" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" @change="onFile($event)">

                                            <!-- Empty state -->
                                            <template x-if="!preview && !uploading">
                                                <div class="flex flex-col items-center justify-center gap-2 p-6 text-center">
                                                    <svg class="w-8 h-8 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    <p class="text-[12px] text-dark/40 font-medium">Drop image or <span class="text-dark underline">click to browse</span></p>
                                                    <p class="text-[11px] text-dark/25">PNG · JPG · WEBP · max 5 MB</p>
                                                </div>
                                            </template>

                                            <!-- Uploading state -->
                                            <template x-if="uploading">
                                                <div class="flex flex-col items-center justify-center gap-3 p-6">
                                                    <div class="w-full bg-dark/10 rounded-full h-1.5 overflow-hidden">
                                                        <div class="h-full bg-dark rounded-full transition-all" :style="'width:'+progress+'%'"></div>
                                                    </div>
                                                    <p class="text-[12px] text-dark/40" x-text="progress+'% uploaded'"></p>
                                                </div>
                                            </template>

                                            <!-- Preview state -->
                                            <template x-if="preview && !uploading">
                                                <div class="flex items-center gap-4 p-4">
                                                    <img :src="preview" alt="Logo preview" style="height:48px;max-width:160px;object-fit:contain;background:#f9fafb;border-radius:6px;padding:4px">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-[12px] font-medium text-dark truncate" x-text="filename || 'Logo uploaded'"></p>
                                                        <p class="text-[11px] text-green-600 mt-0.5">✓ Saved</p>
                                                    </div>
                                                    <button type="button" @click.stop="clearImage()" class="text-dark/30 hover:text-dark/60 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <template x-if="uploadError">
                                            <p class="text-[11px] text-red-500 mt-1.5" x-text="uploadError"></p>
                                        </template>
                                        <p class="text-[11px] text-dark/30 mt-1.5">Transparent PNG looks best. Displays in nav &amp; footer on all pages.</p>
                                    </div>

                                    <!-- Logo URL (manual fallback) -->
                                    <div>
                                        <label class="block text-xs font-medium text-dark/60 mb-2">Or paste Logo URL directly</label>
                                        <input type="url" x-model="form.site_logo_url" placeholder="https://..."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Use this if your logo is hosted externally.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION: Hero / Headline -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <p class="text-[11px] font-semibold tracking-widest uppercase text-dark/30 mb-3">Hero Section</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-dark/60 mb-1.5">Main Headline</label>
                                        <input type="text" x-model="form.landing_headline" placeholder="NO FACE. NO CONNECTIONS. JUST TALENT."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Large centered text on homepage.</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-dark/60 mb-1.5">Tagline / Subtitle</label>
                                        <input type="text" x-model="form.site_tagline" placeholder="India's first anonymous film competition..."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION: Role Cards -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <p class="text-[11px] font-semibold tracking-widest uppercase text-dark/30 mb-3">Role Cards Section (below posters)</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-dark/60 mb-1.5">Section Heading</label>
                                        <input type="text" x-model="form.landing_roles_heading" placeholder="Become a Star in 3 Clicks"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Big bold heading above the Actor / Director / Writer cards.</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-dark/60 mb-1.5">Section Subheading</label>
                                        <input type="text" x-model="form.landing_roles_subheading" placeholder="Pick your role. Shoot your video. Submit. That's it."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION: Posters -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <p class="text-[11px] font-semibold tracking-widest uppercase text-dark/30 mb-3">Film Poster Cards (3 slots)</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <?php
                                    $posterSlots = [
                                        ['Poster 1', 'landing_poster_url',  'landing_poster_title',  'landing_trailer_url'],
                                        ['Poster 2', 'landing_poster2_url', 'landing_poster2_title', 'landing_trailer2_url'],
                                        ['Poster 3', 'landing_poster3_url', 'landing_poster3_title', 'landing_trailer3_url'],
                                        ['Poster 4', 'landing_poster4_url', 'landing_poster4_title', 'landing_trailer4_url'],
                                        ['Poster 5', 'landing_poster5_url', 'landing_poster5_title', 'landing_trailer5_url'],
                                        ['Poster 6', 'landing_poster6_url', 'landing_poster6_title', 'landing_trailer6_url'],
                                    ];
                                    foreach ($posterSlots as $p):
                                    $currentUrl = $settingsModel->get($p[1], '');
                                    ?>
                                    <div class="bg-dark/[.025] rounded-xl p-4 space-y-3" x-data="imageUploader('<?= $p[1] ?>', '<?= addslashes(htmlspecialchars($currentUrl)) ?>')">
                                        <p class="text-xs font-semibold text-dark/50"><?= $p[0] ?></p>

                                        <!-- Poster image uploader -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1.5">Poster Image</label>
                                            <div
                                                class="relative border-2 rounded-xl transition-all cursor-pointer overflow-hidden bg-white"
                                                :class="dragging ? 'border-dark' : 'border-dashed border-dark/15 hover:border-dark/30'"
                                                style="aspect-ratio:2/3;max-height:200px"
                                                @dragover.prevent="dragging=true"
                                                @dragleave.prevent="dragging=false"
                                                @drop.prevent="onDrop($event)"
                                                @click="$refs.imgInput.click()">
                                                <input type="file" x-ref="imgInput" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" @change="onFile($event)">

                                                <template x-if="!preview && !uploading">
                                                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                        <svg class="w-7 h-7 text-dark/15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                        <p class="text-[11px] text-dark/30">Drop or click</p>
                                                    </div>
                                                </template>

                                                <template x-if="uploading">
                                                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 bg-white">
                                                        <div class="w-full bg-dark/10 rounded-full h-1 overflow-hidden">
                                                            <div class="h-full bg-dark rounded-full" :style="'width:'+progress+'%'"></div>
                                                        </div>
                                                        <p class="text-[11px] text-dark/40" x-text="progress+'%'"></p>
                                                    </div>
                                                </template>

                                                <template x-if="preview && !uploading">
                                                    <div class="absolute inset-0">
                                                        <img :src="preview" alt="Poster" style="width:100%;height:100%;object-fit:cover">
                                                        <div class="absolute top-1.5 right-1.5">
                                                            <button type="button" @click.stop="clearImage()" class="w-6 h-6 bg-white/90 rounded-full flex items-center justify-center shadow hover:bg-white transition">
                                                                <svg class="w-3 h-3 text-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </div>
                                                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5">
                                                            <p class="text-[10px] text-white/80 font-medium truncate" x-text="filename || 'Uploaded'"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                            <template x-if="uploadError">
                                                <p class="text-[11px] text-red-500 mt-1" x-text="uploadError"></p>
                                            </template>
                                        </div>

                                        <!-- Film title -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1">Film Title</label>
                                            <input type="text" x-model="form.<?= $p[2] ?>" placeholder="Film name..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>

                                        <!-- Trailer Video Uploader -->
                                        <div x-data="videoUploader('<?= $p[3] ?>', '<?= addslashes(htmlspecialchars($settingsModel->get($p[3], ''))) ?>')">
                                            <label class="block text-[11px] text-dark/40 mb-1.5">Trailer Video</label>
                                            <div
                                                class="relative border-2 rounded-xl transition-all overflow-hidden bg-white"
                                                :class="dragging ? 'border-dark bg-dark/5' : 'border-dashed border-dark/15 hover:border-dark/30'"
                                                style="min-height:80px;cursor:pointer"
                                                @dragover.prevent="dragging=true"
                                                @dragleave.prevent="dragging=false"
                                                @drop.prevent="onDrop($event)"
                                                @click="$refs.vidInput.click()">
                                                <input type="file" x-ref="vidInput" class="hidden"
                                                    accept="video/mp4,video/quicktime,video/webm,video/x-msvideo"
                                                    @change="onFile($event)">

                                                <!-- Empty -->
                                                <template x-if="!preview && !uploading">
                                                    <div class="flex flex-col items-center justify-center gap-1.5 p-4 text-center">
                                                        <svg class="w-6 h-6 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                        <p class="text-[11px] text-dark/30 font-medium">Drop video or <span class="underline">click to browse</span></p>
                                                        <p class="text-[10px] text-dark/20">MP4 · MOV · WEBM · max 500 MB</p>
                                                    </div>
                                                </template>

                                                <!-- Uploading -->
                                                <template x-if="uploading">
                                                    <div class="flex flex-col items-center justify-center gap-2 p-4">
                                                        <div class="w-full bg-dark/10 rounded-full h-1 overflow-hidden">
                                                            <div class="h-full bg-dark rounded-full transition-all" :style="'width:'+progress+'%'"></div>
                                                        </div>
                                                        <p class="text-[11px] text-dark/40" x-text="progress+'% — uploading...'"></p>
                                                    </div>
                                                </template>

                                                <!-- Uploaded -->
                                                <template x-if="preview && !uploading">
                                                    <div class="flex items-center gap-2.5 px-3 py-2.5">
                                                        <div class="w-8 h-8 rounded-lg bg-green-50 border border-green-200 flex items-center justify-center flex-shrink-0">
                                                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-[12px] font-medium text-dark truncate" x-text="filename || 'Video uploaded'"></p>
                                                            <p class="text-[10px] text-green-600">✓ Ready to play</p>
                                                        </div>
                                                        <button type="button" @click.stop="clearVideo()"
                                                            class="w-6 h-6 rounded-full bg-dark/5 hover:bg-dark/10 flex items-center justify-center flex-shrink-0 transition">
                                                            <svg class="w-3 h-3 text-dark/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <template x-if="uploadError">
                                                <p class="text-[11px] text-red-500 mt-1" x-text="uploadError"></p>
                                            </template>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SECTION: About -->
                            <div class="mb-6">
                                <p class="text-[11px] font-semibold tracking-widest uppercase text-dark/30 mb-3">About Section</p>
                                <div>
                                    <label class="block text-xs font-medium text-dark/60 mb-1.5">About Text</label>
                                    <textarea x-model="form.landing_about_text" rows="3" placeholder="Describe Faceless Pictures..."
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition resize-y"></textarea>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <button @click="saveLandingSettings()"
                                    class="bg-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:bg-dark/80 transition flex items-center gap-2"
                                    :disabled="saving">
                                    <svg x-show="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span x-show="!saving">Save All Settings</span>
                                    <span x-show="saving">Saving...</span>
                                </button>
                                <span x-show="saved" x-transition class="text-green-600 text-sm font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Saved successfully
                                </span>
                            </div>
                        </div>

                        <!-- Audition Briefs -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5 md:col-span-2 lg:col-span-3" x-data="auditionBriefs()">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Audition Briefs (shown on /actor, /director, /writer pages)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-dark/50 mb-1">🎭 Actor — Dialog Brief</label>
                                    <textarea x-model="briefs.actor_dialog_script" rows="4" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-dark/50 mb-1">🎤 Actor — Song Brief</label>
                                    <textarea x-model="briefs.actor_song_script" rows="4" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-dark/50 mb-1">🎬 Director Brief</label>
                                    <textarea x-model="briefs.director_brief" rows="4" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-dark/50 mb-1">✍️ Writer Brief</label>
                                    <textarea x-model="briefs.writer_brief" rows="4" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold/30"></textarea>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center gap-3">
                                <button @click="saveBriefs()" class="bg-dark text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:bg-dark/80 transition" :disabled="saving">
                                    <span x-show="!saving">Save Briefs</span>
                                    <span x-show="saving">Saving...</span>
                                </button>
                                <span x-show="saved" class="text-green-600 text-sm font-medium">✓ Saved</span>
                            </div>
                        </div>

                        <!-- Debug Controls -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
                                <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Debug Mode
                            </h3>
                            
                            <div class="flex items-center justify-between p-3 md:p-4 bg-cream rounded-xl mb-4">
                                <div class="min-w-0 mr-3">
                                    <p class="font-medium text-dark text-[13px]">Debug Logging</p>
                                    <p class="text-[11px] text-dark/40 truncate">Logs to /logs/debug.log</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
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
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
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
                                    <div class="min-w-0">
                                        <p class="font-medium text-dark text-[12px] truncate"><?= e($log['name']) ?></p>
                                        <p class="text-[10px] text-dark/40"><?= date('M j, H:i', $log['modified']) ?></p>
                                    </div>
                                    <span class="text-[10px] text-dark/40 flex-shrink-0 ml-2"><?= number_format($log['size'] / 1024, 1) ?> KB</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Environment -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-5">
                            <h3 class="font-semibold text-dark mb-4 flex items-center gap-2 text-[14px] md:text-base">
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

                    <!-- Log Viewers - Stack on mobile -->
                    <?php if ($debugLogContent || $errorLogContent): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mt-6">
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

    <!-- Video Delete Modal -->
    <div x-show="videoDeleteModalOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="videoDeleteModalOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            
            <!-- Header with icon -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-semibold text-[17px]" x-text="videoDeleteBulk ? 'Delete Multiple Videos' : 'Delete Video'"></h3>
                        <p class="text-white/70 text-[13px]">This action cannot be undone</p>
                    </div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="px-6 py-5">
                <!-- Video title or count -->
                <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-4">
                    <template x-if="!videoDeleteBulk">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-red-800 font-medium text-[14px]" x-text="videoDeleteTitle"></p>
                                <p class="text-red-600/70 text-[12px] mt-0.5">Video ID: <span x-text="videoDeleteId"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="videoDeleteBulk">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <p class="text-red-800 font-medium text-[14px]"><span x-text="videoDeleteCount"></span> videos selected for deletion</p>
                        </div>
                    </template>
                </div>
                
                <!-- Warning list -->
                <div class="space-y-2 mb-5">
                    <p class="text-dark/70 text-[13px] font-medium">This will permanently:</p>
                    <div class="flex items-center gap-2 text-[13px] text-dark/60">
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Remove video file(s) from server</span>
                    </div>
                    <div class="flex items-center gap-2 text-[13px] text-dark/60">
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Delete all database records</span>
                    </div>
                    <div class="flex items-center gap-2 text-[13px] text-dark/60">
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Remove from YouTube queue (if queued)</span>
                    </div>
                </div>
                
                <!-- Warning notice -->
                <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg mb-5">
                    <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-[12px] text-amber-700">This action is irreversible. Make sure you want to delete <span x-text="videoDeleteBulk ? 'these videos' : 'this video'"></span>.</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                <button @click="videoDeleteModalOpen = false" 
                        class="px-5 py-2.5 text-[13px] text-dark/60 hover:text-dark hover:bg-gray-100 rounded-xl transition font-medium">
                    Cancel
                </button>
                <button @click="executeVideoDelete()" 
                        class="px-5 py-2.5 text-[13px] bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-medium flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span x-text="videoDeleteBulk ? 'Delete ' + videoDeleteCount + ' Videos' : 'Delete Video'"></span>
                </button>
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
    <div x-show="videoDetailOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="closeVideoDetail()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden" @click.stop>
            <!-- Header -->
            <div class="px-6 py-4 border-b border-dark/10 flex items-center justify-between">
                <h3 class="font-display text-[20px] text-dark" x-text="videoDetail.title"></h3>
                <button @click="closeVideoDetail()" class="text-dark/40 hover:text-dark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <!-- Content -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Video Preview -->
                <template x-if="videoDetail.filePath">
                    <div class="mb-6">
                        <video id="detail-video-player" :src="'/uploads/' + videoDetail.filePath" controls playsinline class="w-full rounded-xl bg-dark max-h-[300px] plyr-video"></video>
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
                            <div class="text-[32px] font-display" :class="(videoDetail.feedback?.score ?? videoDetail.aiScore ?? 0) >= 70 ? 'text-green-600' : ((videoDetail.feedback?.score ?? videoDetail.aiScore ?? 0) >= 40 ? 'text-amber-600' : 'text-red-600')" x-text="videoDetail.feedback?.score ?? videoDetail.aiScore ?? 'N/A'"></div>
                            <div class="text-[10px] text-dark/40 uppercase">Quality Score</div>
                        </div>
                        <div class="flex-1">
                            <div class="h-3 bg-dark/10 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all" 
                                     :class="(videoDetail.feedback?.score ?? videoDetail.aiScore ?? 0) >= 70 ? 'bg-green-500' : ((videoDetail.feedback?.score ?? videoDetail.aiScore ?? 0) >= 40 ? 'bg-amber-500' : 'bg-red-500')"
                                     :style="'width: ' + (videoDetail.feedback?.score ?? videoDetail.aiScore ?? 0) + '%'"></div>
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
                    
                    <!-- NSFW Analysis (if available) -->
                    <template x-if="videoDetail.feedback?.nsfw_result && Object.keys(videoDetail.feedback.nsfw_result).length > 0">
                        <div class="mt-4 pt-4 border-t border-dark/10">
                            <span class="text-[11px] text-dark/40 uppercase">Content Safety Analysis:</span>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-[11px]">
                                <template x-for="(value, key) in videoDetail.feedback.nsfw_result" :key="key">
                                    <div class="flex justify-between bg-dark/5 rounded px-2 py-1">
                                        <span class="text-dark/50 capitalize" x-text="key.replace(/_/g, ' ')"></span>
                                        <span :class="(typeof value === 'number' && value > 0.5 && key !== 'frames_checked') ? 'text-red-600 font-medium' : 'text-green-600'" 
                                              x-text="typeof value === 'number' ? (key === 'frames_checked' ? value : (value <= 1 ? Math.round(value * 100) + '%' : value)) : value"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Show raw feedback data for debugging if no structured data -->
                    <template x-if="!videoDetail.feedback?.nsfw_result && !videoDetail.feedback?.transcript && videoDetail.feedback?.score === undefined">
                        <div class="mt-4 pt-4 border-t border-dark/10">
                            <span class="text-[11px] text-dark/40 uppercase">Raw AI Data:</span>
                            <pre class="text-[10px] text-dark/50 mt-1 bg-dark/5 p-2 rounded overflow-auto max-h-32" x-text="JSON.stringify(videoDetail.feedback, null, 2)"></pre>
                        </div>
                    </template>
                    
                    <!-- Checked At -->
                    <template x-if="videoDetail.feedback?.checked_at">
                        <div class="mt-3 text-[10px] text-dark/30 text-right">
                            Analyzed: <span x-text="videoDetail.feedback.checked_at"></span>
                        </div>
                    </template>
                </div>
                
                <!-- Actions -->
                <div class="flex gap-3">
                    <button @click="closeVideoDetail(); approveVideo(videoDetail.id)" class="flex-1 bg-green-600 text-white py-3 rounded-xl text-[13px] font-medium hover:bg-green-700 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve Video
                    </button>
                    <button @click="closeVideoDetail(); rejectVideo(videoDetail.id)" class="flex-1 bg-crimson text-white py-3 rounded-xl text-[13px] font-medium hover:bg-crimson/90 transition flex items-center justify-center gap-2">
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
                submissions: 'AUDITION SUBMISSIONS',
                seasons: 'SEASONS',
                scripts: 'SCRIPTS',
                aiconfig: 'AI CONFIGURATION',
                youtube: 'YOUTUBE',
                email: 'EMAIL',
                google: 'GOOGLE LOGIN',
                settings: 'SETTINGS'
            },
            
            // Data from PHP
            videos: <?= json_encode($allVideos) ?>,
            users: <?= json_encode($allUsers) ?>,
            seasons: <?= json_encode($allSeasons) ?>,
            scripts: <?= json_encode($allScripts) ?>,
            activeSeason: <?= json_encode($activeSeason) ?>,

            // Submissions (public auditions — no login)
            submissions: <?= json_encode($allSubmissions) ?>,
            submissionCounts: <?= json_encode($submissionCounts) ?>,
            submissionRoles: <?= json_encode($submissionRoles) ?>,
            submissionTotal: <?= (int)$submissionTotal ?>,
            filteredSubmissions: <?= json_encode($allSubmissions) ?>,
            submissionSearch: '',
            submissionRoleFilter: '',
            submissionStatusFilter: '',
            viewingSubmission: null,
            submissionAdminNotes: '',
            
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
            
            // Selected videos for bulk operations
            selectedVideos: [],
            
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
            scriptForm: { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: '', image_url: '', rules: '' },
            editingScript: null,
            
            // Guides
            guides: <?= json_encode($guides) ?>,
            guideTab: 'actor',
            
            // Delete modal
            deleteModalOpen: false,
            deleteType: '',
            deleteId: null,
            deleteName: '',
            
            // Video delete modal
            videoDeleteModalOpen: false,
            videoDeleteId: null,
            videoDeleteTitle: '',
            videoDeleteBulk: false,
            videoDeleteCount: 0,
            
            // Confirm modal
            confirmModalOpen: false,
            confirmMessage: '',
            confirmCallback: null,
            
            // YouTube test
            testingYouTube: false,
            youtubeTestResults: null,
            
            // YouTube auto-publish
            youtubeAutoPublish: true,
            publishQueue: [],
            selectedQueueVideos: [],
            loadingPublishQueue: false,
            publishingAll: false,
            publishingVideoId: null,
            
            // Auto-refresh interval
            refreshInterval: null,
            isRefreshing: false,
            
            // Toast
            toastShow: false,
            toastMessage: '',
            toastType: 'success',
            
            csrf: '<?= csrf_token() ?>',
            
            init() {
                window.addEventListener('resize', () => {
                    this.sidebarCollapsed = window.innerWidth < 1024;
                });
                
                // Load YouTube status
                this.loadYouTubeStatus();

                // Listen for script image picker selections
                document.addEventListener('script-image-picked', e => {
                    this.scriptForm.image_url = e.detail.url;
                    window.activeScriptForm = this.scriptForm;
                });
                
                // Initial refresh
                this.silentRefreshVideos();
                
                // Start auto-refresh every 10 seconds - runs on ALL tabs
                this.refreshInterval = setInterval(() => {
                    this.silentRefreshVideos();
                }, 10000);
                
                console.log('Admin dashboard initialized with global auto-refresh (10s)');
            },
            
            // Silent refresh videos without UI reload - runs globally
            async silentRefreshVideos() {
                this.isRefreshing = true;
                try {
                    const res = await fetch('/api/admin/videos/refresh');
                    const data = await res.json();
                    if (data.success) {
                        // Check if any video status changed (for showing toast)
                        const oldVideos = new Map(this.videos.map(v => [v.id, v.ai_status]));
                        const oldCount = this.videos.length;
                        
                        // Update videos array
                        this.videos = data.videos.map(v => {
                            // Parse ai_feedback if it's a string
                            if (v.ai_feedback && typeof v.ai_feedback === 'string') {
                                try { v.ai_feedback = JSON.parse(v.ai_feedback); } catch(e) {}
                            }
                            return v;
                        });
                        
                        // Check for new videos
                        if (this.videos.length > oldCount) {
                            const newCount = this.videos.length - oldCount;
                            this.showToast(`${newCount} new video${newCount > 1 ? 's' : ''} submitted!`, 'success');
                        }
                        
                        // Check for status changes and notify
                        let statusChanged = false;
                        for (const video of this.videos) {
                            const oldStatus = oldVideos.get(video.id);
                            if (oldStatus && oldStatus !== video.ai_status) {
                                statusChanged = true;
                                if (video.ai_status === 'approved') {
                                    this.showToast(`"${video.title}" - AI Approved! Score: ${video.ai_score}`, 'success');
                                } else if (video.ai_status === 'rejected') {
                                    this.showToast(`"${video.title}" - AI Rejected`, 'error');
                                } else if (video.ai_status === 'flagged') {
                                    this.showToast(`"${video.title}" - Flagged for review`, 'error');
                                }
                            }
                        }
                        
                        this.youtubeAutoPublish = data.youtube_auto_publish === '1';
                        
                        // Also refresh publish queue if status changed
                        if (statusChanged) {
                            this.loadYouTubeStatus();
                        }
                    }
                } catch (e) {
                    // Silent fail - don't spam console
                } finally {
                    this.isRefreshing = false;
                }
            },
            
            // Load YouTube status and publish queue
            async loadYouTubeStatus() {
                try {
                    const res = await fetch('/api/admin/youtube/status');
                    const data = await res.json();
                    if (data.success) {
                        this.youtubeAutoPublish = data.auto_publish;
                        this.publishQueue = data.queue || [];
                    }
                } catch (e) {
                    console.log('Failed to load YouTube status');
                }
            },
            
            // Toggle YouTube auto-publish
            async toggleYouTubeAutoPublish() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/youtube/toggle', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.youtubeAutoPublish = data.auto_publish;
                        this.showToast(data.message, 'success');
                        // Refresh queue if we just paused
                        if (!this.youtubeAutoPublish) {
                            this.refreshPublishQueue();
                        }
                    } else {
                        this.showToast(data.error || 'Failed to toggle', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to toggle auto-publish', 'error');
                }
            },
            
            // Refresh publish queue
            async refreshPublishQueue() {
                this.loadingPublishQueue = true;
                await this.loadYouTubeStatus();
                this.loadingPublishQueue = false;
            },
            
            // Publish single video
            async publishSingleVideo(videoId) {
                this.publishingVideoId = videoId;
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/youtube/publish/' + videoId, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success || data.youtube_id) {
                        this.showToast('Video published to YouTube!', 'success');
                        this.publishQueue = this.publishQueue.filter(v => v.id !== videoId);
                        this.silentRefreshVideos();
                    } else {
                        this.showToast(data.error || 'Failed to publish', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to publish video', 'error');
                }
                this.publishingVideoId = null;
            },
            
            // Publish all queue
            async publishAllQueue() {
                if (this.publishQueue.length === 0) return;
                this.publishingAll = true;
                const videoIds = this.publishQueue.map(v => v.id);
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('video_ids', JSON.stringify(videoIds));
                try {
                    const res = await fetch('/api/admin/youtube/bulk-publish', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(data.message, 'success');
                        this.refreshPublishQueue();
                        this.silentRefreshVideos();
                    } else {
                        this.showToast(data.error || 'Failed to publish', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to publish videos', 'error');
                }
                this.publishingAll = false;
            },
            
            // Publish selected from queue
            async publishSelectedQueue() {
                if (this.selectedQueueVideos.length === 0) return;
                this.publishingAll = true;
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('video_ids', JSON.stringify(this.selectedQueueVideos));
                try {
                    const res = await fetch('/api/admin/youtube/bulk-publish', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(data.message, 'success');
                        this.selectedQueueVideos = [];
                        this.refreshPublishQueue();
                        this.silentRefreshVideos();
                    } else {
                        this.showToast(data.error || 'Failed to publish', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to publish videos', 'error');
                }
                this.publishingAll = false;
            },
            
            // Toast notification
            showToast(message, type = 'success') {
                this.toastMessage = message;
                this.toastType = type;
                this.toastShow = true;
                setTimeout(() => { this.toastShow = false; }, 3000);
            },
            
            // Format date helper for reactive templates
            formatDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                let hours = d.getHours();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                const mins = d.getMinutes().toString().padStart(2, '0');
                return `${months[d.getMonth()]} ${d.getDate()}, ${hours}:${mins} ${ampm}`;
            },

            // ── SUBMISSIONS ──────────────────────────────────────────────

            filterSubmissions() {
                let list = this.submissions;
                if (this.submissionRoleFilter)   list = list.filter(s => s.role === this.submissionRoleFilter);
                if (this.submissionStatusFilter) list = list.filter(s => s.status === this.submissionStatusFilter);
                if (this.submissionSearch) {
                    const q = this.submissionSearch.toLowerCase();
                    list = list.filter(s =>
                        (s.name||'').toLowerCase().includes(q) ||
                        (s.email||'').toLowerCase().includes(q) ||
                        (s.phone||'').toLowerCase().includes(q) ||
                        (s.audition_type||'').toLowerCase().includes(q)
                    );
                }
                this.filteredSubmissions = list;
            },

            viewSubmission(sub) {
                this.viewingSubmission = sub;
                this.submissionAdminNotes = sub.admin_notes || '';
            },

            async updateSubmissionStatus(id, status) {
                const fd = new FormData();
                fd.append('csrf_token', this.csrf);
                fd.append('status', status);
                try {
                    const res = await fetch('/api/admin/submissions/'+id+'/status', {method:'POST', body: fd});
                    const data = await res.json();
                    if (data.success) {
                        const sub = this.submissions.find(s => s.id === id);
                        if (sub) {
                            sub.status = status;
                            // Recount
                            const counts = {new:0,reviewed:0,shortlisted:0,rejected:0};
                            this.submissions.forEach(s => { if(counts[s.status]!==undefined) counts[s.status]++; });
                            this.submissionCounts = counts;
                        }
                        this.filterSubmissions();
                    }
                } catch(e) { console.error('Submission status update failed', e); }
            },

            async saveSubmissionNotes(id) {
                const fd = new FormData();
                fd.append('csrf_token', this.csrf);
                fd.append('status', this.viewingSubmission.status);
                fd.append('admin_notes', this.submissionAdminNotes);
                try {
                    const res = await fetch('/api/admin/submissions/'+id+'/status', {method:'POST', body: fd});
                    const data = await res.json();
                    if (data.success) {
                        const sub = this.submissions.find(s => s.id === id);
                        if (sub) sub.admin_notes = this.submissionAdminNotes;
                    }
                } catch(e) { console.error('Save notes failed', e); }
            },

            async confirmDeleteSubmission(id) {
                if (!confirm('Permanently delete this submission and its file?')) return;
                const fd = new FormData();
                fd.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/submissions/'+id+'/delete', {method:'POST', body: fd});
                    const data = await res.json();
                    if (data.success) {
                        this.submissions = this.submissions.filter(s => s.id !== id);
                        this.filteredSubmissions = this.filteredSubmissions.filter(s => s.id !== id);
                        this.submissionTotal = this.submissions.length;
                        if (this.viewingSubmission && this.viewingSubmission.id === id) this.viewingSubmission = null;
                    }
                } catch(e) { console.error('Delete submission failed', e); }
            },

            // ── END SUBMISSIONS ──────────────────────────────────────────
            
            // Format duration helper (seconds to mm:ss)
            formatDuration(seconds) {
                if (!seconds) return '';
                const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
                const secs = (seconds % 60).toString().padStart(2, '0');
                return `${mins}:${secs}`;
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

            // Video player instance
            videoPlayer: null,

            // Open video detail modal
            openVideoDetail(id, title, filePath, feedback, aiScore = null) {
                this.videoDetail = {
                    id: id,
                    title: title,
                    filePath: filePath,
                    feedback: feedback || {},
                    aiScore: aiScore  // Store ai_score from database as fallback
                };
                this.videoDetailOpen = true;
                
                // Initialize Plyr after DOM updates
                this.$nextTick(() => {
                    setTimeout(() => {
                        const videoEl = document.getElementById('detail-video-player');
                        if (videoEl && typeof Plyr !== 'undefined') {
                            // Destroy previous instance if exists
                            if (this.videoPlayer) {
                                this.videoPlayer.destroy();
                            }
                            this.videoPlayer = new Plyr(videoEl, {
                                controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
                                ratio: '16:9'
                            });
                        }
                    }, 100);
                });
            },
            
            // Close video detail modal
            closeVideoDetail() {
                // Stop and destroy video player
                if (this.videoPlayer) {
                    this.videoPlayer.pause();
                    this.videoPlayer.destroy();
                    this.videoPlayer = null;
                }
                this.videoDetailOpen = false;
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
                    title:         sc.title,
                    content:       sc.content,
                    category:      sc.category,
                    difficulty:    sc.difficulty,
                    duration_hint: sc.duration_hint  || '',
                    audition_type: sc.audition_type  || '',
                    image_url:     sc.image_url      || '',
                    rules:         sc.rules          || '',
                };
                window.activeScriptForm = this.scriptForm;
            },
            
            cancelEditScript() {
                this.editingScript = null;
                this.scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: '', image_url: '', rules: '' };
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
            
            async testYouTubeConnection() {
                this.testingYouTube = true;
                this.youtubeTestResults = null;
                this.showToast('Testing YouTube + AI connection...');
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                try {
                    const res = await fetch('/api/admin/youtube/test', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.youtubeTestResults = data;
                        if (data.all_ready) {
                            this.showToast('✓ YouTube + AI ready!', 'success');
                        } else {
                            this.showToast('Some checks failed - see results', 'warning');
                        }
                    } else {
                        this.showToast('Test failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to test connection', 'error');
                }
                this.testingYouTube = false;
            },
            
            // Email test function
            async testEmailSend() {
                const emailInput = document.querySelector('[x-model="testEmail"]');
                const testEmail = emailInput?.value || '';
                if (!testEmail) {
                    this.showToast('Please enter an email address', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('email', testEmail);
                
                try {
                    const res = await fetch('/api/admin/email/test', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Test email sent successfully!', 'success');
                    } else {
                        this.showToast('Email failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to send test email', 'error');
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
            
            // Delete single video
            async deleteVideo(videoId, title) {
                this.videoDeleteId = videoId;
                this.videoDeleteTitle = title;
                this.videoDeleteBulk = false;
                this.videoDeleteModalOpen = true;
            },
            
            // Execute video delete (called from modal)
            async executeVideoDelete() {
                if (this.videoDeleteBulk) {
                    await this.doExecuteBulkDelete();
                } else {
                    await this.doExecuteSingleDelete();
                }
                this.videoDeleteModalOpen = false;
            },
            
            // Execute single video delete
            async doExecuteSingleDelete() {
                this.showToast('Deleting video...');
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                
                try {
                    const res = await fetch('/api/admin/video/delete/' + this.videoDeleteId, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Video deleted successfully', 'success');
                        this.videos = this.videos.filter(v => v.id !== this.videoDeleteId);
                        this.selectedVideos = this.selectedVideos.filter(id => id !== this.videoDeleteId);
                    } else {
                        this.showToast('Delete failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete video', 'error');
                }
            },
            
            // Bulk delete videos
            async bulkDeleteVideos() {
                this.videoDeleteCount = this.selectedVideos.length;
                this.videoDeleteBulk = true;
                this.videoDeleteModalOpen = true;
            },
            
            // Execute bulk delete
            async doExecuteBulkDelete() {
                this.showToast(`Deleting ${this.videoDeleteCount} videos...`);
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('video_ids', JSON.stringify(this.selectedVideos));
                
                try {
                    const res = await fetch('/api/admin/video/bulk-delete', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(`${data.deleted} video(s) deleted successfully`, 'success');
                        const deletedIds = this.selectedVideos.map(id => parseInt(id));
                        this.videos = this.videos.filter(v => !deletedIds.includes(v.id));
                        this.selectedVideos = [];
                    } else {
                        this.showToast('Bulk delete failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete videos', 'error');
                }
            },
            
            // Toggle all videos selection
            toggleAllVideos(event) {
                if (event.target.checked) {
                    this.selectedVideos = this.filteredVideos.map(v => v.id);
                } else {
                    this.selectedVideos = [];
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
    
    // Google Settings Component
    function googleSettings() {
        return {
            csrf: '<?= csrf_token() ?>',
            clientId: '',
            clientSecret: '',
            redirectUri: '<?= get_base_url() ?>/api/auth/google/callback',
            isConfigured: false,
            hasClientId: false,
            hasClientSecret: false,
            saving: false,
            message: '',
            messageType: 'success',
            
            async loadGoogleSettings() {
                try {
                    const res = await fetch('/api/admin/google/settings');
                    const data = await res.json();
                    if (data.success && data.config) {
                        this.isConfigured = data.config.is_configured || false;
                        this.hasClientId = !!data.config.client_id;
                        this.hasClientSecret = data.config.has_secret || false;
                        this.redirectUri = data.config.redirect_uri || this.redirectUri;
                    }
                } catch (e) {
                    console.error('Failed to load Google settings', e);
                }
            },
            
            async saveGoogleSettings() {
                if (!this.clientId && !this.clientSecret) {
                    this.message = 'Please enter at least one value to update';
                    this.messageType = 'error';
                    return;
                }
                
                this.saving = true;
                this.message = '';
                
                try {
                    const formData = new FormData();
                    formData.append('csrf_token', this.csrf);
                    if (this.clientId) formData.append('google_client_id', this.clientId);
                    if (this.clientSecret) formData.append('google_client_secret', this.clientSecret);
                    
                    const res = await fetch('/api/admin/google/settings/save', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    if (data.success) {
                        this.message = data.message || 'Settings saved successfully!';
                        this.messageType = 'success';
                        this.clientId = '';
                        this.clientSecret = '';
                        // Reload status
                        if (data.config) {
                            this.isConfigured = data.config.is_configured || false;
                            this.hasClientId = !!data.config.client_id;
                            this.hasClientSecret = data.config.has_secret || false;
                        }
                    } else {
                        this.message = data.error || 'Failed to save settings';
                        this.messageType = 'error';
                    }
                } catch (e) {
                    this.message = 'Network error. Please try again.';
                    this.messageType = 'error';
                } finally {
                    this.saving = false;
                }
            },
            
            copyRedirectUri() {
                navigator.clipboard.writeText(this.redirectUri);
                this.message = 'Redirect URI copied to clipboard!';
                this.messageType = 'success';
                setTimeout(() => { this.message = ''; }, 2000);
            }
        };
    }
    
    // Email Settings Component
    function emailSettings() {
        return {
            csrf: '<?= csrf_token() ?>',
            emailProvider: 'smtp', // 'smtp' or 'resend'
            smtp: { host: '', port: '587', username: '', password: '', encryption: 'tls', from_address: '', from_name: '' },
            smtpStatus: { host: '', port: '', username: '', has_password: false, from_address: '', is_configured: false },
            resend: { api_key: '', from_address: '', from_name: 'Faceless Pictures 3' },
            resendStatus: { is_configured: false, has_api_key: false },
            showResendKey: false,
            savingResend: false,
            resendMessage: '',
            resendSuccess: false,
            notifications: { signup: true, submit: true, processing: true, approved: true, rejected: true, flagged: true, admin_address: '', admin_new_video: true, admin_flagged: true },
            showPassword: false,
            savingSmtp: false,
            savingNotifications: false,
            smtpMessage: '',
            smtpSuccess: false,
            notifMessage: '',
            notifSuccess: false,
            testEmail: '',
            testingEmail: false,
            testResult: '',
            
            async loadEmailSettings() {
                try {
                    const res = await fetch('/api/admin/email/settings');
                    const data = await res.json();
                    if (data.success) {
                        this.smtpStatus = data.config || {};
                        // Load email provider setting
                        if (data.settings) {
                            this.emailProvider = data.settings.email_provider || 'smtp';
                            // SMTP settings
                            this.smtp.host = data.settings.smtp_host || '';
                            this.smtp.port = data.settings.smtp_port || '587';
                            this.smtp.username = data.settings.smtp_username || '';
                            this.smtp.encryption = data.settings.smtp_encryption || 'tls';
                            this.smtp.from_address = data.settings.smtp_from_address || '';
                            this.smtp.from_name = data.settings.smtp_from_name || '';
                            // Resend settings
                            this.resend.from_address = data.settings.resend_from_address || '';
                            this.resend.from_name = data.settings.resend_from_name || 'Faceless Pictures 3';
                            this.resendStatus.has_api_key = !!data.settings.resend_api_key;
                            this.resendStatus.is_configured = !!data.settings.resend_api_key && !!data.settings.resend_from_address;
                            // Notification settings
                            this.notifications.signup = data.settings.email_notify_signup === '1';
                            this.notifications.submit = data.settings.email_notify_submit === '1';
                            this.notifications.processing = data.settings.email_notify_processing === '1';
                            this.notifications.approved = data.settings.email_notify_approved === '1';
                            this.notifications.rejected = data.settings.email_notify_rejected === '1';
                            this.notifications.flagged = data.settings.email_notify_flagged === '1';
                            this.notifications.admin_address = data.settings.email_admin_address || '';
                            this.notifications.admin_new_video = data.settings.email_admin_new_video === '1';
                            this.notifications.admin_flagged = data.settings.email_admin_flagged === '1';
                        }
                    }
                } catch (e) { console.error('Failed to load email settings', e); }
            },
            
            setProvider(provider) {
                const providers = {
                    gmail: { host: 'smtp.gmail.com', port: '587', encryption: 'tls' },
                    zoho: { host: 'smtp.zoho.com', port: '587', encryption: 'tls' },
                    outlook: { host: 'smtp.office365.com', port: '587', encryption: 'tls' },
                    sendgrid: { host: 'smtp.sendgrid.net', port: '587', encryption: 'tls' },
                    mailgun: { host: 'smtp.mailgun.org', port: '587', encryption: 'tls' }
                };
                if (providers[provider]) {
                    this.smtp.host = providers[provider].host;
                    this.smtp.port = providers[provider].port;
                    this.smtp.encryption = providers[provider].encryption;
                }
            },
            
            async saveSmtpSettings() {
                this.savingSmtp = true;
                this.smtpMessage = '';
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('email_provider', 'smtp');
                formData.append('smtp_host', this.smtp.host);
                formData.append('smtp_port', this.smtp.port);
                formData.append('smtp_username', this.smtp.username);
                if (this.smtp.password) formData.append('smtp_password', this.smtp.password);
                formData.append('smtp_encryption', this.smtp.encryption);
                formData.append('smtp_from_address', this.smtp.from_address);
                formData.append('smtp_from_name', this.smtp.from_name);
                
                try {
                    const res = await fetch('/api/admin/email/settings/save', { method: 'POST', body: formData });
                    const data = await res.json();
                    this.smtpSuccess = data.success;
                    this.smtpMessage = data.success ? 'SMTP settings saved!' : (data.error || 'Failed to save');
                    if (data.success) {
                        this.smtp.password = '';
                        this.emailProvider = 'smtp';
                        setTimeout(() => location.reload(), 1500);
                    }
                } catch (e) {
                    this.smtpSuccess = false;
                    this.smtpMessage = 'Failed to save settings';
                }
                this.savingSmtp = false;
            },
            
            async saveResendSettings() {
                this.savingResend = true;
                this.resendMessage = '';
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('email_provider', 'resend');
                if (this.resend.api_key) formData.append('resend_api_key', this.resend.api_key);
                formData.append('resend_from_address', this.resend.from_address);
                formData.append('resend_from_name', this.resend.from_name);
                
                try {
                    const res = await fetch('/api/admin/email/settings/save', { method: 'POST', body: formData });
                    const data = await res.json();
                    this.resendSuccess = data.success;
                    this.resendMessage = data.success ? 'Resend settings saved!' : (data.error || 'Failed to save');
                    if (data.success) {
                        this.resend.api_key = '';
                        this.emailProvider = 'resend';
                        setTimeout(() => location.reload(), 1500);
                    }
                } catch (e) {
                    this.resendSuccess = false;
                    this.resendMessage = 'Failed to save settings';
                }
                this.savingResend = false;
            },
            
            async saveNotificationSettings() {
                this.savingNotifications = true;
                this.notifMessage = '';
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                if (this.notifications.signup) formData.append('email_notify_signup', '1');
                if (this.notifications.submit) formData.append('email_notify_submit', '1');
                if (this.notifications.processing) formData.append('email_notify_processing', '1');
                if (this.notifications.approved) formData.append('email_notify_approved', '1');
                if (this.notifications.rejected) formData.append('email_notify_rejected', '1');
                if (this.notifications.flagged) formData.append('email_notify_flagged', '1');
                formData.append('email_admin_address', this.notifications.admin_address);
                if (this.notifications.admin_new_video) formData.append('email_admin_new_video', '1');
                if (this.notifications.admin_flagged) formData.append('email_admin_flagged', '1');
                
                try {
                    const res = await fetch('/api/admin/email/settings/save', { method: 'POST', body: formData });
                    const data = await res.json();
                    this.notifSuccess = data.success;
                    this.notifMessage = data.success ? 'Notification settings saved!' : (data.error || 'Failed to save');
                } catch (e) {
                    this.notifSuccess = false;
                    this.notifMessage = 'Failed to save settings';
                }
                this.savingNotifications = false;
            },
            
            async sendTestEmail() {
                if (!this.testEmail) return;
                this.testingEmail = true;
                this.testResult = '';
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append('email', this.testEmail);
                try {
                    const res = await fetch('/api/admin/email/test', { method: 'POST', body: formData });
                    const data = await res.json();
                    this.testResult = data.success ? 'Test email sent successfully!' : ('Failed: ' + (data.error || 'Unknown error'));
                } catch (e) {
                    this.testResult = 'Failed to send test email';
                }
                this.testingEmail = false;
            }
        };
    }

    // Image uploader component for settings fields
    function imageUploader(fieldKey, initialUrl) {        return {
            fieldKey,
            preview:     initialUrl || null,
            filename:    initialUrl ? initialUrl.split('/').pop() : '',
            dragging:    false,
            uploading:   false,
            progress:    0,
            uploadError: '',

            onDrop(e) {
                this.dragging = false;
                const f = e.dataTransfer?.files?.[0];
                if (f) this.upload(f);
            },
            onFile(e) {
                const f = e.target.files?.[0];
                if (f) this.upload(f);
            },
            clearImage() {
                this.preview  = null;
                this.filename = '';
                this.uploadError = '';
                // Also clear the parent form binding
                const ev = new CustomEvent('image-cleared', { detail: { field: this.fieldKey } });
                document.dispatchEvent(ev);
            },
            upload(file) {
                const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                if (!allowed.includes(file.type)) {
                    this.uploadError = 'Only JPG, PNG, WEBP or GIF accepted.';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.uploadError = 'Image must be under 5 MB.';
                    return;
                }

                this.uploading   = true;
                this.progress    = 0;
                this.uploadError = '';

                // Show local preview immediately
                const reader = new FileReader();
                reader.onload = e => { this.preview = e.target.result; };
                reader.readAsDataURL(file);

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd   = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('field',      this.fieldKey);
                fd.append('image',      file);

                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100);
                };
                xhr.onload = () => {
                    this.uploading = false;
                    try {
                        const r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            this.preview  = r.url;
                            this.filename = r.url.split('/').pop();
                            // Update the parent landingSettings form
                            const ev = new CustomEvent('image-uploaded', {
                                detail: { field: this.fieldKey, url: r.url }
                            });
                            document.dispatchEvent(ev);
                        } else {
                            this.uploadError = r.error || 'Upload failed.';
                        }
                    } catch(err) {
                        this.uploadError = 'Server error. Try again.';
                    }
                };
                xhr.onerror = () => {
                    this.uploading   = false;
                    this.uploadError = 'Network error. Try again.';
                };
                xhr.open('POST', '/api/admin/settings/upload-image');
                xhr.send(fd);
            }
        };
    }

    // Script card image picker — upload new or pick from existing uploaded images
    function scriptImagePicker() {
        return {
            pickerTab:     'upload',
            uploadDragging:false,
            uploadProgress:0,
            uploadError:   '',
            galleryImages: [],
            galleryLoading:false,
            _galleryLoaded:false,

            init() {
                // Preload gallery in background
                this.loadGallery();
            },

            onDrop(e) {
                this.uploadDragging = false;
                const f = e.dataTransfer?.files?.[0];
                if (f) this.upload(f);
            },
            onFile(e) {
                const f = e.target.files?.[0];
                if (f) this.upload(f);
            },

            upload(file) {
                const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                if (!allowed.includes(file.type)) {
                    this.uploadError = 'Only JPG, PNG, WEBP or GIF accepted.';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    this.uploadError = 'Image must be under 5 MB.';
                    return;
                }
                this.uploadError   = '';
                this.uploadProgress = 1;

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd   = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('image', file);

                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) this.uploadProgress = Math.round(e.loaded / e.total * 100);
                };
                xhr.onload = () => {
                    this.uploadProgress = 0;
                    try {
                        const r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            // Set on parent scriptForm
                            const form = this.$root.closest('[x-data]')?.__x?.$data;
                            if (window.activeScriptForm) {
                                window.activeScriptForm.image_url = r.url;
                            }
                            // Also update via Alpine's $dispatch to communicate with parent
                            this.$dispatch('script-image-picked', { url: r.url });
                            // Refresh gallery
                            this._galleryLoaded = false;
                            this.loadGallery();
                            this.pickerTab = 'gallery';
                        } else {
                            this.uploadError = r.error || 'Upload failed.';
                        }
                    } catch(err) {
                        this.uploadError = 'Server error. Try again.';
                    }
                };
                xhr.onerror = () => {
                    this.uploadProgress = 0;
                    this.uploadError = 'Network error.';
                };
                xhr.open('POST', '/api/admin/media/upload-script-image');
                xhr.send(fd);
            },

            selectFromGallery(url) {
                this.$dispatch('script-image-picked', { url });
                // Update scriptForm directly via parent Alpine scope
                if (typeof scriptForm !== 'undefined') {
                    scriptForm.image_url = url;
                }
            },

            async loadGallery() {
                if (this._galleryLoaded) return;
                this.galleryLoading = true;
                try {
                    const res  = await fetch('/api/admin/media/images');
                    const data = await res.json();
                    if (data.success) {
                        this.galleryImages  = data.images;
                        this._galleryLoaded = true;
                    }
                } catch(e) { /* silent */ }
                this.galleryLoading = false;
            }
        };
    }

    // Video uploader component for trailer fields
    function videoUploader(fieldKey, initialUrl) {
        return {
            fieldKey,
            preview:     initialUrl || null,
            filename:    initialUrl ? initialUrl.split('/').pop() : '',
            dragging:    false,
            uploading:   false,
            progress:    0,
            uploadError: '',

            onDrop(e) {
                this.dragging = false;
                const f = e.dataTransfer?.files?.[0];
                if (f) this.upload(f);
            },
            onFile(e) {
                const f = e.target.files?.[0];
                if (f) this.upload(f);
            },
            clearVideo() {
                this.preview  = null;
                this.filename = '';
                this.uploadError = '';
                document.dispatchEvent(new CustomEvent('image-cleared', { detail: { field: this.fieldKey } }));
            },
            upload(file) {
                const allowed = ['video/mp4','video/quicktime','video/webm','video/x-msvideo','video/mpeg','video/avi'];
                const ext = file.name.split('.').pop().toLowerCase();
                const okExt = ['mp4','mov','webm','avi','mpeg'];
                if (!allowed.includes(file.type) && !okExt.includes(ext)) {
                    this.uploadError = 'Only MP4, MOV, or WEBM video files accepted.';
                    return;
                }
                if (file.size > 500 * 1024 * 1024) {
                    this.uploadError = 'Video must be under 500 MB.';
                    return;
                }

                this.uploading   = true;
                this.progress    = 0;
                this.uploadError = '';
                this.preview     = 'uploading'; // truthy placeholder

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd   = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('field',      this.fieldKey);
                fd.append('file',       file);   // controller accepts 'file' or 'image'

                const xhr = new XMLHttpRequest();
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100);
                };
                xhr.onload = () => {
                    this.uploading = false;
                    try {
                        const r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            this.preview  = r.url;
                            this.filename = r.url.split('/').pop();
                            document.dispatchEvent(new CustomEvent('image-uploaded', {
                                detail: { field: this.fieldKey, url: r.url }
                            }));
                        } else {
                            this.uploadError = r.error || 'Upload failed.';
                            this.preview = null;
                        }
                    } catch(err) {
                        this.uploadError = 'Server error. Try again.';
                        this.preview = null;
                    }
                };
                xhr.onerror = () => {
                    this.uploading   = false;
                    this.preview     = null;
                    this.uploadError = 'Network error. Try again.';
                };
                xhr.open('POST', '/api/admin/settings/upload-image');
                xhr.send(fd);
            }
        };
    }

    // Landing page settings panel
    function landingSettings() {
        return {
            saving: false, saved: false,
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            form: {
                site_logo_url:         '<?= addslashes(htmlspecialchars($settingsModel->get('site_logo_url',''))) ?>',
                landing_headline:      <?= json_encode($settingsModel->get('landing_headline','NO FACE. NO CONNECTIONS. JUST TALENT.')) ?>,
                site_tagline:          <?= json_encode($settingsModel->get('site_tagline',"India's first anonymous film competition — no face, no connections, just raw talent.")) ?>,
                landing_roles_heading:    <?= json_encode($settingsModel->get('landing_roles_heading','Become a Star in 3 Clicks')) ?>,
                landing_roles_subheading: <?= json_encode($settingsModel->get('landing_roles_subheading',"Pick your role. Shoot your video. Submit. That's it.")) ?>,
                landing_poster_url:    '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_url',''))) ?>',
                landing_poster_title:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_title','Faceless Pictures 3'))) ?>',
                landing_trailer_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer_url',''))) ?>',
                landing_poster2_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_url',''))) ?>',
                landing_poster2_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_title',''))) ?>',
                landing_trailer2_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer2_url',''))) ?>',
                landing_poster3_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_url',''))) ?>',
                landing_poster3_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_title',''))) ?>',
                landing_trailer3_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer3_url',''))) ?>',
                landing_poster4_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_url',''))) ?>',
                landing_poster4_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_title',''))) ?>',
                landing_trailer4_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer4_url',''))) ?>',
                landing_poster5_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_url',''))) ?>',
                landing_poster5_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_title',''))) ?>',
                landing_trailer5_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer5_url',''))) ?>',
                landing_poster6_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_url',''))) ?>',
                landing_poster6_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_title',''))) ?>',
                landing_trailer6_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer6_url',''))) ?>',
                landing_about_text:    <?= json_encode($settingsModel->get('landing_about_text',"Faceless Pictures is India's first anonymous film competition.")) ?>,
            },
            init() {
                // Sync uploaded image URLs back into the form
                document.addEventListener('image-uploaded', e => {
                    if (this.form.hasOwnProperty(e.detail.field)) {
                        this.form[e.detail.field] = e.detail.url;
                    }
                });
                document.addEventListener('image-cleared', e => {
                    if (this.form.hasOwnProperty(e.detail.field)) {
                        this.form[e.detail.field] = '';
                    }
                });
            },
            async saveLandingSettings() {
                this.saving = true; this.saved = false;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                for (const [key, value] of Object.entries(this.form)) {
                    const fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('key', key);
                    fd.append('value', value);
                    await fetch('/api/admin/settings/landing', {method:'POST', body: fd});
                }
                this.saving = false; this.saved = true;
                setTimeout(() => this.saved = false, 2500);
            }
        };
    }

    // Audition briefs panel
    function auditionBriefs() {
        return {
            saving: false, saved: false,
            briefs: {
                actor_dialog_script: <?= json_encode($settingsModel->get('actor_dialog_script','')) ?>,
                actor_song_script:   <?= json_encode($settingsModel->get('actor_song_script','')) ?>,
                director_brief:      <?= json_encode($settingsModel->get('director_brief','')) ?>,
                writer_brief:        <?= json_encode($settingsModel->get('writer_brief','')) ?>,
            },
            async saveBriefs() {
                this.saving = true; this.saved = false;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                for (const [key, value] of Object.entries(this.briefs)) {
                    const fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('key', key);
                    fd.append('value', value);
                    await fetch('/api/admin/settings/landing', {method:'POST', body: fd});
                }
                this.saving = false; this.saved = true;
                setTimeout(() => this.saved = false, 2500);
            }
        };
    }
    </script>
</body>
</html>

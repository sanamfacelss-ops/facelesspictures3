<?php
require_once __DIR__ . '/../app/config/config.php';

if (!is_admin()) {
    if (!headers_sent()) {
        header('Location: /login');
        exit;
    }
    // Fallback if headers already sent
    echo '<script>window.location.href="/login";</script>';
    exit;
}

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

// Get recent videos for video management tab (LIMIT 20 for faster initial load)
$db = App\Config\Database::getConnection();
$stmt = $db->query(
    "SELECT v.*, u.name as user_name, u.role as user_role, s.title as season_title
     FROM videos v
     JOIN users u ON v.user_id = u.id
     JOIN seasons s ON v.season_id = s.id
     ORDER BY v.created_at DESC
     LIMIT 20"
);
$allVideos = $stmt->fetchAll();

// Count stats (using separate efficient queries instead of loading all data)
$totalUsers = count($allUsers);
$pendingCount = count($pending);
$flaggedCount = count($flagged);
$processingCount = (int)$db->query("SELECT COUNT(*) FROM videos WHERE ai_status = 'processing'")->fetchColumn();
$approvedCount = (int)$db->query("SELECT COUNT(*) FROM videos WHERE status = 'approved'")->fetchColumn();
$publishedCount = (int)$db->query("SELECT COUNT(*) FROM videos WHERE youtube_id IS NOT NULL")->fetchColumn();

// Load public submissions (new no-login system)
$allSubmissions = [];
$submissionCounts = ['new' => 0, 'reviewed' => 0, 'shortlisted' => 0, 'rejected' => 0];
$submissionRoles  = ['actor' => 0, 'director' => 0, 'writer' => 0];
$submissionTotal  = 0;
$submissionTableMissing = false;
try {
    $submissionModel = new App\Models\Submission();
    if ($submissionModel->tableExists()) {
        // Fetch counts first (fast, no joins)
        $submissionCounts = $submissionModel->countByStatus();
        $submissionRoles  = $submissionModel->countByRole();
        $submissionTotal  = $submissionModel->totalCount();
        
        // Fetch only recent submissions with linked video AI data (LIMIT 20 for faster load)
        $stmt = $db->query(
            "SELECT s.id, s.name, s.email, s.phone, s.role, s.audition_type, s.submission_tag,
                    s.status, s.file_path, s.file_path_2, s.video_id, s.video_id_2, 
                    s.submitted_at, s.notes,
                    v1.ai_score as video1_ai_score,
                    v1.ai_status as video1_ai_status,
                    v2.ai_score as video2_ai_score,
                    v2.ai_status as video2_ai_status
             FROM submissions s
             LEFT JOIN videos v1 ON s.video_id = v1.id
             LEFT JOIN videos v2 ON s.video_id_2 = v2.id
             ORDER BY s.submitted_at DESC
             LIMIT 20"
        );
        $allSubmissions = $stmt->fetchAll();
        
        // Don't parse JSON here - will parse on-demand in modal to save time
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
            
            // Force browser to reload without cache
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Location: /admin?debug_updated=1&t=' . time());
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
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
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
        
        /* Hide scrollbar utility */
        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Chrome, Safari, Opera */
        }
        
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
                
                <button @click="activeTab = 'videos'; silentRefreshVideos()" :class="activeTab === 'videos' ? 'active' : ''" class="sidebar-link w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-dark/70 relative group/item">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="lg:inline" :class="sidebarCollapsed ? 'hidden' : ''">Submissions</span>
                    <span x-show="submissionCounts.new > 0" class="ml-auto bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full lg:flex hidden" :class="sidebarCollapsed ? 'hidden' : 'lg:flex'" x-text="submissionCounts.new"></span>
                    <span class="absolute left-full ml-2 px-2 py-1 bg-dark text-white text-[11px] rounded opacity-0 group-hover/item:opacity-100 pointer-events-none whitespace-nowrap z-50 lg:hidden" :class="sidebarCollapsed ? 'lg:hidden' : 'hidden'">Submissions</span>
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

                    <!-- Manual AI Processing Button -->
                    <template x-if="videos.filter(v => v.ai_status === 'pending' || v.ai_status === null).length > 0">
                        <div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-dark">
                                            <span x-text="videos.filter(v => v.ai_status === 'pending' || v.ai_status === null).length"></span> videos waiting for AI processing
                                        </p>
                                        <p class="text-xs text-dark/60">Click to run AI analysis on pending videos</p>
                                    </div>
                                </div>
                                <button @click="processAIQueue()" 
                                        :disabled="processingAI"
                                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                    <svg x-show="!processingAI" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    <svg x-show="processingAI" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span x-text="processingAI ? 'Processing...' : 'Process Now'"></span>
                                </button>
                            </div>
                        </div>
                    </template>

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

                <!-- ==================== SUBMISSIONS TAB ==================== -->
                <div x-show="activeTab === 'videos'" x-cloak>
                    <!-- Bulk Actions Toolbar (shows when items selected) -->
                    <div x-show="selectedSubmissions.length > 0" x-cloak class="bg-crimson text-white rounded-xl p-4 mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-[13px] font-medium" x-text="selectedSubmissions.length + ' selected'"></span>
                            <button @click="selectedSubmissions = []" class="text-[11px] underline hover:no-underline">Clear</button>
                        </div>
                        <button @click="openDeleteConfirm()" class="bg-white text-crimson px-4 py-2 rounded-lg text-[12px] font-medium hover:bg-cream transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Selected
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap gap-2 mb-4 items-center">
                        <button @click="submissionRoleFilter = ''" :class="submissionRoleFilter === '' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition" @click.prevent="filterSubmissions()">All</button>
                        <button @click="submissionRoleFilter = 'actor'" :class="submissionRoleFilter === 'actor' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition" @click.prevent="filterSubmissions()">Actors</button>
                        <button @click="submissionRoleFilter = 'director'" :class="submissionRoleFilter === 'director' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition" @click.prevent="filterSubmissions()">Directors</button>
                        <button @click="submissionRoleFilter = 'writer'" :class="submissionRoleFilter === 'writer' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition" @click.prevent="filterSubmissions()">Writers</button>
                        
                        <button @click="submissionStatusFilter = ''" :class="submissionStatusFilter === '' ? 'bg-green-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition hidden sm:block" @click.prevent="filterSubmissions()">All Status</button>
                        <button @click="submissionStatusFilter = 'new'" :class="submissionStatusFilter === 'new' ? 'bg-amber-500 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition hidden sm:block" @click.prevent="filterSubmissions()">New</button>
                        <button @click="submissionStatusFilter = 'shortlisted'" :class="submissionStatusFilter === 'shortlisted' ? 'bg-green-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition hidden sm:block" @click.prevent="filterSubmissions()">Shortlisted</button>
                        <button @click="submissionStatusFilter = 'rejected'" :class="submissionStatusFilter === 'rejected' ? 'bg-red-600 text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[11px] md:text-[12px] font-medium transition hidden sm:block" @click.prevent="filterSubmissions()">Rejected</button>
                    </div>

                    <?php if ($submissionTableMissing): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                        <div class="text-3xl mb-3">⚠️</div>
                        <h3 class="font-semibold text-amber-800 mb-2">Database Migration Required</h3>
                        <p class="text-amber-700 text-sm">Run <code class="bg-amber-100 px-2 py-0.5 rounded">database/migrations/006_add_submissions_table.sql</code> to enable the Submissions system.</p>
                    </div>
                    <?php else: ?>

                    <!-- Submissions List (REUSING THE AUDITIONS TAB DESIGN) -->
                    <!-- Desktop Table View -->
                    <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="bg-cream/50 text-dark/50">
                                    <tr>
                                        <th class="px-3 py-3 text-left font-medium w-10">
                                            <input type="checkbox" @change="toggleAllSubmissions($event.target.checked)" :checked="selectedSubmissions.length === filteredSubmissions.length && filteredSubmissions.length > 0" class="rounded border-dark/20">
                                        </th>
                                        <th class="px-5 py-3 text-left font-medium">Name</th>
                                        <th class="px-5 py-3 text-left font-medium">Role</th>
                                        <th class="px-5 py-3 text-left font-medium">Type</th>
                                        <th class="px-5 py-3 text-left font-medium">AI Score 1</th>
                                        <th class="px-5 py-3 text-left font-medium">AI Score 2</th>
                                        <th class="px-5 py-3 text-left font-medium">Status</th>
                                        <th class="px-5 py-3 text-left font-medium">Submitted</th>
                                        <th class="px-5 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-dark/5">
                                    <template x-for="sub in filteredSubmissions" :key="sub.id">
                                        <tr class="hover:bg-cream/30 transition">
                                            <td class="px-3 py-3">
                                                <input type="checkbox" :value="sub.id" @change="toggleSubmissionSelection(sub.id, $event.target.checked)" :checked="selectedSubmissions.includes(sub.id)" class="rounded border-dark/20">
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="font-medium text-dark" x-text="sub.name"></div>
                                                <div class="text-[11px] text-dark/50" x-text="sub.email"></div>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-dark/5 text-dark/60" x-text="sub.role"></span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="text-[11px]" x-text="sub.audition_type"></div>
                                                <template x-if="sub.submission_tag === 'actor-dual' && sub.file_path_2">
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">Dual</span>
                                                </template>
                                            </td>
                                            <td class="px-5 py-3">
                                                <template x-if="sub.file_path">
                                                    <div class="flex flex-col gap-1">
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full" 
                                                            :class="{
                                                                'bg-green-100 text-green-700': (sub.video1_ai_status || sub.ai_status)==='approved',
                                                                'bg-red-100 text-red-700': sub.ai_flagged || (sub.video1_ai_status || sub.ai_status)==='flagged',
                                                                'bg-amber-100 text-amber-700': (sub.video1_ai_status || sub.ai_status)==='pending' || (sub.video1_ai_status || sub.ai_status)==='processing',
                                                                'bg-gray-100 text-gray-600': !(sub.video1_ai_status || sub.ai_status)
                                                            }"
                                                            x-text="sub.ai_flagged || (sub.video1_ai_status || sub.ai_status)==='flagged' ? 'Flagged' : ((sub.video1_ai_status || sub.ai_status) || '-')"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!sub.file_path">
                                                    <span class="text-[10px] text-dark/30">-</span>
                                                </template>
                                            </td>
                                            <td class="px-5 py-3">
                                                <template x-if="sub.file_path_2">
                                                    <div class="flex flex-col gap-1">
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full" 
                                                            :class="{
                                                                'bg-green-100 text-green-700': (sub.video2_ai_status || sub.ai_status_2)==='approved',
                                                                'bg-red-100 text-red-700': sub.ai_flagged_2 || (sub.video2_ai_status || sub.ai_status_2)==='flagged',
                                                                'bg-amber-100 text-amber-700': (sub.video2_ai_status || sub.ai_status_2)==='pending' || (sub.video2_ai_status || sub.ai_status_2)==='processing',
                                                                'bg-gray-100 text-gray-600': !(sub.video2_ai_status || sub.ai_status_2)
                                                            }"
                                                            x-text="sub.ai_flagged_2 || (sub.video2_ai_status || sub.ai_status_2)==='flagged' ? 'Flagged' : ((sub.video2_ai_status || sub.ai_status_2) || '-')"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!sub.file_path_2">
                                                    <span class="text-[10px] text-dark/30">-</span>
                                                </template>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] px-2 py-0.5 rounded-full" 
                                                    :class="{
                                                        'bg-amber-100 text-amber-700': sub.status==='new', 
                                                        'bg-blue-100 text-blue-700': sub.status==='reviewed',
                                                        'bg-green-100 text-green-700': sub.status==='shortlisted', 
                                                        'bg-red-100 text-red-700': sub.status==='rejected'
                                                    }" 
                                                    x-text="sub.status"></span>
                                            </td>
                                            <td class="px-5 py-3 text-dark/40 text-[11px]" x-text="formatDate(sub.submitted_at)"></td>
                                            <td class="px-5 py-3">
                                                <div class="flex gap-1 flex-wrap">
                                                    <!-- View Details -->
                                                    <button @click="viewSubmission(sub)" class="bg-dark/10 text-dark/60 px-2 py-1 rounded text-[10px] font-medium hover:bg-dark/20 transition">Details</button>
                                                    <!-- Status Change Buttons -->
                                                    <template x-if="sub.status === 'new' || sub.status === 'reviewed'">
                                                        <button @click="updateSubmissionStatus(sub.id, 'shortlisted')" class="bg-green-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-green-700 transition">✓</button>
                                                    </template>
                                                    <template x-if="sub.status !== 'rejected'">
                                                        <button @click="updateSubmissionStatus(sub.id, 'rejected')" class="bg-red-600 text-white px-2 py-1 rounded text-[10px] font-medium hover:bg-red-700 transition">✕</button>
                                                    </template>
                                                    <!-- Delete Button -->
                                                    <button @click="openDeleteConfirm(sub.id)" class="bg-red-100 text-red-700 px-2 py-1 rounded text-[10px] font-medium hover:bg-red-200 transition">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredSubmissions.length === 0">
                                        <td colspan="9" class="px-5 py-10 text-center text-dark/30">No submissions found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
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

                    <!-- Modern Card-Based Submissions List -->
                    <div class="space-y-4">
                        <template x-for="sub in filteredSubmissions" :key="sub.id">
                            <div class="bg-white rounded-xl border border-dark/5 overflow-hidden hover:shadow-lg transition-shadow">
                                <!-- Main Row -->
                                <div class="p-4 md:p-5">
                                    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                                        <!-- Left: Applicant Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start gap-3">
                                                <!-- Avatar -->
                                                <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                                                    :class="{
                                                        'bg-red-100': sub.role==='actor',
                                                        'bg-amber-100': sub.role==='director',
                                                        'bg-blue-100': sub.role==='writer'
                                                    }">
                                                    <span class="text-lg font-bold"
                                                        :class="{
                                                            'text-red-600': sub.role==='actor',
                                                            'text-amber-600': sub.role==='director',
                                                            'text-blue-600': sub.role==='writer'
                                                        }"
                                                        x-text="sub.name.charAt(0).toUpperCase()"></span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                                        <h3 class="font-semibold text-dark" x-text="sub.name"></h3>
                                                        <!-- Role Badge -->
                                                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full"
                                                            :class="{
                                                                'bg-red-100 text-red-700': sub.role==='actor',
                                                                'bg-amber-100 text-amber-700': sub.role==='director',
                                                                'bg-blue-100 text-blue-700': sub.role==='writer'
                                                            }">
                                                            <template x-if="sub.role==='actor'">🎭</template>
                                                            <template x-if="sub.role==='director'">🎬</template>
                                                            <template x-if="sub.role==='writer'">✍️</template>
                                                            <span x-text="sub.role.toUpperCase()"></span>
                                                        </span>
                                                        <!-- Dual Video Badge for Actor -->
                                                        <template x-if="sub.submission_tag === 'actor-dual' && sub.file_path_2">
                                                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded bg-purple-100 text-purple-700">
                                                                📹 Dual Video
                                                            </span>
                                                        </template>
                                                    </div>
                                                    <p class="text-sm text-dark/60 mb-2" x-text="sub.audition_type"></p>
                                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-dark/50">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                            <span x-text="sub.email"></span>
                                                        </span>
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                            <span x-text="sub.phone"></span>
                                                        </span>
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            <span x-text="formatDate(sub.submitted_at)"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Right: Status & Actions -->
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <!-- Status Dropdown -->
                                            <select :value="sub.status" @change="updateSubmissionStatus(sub.id, $event.target.value)"
                                                class="text-sm border-2 rounded-lg px-3 py-2 font-semibold focus:outline-none focus:ring-2 focus:ring-gold/40 transition"
                                                :class="{
                                                    'border-amber-200 bg-amber-50 text-amber-700': sub.status==='new',
                                                    'border-blue-200 bg-blue-50 text-blue-700': sub.status==='reviewed',
                                                    'border-green-200 bg-green-50 text-green-700': sub.status==='shortlisted',
                                                    'border-red-200 bg-red-50 text-red-700': sub.status==='rejected'
                                                }">
                                                <option value="new">🆕 New</option>
                                                <option value="reviewed">👀 Reviewed</option>
                                                <option value="shortlisted">⭐ Shortlisted</option>
                                                <option value="rejected">❌ Rejected</option>
                                            </select>
                                            
                                            <!-- View Button -->
                                            <button @click="viewSubmission(sub)"
                                                class="px-4 py-2 bg-dark text-white text-sm font-semibold rounded-lg hover:bg-dark/80 transition">
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Video Section - Expandable for Actor Dual Video -->
                                    <template x-if="sub.submission_tag === 'actor-dual' && sub.file_path_2">
                                        <div class="mt-4 pt-4 border-t border-dark/5">
                                            <!-- Toggle Button -->
                                            <button @click="sub.showVideos = !sub.showVideos" 
                                                class="w-full flex items-center justify-between px-4 py-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition text-sm font-semibold text-purple-700">
                                                <span class="flex items-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                    <span>2 Videos Submitted (Dialog + Song)</span>
                                                </span>
                                                <svg class="w-5 h-5 transition-transform" :class="{'rotate-180': sub.showVideos}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            
                                            <!-- Video Cards (Collapsible) -->
                                            <div x-show="sub.showVideos" x-collapse class="mt-3 grid md:grid-cols-2 gap-4">
                                                <!-- Dialog Video -->
                                                <div class="border-2 border-blue-200 rounded-xl p-4 bg-blue-50/50">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h4 class="font-semibold text-blue-900 flex items-center gap-2">
                                                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                                                            Dialog Audition
                                                        </h4>
                                                        <span class="text-xs px-2 py-1 rounded font-semibold"
                                                            :class="{
                                                                'bg-green-100 text-green-700': sub.ai_status==='approved',
                                                                'bg-red-100 text-red-700': sub.ai_flagged,
                                                                'bg-amber-100 text-amber-700': sub.ai_status==='pending',
                                                                'bg-gray-100 text-gray-600': !sub.ai_status || sub.ai_status==='processing'
                                                            }"
                                                            x-text="sub.ai_flagged ? '🚩 Flagged' : (sub.ai_status==='approved' ? '✅ Pass' : (sub.ai_status==='pending' ? '⏳ Pending' : '🔄 Processing'))"></span>
                                                    </div>
                                                    <template x-if="sub.file_path">
                                                        <a :href="'/uploads/'+sub.file_path" target="_blank"
                                                            class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Watch Video
                                                        </a>
                                                    </template>
                                                    <template x-if="sub.ai_notes">
                                                        <p class="mt-2 text-xs text-blue-800 bg-blue-100 rounded p-2" x-text="sub.ai_notes"></p>
                                                    </template>
                                                </div>
                                                
                                                <!-- Song Video -->
                                                <div class="border-2 border-pink-200 rounded-xl p-4 bg-pink-50/50">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <h4 class="font-semibold text-pink-900 flex items-center gap-2">
                                                            <span class="w-6 h-6 rounded-full bg-pink-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                                                            Song Audition
                                                        </h4>
                                                        <span class="text-xs px-2 py-1 rounded font-semibold"
                                                            :class="{
                                                                'bg-green-100 text-green-700': sub.ai_status_2==='approved',
                                                                'bg-red-100 text-red-700': sub.ai_flagged_2,
                                                                'bg-amber-100 text-amber-700': sub.ai_status_2==='pending',
                                                                'bg-gray-100 text-gray-600': !sub.ai_status_2 || sub.ai_status_2==='processing'
                                                            }"
                                                            x-text="sub.ai_flagged_2 ? '🚩 Flagged' : (sub.ai_status_2==='approved' ? '✅ Pass' : (sub.ai_status_2==='pending' ? '⏳ Pending' : '🔄 Processing'))"></span>
                                                    </div>
                                                    <template x-if="sub.file_path_2">
                                                        <a :href="'/uploads/'+sub.file_path_2" target="_blank"
                                                            class="flex items-center justify-center gap-2 bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Watch Video
                                                        </a>
                                                    </template>
                                                    <template x-if="sub.ai_notes_2">
                                                        <p class="mt-2 text-xs text-pink-800 bg-pink-100 rounded p-2" x-text="sub.ai_notes_2"></p>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- Single Video Display for Director/Writer -->
                                    <template x-if="sub.submission_tag !== 'actor-dual' && sub.file_path">
                                        <div class="mt-4 pt-4 border-t border-dark/5">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <a :href="'/uploads/'+sub.file_path" target="_blank"
                                                        class="flex items-center gap-2 bg-crimson hover:bg-crimson/90 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Watch Submission
                                                    </a>
                                                    <span class="text-xs px-3 py-1.5 rounded-lg font-semibold"
                                                        :class="{
                                                            'bg-green-100 text-green-700': sub.ai_status==='approved',
                                                            'bg-red-100 text-red-700': sub.ai_flagged,
                                                            'bg-amber-100 text-amber-700': sub.ai_status==='pending',
                                                            'bg-gray-100 text-gray-600': !sub.ai_status || sub.ai_status==='processing'
                                                        }"
                                                        x-text="sub.ai_flagged ? '🚩 AI Flagged' : (sub.ai_status==='approved' ? '✅ AI Approved' : (sub.ai_status==='pending' ? '⏳ AI Pending' : '🔄 Processing'))"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="filteredSubmissions.length === 0" class="bg-white rounded-xl border border-dark/5 p-12 text-center">
                            <div class="text-5xl mb-4">📋</div>
                            <h3 class="font-semibold text-dark mb-2">No Submissions Found</h3>
                            <p class="text-dark/50 text-sm">Try adjusting your filters or check back later for new auditions.</p>
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
                    <!-- Scripts List (Full Width) -->
                    <div>
                        <!-- Filter and Create Button Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <!-- Filter Buttons -->
                            <div class="flex flex-wrap gap-2">
                                <button @click="scriptFilter = 'all'" :class="scriptFilter === 'all' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">All</button>
                                <button @click="scriptFilter = 'actor'" :class="scriptFilter === 'actor' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Actor</button>
                                <button @click="scriptFilter = 'director'" :class="scriptFilter === 'director' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Director</button>
                                <button @click="scriptFilter = 'writer'" :class="scriptFilter === 'writer' ? 'bg-crimson text-white' : 'bg-white text-dark/60 hover:bg-cream'" class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition">Writer</button>
                            </div>
                            
                            <!-- Create New Script Button -->
                            <button @click="showScriptModal = true; editingScript = null; scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: 'Dialog Audition', image_url: '', preview_video_url: '', script_pdf_url: '', tune_youtube_url: '', rules: '' }; songEntries = [{ label: '', url: '' }]; if (typeof window.setScriptVideo === 'function') window.setScriptVideo(''); if (typeof window.setScriptPdf === 'function') window.setScriptPdf(''); if (typeof window.setScriptImage === 'function') window.setScriptImage('');" 
                                type="button"
                                class="bg-crimson text-white px-4 py-2 rounded-lg font-medium hover:bg-crimson/90 transition flex items-center justify-center gap-2 text-sm shadow-md whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create New Script
                            </button>
                        </div>

                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="divide-y divide-dark/5 max-h-[500px] md:max-h-[600px] overflow-y-auto">
                                <template x-for="sc in filteredScripts" :key="sc.id">
                                    <div class="p-4 hover:bg-cream/30 transition">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                            <div class="flex gap-3 flex-1 min-w-0">
                                                <!-- Script image thumbnail -->
                                                <div x-show="sc.image_url" class="w-12 h-16 rounded-lg overflow-hidden flex-shrink-0 bg-dark/5 border border-dark/10">
                                                    <img :src="sc.image_url" loading="lazy" class="w-full h-full object-cover">
                                                </div>
                                                <div x-show="!sc.image_url" class="w-12 h-16 rounded-lg flex-shrink-0 bg-dark/5 border border-dashed border-dark/15 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                        <h4 class="font-medium text-dark truncate text-[13px] md:text-[14px]" x-text="sc.title"></h4>
                                                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-dark/5 text-dark/50 capitalize" x-text="sc.category"></span>
                                                        <span x-show="sc.audition_type" class="text-[9px] px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200" x-text="sc.audition_type"></span>
                                                    </div>
                                                    <p class="text-[12px] text-dark/50 line-clamp-2" x-text="sc.content"></p>
                                                    <p class="text-[10px] text-dark/30 mt-1" x-show="sc.duration_hint" x-text="'⏱ ' + sc.duration_hint"></p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                                                <button @click="editScript(sc); showScriptModal = true" 
                                                    class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs sm:text-sm font-medium hover:bg-blue-100 transition flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    <span>Edit</span>
                                                </button>
                                                <button @click="openDeleteModal('script', sc.id, sc.title)" 
                                                    class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs sm:text-sm font-medium hover:bg-red-100 transition flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <span>Delete</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="filteredScripts.length === 0" class="p-10 text-center text-dark/30 text-[13px]">No scripts found</div>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Script Create/Edit Modal -->
                    <div x-show="showScriptModal" 
                        x-cloak
                        @keydown.escape.window="showScriptModal = false"
                        @click.self="showScriptModal = false; cancelEditScript()"
                        class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm overflow-hidden"
                        style="margin: 0;">
                        
                        <div @click.stop 
                            class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto scrollbar-hide"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95">
                            
                            <!-- Modal Header -->
                            <div class="flex items-center justify-between px-6 py-4 border-b border-dark/10">
                                <h3 class="text-lg font-semibold text-dark" x-text="editingScript ? 'Edit Script' : 'Create New Script'"></h3>
                                <button @click="showScriptModal = false; cancelEditScript()" 
                                    type="button"
                                    class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-dark/5 transition text-dark/50 hover:text-dark">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Modal Body (Form Content) -->
                            <div class="px-6 py-5 max-h-[calc(100vh-200px)] overflow-y-auto">
                                <form @submit.prevent="editingScript ? updateScript() : createScript()" id="scriptModalForm">
                                    <div class="space-y-5">
                                        <!-- SECTION: Basic Information -->
                                        <div class="pb-4 border-b border-dark/10">
                                            <h4 class="text-sm font-semibold text-dark mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                Basic Information
                                            </h4>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-[12px] text-dark/50 mb-1">Title *</label>
                                                    <input type="text" x-model="scriptForm.title" required class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="e.g., Dramatic Monologue #1">
                                                </div>
                                                <div>
                                                    <label class="block text-[12px] text-dark/50 mb-1">Script Content *</label>
                                                    <textarea x-model="scriptForm.content" rows="4" required class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-none" placeholder="Enter the script content here..."></textarea>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                    <div>
                                                        <label class="block text-[12px] text-dark/50 mb-1">Category *</label>
                                                        <select x-model="scriptForm.category"
                                                            @change="if(scriptForm.category!=='actor') scriptForm.audition_type=(scriptForm.category==='director'?'Director Audition':'Writer Submission')"
                                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                                            <option value="actor">Actor</option>
                                                            <option value="director">Director</option>
                                                            <option value="writer">Writer</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[12px] text-dark/50 mb-1">Audition Type</label>
                                                        <template x-if="scriptForm.category === 'actor'">
                                                            <select x-model="scriptForm.audition_type" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson">
                                                                <option value="Dialog Audition">Dialog</option>
                                                                <option value="Song Audition">Song</option>
                                                            </select>
                                                        </template>
                                                        <template x-if="scriptForm.category !== 'actor'">
                                                            <input type="text" :value="scriptForm.category === 'director' ? 'Director' : 'Writer'" readonly
                                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] bg-dark/[.03] text-dark/50 cursor-not-allowed">
                                                        </template>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[12px] text-dark/50 mb-1">Duration</label>
                                                        <input type="text" x-model="scriptForm.duration_hint" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson" placeholder="e.g., 60-90 sec">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECTION: Media Files -->
                                        <div class="pb-4 border-b border-dark/10">
                                            <h4 class="text-sm font-semibold text-dark mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                Media Files
                                            </h4>
                                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                                <!-- Card Image -->
                                                <div x-data="scriptImagePicker()" x-init="init()" class="bg-dark/[.02] rounded-lg p-3 border border-dark/5">
                                                    <label class="block text-[12px] font-medium text-dark/60 mb-1.5">Card Poster Image</label>
                                                    <div x-show="scriptForm.image_url" class="mb-2 relative rounded-lg overflow-hidden border border-dark/10" style="aspect-ratio:16/9;max-height:100px">
                                                        <img :src="scriptForm.image_url" loading="lazy" class="w-full h-full object-cover">
                                                        <button type="button" @click="scriptForm.image_url=''; if(typeof window.setScriptImage==='function') window.setScriptImage('')"
                                                            class="absolute top-1 right-1 w-6 h-6 bg-white/90 hover:bg-white rounded-full flex items-center justify-center shadow transition text-dark/60 hover:text-dark">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                    <div class="flex border border-dark/10 rounded-lg overflow-hidden mb-2 text-[11px]">
                                                        <button type="button" @click="pickerTab='upload'"
                                                            :class="pickerTab==='upload' ? 'bg-dark text-white' : 'bg-white text-dark/50 hover:bg-cream'"
                                                            class="flex-1 py-1.5 font-medium transition">Upload New</button>
                                                        <button type="button" @click="pickerTab='gallery'; loadGallery()"
                                                            :class="pickerTab==='gallery' ? 'bg-dark text-white' : 'bg-white text-dark/50 hover:bg-cream'"
                                                            class="flex-1 py-1.5 font-medium transition">Browse Existing</button>
                                                    </div>
                                                    <div x-show="pickerTab==='upload'">
                                                        <div class="border-2 rounded-lg transition-all cursor-pointer overflow-hidden"
                                                            :class="uploadDragging ? 'border-crimson bg-crimson/5' : 'border-dashed border-dark/20 hover:border-crimson hover:bg-crimson/5 bg-white'"
                                                            style="min-height:80px"
                                                            @dragover.prevent="uploadDragging=true"
                                                            @dragleave.prevent="uploadDragging=false"
                                                            @drop.prevent="onDrop($event)"
                                                            @click="$refs.imgPick.click()">
                                                            <input type="file" x-ref="imgPick" class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/svg+xml,image/bmp" @change="onFile($event)">
                                                            <template x-if="!uploadProgress && !uploadError">
                                                                <div class="flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                                    <div class="w-8 h-8 rounded-full bg-crimson/10 flex items-center justify-center">
                                                                        <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                                    </div>
                                                                    <p class="text-[12px] font-medium text-dark">Click to upload</p>
                                                                    <p class="text-[10px] text-dark/30">JPEG, PNG, WebP, GIF, SVG, BMP</p>
                                                                </div>
                                                            </template>
                                                            <template x-if="uploadProgress > 0 && uploadProgress < 100">
                                                                <div class="flex items-center justify-center p-3">
                                                                    <div class="w-full max-w-[150px]">
                                                                        <div class="w-full bg-dark/10 rounded-full h-1.5 overflow-hidden">
                                                                            <div class="h-full bg-crimson rounded-full transition-all" :style="'width:'+uploadProgress+'%'"></div>
                                                                        </div>
                                                                        <p class="text-[10px] text-center text-dark/40 mt-1" x-text="uploadProgress + '%'"></p>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        <template x-if="uploadError"><p class="text-[10px] text-red-500 mt-1" x-text="uploadError"></p></template>
                                                    </div>
                                                    <div x-show="pickerTab==='gallery'">
                                                        <template x-if="galleryLoading"><p class="text-[11px] text-dark/30 text-center py-3">Loading...</p></template>
                                                        <template x-if="!galleryLoading && galleryImages.length === 0"><p class="text-[11px] text-dark/30 text-center py-3">No images uploaded yet</p></template>
                                                        <div x-show="!galleryLoading && galleryImages.length > 0" class="grid grid-cols-4 gap-1.5 max-h-32 overflow-y-auto rounded-lg border border-dark/10 p-2 bg-white">
                                                            <template x-for="img in galleryImages" :key="img.url">
                                                                <div class="relative group">
                                                                    <button type="button" @click="selectFromGallery(img.url)" class="w-full relative rounded-lg overflow-hidden border-2 transition aspect-square" :class="img.url === scriptForm.image_url ? 'border-crimson ring-2 ring-crimson/20' : 'border-transparent hover:border-crimson/50'">
                                                                        <img :src="img.url" :alt="img.name" loading="lazy" class="w-full h-full object-cover">
                                                                    </button>
                                                                    <button type="button" @click.stop="deleteGalleryImage(img.url)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                    </button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Video Upload -->
                                                <div x-data="scriptVideoUploader()" class="bg-dark/[.02] rounded-lg p-3 border border-dark/5">
                                                    <label class="block text-[12px] font-medium text-dark/60 mb-1.5">Preview Video</label>
                                                    <div class="flex border border-dark/10 rounded-lg overflow-hidden mb-2 text-[11px]">
                                                        <button type="button" @click="mode='upload'" :class="mode==='upload'?'bg-dark text-white':'bg-white text-dark/50 hover:bg-cream'" class="flex-1 py-1.5 font-medium transition">Upload</button>
                                                        <button type="button" @click="mode='youtube'" :class="mode==='youtube'?'bg-dark text-white':'bg-white text-dark/50 hover:bg-cream'" class="flex-1 py-1.5 font-medium transition">YouTube</button>
                                                    </div>
                                                    <div x-show="mode==='upload'">
                                                        <div class="border-2 rounded-lg transition-all cursor-pointer overflow-hidden" :class="dragging?'border-crimson bg-crimson/5':'border-dashed border-dark/20 hover:border-crimson hover:bg-crimson/5 bg-white'" style="min-height:80px" @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false" @drop.prevent="onDrop($event)" @click="$refs.vidPick.click()">
                                                            <input type="file" x-ref="vidPick" class="hidden" accept="video/mp4,video/quicktime,video/webm" @change="onFile($event)">
                                                            <template x-if="!preview&&!uploading">
                                                                <div class="flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                                    <div class="w-8 h-8 rounded-full bg-crimson/10 flex items-center justify-center">
                                                                        <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                                    </div>
                                                                    <p class="text-[12px] font-medium text-dark">Click to upload</p>
                                                                    <p class="text-[10px] text-dark/30">MP4, MOV, WebM</p>
                                                                </div>
                                                            </template>
                                                            <template x-if="uploading">
                                                                <div class="flex items-center justify-center p-3">
                                                                    <div class="w-full max-w-[150px]">
                                                                        <div class="w-full bg-dark/10 rounded-full h-1.5 overflow-hidden"><div class="h-full bg-crimson rounded-full" :style="'width:'+progress+'%'"></div></div>
                                                                        <p class="text-[10px] text-center text-dark/40 mt-1" x-text="progress + '%'"></p>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <template x-if="preview&&!uploading&&!isYoutube(preview)">
                                                                <div class="flex items-center gap-2 px-3 py-2 group relative">
                                                                    <div class="w-7 h-7 rounded-lg bg-green-50 border border-green-200 flex items-center justify-center flex-shrink-0"><svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
                                                                    <p class="text-[11px] font-medium text-dark truncate flex-1" x-text="filename||'Video uploaded'"></p>
                                                                    <button type="button" @click.stop="clear()" class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-[10px] font-medium flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                        Delete
                                                                    </button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        <template x-if="uploadError"><p class="text-[10px] text-red-500 mt-1" x-text="uploadError"></p></template>
                                                    </div>
                                                    <div x-show="mode==='youtube'">
                                                        <div class="flex gap-2 items-center">
                                                            <input type="url" x-model="ytUrl" @input="onYtInput()" class="flex-1 border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson" placeholder="Paste YouTube URL here">
                                                            <button type="button" x-show="ytUrl" @click="clearYt()" class="px-2 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded text-[10px] font-medium flex items-center gap-1 flex-shrink-0">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                Delete
                                                            </button>
                                                        </div>
                                                        <div x-show="ytUrl && isYoutube(ytUrl)" class="mt-2 rounded-lg overflow-hidden border border-dark/10" style="aspect-ratio:9/16;max-height:150px">
                                                            <iframe :src="ytEmbedUrl(ytUrl)+'?mute=1'" class="w-full h-full" frameborder="0" allowfullscreen title="Preview"></iframe>
                                                        </div>
                                                        <p x-show="ytUrl && !isYoutube(ytUrl)" class="text-[10px] text-red-500 mt-1">Invalid YouTube URL</p>
                                                    </div>
                                                </div>

                                                <!-- PDF Upload -->
                                                <div x-data="scriptPdfUploader()" class="bg-dark/[.02] rounded-lg p-3 border border-dark/5 lg:col-span-2">
                                                    <label class="block text-[12px] font-medium text-dark/60 mb-1.5">Script PDF</label>
                                                    <div class="border-2 rounded-lg transition-all cursor-pointer overflow-hidden" :class="dragging?'border-crimson bg-crimson/5':'border-dashed border-dark/20 hover:border-crimson hover:bg-crimson/5 bg-white'" style="min-height:80px" @dragover.prevent="dragging=true" @dragleave.prevent="dragging=false" @drop.prevent="onDrop($event)" @click="$refs.pdfPick.click()">
                                                        <input type="file" x-ref="pdfPick" class="hidden" accept="application/pdf" @change="onFile($event)">
                                                        <template x-if="!preview&&!uploading">
                                                            <div class="flex flex-col items-center justify-center gap-1.5 p-3 text-center">
                                                                <div class="w-8 h-8 rounded-full bg-crimson/10 flex items-center justify-center">
                                                                    <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                                </div>
                                                                <p class="text-[12px] font-medium text-dark">Click to upload PDF</p>
                                                                <p class="text-[10px] text-dark/30">PDF format only</p>
                                                            </div>
                                                        </template>
                                                        <template x-if="uploading">
                                                            <div class="flex items-center justify-center p-3">
                                                                <div class="w-full max-w-[150px]">
                                                                    <div class="w-full bg-dark/10 rounded-full h-1.5 overflow-hidden"><div class="h-full bg-crimson rounded-full" :style="'width:'+progress+'%'"></div></div>
                                                                    <p class="text-[10px] text-center text-dark/40 mt-1" x-text="progress + '%'"></p>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <template x-if="preview&&!uploading">
                                                            <div class="flex items-center gap-2 px-3 py-2 group relative">
                                                                <div class="w-7 h-7 rounded bg-red-50 border border-red-200 flex items-center justify-center text-[9px] font-bold text-red-600 flex-shrink-0">PDF</div>
                                                                <p class="text-[11px] font-medium text-dark truncate flex-1" x-text="filename||'PDF uploaded'"></p>
                                                                <button type="button" @click.stop="clear()" class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-[10px] font-medium flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <template x-if="uploadError"><p class="text-[10px] text-red-500 mt-1" x-text="uploadError"></p></template>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECTION: Song Links (for Song Auditions) -->
                                        <div x-show="scriptForm.audition_type === 'Song Audition'" x-cloak class="pb-4 border-b border-dark/10">
                                            <h4 class="text-sm font-semibold text-dark mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                                Song Links
                                            </h4>
                                            <div class="space-y-2">
                                                <template x-for="(entry, idx) in songEntries" :key="idx">
                                                    <div class="flex gap-2 items-center">
                                                        <input type="text" :value="entry.label" @input="updateSongLabel(idx, $event.target.value)" class="w-24 flex-shrink-0 border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] focus:outline-none focus:border-crimson" placeholder="Label">
                                                        <input type="url" :value="entry.url" @input="updateSongUrl(idx, $event.target.value)" class="flex-1 border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] focus:outline-none focus:border-crimson" placeholder="YouTube URL">
                                                        <button type="button" @click="removeSongUrl(idx)" x-show="songEntries.length > 1" class="w-6 h-6 flex items-center justify-center rounded-full bg-red-50 hover:bg-red-100 text-red-500 transition flex-shrink-0">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                            <button type="button" @click="addSongUrl()" class="mt-2 flex items-center gap-1 text-[11px] text-dark/50 hover:text-dark transition">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                Add song link
                                            </button>
                                        </div>

                                        <!-- SECTION: Rules & Guidelines -->
                                        <div>
                                            <h4 class="text-sm font-semibold text-dark mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                                Rules & Guidelines
                                            </h4>
                                            <textarea x-model="scriptForm.rules" rows="4" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-crimson resize-y" placeholder="One rule per line:&#10;• Video under 3 minutes&#10;• Clear audio required&#10;• Face not visible"></textarea>
                                            <p class="text-[10px] text-dark/30 mt-1">One rule per line</p>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Modal Footer -->
                            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-dark/10 bg-cream/30">
                                <button type="button" @click="showScriptModal = false; cancelEditScript()" 
                                    class="px-5 py-2.5 rounded-lg text-[13px] font-medium text-dark/60 hover:bg-dark/5 transition">
                                    Cancel
                                </button>
                                <button type="submit" form="scriptModalForm"
                                    class="px-5 py-2.5 bg-crimson text-white rounded-lg text-[13px] font-medium hover:bg-crimson/90 transition shadow-lg shadow-crimson/20"
                                    x-text="editingScript ? 'Update Script' : 'Create Script'">
                                </button>
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
                                
                                <!-- NSFW Reject Threshold -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[12px] text-dark/50">NSFW Reject Threshold</label>
                                        <span class="text-[12px] font-medium text-red-600" x-text="(aiSettings.ai_nsfw_reject_threshold * 100) + '%'"></span>
                                    </div>
                                    <input type="range" min="0.3" max="0.9" step="0.1" x-model="aiSettings.ai_nsfw_reject_threshold" @change="saveAISetting('ai_nsfw_reject_threshold', aiSettings.ai_nsfw_reject_threshold)" class="w-full h-2 bg-dark/10 rounded-lg appearance-none cursor-pointer accent-red-600">
                                    <p class="text-[10px] text-dark/30 mt-1">NSFW score above this = auto-reject</p>
                                </div>
                                
                                <!-- NSFW Flag Threshold -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-[12px] text-dark/50">NSFW Flag Threshold</label>
                                        <span class="text-[12px] font-medium text-orange-600" x-text="(aiSettings.ai_nsfw_flag_threshold * 100) + '%'"></span>
                                    </div>
                                    <input type="range" min="0.3" max="0.9" step="0.1" x-model="aiSettings.ai_nsfw_flag_threshold" @change="saveAISetting('ai_nsfw_flag_threshold', aiSettings.ai_nsfw_flag_threshold)" class="w-full h-2 bg-dark/10 rounded-lg appearance-none cursor-pointer accent-orange-600">
                                    <p class="text-[10px] text-dark/30 mt-1">NSFW score above this = flagged for review (set higher to reduce false positives)</p>
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
                <div x-show="activeTab === 'youtube'" x-cloak style="max-width:1180px;margin:0 auto;">
                    <!-- Hero Header -->
                    <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
                        <div style="width:42px;height:42px;border-radius:11px;background:#FDEAEA;display:flex;align-items:center;justify-content:center;flex:0 0 42px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#DC2626"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </div>
                        <div style="flex:1;">
                            <div class="font-display" style="font-size:24px;font-weight:800;letter-spacing:0.02em;">YouTube Integration</div>
                            <div style="font-size:13px;color:#5B6172;margin-top:2px;">Connect your channel so approved videos publish automatically</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
                        <!-- Connection Status Card -->
                        <div class="lg:col-span-3">
                            <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
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
                            <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
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
                            <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:18px 20px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
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
                                
                                <template x-if="youtubeTestResults?.results?.oauth_error">
                                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-[11px] text-red-700 font-semibold mb-1">OAuth Error Details:</p>
                                        <p class="text-[11px] text-red-600" x-text="youtubeTestResults.results.oauth_error.message"></p>
                                        <p class="text-[10px] text-red-500 mt-1">HTTP Code: <span x-text="youtubeTestResults.results.oauth_error.http_code"></span></p>
                                        <details class="mt-2">
                                            <summary class="text-[10px] text-red-600 cursor-pointer">View raw response</summary>
                                            <pre class="text-[9px] mt-1 p-2 bg-red-100 rounded overflow-auto" x-text="JSON.stringify(youtubeTestResults.results.oauth_error.response, null, 2)"></pre>
                                        </details>
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                        <!-- Connect Your Channel — unified step-by-step guide with inline fields -->
                        <div class="lg:col-span-2">
                            <form @submit.prevent="saveAPIKeys()" style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
                                <div class="flex items-center justify-between mb-1 gap-3">
                                    <h3 class="font-semibold text-dark flex items-center gap-2 text-[14px] md:text-base">
                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                        Connect Your Channel
                                    </h3>
                                    <span class="text-[11px] text-dark/40 flex-shrink-0"
                                        x-text="['YOUTUBE_CLIENT_ID','YOUTUBE_CLIENT_SECRET','YOUTUBE_REFRESH_TOKEN','YOUTUBE_API_KEY','YOUTUBE_CHANNEL_ID'].filter(k => apiKeyStatus[k]?.configured).length + ' of 5 connected'"></span>
                                </div>
                                <p class="text-[11px] md:text-[12px] text-dark/50 mb-4">Six steps, in order. Each one shows exactly what to copy, right where it goes — do it once and you shouldn't need this page again.</p>

                                <div class="space-y-3" x-data="{ ytOpen: 1 }">

                                    <!-- Step 1: Enable API (no field) -->
                                    <div class="border border-dark/10 rounded-xl overflow-hidden">
                                        <button type="button" @click="ytOpen = ytOpen === 1 ? 0 : 1" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">1</div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Enable YouTube Data API v3</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Go to Google Cloud Console and enable the API.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===1" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                <li>Go to <a href="https://console.cloud.google.com/apis/library" target="_blank" class="text-blue-600 hover:underline break-all">API Library</a></li>
                                                <li>Search for "YouTube Data API v3"</li>
                                                <li>Click and press "Enable"</li>
                                            </ol>
                                            <button type="button" @click="ytOpen = 2" class="mt-3 text-[11px] font-medium text-crimson hover:underline">Next step →</button>
                                        </div>
                                    </div>

                                    <!-- Step 2: OAuth credentials → Client ID + Secret -->
                                    <div class="border rounded-xl overflow-hidden" :class="(apiKeyStatus.YOUTUBE_CLIENT_ID?.configured && apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured) ? 'border-green-300' : 'border-dark/10'">
                                        <button type="button" @click="ytOpen = ytOpen === 2 ? 0 : 2" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0"
                                                :class="(apiKeyStatus.YOUTUBE_CLIENT_ID?.configured && apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured) ? 'bg-green-500 text-white' : 'bg-crimson text-white'">
                                                <svg x-show="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured && apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                <span x-show="!(apiKeyStatus.YOUTUBE_CLIENT_ID?.configured && apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured)">2</span>
                                            </div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Create OAuth 2.0 credentials</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Gives you a Client ID and Client Secret.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===2" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal mb-3">
                                                <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials</a> → "Create Credentials" → "OAuth client ID"</li>
                                                <li>Type: <strong>Web application</strong>, name it "Faceless Pictures 3"</li>
                                                <li class="break-all">Redirect URI: <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px] break-all">developers.google.com/oauthplayground</code></li>
                                            </ol>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                                        OAuth Client ID
                                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                                    </label>
                                                    <input type="text" x-model="apiKeyForm.YOUTUBE_CLIENT_ID"
                                                        :placeholder="apiKeyStatus.YOUTUBE_CLIENT_ID?.configured ? apiKeyStatus.YOUTUBE_CLIENT_ID.masked : 'xxxx.apps.googleusercontent.com'"
                                                        class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                                        OAuth Client Secret
                                                        <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                                    </label>
                                                    <input type="password" x-model="apiKeyForm.YOUTUBE_CLIENT_SECRET"
                                                        :placeholder="apiKeyStatus.YOUTUBE_CLIENT_SECRET?.configured ? '••••••••••••' : 'GOCSPX-...'"
                                                        class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Add test user (no field) -->
                                    <div class="border border-dark/10 rounded-xl overflow-hidden">
                                        <button type="button" @click="ytOpen = ytOpen === 3 ? 0 : 3" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 bg-crimson text-white rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0">3</div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Add test user</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Required if the app is in "Testing" mode.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===3" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal">
                                                <li>Go to <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" class="text-blue-600 hover:underline">OAuth consent</a></li>
                                                <li>Scroll to "Test users" → Click "Add Users"</li>
                                                <li>Enter your YouTube email → Click "Save"</li>
                                            </ol>
                                            <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded-lg">
                                                <p class="text-[9px] md:text-[10px] text-yellow-800"><strong>Note:</strong> Use the Gmail that owns your YouTube channel!</p>
                                            </div>
                                            <button type="button" @click="ytOpen = 4" class="mt-3 text-[11px] font-medium text-crimson hover:underline">Next step →</button>
                                        </div>
                                    </div>

                                    <!-- Step 4: OAuth Playground → Refresh Token -->
                                    <div class="border rounded-xl overflow-hidden" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'border-green-300' : 'border-dark/10'">
                                        <button type="button" @click="ytOpen = ytOpen === 4 ? 0 : 4" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0"
                                                :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500 text-white' : 'bg-crimson text-white'">
                                                <svg x-show="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                <span x-show="!apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured">4</span>
                                            </div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Connect your YouTube account</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Use OAuth Playground to generate a refresh token.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===4" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal mb-3">
                                                <li>Go to <a href="https://developers.google.com/oauthplayground" target="_blank" class="text-blue-600 hover:underline">OAuth Playground</a> → ⚙️ gear icon (top-right)</li>
                                                <li>Check "Use your own OAuth credentials" → enter Client ID & Secret from Step 2</li>
                                                <li>Find "YouTube Data API v3" → select <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px] break-all">.../auth/youtube.upload</code></li>
                                                <li>Click "Authorize APIs" → sign in → Allow access</li>
                                                <li>"Exchange authorization code for tokens" → copy the <strong>Refresh token</strong></li>
                                            </ol>
                                            <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                                OAuth Refresh Token
                                                <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                            </label>
                                            <input type="password" x-model="apiKeyForm.YOUTUBE_REFRESH_TOKEN"
                                                :placeholder="apiKeyStatus.YOUTUBE_REFRESH_TOKEN?.configured ? '••••••••••••' : '1//0g...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                    </div>

                                    <!-- Step 5: API Key -->
                                    <div class="border rounded-xl overflow-hidden" :class="apiKeyStatus.YOUTUBE_API_KEY?.configured ? 'border-green-300' : 'border-dark/10'">
                                        <button type="button" @click="ytOpen = ytOpen === 5 ? 0 : 5" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0"
                                                :class="apiKeyStatus.YOUTUBE_API_KEY?.configured ? 'bg-green-500 text-white' : 'bg-crimson text-white'">
                                                <svg x-show="apiKeyStatus.YOUTUBE_API_KEY?.configured" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                <span x-show="!apiKeyStatus.YOUTUBE_API_KEY?.configured">5</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Get a public API key</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Lets the app read channel stats — no upload access.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===5 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===5" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal mb-3">
                                                <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Credentials</a> → "Create Credentials" → "API key"</li>
                                                <li>Optional: restrict it to YouTube Data API v3</li>
                                            </ol>
                                            <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                                YouTube API Key
                                                <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_API_KEY?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                            </label>
                                            <input type="password" x-model="apiKeyForm.YOUTUBE_API_KEY"
                                                :placeholder="apiKeyStatus.YOUTUBE_API_KEY?.configured ? '••••••••••••' : 'AIza...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                    </div>

                                    <!-- Step 6: Channel ID -->
                                    <div class="border rounded-xl overflow-hidden" :class="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? 'border-green-300' : 'border-dark/10'">
                                        <button type="button" @click="ytOpen = ytOpen === 6 ? 0 : 6" class="w-full flex items-center gap-2 md:gap-3 p-3 md:p-4 text-left">
                                            <div class="w-6 h-6 md:w-7 md:h-7 rounded-full flex items-center justify-center text-[11px] md:text-[12px] font-bold flex-shrink-0"
                                                :class="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? 'bg-green-500 text-white' : 'bg-crimson text-white'">
                                                <svg x-show="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                <span x-show="!apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured">6</span>
                                            </div>
                                            <div class="flex-1 min-w-0 overflow-hidden">
                                                <h4 class="font-semibold text-dark text-[12px] md:text-[13px]">Point it at your channel</h4>
                                                <p class="text-[10px] md:text-[11px] text-dark/50">Find your YouTube channel ID.</p>
                                            </div>
                                            <svg class="w-4 h-4 text-dark/30 transition-transform flex-shrink-0" :class="ytOpen===6 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <div x-show="ytOpen===6" x-collapse class="px-3 md:px-4 pb-4 border-t border-dark/10 pt-3">
                                            <ol class="text-[10px] md:text-[11px] text-dark/70 space-y-1 ml-3 md:ml-4 list-decimal mb-3">
                                                <li>Go to <a href="https://www.youtube.com" target="_blank" class="text-blue-600 hover:underline">YouTube</a> → profile → "Your channel"</li>
                                                <li class="break-all">URL shows: <code class="bg-dark/5 px-1 py-0.5 rounded text-[9px] md:text-[10px]">channel/<strong>UC...</strong></code> — copy the ID (starts with "UC")</li>
                                            </ol>
                                            <div class="mb-3 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                                                <p class="text-[9px] md:text-[10px] text-blue-800">If the URL shows @username instead, go to <a href="https://www.youtube.com/account_advanced" target="_blank" class="underline">Advanced Settings</a></p>
                                            </div>
                                            <label class="block text-[11px] text-dark/50 mb-1 flex items-center gap-1">
                                                Channel ID
                                                <span class="w-2 h-2 rounded-full" :class="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                            </label>
                                            <input type="text" x-model="apiKeyForm.YOUTUBE_CHANNEL_ID"
                                                :placeholder="apiKeyStatus.YOUTUBE_CHANNEL_ID?.configured ? apiKeyStatus.YOUTUBE_CHANNEL_ID.masked : 'UC...'"
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-[12px] focus:outline-none focus:border-crimson font-mono">
                                        </div>
                                    </div>

                                </div>

                                <div class="mt-5 pt-4 border-t border-dark/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <p class="text-[10px] text-dark/40">Only filled fields will be updated. Leave empty to keep current value.</p>
                                    <button type="submit"
                                        class="w-full sm:w-auto px-4 py-2.5 bg-crimson text-white rounded-xl text-[13px] font-medium hover:bg-crimson/90 transition flex items-center justify-center gap-2 flex-shrink-0"
                                        :disabled="savingKeys"
                                        :class="savingKeys ? 'opacity-50 cursor-not-allowed' : ''">
                                        <svg x-show="savingKeys" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <svg x-show="!savingKeys" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                        <span x-text="savingKeys ? 'Saving...' : 'Save YouTube Settings'"></span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Side: connection checklist + quick links -->
                        <div class="lg:col-span-1">
                            <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:16px 18px;box-shadow:0 1px 2px rgba(22,26,36,0.04);position:sticky;top:24px;">
                                <h4 class="font-semibold text-dark text-[13px] mb-3">What's connected</h4>
                                <template x-for="f in [
                                        {key:'YOUTUBE_CLIENT_ID', label:'OAuth Client ID'},
                                        {key:'YOUTUBE_CLIENT_SECRET', label:'OAuth Client Secret'},
                                        {key:'YOUTUBE_REFRESH_TOKEN', label:'Refresh Token'},
                                        {key:'YOUTUBE_API_KEY', label:'API Key'},
                                        {key:'YOUTUBE_CHANNEL_ID', label:'Channel ID'}
                                    ]" :key="f.key">
                                    <div class="flex items-center gap-2 py-1.5 text-[12px]">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0" :class="apiKeyStatus[f.key]?.configured ? 'bg-green-500' : 'bg-gray-300'"></span>
                                        <span :class="apiKeyStatus[f.key]?.configured ? 'text-dark' : 'text-dark/40'" x-text="f.label"></span>
                                    </div>
                                </template>
                            </div>

                            <!-- Quick Links -->
                            <div style="background:#EEF0FF;border:1px solid #C7C9F5;border-radius:14px;padding:16px 18px;margin-top:16px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
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

                    <!-- YouTube Playlists Section -->
                    <div class="mt-8">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-display text-[22px] text-dark">YouTube Playlists</h3>
                                <p class="text-[12px] text-dark/50 mt-1">Organize videos into role-based playlists automatically</p>
                            </div>
                        </div>

                        <div class="grid lg:grid-cols-3 gap-5">
                            <!-- Playlist Settings -->
                            <div class="lg:col-span-2">
                                <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:20px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-semibold text-dark text-[15px]">Playlist Settings</h4>
                                        <button @click="loadPlaylistSettings()" class="text-[11px] text-blue-600 hover:underline">Refresh</button>
                                    </div>

                                    <div class="space-y-4">
                                        <!-- Enable Playlists Toggle -->
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <label class="font-medium text-[13px] text-dark">Enable Automatic Playlists</label>
                                                <p class="text-[11px] text-dark/50 mt-0.5">Automatically add videos to role-based playlists when published</p>
                                            </div>
                                            <button @click="togglePlaylistSetting('enabled')" 
                                                :class="playlistSettings.enabled ? 'bg-green-500' : 'bg-gray-300'"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                                                <span :class="playlistSettings.enabled ? 'translate-x-6' : 'translate-x-1'" 
                                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                            </button>
                                        </div>

                                        <!-- Per-Season Playlists Toggle -->
                                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                            <div>
                                                <label class="font-medium text-[13px] text-dark">Separate Playlists Per Season</label>
                                                <p class="text-[11px] text-dark/50 mt-0.5">Create individual playlists for each season (not recommended for most setups)</p>
                                            </div>
                                            <button @click="togglePlaylistSetting('perSeason')" 
                                                :class="playlistSettings.perSeason ? 'bg-green-500' : 'bg-gray-300'"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                                                <span :class="playlistSettings.perSeason ? 'translate-x-6' : 'translate-x-1'" 
                                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Info Box -->
                                    <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="flex gap-2">
                                            <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <div>
                                                <p class="text-[11px] text-blue-800 font-medium">How Playlists Work</p>
                                                <p class="text-[10px] text-blue-700 mt-1">Videos are automatically organized by role:</p>
                                                <ul class="text-[10px] text-blue-700 mt-1 space-y-0.5 ml-4">
                                                    <li>• <strong>Actors:</strong> 2 playlists (Auditions & Song Auditions)</li>
                                                    <li>• <strong>Directors:</strong> 1 playlist (Director Submissions)</li>
                                                    <li>• <strong>Writers:</strong> 1 playlist (Writer Submissions)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Playlist Actions -->
                            <div class="lg:col-span-1">
                                <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:18px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
                                    <h4 class="font-semibold text-dark text-[13px] mb-3">Quick Actions</h4>
                                    
                                    <div class="space-y-3">
                                        <!-- Create Default Playlists -->
                                        <button @click="createDefaultPlaylists()" 
                                            :disabled="creatingPlaylists"
                                            :class="creatingPlaylists ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-600'"
                                            class="w-full px-4 py-3 bg-green-500 text-white rounded-lg text-[12px] font-medium transition flex items-center justify-center gap-2">
                                            <svg x-show="creatingPlaylists" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <svg x-show="!creatingPlaylists" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <span x-text="creatingPlaylists ? 'Creating...' : 'Create Default Playlists'"></span>
                                        </button>

                                        <!-- Organize Existing Videos -->
                                        <button @click="organizeVideosIntoPlaylists()" 
                                            :disabled="organizingVideos"
                                            :class="organizingVideos ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-600'"
                                            class="w-full px-4 py-3 bg-blue-500 text-white rounded-lg text-[12px] font-medium transition flex items-center justify-center gap-2">
                                            <svg x-show="organizingVideos" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <svg x-show="!organizingVideos" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span x-text="organizingVideos ? 'Organizing...' : 'Organize Existing Videos'"></span>
                                        </button>

                                        <!-- Refresh Playlists List -->
                                        <button @click="loadPlaylists()" 
                                            :disabled="loadingPlaylists"
                                            :class="loadingPlaylists ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-600'"
                                            class="w-full px-4 py-3 bg-gray-500 text-white rounded-lg text-[12px] font-medium transition flex items-center justify-center gap-2">
                                            <svg x-show="loadingPlaylists" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <svg x-show="!loadingPlaylists" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                            <span x-text="loadingPlaylists ? 'Loading...' : 'Refresh Playlists'"></span>
                                        </button>
                                    </div>

                                    <div class="mt-4 pt-3 border-t border-dark/10">
                                        <p class="text-[10px] text-dark/40">
                                            Use "Create Default Playlists" to set up the 4 standard playlists on YouTube. Then use "Organize Existing Videos" to add any already-published videos to the appropriate playlists.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Existing Playlists List -->
                        <div class="mt-5">
                            <div style="background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;padding:20px;box-shadow:0 1px 2px rgba(22,26,36,0.04);">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="font-semibold text-dark text-[15px]">Your YouTube Playlists</h4>
                                    <div class="flex items-center gap-2">
                                        <span x-text="playlists.length + ' playlist' + (playlists.length !== 1 ? 's' : '')" class="text-[11px] text-dark/40"></span>
                                        <button @click="deleteAllPlaylists()" 
                                            x-show="playlists.length > 0"
                                            class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-[11px] font-medium hover:bg-red-600 transition flex items-center gap-1.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Delete All
                                        </button>
                                    </div>
                                </div>

                                <div x-show="playlists.length === 0 && !loadingPlaylists" class="text-center py-8">
                                    <svg class="w-16 h-16 mx-auto text-dark/20 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <p class="text-[13px] text-dark/40 mb-4">No playlists found</p>
                                    <button @click="createDefaultPlaylists()" class="px-4 py-2 bg-crimson text-white rounded-lg text-[12px] font-medium hover:bg-crimson/90 transition">
                                        Create Default Playlists
                                    </button>
                                </div>

                                <div x-show="loadingPlaylists" class="text-center py-8">
                                    <svg class="w-8 h-8 mx-auto text-crimson animate-spin mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <p class="text-[11px] text-dark/40">Loading playlists...</p>
                                </div>

                                <div x-show="playlists.length > 0 && !loadingPlaylists" class="space-y-3">
                                    <template x-for="playlist in playlists" :key="playlist.id">
                                        <div class="flex items-start gap-4 p-3 border border-dark/10 rounded-lg hover:border-crimson/30 transition">
                                            <div class="flex-shrink-0 w-10 h-10 bg-crimson/10 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-crimson" fill="currentColor" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h5 class="font-medium text-[13px] text-dark" x-text="playlist.title"></h5>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span class="text-[10px] text-dark/40">
                                                        <span class="capitalize" x-text="playlist.role"></span>
                                                        <template x-if="playlist.audition_type">
                                                            <span> - <span x-text="playlist.audition_type === 'song_audition' ? 'Song Audition' : 'Audition'"></span></span>
                                                        </template>
                                                    </span>
                                                    <template x-if="playlist.season_title">
                                                        <span class="text-[10px] text-dark/40" x-text="'Season: ' + playlist.season_title"></span>
                                                    </template>
                                                    <span class="text-[10px] text-dark/30" x-text="'Created: ' + new Date(playlist.created_at).toLocaleDateString()"></span>
                                                </div>
                                                <template x-if="playlist.description">
                                                    <p class="text-[11px] text-dark/50 mt-1 line-clamp-2" x-text="playlist.description"></p>
                                                </template>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a :href="'https://www.youtube.com/playlist?list=' + playlist.playlist_id" target="_blank" 
                                                    class="flex-shrink-0 px-3 py-1.5 bg-red-600 text-white rounded-lg text-[11px] font-medium hover:bg-red-700 transition flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path fill="white" d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                                    View
                                                </a>
                                                <button @click="deletePlaylist(playlist.id, playlist.title)" 
                                                    class="flex-shrink-0 px-3 py-1.5 bg-red-500 text-white rounded-lg text-[11px] font-medium hover:bg-red-600 transition flex items-center gap-1.5"
                                                    title="Delete this playlist from database">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </template>
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

                        <!-- Database Migrations -->
                        <div class="bg-white rounded-xl border border-dark/5 p-4 md:p-6 md:col-span-2 lg:col-span-3" x-data="migrationRunner()">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-dark flex items-center gap-2 text-[15px]">
                                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                                    Database Migrations
                                </h3>
                                <button @click="run()" :disabled="running"
                                    class="px-4 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition disabled:opacity-50 flex items-center gap-1.5">
                                    <svg x-show="running" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-text="running ? 'Running...' : 'Run Pending Migrations'"></span>
                                </button>
                            </div>
                            <p class="text-xs text-dark/40 mb-3">Apply any pending SQL migrations to the database. Safe to run multiple times — already-applied migrations are skipped.</p>
                            <div x-show="result" class="rounded-lg p-3 text-xs font-mono" :class="result && result.error ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-800'">
                                <template x-if="result && result.error"><span x-text="'Error: ' + result.error"></span></template>
                                <template x-if="result && result.success">
                                    <div>
                                        <div x-show="result.ran && result.ran.length" x-text="'Applied: ' + (result.ran ? result.ran.join(', ') : '')"></div>
                                        <div x-show="result.skipped && result.skipped.length" class="text-green-600" x-text="'Skipped (already applied): ' + (result.skipped ? result.skipped.join(', ') : '')"></div>
                                        <div x-show="result.errors && result.errors.length" class="text-red-600" x-text="'Errors: ' + (result.errors ? result.errors.join(' | ') : '')"></div>
                                        <div x-show="result.ran && !result.ran.length && result.errors && !result.errors.length" class="text-green-700 font-semibold">All migrations already up to date</div>
                                    </div>
                                </template>
                            </div>
                        </div>

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

                            <!-- Collapsible Sections Wrapper -->
                            <div x-data="{showHome: false, showRoleCards: false, showWriter: false, showDirector: false, showActor: false}">

                            <!-- ========== HOME PAGE CONTENT ========== -->
                            <div class="mt-6 mb-6">
                                <div @click="showHome = !showHome" 
                                    class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg cursor-pointer hover:from-gray-100 hover:to-gray-150 transition-all">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🏠</span>
                                        <h4 class="font-semibold text-dark text-sm">HOME PAGE CONTENT</h4>
                                        <span class="text-xs text-dark/40">(Brand, Hero, Posters, Videos, About, Marquee)</span>
                                    </div>
                                    <svg class="w-5 h-5 text-dark/40 transition-transform" :class="{'rotate-180': showHome}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="showHome" x-transition>
                                    <div class="mt-3 pl-4 pr-4 pb-4">

                            <!-- ========== SECTION: Header Section ========== -->
                            <div class="mb-6 pb-6 border-b-2 border-dark/10">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                    <h5 class="text-[14px] font-bold text-dark">Header Section</h5>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-4">Logo, navigation bar, and header content</p>
                                
                                <!-- Header Content Text -->
                                <div class="mb-5">
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Header Content Text</label>
                                    <textarea x-model="form.landing_header_content" rows="2" placeholder="Optional text to display in the header area..."
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition resize-none"></textarea>
                                    <p class="text-[11px] text-dark/30 mt-1">Optional announcement or message shown in the header across all pages.</p>
                                </div>

                                <!-- Header Menu Items -->
                                <div class="mb-5 bg-dark/[.02] rounded-lg p-4 border border-dark/5">
                                    <h6 class="text-[12px] font-bold text-dark mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        Header Navigation Menu
                                    </h6>
                                    <p class="text-[11px] text-dark/40 mb-3">Four customizable menu items. Logo displays in center, with 2 items on left and 2 on right based on order (1-4).</p>
                                    
                                    <!-- Left Side of Logo -->
                                    <div class="mb-4">
                                        <h6 class="text-[11px] font-bold text-dark/70 mb-2 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                            Left Side of Logo
                                        </h6>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <!-- Menu Item 1 -->
                                            <div class="bg-white rounded-lg p-3 border border-dark/10">
                                                <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 1</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                        <input type="text" x-model="form.header_menu_item_1_text" placeholder="About"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                        <select x-model="form.header_menu_item_1_page"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                            <option value="home">Home</option>
                                                            <option value="writer">Writers</option>
                                                            <option value="director">Directors</option>
                                                            <option value="actor">Actors</option>
                                                            <option value="about">About</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-2)</label>
                                                        <input type="number" x-model="form.header_menu_item_1_order" min="1" max="2" placeholder="1"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Menu Item 2 -->
                                            <div class="bg-white rounded-lg p-3 border border-dark/10">
                                                <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 2</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                        <input type="text" x-model="form.header_menu_item_2_text" placeholder="Writers"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                        <select x-model="form.header_menu_item_2_page"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                            <option value="home">Home</option>
                                                            <option value="writer">Writers</option>
                                                            <option value="director">Directors</option>
                                                            <option value="actor">Actors</option>
                                                            <option value="about">About</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-2)</label>
                                                        <input type="number" x-model="form.header_menu_item_2_order" min="1" max="2" placeholder="2"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Side of Logo -->
                                    <div>
                                        <h6 class="text-[11px] font-bold text-dark/70 mb-2 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            Right Side of Logo
                                        </h6>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <!-- Menu Item 3 -->
                                            <div class="bg-white rounded-lg p-3 border border-dark/10">
                                                <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 3</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                        <input type="text" x-model="form.header_menu_item_3_text" placeholder="Directors"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                        <select x-model="form.header_menu_item_3_page"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                            <option value="home">Home</option>
                                                            <option value="writer">Writers</option>
                                                            <option value="director">Directors</option>
                                                            <option value="actor">Actors</option>
                                                            <option value="about">About</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Display Order (3-4)</label>
                                                        <input type="number" x-model="form.header_menu_item_3_order" min="3" max="4" placeholder="3"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Menu Item 4 -->
                                            <div class="bg-white rounded-lg p-3 border border-dark/10">
                                                <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 4</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                        <input type="text" x-model="form.header_menu_item_4_text" placeholder="Actors"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                        <select x-model="form.header_menu_item_4_page"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                            <option value="home">Home</option>
                                                            <option value="writer">Writers</option>
                                                            <option value="director">Directors</option>
                                                            <option value="actor">Actors</option>
                                                            <option value="about">About</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/40 mb-1">Display Order (3-4)</label>
                                                        <input type="number" x-model="form.header_menu_item_4_order" min="3" max="4" placeholder="4"
                                                            class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p class="text-[10px] text-blue-800 flex items-start gap-2">
                                            <svg class="w-3 h-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                            <span><strong>Layout:</strong> Items 1-2 appear on the left of centered logo, Items 3-4 appear on the right. Order determines exact position.</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Footer Navigation Menu -->
                                <div class="bg-gold/5 rounded-xl p-4 border border-gold/20">
                                    <h6 class="text-[12px] font-bold text-dark mb-3 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                        Footer Navigation Menu
                                    </h6>
                                    <p class="text-[11px] text-dark/40 mb-3">Four customizable footer menu items with ordering.</p>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <!-- Footer Menu Item 1 -->
                                        <div class="bg-white rounded-lg p-3 border border-dark/10">
                                            <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 1</p>
                                            <div class="space-y-2">
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                    <input type="text" x-model="form.footer_menu_item_1_text" placeholder="About"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                    <select x-model="form.footer_menu_item_1_page"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                        <option value="home">Home</option>
                                                        <option value="writer">Writers</option>
                                                        <option value="director">Directors</option>
                                                        <option value="actor">Actors</option>
                                                        <option value="about">About</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-4)</label>
                                                    <input type="number" x-model="form.footer_menu_item_1_order" min="1" max="4" placeholder="1"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Footer Menu Item 2 -->
                                        <div class="bg-white rounded-lg p-3 border border-dark/10">
                                            <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 2</p>
                                            <div class="space-y-2">
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                    <input type="text" x-model="form.footer_menu_item_2_text" placeholder="Writers"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                    <select x-model="form.footer_menu_item_2_page"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                        <option value="home">Home</option>
                                                        <option value="writer">Writers</option>
                                                        <option value="director">Directors</option>
                                                        <option value="actor">Actors</option>
                                                        <option value="about">About</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-4)</label>
                                                    <input type="number" x-model="form.footer_menu_item_2_order" min="1" max="4" placeholder="2"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Footer Menu Item 3 -->
                                        <div class="bg-white rounded-lg p-3 border border-dark/10">
                                            <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 3</p>
                                            <div class="space-y-2">
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                    <input type="text" x-model="form.footer_menu_item_3_text" placeholder="Directors"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                    <select x-model="form.footer_menu_item_3_page"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                        <option value="home">Home</option>
                                                        <option value="writer">Writers</option>
                                                        <option value="director">Directors</option>
                                                        <option value="actor">Actors</option>
                                                        <option value="about">About</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-4)</label>
                                                    <input type="number" x-model="form.footer_menu_item_3_order" min="1" max="4" placeholder="3"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Footer Menu Item 4 -->
                                        <div class="bg-white rounded-lg p-3 border border-dark/10">
                                            <p class="text-[11px] font-semibold text-dark/50 mb-2">Menu Item 4</p>
                                            <div class="space-y-2">
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link Text</label>
                                                    <input type="text" x-model="form.footer_menu_item_4_text" placeholder="Actors"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Link To Page</label>
                                                    <select x-model="form.footer_menu_item_4_page"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                        <option value="home">Home</option>
                                                        <option value="writer">Writers</option>
                                                        <option value="director">Directors</option>
                                                        <option value="actor">Actors</option>
                                                        <option value="about">About</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-dark/40 mb-1">Display Order (1-4)</label>
                                                    <input type="number" x-model="form.footer_menu_item_4_order" min="1" max="4" placeholder="4"
                                                        class="w-full border border-dark/10 rounded-lg px-2 py-1.5 text-[12px] bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <!-- Logo uploader -->
                                    <div x-data="imageUploader('site_logo_url', '<?= addslashes(htmlspecialchars($settingsModel->get('site_logo_url',''))) ?>')">
                                        <label class="block text-[12px] font-semibold text-dark mb-2">Logo Image</label>
                                        
                                        <!-- Browse Existing Button -->
                                        <button type="button" @click="openMediaBrowser()" 
                                            class="mb-2 px-3 py-1.5 bg-dark text-white rounded-lg text-xs font-medium hover:bg-dark/90 transition flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            Browse Existing Images
                                        </button>
                                        
                                        <div
                                            class="relative border-2 rounded-xl transition-all cursor-pointer overflow-hidden"
                                            :class="dragging ? 'border-dark bg-dark/5' : 'border-dashed border-dark/15 hover:border-dark/30 bg-dark/[.02]'"
                                            style="min-height:120px"
                                            @dragover.prevent="dragging=true"
                                            @dragleave.prevent="dragging=false"
                                            @drop.prevent="onDrop($event)"
                                            @click="$refs.imgInput.click()">
                                            <input type="file" x-ref="imgInput" class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/svg+xml,image/bmp" @change="onFile($event)">

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
                                                    <img :src="preview" alt="Logo preview" loading="lazy" style="height:48px;max-width:160px;object-fit:contain;background:#f9fafb;border-radius:6px;padding:4px">
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
                                        
                                        <!-- Media Browser Modal -->
                                        <div x-show="showMediaBrowser" x-cloak
                                            @keydown.escape.window="closeMediaBrowser()"
                                            @click.self="closeMediaBrowser()"
                                            class="fixed inset-0 z-[100] flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm">
                                            <div @click.stop class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl max-h-[80vh] overflow-hidden">
                                                <!-- Modal Header -->
                                                <div class="flex items-center justify-between px-6 py-4 border-b border-dark/10">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-dark">Browse Media Library</h3>
                                                        <p class="text-xs text-dark/50 mt-0.5" x-text="filteredMedia.length + ' images available'"></p>
                                                    </div>
                                                    <button @click="closeMediaBrowser()" type="button"
                                                        class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-dark/5 transition text-dark/50 hover:text-dark">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                
                                                <!-- Search Bar -->
                                                <div class="px-6 py-3 border-b border-dark/5 bg-gray-50">
                                                    <input type="text" x-model="mediaSearch" placeholder="Search images by filename..."
                                                        class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dark/20">
                                                </div>
                                                
                                                <!-- Modal Body -->
                                                <div class="px-6 py-4 max-h-[calc(80vh-160px)] overflow-y-auto">
                                                    <template x-if="mediaLoading">
                                                        <div class="flex items-center justify-center py-12">
                                                            <div class="text-center">
                                                                <div class="inline-block w-8 h-8 border-3 border-dark/20 border-t-dark rounded-full animate-spin mb-2"></div>
                                                                <p class="text-sm text-dark/50">Loading images...</p>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    
                                                    <template x-if="!mediaLoading && filteredMedia.length === 0">
                                                        <div class="text-center py-12">
                                                            <svg class="w-16 h-16 text-dark/10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                            <p class="text-sm text-dark/50">No images found</p>
                                                        </div>
                                                    </template>
                                                    
                                                    <div x-show="!mediaLoading && filteredMedia.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                                        <template x-for="img in filteredMedia" :key="img.url">
                                                            <button type="button" @click="selectFromLibrary(img.url)" 
                                                                class="group relative aspect-square rounded-lg overflow-hidden border-2 transition hover:border-crimson focus:outline-none focus:ring-2 focus:ring-crimson/50"
                                                                :class="preview === img.url ? 'border-crimson ring-2 ring-crimson/20' : 'border-dark/10'">
                                                                <img :src="img.url" :alt="img.name" loading="lazy" class="w-full h-full object-cover">
                                                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-center justify-center">
                                                                    <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                </div>
                                                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2 opacity-0 group-hover:opacity-100 transition">
                                                                    <p class="text-white text-[10px] font-medium truncate" x-text="img.name"></p>
                                                                    <p class="text-white/70 text-[9px]" x-text="(img.size / 1024).toFixed(0) + ' KB'"></p>
                                                                </div>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Logo URL (manual fallback) -->
                                    <div>
                                        <label class="block text-xs font-medium text-dark/60 mb-2">Or paste Logo URL directly</label>
                                        <input type="url" x-model="form.site_logo_url" placeholder="https://..."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Use this if your logo is hosted externally.</p>
                                        
                                        <!-- Logo size control -->
                                        <div class="mt-4">
                                            <label class="block text-xs font-medium text-dark/60 mb-2">Logo Height (pixels)</label>
                                            <div class="flex items-center gap-3">
                                                <input type="number" x-model="form.site_logo_height" min="20" max="200" step="1" placeholder="44"
                                                    class="w-24 border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                                <span class="text-xs text-dark/50">px (width auto-adjusts)</span>
                                            </div>
                                            <p class="text-[11px] text-dark/30 mt-1">Default: 44px. Range: 20-200px.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== SECTION: Hero Section ========== -->
                            <div class="mb-6 pb-6 border-b-2 border-dark/10">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                    <h5 class="text-[14px] font-bold text-dark">Hero Section</h5>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-4">Main headline and tagline displayed prominently on homepage</p>
                                <div>
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Main Headline</label>
                                    <input type="text" x-model="form.landing_headline" placeholder="NO FACE. NO CONNECTIONS. JUST TALENT."
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <p class="text-[11px] text-dark/30 mt-1">Large centered text displayed at the top of the homepage.</p>
                                </div>
                                
                                <!-- Hero Subheading (NEW) -->
                                <div>
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Hero Subheading (Small Text Above Main Heading)</label>
                                    <input type="text" x-model="form.landing_hero_subheading" placeholder="KHATAA OFFICIAL TEASER"
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <p class="text-[11px] text-dark/30 mt-1">Small uppercase text displayed below the video, above the main heading.</p>
                                </div>
                                
                                <!-- Hero Tagline (NEW) -->
                                <div>
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Hero Tagline (Gray Text Below Main Heading)</label>
                                    <input type="text" x-model="form.landing_hero_tagline" placeholder="10 FILMS. 10 RASAS. 10 EMOTIONS. ONE UNIVERSE."
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <p class="text-[11px] text-dark/30 mt-1">Gray uppercase text displayed at the bottom of the hero section.</p>
                                </div>
                            </div>

                            <!-- ========== SECTION: Film Posters ========== -->
                            <div class="mb-6 pb-6 border-b-2 border-dark/10">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
                                    <h5 class="text-[14px] font-bold text-dark">Film Poster Cards</h5>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-4">Up to 10 poster slots with images, film titles, trailers, and button text</p>
                                
                                <!-- Section Heading & Subtitle -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                                    <div>
                                        <label class="block text-[12px] font-semibold text-dark mb-1.5">Poster Section Heading</label>
                                        <input type="text" x-model="form.poster_section_heading" placeholder="RASAS REVEALED."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Main heading above poster grid</p>
                                    </div>
                                    <div>
                                        <label class="block text-[12px] font-semibold text-dark mb-1.5">Poster Section Subtitle</label>
                                        <input type="text" x-model="form.poster_section_subtitle" placeholder="Each film is a rasa. Each rasa is a world."
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <p class="text-[11px] text-dark/30 mt-1">Subtitle text below heading</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <?php
                                    $posterSlots = [
                                        ['Poster 1', 'landing_poster_url',  'landing_poster_title',  'landing_poster_subtitle',  'landing_trailer_url',  'landing_poster_btn_label'],
                                        ['Poster 2', 'landing_poster2_url', 'landing_poster2_title', 'landing_poster2_subtitle', 'landing_trailer2_url', 'landing_poster2_btn_label'],
                                        ['Poster 3', 'landing_poster3_url', 'landing_poster3_title', 'landing_poster3_subtitle', 'landing_trailer3_url', 'landing_poster3_btn_label'],
                                        ['Poster 4', 'landing_poster4_url', 'landing_poster4_title', 'landing_poster4_subtitle', 'landing_trailer4_url', 'landing_poster4_btn_label'],
                                        ['Poster 5', 'landing_poster5_url', 'landing_poster5_title', 'landing_poster5_subtitle', 'landing_trailer5_url', 'landing_poster5_btn_label'],
                                        ['Poster 6', 'landing_poster6_url', 'landing_poster6_title', 'landing_poster6_subtitle', 'landing_trailer6_url', 'landing_poster6_btn_label'],
                                        ['Poster 7', 'landing_poster7_url', 'landing_poster7_title', 'landing_poster7_subtitle', 'landing_trailer7_url', 'landing_poster7_btn_label'],
                                        ['Poster 8', 'landing_poster8_url', 'landing_poster8_title', 'landing_poster8_subtitle', 'landing_trailer8_url', 'landing_poster8_btn_label'],
                                        ['Poster 9', 'landing_poster9_url', 'landing_poster9_title', 'landing_poster9_subtitle', 'landing_trailer9_url', 'landing_poster9_btn_label'],
                                        ['Poster 10', 'landing_poster10_url', 'landing_poster10_title', 'landing_poster10_subtitle', 'landing_trailer10_url', 'landing_poster10_btn_label'],
                                    ];
                                    foreach ($posterSlots as $p):
                                    $currentUrl = $settingsModel->get($p[1], '');
                                    ?>
                                    <div class="bg-dark/[.025] rounded-xl p-4 space-y-3" x-data="imageUploader('<?= $p[1] ?>', '<?= addslashes(htmlspecialchars($currentUrl)) ?>')">
                                        <p class="text-xs font-semibold text-dark/50"><?= $p[0] ?></p>

                                        <!-- Poster image uploader -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1.5">Poster Image</label>
                                            
                                            <!-- Browse Existing Button -->
                                            <button type="button" @click="openMediaBrowser()" 
                                                class="mb-2 w-full px-3 py-1.5 bg-dark text-white rounded-lg text-xs font-medium hover:bg-dark/90 transition flex items-center justify-center gap-1.5">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                Browse Existing
                                            </button>
                                            
                                            <div
                                                class="relative border-2 rounded-xl transition-all cursor-pointer overflow-hidden bg-white"
                                                :class="dragging ? 'border-dark' : 'border-dashed border-dark/15 hover:border-dark/30'"
                                                style="aspect-ratio:2/3;max-height:200px"
                                                @dragover.prevent="dragging=true"
                                                @dragleave.prevent="dragging=false"
                                                @drop.prevent="onDrop($event)"
                                                @click="$refs.imgInput.click()">
                                                <input type="file" x-ref="imgInput" class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,image/svg+xml,image/bmp" @change="onFile($event)">

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
                                                        <img :src="preview" alt="Poster" loading="lazy" style="width:100%;height:100%;object-fit:cover">
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
                                            
                                            <!-- Media Browser Modal -->
                                            <div x-show="showMediaBrowser" x-cloak
                                                @keydown.escape.window="closeMediaBrowser()"
                                                @click.self="closeMediaBrowser()"
                                                class="fixed inset-0 z-[100] flex items-center justify-center px-4 bg-black/50 backdrop-blur-sm">
                                                <div @click.stop class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl max-h-[80vh] overflow-hidden">
                                                    <!-- Modal Header -->
                                                    <div class="flex items-center justify-between px-6 py-4 border-b border-dark/10">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-dark">Browse Media Library</h3>
                                                            <p class="text-xs text-dark/50 mt-0.5" x-text="filteredMedia.length + ' images available'"></p>
                                                        </div>
                                                        <button @click="closeMediaBrowser()" type="button"
                                                            class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-dark/5 transition text-dark/50 hover:text-dark">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Search Bar -->
                                                    <div class="px-6 py-3 border-b border-dark/5 bg-gray-50">
                                                        <input type="text" x-model="mediaSearch" placeholder="Search images by filename..."
                                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-dark/20">
                                                    </div>
                                                    
                                                    <!-- Modal Body -->
                                                    <div class="px-6 py-4 max-h-[calc(80vh-160px)] overflow-y-auto">
                                                        <template x-if="mediaLoading">
                                                            <div class="flex items-center justify-center py-12">
                                                                <div class="text-center">
                                                                    <div class="inline-block w-8 h-8 border-3 border-dark/20 border-t-dark rounded-full animate-spin mb-2"></div>
                                                                    <p class="text-sm text-dark/50">Loading images...</p>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        
                                                        <template x-if="!mediaLoading && filteredMedia.length === 0">
                                                            <div class="text-center py-12">
                                                                <svg class="w-16 h-16 text-dark/10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                                <p class="text-sm text-dark/50">No images found</p>
                                                            </div>
                                                        </template>
                                                        
                                                        <div x-show="!mediaLoading && filteredMedia.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                                            <template x-for="img in filteredMedia" :key="img.url">
                                                                <button type="button" @click="selectFromLibrary(img.url)" 
                                                                    class="group relative aspect-square rounded-lg overflow-hidden border-2 transition hover:border-crimson focus:outline-none focus:ring-2 focus:ring-crimson/50"
                                                                    :class="preview === img.url ? 'border-crimson ring-2 ring-crimson/20' : 'border-dark/10'">
                                                                    <img :src="img.url" :alt="img.name" loading="lazy" class="w-full h-full object-cover">
                                                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-center justify-center">
                                                                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                    </div>
                                                                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-2 opacity-0 group-hover:opacity-100 transition">
                                                                        <p class="text-white text-[10px] font-medium truncate" x-text="img.name"></p>
                                                                        <p class="text-white/70 text-[9px]" x-text="(img.size / 1024).toFixed(0) + ' KB'"></p>
                                                                    </div>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Film title -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1">Film Title</label>
                                            <input type="text" x-model="form.<?= $p[2] ?>" placeholder="Film name..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>

                                        <!-- Film subtitle -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1">Film Subtitle <span class="text-dark/25">(e.g., WONDER, PEACE, PARENTAL)</span></label>
                                            <input type="text" x-model="form.<?= $p[3] ?>" placeholder="Subtitle or category..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>

                                        <!-- Button label -->
                                        <div>
                                            <label class="block text-[11px] text-dark/40 mb-1">Button Text <span class="text-dark/25">(leave blank for smart default)</span></label>
                                            <input type="text" x-model="form.<?= $p[5] ?>" placeholder="Watch Trailer Now / Trailer · Teaser Coming Soon..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>

                                        <!-- Trailer Video Uploader — local file OR YouTube URL -->
                                        <div x-data="videoUploader('<?= $p[4] ?>', '<?= addslashes(htmlspecialchars($settingsModel->get($p[4], ''))) ?>')">
                                            <label class="block text-[11px] text-dark/40 mb-1.5">Trailer Video</label>

                                            <!-- Toggle tabs -->
                                            <div class="flex gap-1 mb-2">
                                                <button type="button"
                                                    @click="trailerTab='upload'"
                                                    :class="trailerTab==='upload' ? 'bg-dark text-white' : 'bg-dark/5 text-dark/50 hover:bg-dark/10'"
                                                    class="px-3 py-1 rounded-lg text-[11px] font-semibold transition">
                                                    Upload File
                                                </button>
                                                <button type="button"
                                                    @click="trailerTab='yt'"
                                                    :class="trailerTab==='yt' ? 'bg-dark text-white' : 'bg-dark/5 text-dark/50 hover:bg-dark/10'"
                                                    class="px-3 py-1 rounded-lg text-[11px] font-semibold transition">
                                                    YouTube URL
                                                </button>
                                            </div>

                                            <!-- File Upload tab -->
                                            <div x-show="trailerTab==='upload'">
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
                                            </div>

                                            <!-- YouTube URL tab -->
                                            <div x-show="trailerTab==='yt'">
                                                <div class="flex items-center gap-2 border border-dark/10 rounded-xl px-3 py-2 bg-white">
                                                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                                    <input type="url"
                                                        x-model="ytUrl"
                                                        @input="syncYtToForm()"
                                                        placeholder="https://youtu.be/... or youtube.com/watch?v=..."
                                                        class="flex-1 text-[12px] outline-none bg-transparent text-dark placeholder-dark/25">
                                                    <button x-show="ytUrl" type="button" @click.stop="ytUrl=''; saveYtUrl()"
                                                        class="w-5 h-5 rounded-full bg-dark/5 hover:bg-dark/10 flex items-center justify-center flex-shrink-0 transition">
                                                        <svg class="w-3 h-3 text-dark/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <button type="button" @click.stop="saveYtUrl()"
                                                        class="px-3 py-1 bg-dark text-white rounded-lg text-[11px] font-semibold hover:bg-dark/80 transition"
                                                        x-text="ytSaved ? '✓ Saved' : 'Save URL'">
                                                    </button>
                                                    <p class="text-[10px] text-dark/25">YouTube URL takes priority over uploaded file</p>
                                                </div>
                                            </div>

                                            <template x-if="uploadError">
                                                <p class="text-[11px] text-red-500 mt-1" x-text="uploadError"></p>
                                            </template>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SECTION: Horizontal Auto-Play Trailer -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <h5 class="text-[13px] font-bold text-dark mb-1">Horizontal Auto-Play Trailer</h5>
                                <p class="text-[11px] text-dark/40 mb-3">Full-width horizontal video player displayed below the three posters. Auto-plays on page load.</p>
                                
                                <div class="bg-dark/[.025] rounded-xl p-4" x-data="videoUploader('landing_hero_trailer_url', '<?= addslashes(htmlspecialchars($settingsModel->get('landing_hero_trailer_url', ''))) ?>')">
                                    
                                    <!-- Toggle tabs -->
                                    <div class="flex gap-1 mb-3">
                                        <button type="button"
                                            @click="trailerTab='upload'"
                                            :class="trailerTab==='upload' ? 'bg-dark text-white' : 'bg-dark/5 text-dark/50 hover:bg-dark/10'"
                                            class="px-3 py-1.5 rounded-lg text-[11px] font-semibold transition">
                                            Upload Video File
                                        </button>
                                        <button type="button"
                                            @click="trailerTab='yt'"
                                            :class="trailerTab==='yt' ? 'bg-dark text-white' : 'bg-dark/5 text-dark/50 hover:bg-dark/10'"
                                            class="px-3 py-1.5 rounded-lg text-[11px] font-semibold transition">
                                            YouTube URL
                                        </button>
                                    </div>

                                    <!-- File Upload tab -->
                                    <div x-show="trailerTab==='upload'">
                                        <div
                                            class="relative border-2 rounded-xl transition-all overflow-hidden bg-white"
                                            :class="dragging ? 'border-dark bg-dark/5' : 'border-dashed border-dark/15 hover:border-dark/30'"
                                            style="min-height:120px;cursor:pointer"
                                            @dragover.prevent="dragging=true"
                                            @dragleave.prevent="dragging=false"
                                            @drop.prevent="onDrop($event)"
                                            @click="$refs.vidInput.click()">
                                            <input type="file" x-ref="vidInput" class="hidden"
                                                accept="video/mp4,video/quicktime,video/webm,video/x-msvideo"
                                                @change="onFile($event)">

                                            <!-- Empty -->
                                            <template x-if="!preview && !uploading">
                                                <div class="flex flex-col items-center justify-center gap-2 p-6 text-center">
                                                    <svg class="w-8 h-8 text-dark/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                    <div>
                                                        <p class="text-[13px] text-dark/40 font-medium">Drop video file or <span class="underline">click to browse</span></p>
                                                        <p class="text-[11px] text-dark/25 mt-1">MP4 · MOV · WEBM · max 500 MB</p>
                                                        <p class="text-[11px] text-dark/25 mt-0.5">Recommended: Horizontal 16:9 format</p>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Uploading -->
                                            <template x-if="uploading">
                                                <div class="flex flex-col items-center justify-center gap-3 p-6">
                                                    <div class="w-full max-w-md bg-dark/10 rounded-full h-1.5 overflow-hidden">
                                                        <div class="h-full bg-dark rounded-full transition-all" :style="'width:'+progress+'%'"></div>
                                                    </div>
                                                    <p class="text-[12px] text-dark/50 font-medium" x-text="progress+'% — uploading...'"></p>
                                                </div>
                                            </template>

                                            <!-- Uploaded -->
                                            <template x-if="preview && !uploading">
                                                <div class="flex items-center gap-3 px-4 py-3">
                                                    <div class="w-10 h-10 rounded-lg bg-green-50 border border-green-200 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-[13px] font-semibold text-dark truncate" x-text="filename || 'Video uploaded'"></p>
                                                        <p class="text-[11px] text-green-600">✓ Ready to auto-play on homepage</p>
                                                    </div>
                                                    <button type="button" @click.stop="clearVideo()"
                                                        class="w-7 h-7 rounded-full bg-dark/5 hover:bg-dark/10 flex items-center justify-center flex-shrink-0 transition">
                                                        <svg class="w-4 h-4 text-dark/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- YouTube URL tab -->
                                    <div x-show="trailerTab==='yt'">
                                        <div class="flex items-center gap-2 border border-dark/10 rounded-xl px-4 py-3 bg-white">
                                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                            <input type="url"
                                                x-model="ytUrl"
                                                @input="syncYtToForm()"
                                                placeholder="https://youtu.be/... or youtube.com/watch?v=..."
                                                class="flex-1 text-[13px] outline-none bg-transparent text-dark placeholder-dark/25">
                                            <button x-show="ytUrl" type="button" @click.stop="ytUrl=''; saveYtUrl()"
                                                class="w-6 h-6 rounded-full bg-dark/5 hover:bg-dark/10 flex items-center justify-center flex-shrink-0 transition">
                                                <svg class="w-3.5 h-3.5 text-dark/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-2 mt-2">
                                            <button type="button" @click.stop="saveYtUrl()"
                                                class="px-4 py-1.5 bg-dark text-white rounded-lg text-[12px] font-semibold hover:bg-dark/80 transition"
                                                x-text="ytSaved ? '✓ Saved' : 'Save URL'">
                                            </button>
                                            <p class="text-[11px] text-dark/30">YouTube URL takes priority over uploaded file</p>
                                        </div>
                                    </div>

                                    <template x-if="uploadError">
                                        <p class="text-[11px] text-red-500 mt-2" x-text="uploadError"></p>
                                    </template>
                                </div>
                            </div>

                            <!-- ========== SECTION: Role Cards ========== -->
                            <div class="mb-6 pb-6 border-b-2 border-dark/10">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <h5 class="text-[14px] font-bold text-dark">Role Cards Section</h5>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-4">Heading displayed above the Actor / Director / Writer cards</p>
                                <div>
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Section Heading</label>
                                    <input type="text" x-model="form.landing_roles_heading" placeholder="Become a Star in 3 Clicks"
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <p class="text-[11px] text-dark/30 mt-1">Bold heading displayed above the three role cards (Actor, Director, Writer).</p>
                                </div>
                            </div>

                            <!-- ========== SECTION: Footer Section ========== -->
                            <div class="mb-6 pb-6 border-b-2 border-dark/10">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                    <h5 class="text-[14px] font-bold text-dark">Footer Section</h5>
                                </div>
                                <p class="text-[11px] text-dark/40 mb-4">Footer content and copyright information</p>
                                <div>
                                    <label class="block text-[12px] font-semibold text-dark mb-1.5">Footer Content Text</label>
                                    <textarea x-model="form.landing_footer_content" rows="3" placeholder="© 2024 Faceless Pictures. All rights reserved."
                                        class="w-full border border-dark/10 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition resize-none"></textarea>
                                    <p class="text-[11px] text-dark/30 mt-1">Text displayed in the footer area. Supports multiple lines.</p>
                                </div>
                            </div>

                            <!-- SECTION: Manifesto Videos -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <h5 class="text-[13px] font-bold text-dark mb-1">Manifesto Video Slider</h5>
                                <p class="text-[11px] text-dark/40 mb-3">YouTube links shown between the film posters and role cards. Desktop shows 3 at a time, mobile shows 1.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div class="md:col-span-2">
                                        <label class="block text-[11px] text-dark/40 mb-1">Section Heading</label>
                                        <input type="text" x-model="form.manifesto_heading" placeholder="OUR MANIFESTO"
                                            class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <?php for ($mv = 1; $mv <= 6; $mv++): ?>
                                    <div class="bg-dark/[.02] rounded-lg p-3 space-y-2">
                                        <p class="text-[11px] font-semibold text-dark/40">Video <?= $mv ?></p>
                                        <div>
                                            <label class="block text-[10px] text-dark/35 mb-1">YouTube URL</label>
                                            <input type="url" x-model="form.manifesto_video<?= $mv ?>_url" placeholder="https://youtu.be/..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-dark/35 mb-1">Title <span class="text-dark/20">(optional — leave blank to hide)</span></label>
                                            <input type="text" x-model="form.manifesto_video<?= $mv ?>_title" placeholder="Video title..."
                                                class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- SECTION: Marquee Text -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <h5 class="text-[13px] font-bold text-dark mb-1">Scrolling Marquee Text</h5>
                                <p class="text-[11px] text-dark/40 mb-4">Up to 10 text items shown in scrolling animation on home page. Leave blank to hide. If all blank, defaults to original text.</p>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                    <?php for ($mi = 1; $mi <= 10; $mi++): ?>
                                    <div>
                                        <label class="block text-[9px] text-dark/40 mb-1">Item <?= $mi ?></label>
                                        <input type="text" x-model="form.marquee_item<?= $mi ?>" placeholder="TEXT"
                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-dark/20">
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- SECTION: About -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <h5 class="text-[13px] font-bold text-dark mb-1">Stats Section (Replaces About)</h5>
                                <p class="text-[11px] text-dark/40 mb-4">Numbers, labels, description lines and tagline</p>
                                
                                <!-- Stats Numbers & Labels -->
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
                                    <div>
                                        <label class="block text-[11px] font-medium text-dark/60 mb-1">Number 1</label>
                                        <input type="text" x-model="form.stats_number_1" placeholder="10" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <input type="text" x-model="form.stats_label_1" placeholder="FILMS" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition mt-2">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-dark/60 mb-1">Number 2</label>
                                        <input type="text" x-model="form.stats_number_2" placeholder="100" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <input type="text" x-model="form.stats_label_2" placeholder="SCENES" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition mt-2">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-dark/60 mb-1">Number 3</label>
                                        <input type="text" x-model="form.stats_number_3" placeholder="30" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <input type="text" x-model="form.stats_label_3" placeholder="DAYS" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition mt-2">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-dark/60 mb-1">Number 4</label>
                                        <input type="text" x-model="form.stats_number_4" placeholder="20" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <input type="text" x-model="form.stats_label_4" placeholder="ARTISTS" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition mt-2">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-dark/60 mb-1">Number 5</label>
                                        <input type="text" x-model="form.stats_number_5" placeholder="150" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                        <input type="text" x-model="form.stats_label_5" placeholder="LIVES" class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition mt-2">
                                    </div>
                                </div>
                                
                                <!-- Description Lines -->
                                <div class="space-y-2 mb-3">
                                    <input type="text" x-model="form.stats_line_1" placeholder="Many talented people never get their first chance." class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <input type="text" x-model="form.stats_line_2" placeholder="We are giving them one." class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <input type="text" x-model="form.stats_line_3" placeholder="We don't just make films." class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                    <input type="text" x-model="form.stats_line_4" placeholder="We open the door." class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                </div>
                                
                                <!-- Tagline -->
                                <div>
                                    <label class="block text-[11px] font-medium text-dark/60 mb-1">Orange Tagline</label>
                                    <input type="text" x-model="form.stats_tagline" placeholder="FACELESS TO STAR." class="w-full border border-dark/10 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dark/20 transition">
                                </div>
                            </div>

                                    </div><!-- End Home Page Content -->
                                </div><!-- End Home Page Collapse -->
                            </div><!-- End Home Page Section -->

                            <!-- ========== ROLE CARDS (shown on home page) ========== -->
                            <div class="mt-6 mb-6">
                                <div @click="showRoleCards = !showRoleCards" 
                                    class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-lg cursor-pointer hover:from-purple-100 hover:to-purple-150 transition-all">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🎭</span>
                                        <h4 class="font-semibold text-dark text-sm">ROLE CARDS</h4>
                                        <span class="text-xs text-dark/40">(Writer, Director, Actor cards shown on home page)</span>
                                    </div>
                                    <svg class="w-5 h-5 text-dark/40 transition-transform" :class="{'rotate-180': showRoleCards}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="showRoleCards" x-transition>
                                    <div class="mt-3 pl-4 pr-4 pb-4">

                            <!-- SECTION: Role Cards -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <h5 class="text-[13px] font-bold text-dark mb-1">Role Cards (Writer, Director, Actor)</h5>
                                <p class="text-[11px] text-dark/40 mb-4">Leave title blank to hide entire card. <strong>Descriptions support:</strong> Regular line breaks (use \n) OR numbered lists (type "1. Item" on each line)</p>
                                
                                <div class="space-y-6">
                                    <!-- WRITER -->
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">✍️ WRITER</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Title</label>
                                                <input type="text" x-model="form.role_writer_title" placeholder="WRITER"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Icon Emoji</label>
                                                <input type="text" x-model="form.role_writer_icon" placeholder="✍️"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-[10px] text-dark/50 mb-1">Description <span class="text-dark/30">(use \n for breaks OR "1. Item" for numbered list)</span></label>
                                                <textarea x-model="form.role_writer_description" rows="2" placeholder="Read your script on camera.\nYour words. Your voice. One video."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 1 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_writer_badge1" placeholder="Script Reading"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 2 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_writer_badge2" placeholder=""
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button Text</label>
                                                <input type="text" x-model="form.role_writer_button_text" placeholder="Click Here →"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button URL</label>
                                                <input type="text" x-model="form.role_writer_button_url" placeholder="/writer"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DIRECTOR -->
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">🎬 DIRECTOR</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Title</label>
                                                <input type="text" x-model="form.role_director_title" placeholder="DIRECTOR"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Icon Emoji</label>
                                                <input type="text" x-model="form.role_director_icon" placeholder="🎬"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-[10px] text-dark/50 mb-1">Description <span class="text-dark/30">(use \n for breaks OR "1. Item" for numbered list)</span></label>
                                                <textarea x-model="form.role_director_description" rows="2" placeholder="Shoot your scene your way.\nOne phone. One take. Your vision."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 1 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_director_badge1" placeholder="Scene Direction"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 2 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_director_badge2" placeholder="Pitch"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button Text</label>
                                                <input type="text" x-model="form.role_director_button_text" placeholder="Click Here →"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button URL</label>
                                                <input type="text" x-model="form.role_director_button_url" placeholder="/director"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ACTOR -->
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">🎭 ACTOR</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Title</label>
                                                <input type="text" x-model="form.role_actor_title" placeholder="ACTOR"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Icon Emoji</label>
                                                <input type="text" x-model="form.role_actor_icon" placeholder="🎭"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-[10px] text-dark/50 mb-1">Description <span class="text-dark/30">(use \n for breaks OR "1. Item" for numbered list)</span></label>
                                                <textarea x-model="form.role_actor_description" rows="2" placeholder="Shoot your scene on camera.\nFace hidden. Talent only."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 1 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_actor_badge1" placeholder="Dialogue"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Badge 2 <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.role_actor_badge2" placeholder="Song"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button Text</label>
                                                <input type="text" x-model="form.role_actor_button_text" placeholder="Click Here →"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Button URL</label>
                                                <input type="text" x-model="form.role_actor_button_url" placeholder="/actor"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                    </div><!-- End Role Cards Content -->
                                </div><!-- End Role Cards Collapse -->
                            </div><!-- End Role Cards Section -->

                            <!-- ========== WRITER PAGE TEXT ========== -->
                            <div class="mt-6 mb-6">
                                <div @click="showWriter = !showWriter" 
                                    class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg cursor-pointer hover:from-blue-100 hover:to-blue-150 transition-all">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">✍️</span>
                                        <h4 class="font-semibold text-dark text-sm">WRITER PAGE TEXT</h4>
                                        <span class="text-xs text-dark/40">(Hero & Submission Form text on <a href="/writer" target="_blank" class="underline">/writer</a> page)</span>
                                    </div>
                                    <svg class="w-5 h-5 text-dark/40 transition-transform" :class="{'rotate-180': showWriter}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="showWriter" x-transition>
                                    <div class="mt-3 pl-4 pr-4 pb-4">

                            <!-- WRITER PAGE TEXT -->
                            <div class="mb-6 pb-6 border-b border-dark/5">
                                <p class="text-xs text-dark/40 mb-4">Control the hero section and submission form text shown on the Writer page. Leave blank to hide sections.</p>
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">✍️ WRITER PAGE</p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Label <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.writer_hero_label" placeholder="Submissions Now Open"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Heading</label>
                                                <input type="text" x-model="form.writer_hero_heading" placeholder="WRITER SUBMISSIONS"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Description</label>
                                                <textarea x-model="form.writer_hero_description" rows="2" placeholder="READ THE SCENE. WRITE THE NEXT PAGE. RECORD YOUR NARRATION. UPLOAD YOUR VIDEO."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">3-Step Process</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Title</label>
                                                        <input type="text" x-model="form.writer_step1_title" placeholder="WHAT WE GIVE"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Text</label>
                                                        <input type="text" x-model="form.writer_step1_text" placeholder="One page"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Title</label>
                                                        <input type="text" x-model="form.writer_step2_title" placeholder="WHAT YOU DO"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Text</label>
                                                        <input type="text" x-model="form.writer_step2_text" placeholder="Continue it. One more page only"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Title</label>
                                                        <input type="text" x-model="form.writer_step3_title" placeholder="SUBMIT"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Text</label>
                                                        <input type="text" x-model="form.writer_step3_text" placeholder="Your page PDF plus narration video"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">Submission Form Text</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Heading</label>
                                                        <input type="text" x-model="form.writer_form_heading" placeholder="Ready to Write? Submit Your Continuation"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Description</label>
                                                        <textarea x-model="form.writer_form_description" rows="2" placeholder="Read the given script, write what happens next, then record yourself narrating it on camera."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submission Messages -->
                                    <div class="border-t border-dark/10 pt-4 mt-4 px-4">
                                        <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-3">Submission Messages</p>
                                        <p class="text-[11px] text-dark/50 mb-3">Customize success and failure messages shown after writer submissions.</p>
                                        
                                        <div class="space-y-3">
                                            <!-- Success Messages -->
                                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                                <p class="text-[10px] font-semibold text-green-800 mb-2">✓ Success Messages</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Success Heading</label>
                                                        <input type="text" x-model="form.writer_success_heading" placeholder="WRITER SUBMISSION RECEIVED!"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Success Message</label>
                                                        <textarea x-model="form.writer_success_message" rows="2" placeholder="Your writer video is in the queue for AI review..."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300"></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">PDF Button Text</label>
                                                        <input type="text" x-model="form.writer_success_pdf_button" placeholder="Download Writer Brief PDF"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Failure Messages -->
                                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                                <p class="text-[10px] font-semibold text-red-800 mb-2">✗ Failure Messages</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Failure Heading</label>
                                                        <input type="text" x-model="form.writer_failure_heading" placeholder="SUBMISSION FAILED"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Failure Message</label>
                                                        <textarea x-model="form.writer_failure_message" rows="2" placeholder="We couldn't process your writer video..."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300"></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Retry Button Text</label>
                                                        <input type="text" x-model="form.writer_failure_retry_button" placeholder="Try Again"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    </div><!-- End Writer Page Content -->
                                </div><!-- End Writer Page Collapse -->
                            </div><!-- End Writer Page Section -->

                            <!-- ========== DIRECTOR PAGE TEXT ========== -->
                            <div class="mt-6 mb-6">
                                <div @click="showDirector = !showDirector" 
                                    class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg cursor-pointer hover:from-green-100 hover:to-green-150 transition-all">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🎬</span>
                                        <h4 class="font-semibold text-dark text-sm">DIRECTOR PAGE TEXT</h4>
                                        <span class="text-xs text-dark/40">(Hero & Submission Form text on <a href="/director" target="_blank" class="underline">/director</a> page)</span>
                                    </div>
                                    <svg class="w-5 h-5 text-dark/40 transition-transform" :class="{'rotate-180': showDirector}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="showDirector" x-transition>
                                    <div class="mt-3 pl-4 pr-4 pb-4">

                                    <!-- DIRECTOR PAGE TEXT -->
                                    <div class="mb-6 pb-6 border-b border-dark/5">
                                        <p class="text-xs text-dark/40 mb-4">Control the hero section and submission form text shown on the Director page. Leave blank to hide sections.</p>
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">🎬 DIRECTOR PAGE</p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Label <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.director_hero_label" placeholder="Auditions Now Open"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Heading</label>
                                                <input type="text" x-model="form.director_hero_heading" placeholder="DIRECTOR AUDITIONS"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Description</label>
                                                <textarea x-model="form.director_hero_description" rows="2" placeholder="CAST YOUR ACTOR. SHOOT YOUR SCENE. SHOW US YOUR VISION."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">3-Step Process</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Title</label>
                                                        <input type="text" x-model="form.director_step1_title" placeholder="WHAT WE GIVE"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Text</label>
                                                        <input type="text" x-model="form.director_step1_text" placeholder="Script and actor"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Title</label>
                                                        <input type="text" x-model="form.director_step2_title" placeholder="WHAT YOU DO"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Text</label>
                                                        <input type="text" x-model="form.director_step2_text" placeholder="Direct the scene"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Title</label>
                                                        <input type="text" x-model="form.director_step3_title" placeholder="SUBMIT"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Text</label>
                                                        <input type="text" x-model="form.director_step3_text" placeholder="Your scene video"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">Submission Form Text</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Heading</label>
                                                        <input type="text" x-model="form.director_form_heading" placeholder="Ready to Direct? Submit Your Scene"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Description</label>
                                                        <textarea x-model="form.director_form_description" rows="2" placeholder="Cast your actor, give them the script, shoot the scene, and upload your video."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Submission Messages -->
                                    <div class="border-t border-dark/10 pt-4 mt-4 px-4">
                                        <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-3">Submission Messages</p>
                                        <p class="text-[11px] text-dark/50 mb-3">Customize success and failure messages shown after director submissions.</p>
                                        
                                        <div class="space-y-3">
                                            <!-- Success Messages -->
                                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                                <p class="text-[10px] font-semibold text-green-800 mb-2">✓ Success Messages</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Success Heading</label>
                                                        <input type="text" x-model="form.director_success_heading" placeholder="DIRECTOR SUBMISSION RECEIVED!"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Success Message</label>
                                                        <textarea x-model="form.director_success_message" rows="2" placeholder="Your director video is in the queue for AI review..."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300"></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">PDF Button Text</label>
                                                        <input type="text" x-model="form.director_success_pdf_button" placeholder="Download Director Brief PDF"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Failure Messages -->
                                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                                <p class="text-[10px] font-semibold text-red-800 mb-2">✗ Failure Messages</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Failure Heading</label>
                                                        <input type="text" x-model="form.director_failure_heading" placeholder="SUBMISSION FAILED"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Failure Message</label>
                                                        <textarea x-model="form.director_failure_message" rows="2" placeholder="We couldn't process your director video..."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300"></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/60 mb-1">Retry Button Text</label>
                                                        <input type="text" x-model="form.director_failure_retry_button" placeholder="Try Again"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    </div><!-- End Director Page Content -->
                                </div><!-- End Director Page Collapse -->
                            </div><!-- End Director Page Section -->

                            <!-- ========== ACTOR PAGE TEXT ========== -->
                            <div class="mt-6 mb-6">
                                <div @click="showActor = !showActor" 
                                    class="flex items-center justify-between p-4 bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg cursor-pointer hover:from-orange-100 hover:to-orange-150 transition-all">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">🎤</span>
                                        <h4 class="font-semibold text-dark text-sm">ACTOR PAGE TEXT</h4>
                                        <span class="text-xs text-dark/40">(Hero & Submission Form text on <a href="/actor" target="_blank" class="underline">/actor</a> page)</span>
                                    </div>
                                    <svg class="w-5 h-5 text-dark/40 transition-transform" :class="{'rotate-180': showActor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="showActor" x-transition>
                                    <div class="mt-3 pl-4 pr-4 pb-4">

                                    <!-- ACTOR PAGE TEXT -->
                                    <div class="mb-6 pb-6 border-b border-dark/5">
                                        <p class="text-xs text-dark/40 mb-4">Control the hero section and submission form text shown on the Actor page. Leave blank to hide sections.</p>
                                    <div class="p-4 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-bold text-dark/70 mb-3">🎭 ACTOR PAGE</p>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Label <span class="text-dark/30">(blank to hide)</span></label>
                                                <input type="text" x-model="form.actor_hero_label" placeholder="Auditions Now Open"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Heading</label>
                                                <input type="text" x-model="form.actor_hero_heading" placeholder="ACTOR AUDITIONS"
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-dark/50 mb-1">Hero Description</label>
                                                <textarea x-model="form.actor_hero_description" rows="2" placeholder="Two auditions, one submission. Read the dialog brief, learn the song, then shoot both videos."
                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">3-Step Process</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Title</label>
                                                        <input type="text" x-model="form.actor_step1_title" placeholder="WHAT WE GIVE"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 1 Text</label>
                                                        <input type="text" x-model="form.actor_step1_text" placeholder="Dialog brief and song"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Title</label>
                                                        <input type="text" x-model="form.actor_step2_title" placeholder="WHAT YOU DO"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 2 Text</label>
                                                        <input type="text" x-model="form.actor_step2_text" placeholder="Perform both auditions"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Title</label>
                                                        <input type="text" x-model="form.actor_step3_title" placeholder="SUBMIT"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Step 3 Text</label>
                                                        <input type="text" x-model="form.actor_step3_text" placeholder="Two audition videos"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">Submission Form Text</p>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Heading</label>
                                                        <input type="text" x-model="form.actor_form_heading" placeholder="Ready to Perform? Submit Your Auditions"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Form Description</label>
                                                        <textarea x-model="form.actor_form_description" rows="2" placeholder="Shoot your dialog scene and song audition, then upload both videos below."
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20"></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Submission Messages -->
                                            <div class="border-t border-dark/10 pt-4 mt-4">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-3">Submission Messages</p>
                                                <p class="text-[11px] text-dark/50 mb-3">Customize success and failure messages shown after actor submissions.</p>
                                                
                                                <div class="space-y-3">
                                                    <!-- Success Messages -->
                                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                                        <p class="text-[10px] font-semibold text-green-800 mb-2">✓ Success Messages</p>
                                                        <div class="space-y-2">
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">Success Heading</label>
                                                                <input type="text" x-model="form.actor_success_heading" placeholder="ACTOR SUBMISSION RECEIVED!"
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">Success Message</label>
                                                                <textarea x-model="form.actor_success_message" rows="2" placeholder="Your acting video is in the queue for AI review..."
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300"></textarea>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">PDF Button Text</label>
                                                                <input type="text" x-model="form.actor_success_pdf_button" placeholder="Download Actor Brief PDF"
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-green-300">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Failure Messages -->
                                                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                                        <p class="text-[10px] font-semibold text-red-800 mb-2">✗ Failure Messages</p>
                                                        <div class="space-y-2">
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">Failure Heading</label>
                                                                <input type="text" x-model="form.actor_failure_heading" placeholder="SUBMISSION FAILED"
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">Failure Message</label>
                                                                <textarea x-model="form.actor_failure_message" rows="2" placeholder="We couldn't process your acting video..."
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300"></textarea>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[10px] text-dark/60 mb-1">Retry Button Text</label>
                                                                <input type="text" x-model="form.actor_failure_retry_button" placeholder="Try Again"
                                                                    class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-red-300">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="border-t border-dark/10 pt-3 mt-3">
                                                <p class="text-[9px] text-dark/40 uppercase tracking-wider mb-2">Film Song Card (shown between audition cards and submit form)</p>
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Heading</label>
                                                        <input type="text" x-model="form.film_song_heading" placeholder="FILM SONG"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Subtitle</label>
                                                        <input type="text" x-model="form.film_song_subtitle" placeholder="Listen to the song before you record"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] text-dark/50 mb-1">Button Text</label>
                                                        <input type="text" x-model="form.film_song_btn_label" placeholder="Get Song"
                                                            class="w-full border border-dark/10 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-dark/20">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                    </div><!-- End Actor Page Content -->
                                </div><!-- End Actor Page Collapse -->
                            </div><!-- End Actor Page Section -->

                            </div><!-- End Collapsible Sections Wrapper -->

                            <!-- Media fields are now per-script — see Scripts tab -->
                            <div class="mb-2 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-[12px] text-blue-700">Preview videos, script images, PDFs and song tune URLs are now managed <strong>per-script</strong>. Go to <a href="#" @click.prevent="activeTab='scripts'" class="font-semibold underline">Scripts tab</a> → Edit any script to upload its media.</p>
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


                        <!-- Debug, Log Files, Environment - Single Row on Desktop -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 md:col-span-2 lg:col-span-3">
                            
                            <!-- Debug Logging -->
                            <div class="bg-white rounded-xl border border-dark/5 p-4">
                                <h3 class="font-semibold text-dark mb-3 flex items-center gap-2 text-[13px]">
                                    <svg class="w-4 h-4 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Debug Logging
                                </h3>
                                
                                <div class="flex items-center justify-between p-3 bg-cream rounded-lg mb-3">
                                    <div>
                                        <p class="font-medium text-dark text-[12px]">Status</p>
                                        <p class="text-[10px] text-dark/40">Logs to /logs/debug.log</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/30' ?>">
                                            <?= FP3_DEBUG ? 'ON' : 'OFF' ?>
                                        </span>
                                        <form method="POST" action="" class="inline" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'text-[9px]\'>Saving...</span>';">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="toggle_debug" value="1">
                                            <button type="submit" class="relative w-10 h-5 rounded-full transition-colors <?= FP3_DEBUG ? 'bg-green-500' : 'bg-dark/20' ?>">
                                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform <?= FP3_DEBUG ? 'translate-x-5' : '' ?>"></span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-2 mb-3">
                                    <p class="text-[10px] text-amber-800">⚠️ Logs sensitive data</p>
                                </div>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="clear_logs" value="1">
                                    <button type="submit" class="w-full bg-crimson/10 text-crimson font-medium py-2 rounded-lg hover:bg-crimson/20 transition text-[11px]">
                                        Clear All Logs
                                    </button>
                                </form>
                            </div>

                            <!-- Log Files -->
                            <div class="bg-white rounded-xl border border-dark/5 p-4">
                                <h3 class="font-semibold text-dark mb-3 flex items-center gap-2 text-[13px]">
                                    <svg class="w-4 h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Log Files
                                </h3>
                                
                                <?php if (empty($logFiles)): ?>
                                <p class="text-[11px] text-dark/30 text-center py-8">No log files</p>
                                <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($logFiles as $log): ?>
                                    <div class="p-2 bg-cream rounded-lg">
                                        <p class="font-medium text-dark text-[11px] truncate"><?= e($log['name']) ?></p>
                                        <div class="flex justify-between items-center mt-0.5">
                                            <p class="text-[9px] text-dark/40"><?= date('M j, H:i', $log['modified']) ?></p>
                                            <span class="text-[9px] text-dark/40"><?= number_format($log['size'] / 1024, 1) ?> KB</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Environment -->
                            <div class="bg-white rounded-xl border border-dark/5 p-4">
                                <h3 class="font-semibold text-dark mb-3 flex items-center gap-2 text-[13px]">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    Environment
                                </h3>
                                
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-[11px] p-2 bg-cream rounded">
                                        <span class="text-dark/50">Environment</span>
                                        <span class="font-medium text-dark"><?= APP_ENV ?></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] p-2 bg-cream rounded">
                                        <span class="text-dark/50">PHP</span>
                                        <span class="font-medium text-dark"><?= PHP_VERSION ?></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] p-2 bg-cream rounded">
                                        <span class="text-dark/50">Debug</span>
                                        <span class="font-medium <?= FP3_DEBUG ? 'text-green-600' : 'text-dark/40' ?>"><?= FP3_DEBUG ? 'ON' : 'OFF' ?></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] p-2 bg-cream rounded">
                                        <span class="text-dark/50">Memory</span>
                                        <span class="font-medium text-dark"><?= ini_get('memory_limit') ?></span>
                                    </div>
                                    <div class="flex justify-between text-[11px] p-2 bg-cream rounded">
                                        <span class="text-dark/50">Upload</span>
                                        <span class="font-medium text-dark"><?= ini_get('upload_max_filesize') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Log Viewers - Full Width Below -->
                    <?php if ($debugLogContent || $errorLogContent): ?>
                    <div class="grid grid-cols-1 gap-4 md:gap-6 md:col-span-2 lg:col-span-3">
                        <?php if ($debugLogContent): ?>
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-4 py-3 border-b border-dark/5 bg-blue-50">
                                <h3 class="font-semibold text-dark text-[12px] flex items-center gap-2">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    Debug Log (last 100 lines)
                                </h3>
                            </div>
                            <div class="p-3 bg-dark max-h-[300px] overflow-auto">
                                <pre class="log-viewer text-green-400 whitespace-pre-wrap text-[10px] leading-relaxed"><?= e($debugLogContent) ?></pre>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($errorLogContent): ?>
                        <div class="bg-white rounded-xl border border-dark/5 overflow-hidden">
                            <div class="px-4 py-3 border-b border-dark/5 bg-red-50">
                                <h3 class="font-semibold text-dark text-[12px] flex items-center gap-2">
                                    <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                                    Error Log (last 50 lines)
                                </h3>
                            </div>
                            <div class="p-3 bg-dark max-h-[300px] overflow-auto">
                                <pre class="log-viewer text-red-400 whitespace-pre-wrap text-[10px] leading-relaxed"><?= e($errorLogContent) ?></pre>
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

    <!-- Submission Detail Modal - Compact & Mobile Friendly -->
    <div x-show="viewingSubmission" x-cloak @click.self="closeSubmission()"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-data="{ activeVideoTab: 'video1' }">
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-full sm:max-w-3xl max-h-[95vh] sm:max-h-[85vh] overflow-hidden flex flex-col" @click.stop>
            <!-- Compact Header -->
            <div class="px-3 sm:px-5 py-2 sm:py-3 border-b border-dark/10 flex items-center justify-between flex-shrink-0">
                <template x-if="viewingSubmission">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-display text-[16px] sm:text-[18px] text-dark truncate" x-text="viewingSubmission.name"></h3>
                        <p class="text-[10px] sm:text-[11px] text-dark/50" x-text="viewingSubmission.role.toUpperCase() + ' — ' + viewingSubmission.audition_type"></p>
                    </div>
                </template>
                <button @click="closeSubmission()" class="text-dark/40 hover:text-dark ml-2 sm:ml-3 flex-shrink-0">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <template x-if="viewingSubmission">
                <div class="flex-1 overflow-y-auto">
                    <!-- Compact Contact Bar -->
                    <div class="px-3 sm:px-5 py-2 bg-cream/30 border-b border-dark/5 text-[10px] sm:text-[11px] flex flex-wrap gap-x-3 sm:gap-x-4 gap-y-1">
                        <span class="flex items-center gap-1 text-dark/70 min-w-0">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <a :href="'mailto:' + viewingSubmission.email" x-text="viewingSubmission.email" class="hover:text-crimson truncate"></a>
                        </span>
                        <span class="flex items-center gap-1 text-dark/70 flex-shrink-0">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <a :href="'tel:' + viewingSubmission.phone" x-text="viewingSubmission.phone" class="hover:text-crimson"></a>
                        </span>
                        <span class="flex items-center gap-1 text-dark/70 flex-shrink-0">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="formatDate(viewingSubmission.submitted_at)"></span>
                        </span>
                        <span class="ml-auto px-2 py-0.5 rounded-full text-[9px] sm:text-[10px] font-semibold flex-shrink-0"
                            :class="{
                                'bg-amber-100 text-amber-700': viewingSubmission.status==='new',
                                'bg-blue-100 text-blue-700': viewingSubmission.status==='reviewed',
                                'bg-green-100 text-green-700': viewingSubmission.status==='shortlisted',
                                'bg-red-100 text-red-700': viewingSubmission.status==='rejected'
                            }"
                            x-text="viewingSubmission.status.toUpperCase()"></span>
                    </div>

                    <!-- Tabs (for actor dual videos) -->
                    <template x-if="viewingSubmission.submission_tag === 'actor-dual' && viewingSubmission.file_path_2">
                        <div class="border-b border-dark/5 px-3 sm:px-5">
                            <div class="flex gap-1 sm:gap-2">
                                <button @click="activeVideoTab = 'video1'" 
                                    class="px-3 sm:px-4 py-2 text-[11px] sm:text-[12px] font-medium border-b-2 transition"
                                    :class="activeVideoTab === 'video1' ? 'border-crimson text-crimson' : 'border-transparent text-dark/50 hover:text-dark'">
                                    🎭 Dialog
                                </button>
                                <button @click="activeVideoTab = 'video2'" 
                                    class="px-3 sm:px-4 py-2 text-[11px] sm:text-[12px] font-medium border-b-2 transition"
                                    :class="activeVideoTab === 'video2' ? 'border-crimson text-crimson' : 'border-transparent text-dark/50 hover:text-dark'">
                                    🎵 Song
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="p-3 sm:p-5">
                        <!-- Dialog Video / Single Video -->
                        <template x-if="viewingSubmission.file_path && (viewingSubmission.submission_tag !== 'actor-dual' || activeVideoTab === 'video1')">
                            <div>
                                <!-- Video Player (Plyr) -->
                                <div class="mb-3 sm:mb-4">
                                    <video :id="'submission-player-' + viewingSubmission.id" 
                                           :src="'/uploads/' + viewingSubmission.file_path" 
                                           controls playsinline 
                                           class="w-full rounded-lg bg-dark max-h-[250px] sm:max-h-[350px] plyr-video"></video>
                                </div>

                                <!-- AI Analysis (Compact) -->
                                <div class="bg-cream rounded-lg p-2.5 sm:p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-dark text-[11px] sm:text-[12px] flex items-center gap-1.5">
                                            <svg class="w-3 sm:w-3.5 h-3 sm:h-3.5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                            AI Analysis
                                        </h4>
                                        <span class="text-[9px] sm:text-[10px] px-1.5 sm:px-2 py-0.5 rounded-full font-semibold"
                                            :class="{
                                                'bg-green-100 text-green-700': (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='approved',
                                                'bg-red-100 text-red-700': viewingSubmission.ai_flagged || (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='flagged',
                                                'bg-amber-100 text-amber-700': (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='pending' || (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='processing',
                                                'bg-gray-100 text-gray-600': !(viewingSubmission.video1_ai_status || viewingSubmission.ai_status)
                                            }"
                                            x-text="viewingSubmission.ai_flagged || (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='flagged' ? '🚩 FLAGGED' : ((viewingSubmission.video1_ai_status || viewingSubmission.ai_status) ? (viewingSubmission.video1_ai_status || viewingSubmission.ai_status).toUpperCase() : 'PENDING')"></span>
                                    </div>

                                    <!-- AI Score (from video feedback) -->
                                    <template x-if="viewingSubmission.video1_ai_score">
                                        <div class="mb-2 sm:mb-3 flex items-center gap-2 sm:gap-3">
                                            <div class="text-center">
                                                <div class="text-[24px] sm:text-[28px] font-display"
                                                    :class="{
                                                        'text-green-600': viewingSubmission.video1_ai_score >= 70,
                                                        'text-amber-600': viewingSubmission.video1_ai_score >= 40 && viewingSubmission.video1_ai_score < 70,
                                                        'text-red-600': viewingSubmission.video1_ai_score < 40
                                                    }"
                                                    x-text="viewingSubmission.video1_ai_score"></div>
                                                <div class="text-[9px] text-dark/40">SCORE</div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="h-2 bg-dark/10 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full"
                                                        :class="{
                                                            'bg-green-500': viewingSubmission.video1_ai_score >= 70,
                                                            'bg-amber-500': viewingSubmission.video1_ai_score >= 40 && viewingSubmission.video1_ai_score < 70,
                                                            'bg-red-500': viewingSubmission.video1_ai_score < 40
                                                        }"
                                                        :style="'width: ' + viewingSubmission.video1_ai_score + '%'"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- AI Summary (from video feedback) -->
                                    <template x-if="viewingSubmission.video1_ai_feedback?.summary">
                                        <p class="text-[11px] sm:text-[12px] text-dark/70 mb-2" x-text="viewingSubmission.video1_ai_feedback.summary"></p>
                                    </template>

                                    <!-- AI Flags (from video feedback) -->
                                    <template x-if="viewingSubmission.video1_ai_feedback?.flags && viewingSubmission.video1_ai_feedback.flags.length > 0">
                                        <div class="mb-2">
                                            <span class="text-[10px] text-dark/50 uppercase">Flags:</span>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <template x-for="flag in viewingSubmission.video1_ai_feedback.flags" :key="flag">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700" x-text="flag"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Transcript (from video feedback) -->
                                    <template x-if="viewingSubmission.video1_ai_feedback?.transcript">
                                        <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-dark/10">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-[9px] sm:text-[10px] text-dark/50 uppercase font-semibold">Transcript</span>
                                                <template x-if="viewingSubmission.video1_ai_feedback?.feedback">
                                                    <span class="text-[8px] sm:text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700"
                                                          x-text="(viewingSubmission.video1_ai_feedback.feedback.find(f => f.startsWith('Language detected:')) || '').replace('Language detected: ', '')"></span>
                                                </template>
                                            </div>
                                            <p class="text-[11px] sm:text-[12px] text-dark/70 leading-relaxed bg-dark/5 rounded p-2" x-text="viewingSubmission.video1_ai_feedback.transcript"></p>
                                        </div>
                                    </template>

                                    <!-- NSFW Analysis (from video feedback) -->
                                    <template x-if="viewingSubmission.video1_ai_feedback?.nsfw_result">
                                        <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-dark/10">
                                            <span class="text-[9px] sm:text-[10px] text-dark/50 uppercase font-semibold">Visual Content Safety</span>
                                            <div class="mt-1.5 grid grid-cols-2 gap-1 sm:gap-1.5 text-[9px] sm:text-[10px]">
                                                <template x-for="(value, key) in viewingSubmission.video1_ai_feedback.nsfw_result" :key="key">
                                                    <div class="flex justify-between bg-dark/5 rounded px-1.5 sm:px-2 py-1">
                                                        <span class="text-dark/60 capitalize truncate" x-text="key.replace(/_/g, ' ')"></span>
                                                        <span :class="(typeof value === 'number' && value > 0.5 && key !== 'frames_checked') ? 'text-red-600 font-medium' : 'text-green-600'" 
                                                              x-text="typeof value === 'number' ? (key === 'frames_checked' ? value : Math.round(value * 100) + '%') : value"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- AI Notes (fallback from submissions table) -->
                                    <template x-if="viewingSubmission.ai_notes && !viewingSubmission.video1_ai_feedback?.summary">
                                        <p class="text-[12px] text-dark/70 whitespace-pre-wrap" x-text="viewingSubmission.ai_notes"></p>
                                    </template>

                                    <!-- No data -->
                                    <template x-if="!viewingSubmission.video1_ai_score && !viewingSubmission.video1_ai_feedback && !viewingSubmission.ai_notes && !(viewingSubmission.video1_ai_status || viewingSubmission.ai_status)">
                                        <p class="text-[11px] text-dark/40 italic">No AI analysis available yet.</p>
                                    </template>
                                    
                                    <!-- Processing -->
                                    <template x-if="!viewingSubmission.video1_ai_feedback && ((viewingSubmission.video1_ai_status || viewingSubmission.ai_status) === 'processing')">
                                        <div class="flex items-center gap-2 text-[11px] text-dark/50">
                                            <svg class="animate-spin w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Processing...
                                        </div>
                                    </template>
                                    
                                    <!-- Warning for flagged -->
                                    <template x-if="viewingSubmission.ai_flagged || (viewingSubmission.video1_ai_status || viewingSubmission.ai_status)==='flagged'">
                                        <div class="mt-3 p-2 bg-red-50 border border-red-200 rounded flex items-start gap-2">
                                            <svg class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <p class="text-[10px] text-red-700">Flagged for manual review</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Song Video (for actor dual) -->
                        <template x-if="viewingSubmission.submission_tag === 'actor-dual' && viewingSubmission.file_path_2 && activeVideoTab === 'video2'">
                            <div>
                                <div class="mb-3 sm:mb-4">
                                    <video :id="'submission-player-2-' + viewingSubmission.id" 
                                           :src="'/uploads/' + viewingSubmission.file_path_2" 
                                           controls playsinline 
                                           class="w-full rounded-lg bg-dark max-h-[250px] sm:max-h-[350px] plyr-video"></video>
                                </div>

                                <div class="bg-cream rounded-lg p-2.5 sm:p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-semibold text-dark text-[11px] sm:text-[12px] flex items-center gap-1.5">
                                            <svg class="w-3 sm:w-3.5 h-3 sm:h-3.5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                            AI Analysis
                                        </h4>
                                        <span class="text-[9px] sm:text-[10px] px-1.5 sm:px-2 py-0.5 rounded-full font-semibold"
                                            :class="{
                                                'bg-green-100 text-green-700': (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='approved',
                                                'bg-red-100 text-red-700': viewingSubmission.ai_flagged_2 || (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='flagged',
                                                'bg-amber-100 text-amber-700': (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='pending' || (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='processing',
                                                'bg-gray-100 text-gray-600': !(viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)
                                            }"
                                            x-text="viewingSubmission.ai_flagged_2 || (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='flagged' ? '🚩 FLAGGED' : ((viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2) ? (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2).toUpperCase() : 'PENDING')"></span>
                                    </div>

                                    <!-- AI Score -->
                                    <template x-if="viewingSubmission.video2_ai_score">
                                        <div class="mb-2 sm:mb-3 flex items-center gap-2 sm:gap-3">
                                            <div class="text-center">
                                                <div class="text-[24px] sm:text-[28px] font-display"
                                                    :class="{
                                                        'text-green-600': viewingSubmission.video2_ai_score >= 70,
                                                        'text-amber-600': viewingSubmission.video2_ai_score >= 40 && viewingSubmission.video2_ai_score < 70,
                                                        'text-red-600': viewingSubmission.video2_ai_score < 40
                                                    }"
                                                    x-text="viewingSubmission.video2_ai_score"></div>
                                                <div class="text-[9px] text-dark/40">SCORE</div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="h-2 bg-dark/10 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full"
                                                        :class="{
                                                            'bg-green-500': viewingSubmission.video2_ai_score >= 70,
                                                            'bg-amber-500': viewingSubmission.video2_ai_score >= 40 && viewingSubmission.video2_ai_score < 70,
                                                            'bg-red-500': viewingSubmission.video2_ai_score < 40
                                                        }"
                                                        :style="'width: ' + viewingSubmission.video2_ai_score + '%'"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- AI Summary -->
                                    <template x-if="viewingSubmission.video2_ai_feedback?.summary">
                                        <p class="text-[11px] sm:text-[12px] text-dark/70 mb-2" x-text="viewingSubmission.video2_ai_feedback.summary"></p>
                                    </template>

                                    <!-- AI Flags -->
                                    <template x-if="viewingSubmission.video2_ai_feedback?.flags && viewingSubmission.video2_ai_feedback.flags.length > 0">
                                        <div class="mb-2">
                                            <span class="text-[10px] text-dark/50 uppercase">Flags:</span>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <template x-for="flag in viewingSubmission.video2_ai_feedback.flags" :key="flag">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-red-100 text-red-700" x-text="flag"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Transcript (from video feedback) -->
                                    <template x-if="viewingSubmission.video2_ai_feedback?.transcript">
                                        <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-dark/10">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-[9px] sm:text-[10px] text-dark/50 uppercase font-semibold">Transcript</span>
                                                <template x-if="viewingSubmission.video2_ai_feedback?.feedback">
                                                    <span class="text-[8px] sm:text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700"
                                                          x-text="(viewingSubmission.video2_ai_feedback.feedback.find(f => f.startsWith('Language detected:')) || '').replace('Language detected: ', '')"></span>
                                                </template>
                                            </div>
                                            <p class="text-[11px] sm:text-[12px] text-dark/70 leading-relaxed bg-dark/5 rounded p-2" x-text="viewingSubmission.video2_ai_feedback.transcript"></p>
                                        </div>
                                    </template>

                                    <!-- NSFW Analysis (from video feedback) -->
                                    <template x-if="viewingSubmission.video2_ai_feedback?.nsfw_result">
                                        <div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-dark/10">
                                            <span class="text-[9px] sm:text-[10px] text-dark/50 uppercase font-semibold">Visual Content Safety</span>
                                            <div class="mt-1.5 grid grid-cols-2 gap-1 sm:gap-1.5 text-[9px] sm:text-[10px]">
                                                <template x-for="(value, key) in viewingSubmission.video2_ai_feedback.nsfw_result" :key="key">
                                                    <div class="flex justify-between bg-dark/5 rounded px-1.5 sm:px-2 py-1">
                                                        <span class="text-dark/60 capitalize truncate" x-text="key.replace(/_/g, ' ')"></span>
                                                        <span :class="(typeof value === 'number' && value > 0.5 && key !== 'frames_checked') ? 'text-red-600 font-medium' : 'text-green-600'" 
                                                              x-text="typeof value === 'number' ? (key === 'frames_checked' ? value : Math.round(value * 100) + '%') : value"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- AI Notes (fallback) -->
                                    <template x-if="viewingSubmission.ai_notes_2 && !viewingSubmission.video2_ai_feedback?.summary">
                                        <p class="text-[12px] text-dark/70 whitespace-pre-wrap" x-text="viewingSubmission.ai_notes_2"></p>
                                    </template>

                                    <!-- No data -->
                                    <template x-if="!viewingSubmission.video2_ai_score && !viewingSubmission.video2_ai_feedback && !viewingSubmission.ai_notes_2 && !(viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)">
                                        <p class="text-[11px] text-dark/40 italic">No AI analysis available yet.</p>
                                    </template>
                                    
                                    <!-- Processing -->
                                    <template x-if="!viewingSubmission.video2_ai_feedback && ((viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2) === 'processing')">
                                        <div class="flex items-center gap-2 text-[11px] text-dark/50">
                                            <svg class="animate-spin w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Processing...
                                        </div>
                                    </template>
                                    
                                    <!-- Warning -->
                                    <template x-if="viewingSubmission.ai_flagged_2 || (viewingSubmission.video2_ai_status || viewingSubmission.ai_status_2)==='flagged'">
                                        <div class="mt-3 p-2 bg-red-50 border border-red-200 rounded flex items-start gap-2">
                                            <svg class="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <p class="text-[10px] text-red-700">Flagged for manual review</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Applicant Notes -->
                        <template x-if="viewingSubmission.notes">
                            <div class="mt-3 sm:mt-4 bg-blue-50 border border-blue-200 rounded-lg p-2.5 sm:p-3">
                                <h4 class="text-[10px] sm:text-[11px] font-semibold text-dark/70 uppercase mb-1">Notes:</h4>
                                <p class="text-[11px] sm:text-[12px] text-dark" x-text="viewingSubmission.notes"></p>
                            </div>
                        </template>

                        <!-- Compact Actions - Mobile Friendly -->
                        <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-dark/5 flex gap-2">
                            <button @click="updateSubmissionStatus(viewingSubmission.id, 'shortlisted'); closeSubmission()" 
                                class="flex-1 bg-green-600 text-white py-3 sm:py-2.5 rounded-lg text-[12px] sm:text-[13px] font-medium hover:bg-green-700 transition flex items-center justify-center gap-1.5 touch-manipulation">
                                <svg class="w-4 h-4 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Shortlist
                            </button>
                            <button @click="updateSubmissionStatus(viewingSubmission.id, 'rejected'); closeSubmission()" 
                                class="flex-1 bg-red-600 text-white py-3 sm:py-2.5 rounded-lg text-[12px] sm:text-[13px] font-medium hover:bg-red-700 transition flex items-center justify-center gap-1.5 touch-manipulation">
                                <svg class="w-4 h-4 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="deleteConfirmOpen" x-cloak @click.self="deleteConfirmOpen=false"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6" @click.stop>
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-[16px] text-dark mb-2">Delete Submission?</h3>
                    <p class="text-[13px] text-dark/60 mb-4">
                        <template x-if="deleteSubmissionIds.length > 1">
                            <span x-text="'Are you sure you want to delete ' + deleteSubmissionIds.length + ' submissions? This action cannot be undone.'"></span>
                        </template>
                        <template x-if="deleteSubmissionIds.length === 1">
                            <span>Are you sure you want to delete this submission? This action cannot be undone.</span>
                        </template>
                    </p>
                    <div class="flex gap-3 justify-end">
                        <button @click="deleteConfirmOpen=false" class="px-4 py-2 text-[13px] text-dark/50 hover:text-dark transition">Cancel</button>
                        <button @click="confirmDeleteSubmissions()" class="px-4 py-2 text-[13px] bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">Delete</button>
                    </div>
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
            processingAI: false,
            
            // Tab titles
            tabTitles: {
                overview: 'OVERVIEW',
                videos: 'SUBMISSIONS',
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
            
            // Bulk delete
            selectedSubmissions: [],
            deleteConfirmOpen: false,
            deleteSubmissionIds: [],
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
            
            // Script modal
            showScriptModal: false,
            
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
            scriptForm: { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: 'Dialog Audition', image_url: '', preview_video_url: '', script_pdf_url: '', tune_youtube_url: '', tune_youtube_url_1: '', tune_youtube_url_2: '', tune_youtube_url_3: '', rules: '' },
            songEntries: [{ label: '', url: '' }],
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
            
            // Playlist Management
            playlists: [],
            playlistSettings: {
                enabled: true,
                perSeason: false
            },
            loadingPlaylists: false,
            creatingPlaylists: false,
            organizingVideos: false,
            
            init() {
                window._adminDashboard = this;
                window.addEventListener('resize', () => {
                    this.sidebarCollapsed = window.innerWidth < 1024;
                });
                
                // Load YouTube status
                this.loadYouTubeStatus();

                // Watch for tab changes to load playlist data
                this.$watch('activeTab', (newTab) => {
                    if (newTab === 'youtube') {
                        this.loadPlaylists();
                        this.loadPlaylistSettings();
                    }
                });

                // Listen for script image picker selections
                // Uses a global bridge function so child Alpine components can set parent state
                window.setScriptImage = (url) => {
                    this.scriptForm.image_url = url;
                    if (window._adminDashboard) window._adminDashboard.scriptForm.image_url = url || '';
                };
                document.addEventListener('script-image-picked', e => {
                    this.scriptForm.image_url = e.detail.url;
                });

                // Bridge for per-script video uploader
                window.setScriptVideo = (url) => {
                    this.scriptForm.preview_video_url = url;
                };
                // Bridge for per-script PDF uploader
                window.setScriptPdf = (url) => {
                    this.scriptForm.script_pdf_url = url;
                };
                
                // Lock/unlock body scroll when modal opens/closes
                this.$watch('showScriptModal', (isOpen) => {
                    if (isOpen) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
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
            
            // ── PLAYLIST MANAGEMENT ──────────────────────────────────────

            async loadPlaylists() {
                this.loadingPlaylists = true;
                try {
                    const res = await fetch('/api/admin/playlists');
                    const data = await res.json();
                    if (data.success) {
                        this.playlists = data.playlists || [];
                    } else {
                        this.showToast('Failed to load playlists: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to load playlists', 'error');
                }
                this.loadingPlaylists = false;
            },

            async loadPlaylistSettings() {
                try {
                    const res = await fetch('/api/admin/playlists/settings');
                    const data = await res.json();
                    if (data.success) {
                        this.playlistSettings.enabled = data.settings.youtube_playlist_enabled;
                        this.playlistSettings.perSeason = data.settings.youtube_playlist_per_season;
                    }
                } catch (e) {
                    console.error('Failed to load playlist settings', e);
                }
            },

            async togglePlaylistSetting(type) {
                const settingKey = type === 'enabled' ? 'youtube_playlist_enabled' : 'youtube_playlist_per_season';
                const newValue = type === 'enabled' ? !this.playlistSettings.enabled : !this.playlistSettings.perSeason;
                
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                formData.append(settingKey, newValue ? '1' : '0');
                
                try {
                    const res = await fetch('/api/admin/playlists/settings/update', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        if (type === 'enabled') {
                            this.playlistSettings.enabled = newValue;
                        } else {
                            this.playlistSettings.perSeason = newValue;
                        }
                        this.showToast('Playlist settings updated', 'success');
                    } else {
                        this.showToast('Failed to update settings: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to update playlist settings', 'error');
                }
            },

            async createDefaultPlaylists() {
                this.creatingPlaylists = true;
                this.showToast('Creating playlists on YouTube...', 'success');
                
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                
                try {
                    const res = await fetch('/api/admin/playlists/create-default', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(`Created ${data.created.length} playlist(s): ${data.created.join(', ')}`, 'success');
                        await this.loadPlaylists();
                    } else {
                        if (data.created && data.created.length > 0) {
                            this.showToast(`Partial success: ${data.created.join(', ')} created. Errors: ${data.errors.join(', ')}`, 'warning');
                            await this.loadPlaylists();
                        } else {
                            this.showToast('Failed to create playlists: ' + (data.error || data.errors?.join(', ') || 'Unknown error'), 'error');
                        }
                    }
                } catch (e) {
                    this.showToast('Failed to create playlists', 'error');
                }
                this.creatingPlaylists = false;
            },

            async organizeVideosIntoPlaylists() {
                this.organizingVideos = true;
                this.showToast('Organizing videos into playlists...', 'success');
                
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                
                try {
                    const res = await fetch('/api/admin/playlists/organize', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(`${data.organized} of ${data.total} video(s) organized into playlists`, 'success');
                        if (data.errors && data.errors.length > 0) {
                            console.warn('Some videos failed:', data.errors);
                        }
                    } else {
                        this.showToast('Failed to organize videos: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to organize videos', 'error');
                }
                this.organizingVideos = false;
            },

            async deletePlaylist(playlistId, playlistTitle) {
                if (!confirm(`Are you sure you want to delete "${playlistTitle}" from the database?\n\nNote: This only removes it from your database, not from YouTube.`)) {
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                
                try {
                    const res = await fetch(`/api/admin/playlists/${playlistId}/delete`, { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Playlist deleted successfully', 'success');
                        // Remove from local array
                        this.playlists = this.playlists.filter(p => p.id !== playlistId);
                    } else {
                        this.showToast('Failed to delete playlist: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete playlist', 'error');
                }
            },

            async deleteAllPlaylists() {
                if (!confirm(`Are you sure you want to delete ALL ${this.playlists.length} playlist(s) from the database?\n\nNote: This only removes them from your database, not from YouTube.`)) {
                    return;
                }

                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                
                try {
                    const res = await fetch('/api/admin/playlists/delete-all', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(`${data.deleted} playlist(s) deleted successfully`, 'success');
                        this.playlists = [];
                    } else {
                        this.showToast('Failed to delete playlists: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to delete playlists', 'error');
                }
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

            // Submission player instances
            submissionPlayer1: null,
            submissionPlayer2: null,

            async viewSubmission(sub) {
                // Set submission immediately with basic data
                this.viewingSubmission = sub;
                this.submissionAdminNotes = sub.admin_notes || '';
                
                // Fetch full details with AI feedback JSON in background
                if (sub.video_id || sub.video_id_2) {
                    try {
                        const res = await fetch('/api/admin/submissions/' + sub.id);
                        const data = await res.json();
                        if (data.success && data.submission) {
                            // Update with full AI feedback data
                            this.viewingSubmission = data.submission;
                        }
                    } catch (e) {
                        console.error('Failed to load AI feedback', e);
                    }
                }
                
                // Initialize Plyr after DOM updates
                this.$nextTick(() => {
                    setTimeout(() => {
                        // Initialize player 1 (dialog or single video)
                        if (sub.file_path) {
                            const player1El = document.getElementById('submission-player-' + sub.id);
                            if (player1El && typeof Plyr !== 'undefined') {
                                if (this.submissionPlayer1) {
                                    this.submissionPlayer1.destroy();
                                }
                                this.submissionPlayer1 = new Plyr(player1El, {
                                    controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
                                    ratio: '16:9'
                                });
                            }
                        }
                        
                        // Initialize player 2 (song video for dual)
                        if (sub.file_path_2) {
                            const player2El = document.getElementById('submission-player-2-' + sub.id);
                            if (player2El && typeof Plyr !== 'undefined') {
                                if (this.submissionPlayer2) {
                                    this.submissionPlayer2.destroy();
                                }
                                this.submissionPlayer2 = new Plyr(player2El, {
                                    controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
                                    ratio: '16:9'
                                });
                            }
                        }
                    }, 150);
                });
            },
            
            closeSubmission() {
                // Stop and destroy players
                if (this.submissionPlayer1) {
                    this.submissionPlayer1.pause();
                    this.submissionPlayer1.destroy();
                    this.submissionPlayer1 = null;
                }
                if (this.submissionPlayer2) {
                    this.submissionPlayer2.pause();
                    this.submissionPlayer2.destroy();
                    this.submissionPlayer2 = null;
                }
                this.viewingSubmission = null;
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
                        
                        // Silent refresh videos list to sync Overview page
                        this.silentRefreshVideos();
                    }
                } catch(e) { console.error('Submission status update failed', e); }
            },
            
            // Silent refresh videos (no page reload, just updates data)
            async silentRefreshVideos() {
                try {
                    const res = await fetch('/api/admin/videos/refresh');
                    const data = await res.json();
                    if (data.success && data.videos) {
                        this.videos = data.videos;
                    }
                } catch(e) {
                    console.error('Silent refresh failed', e);
                }
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

            // Bulk delete functions
            toggleSubmissionSelection(id, checked) {
                if (checked) {
                    if (!this.selectedSubmissions.includes(id)) {
                        this.selectedSubmissions.push(id);
                    }
                } else {
                    this.selectedSubmissions = this.selectedSubmissions.filter(sid => sid !== id);
                }
            },
            
            toggleAllSubmissions(checked) {
                if (checked) {
                    this.selectedSubmissions = this.filteredSubmissions.map(s => s.id);
                } else {
                    this.selectedSubmissions = [];
                }
            },
            
            openDeleteConfirm(id = null) {
                if (id) {
                    // Single delete
                    this.deleteSubmissionIds = [id];
                } else {
                    // Bulk delete
                    this.deleteSubmissionIds = [...this.selectedSubmissions];
                }
                this.deleteConfirmOpen = true;
            },
            
            async confirmDeleteSubmissions() {
                const fd = new FormData();
                fd.append('csrf_token', this.csrf);
                
                for (const id of this.deleteSubmissionIds) {
                    try {
                        const res = await fetch('/api/admin/submissions/'+id+'/delete', {method:'POST', body: fd});
                        const data = await res.json();
                        if (data.success) {
                            this.submissions = this.submissions.filter(s => s.id !== id);
                            this.selectedSubmissions = this.selectedSubmissions.filter(sid => sid !== id);
                        }
                    } catch(e) { 
                        console.error('Delete submission failed', e); 
                    }
                }
                
                // Recalculate counts
                const counts = {new:0, reviewed:0, shortlisted:0, rejected:0};
                this.submissions.forEach(s => { 
                    if(counts[s.status]!==undefined) counts[s.status]++; 
                });
                this.submissionCounts = counts;
                this.submissionTotal = this.submissions.length;
                
                // Re-filter to update display
                this.filterSubmissions();
                
                // Close modal and reset
                this.deleteConfirmOpen = false;
                this.deleteSubmissionIds = [];
                this.selectedSubmissions = [];
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
                    const data = await res.json();
                    
                    if (!res.ok) {
                        throw new Error(data.error || 'Request failed');
                    }
                    
                    this.modalOpen = false;
                    
                    // Show detailed YouTube result if available
                    if (this.modalAction === 'approve' && data.youtube) {
                        if (data.youtube.error) {
                            this.showToast('Video approved but YouTube upload failed: ' + data.youtube.message, 'error');
                            console.error('YouTube error details:', data.youtube);
                        } else if (data.youtube.waiting) {
                            this.showToast('Video approved. ' + data.youtube.message, 'success');
                        } else if (data.youtube.paused) {
                            this.showToast('Video approved. ' + data.youtube.message, 'success');
                        } else if (data.youtube.video1 && data.youtube.video2) {
                            this.showToast('Video approved! Both dual videos uploaded to YouTube', 'success');
                        } else {
                            this.showToast('Video approved and uploaded to YouTube', 'success');
                        }
                    } else {
                        this.showToast(this.modalAction === 'approve' ? 'Video approved' : 'Video rejected', 'success');
                    }
                    
                    // Silent refresh instead of page reload
                    await this.silentRefreshVideos();
                } catch (e) {
                    console.error('Action failed:', e);
                    this.showToast('Action failed: ' + e.message, 'error');
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
                    title:             sc.title,
                    content:           sc.content,
                    category:          sc.category,
                    difficulty:        sc.difficulty,
                    duration_hint:     sc.duration_hint      || '',
                    audition_type:     sc.audition_type      || '',
                    image_url:         sc.image_url          || '',
                    preview_video_url: sc.preview_video_url  || '',
                    script_pdf_url:    sc.script_pdf_url     || '',
                    tune_youtube_url:  sc.tune_youtube_url   || '',
                    rules:             sc.rules              || '',
                };
                this._loadSongEntriesFromForm();
                // Sync uploader previews via global bridge
                if (typeof window.setScriptVideo === 'function') window.setScriptVideo(sc.preview_video_url || '');
                if (typeof window.setScriptPdf   === 'function') window.setScriptPdf(sc.script_pdf_url || '');
                if (typeof window.setScriptImage === 'function') window.setScriptImage(sc.image_url || '');
            },
            
            cancelEditScript() {
                this.editingScript = null;
                this.scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: 'Dialog Audition', image_url: '', preview_video_url: '', script_pdf_url: '', tune_youtube_url: '', tune_youtube_url_1: '', tune_youtube_url_2: '', tune_youtube_url_3: '', rules: '' };
                this.songEntries = [{ label: '', url: '' }];
                if (typeof window.setScriptVideo === 'function') window.setScriptVideo('');
                if (typeof window.setScriptPdf   === 'function') window.setScriptPdf('');
            },

            // Song URL list helpers — use reactive songEntries array directly
            _syncSongEntriesToForm() {
                this.scriptForm.tune_youtube_url = this.songEntries
                    .map(e => (e.label.trim() ? e.label.trim() + '|' : '') + e.url.trim())
                    .filter(s => s.replace('|','').trim().length > 0)
                    .join('\n');
            },
            _loadSongEntriesFromForm() {
                const raw = this.scriptForm.tune_youtube_url || '';
                const lines = raw.split('\n').map(s => s.trim()).filter(s => s.length > 0);
                this.songEntries = lines.length > 0
                    ? lines.map(line => {
                        const sep = line.indexOf('|');
                        if (sep === -1) return { label: '', url: line };
                        return { label: line.substring(0, sep), url: line.substring(sep + 1) };
                      })
                    : [{ label: '', url: '' }];
            },
            songUrlList() { return this.songEntries; },
            updateSongUrl(idx, val) {
                this.songEntries[idx].url = val;
                this._syncSongEntriesToForm();
            },
            updateSongLabel(idx, val) {
                this.songEntries[idx].label = val;
                this._syncSongEntriesToForm();
            },
            addSongUrl() {
                this.songEntries.push({ label: '', url: '' });
                this._syncSongEntriesToForm();
            },
            removeSongUrl(idx) {
                this.songEntries.splice(idx, 1);
                if (this.songEntries.length === 0) this.songEntries.push({ label: '', url: '' });
                this._syncSongEntriesToForm();
            },
            
            async createScript() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                // Always read from _adminDashboard to get latest uploader values
                const form = (window._adminDashboard && window._adminDashboard.scriptForm) || this.scriptForm;
                // Strip blank lines from tune_youtube_url before sending
                if (form.tune_youtube_url) {
                    form.tune_youtube_url = form.tune_youtube_url.split('\n').map(s => s.trim()).filter(s => s.replace('|','').trim().length > 0).join('\n');
                }
                // tune_youtube_url is already newline-separated (written directly by the dynamic list)
                Object.keys(form).forEach(k => {
                    if (k === 'tune_youtube_url_1' || k === 'tune_youtube_url_2' || k === 'tune_youtube_url_3') return;
                    formData.append(k, form[k] ?? '');
                });
                try {
                    const res  = await fetch('/api/admin/scripts/create', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Script created successfully');
                        // Add to local array without reload
                        const newScript = {
                            id:                data.id,
                            title:             this.scriptForm.title,
                            content:           this.scriptForm.content,
                            category:          this.scriptForm.category,
                            difficulty:        this.scriptForm.difficulty,
                            duration_hint:     this.scriptForm.duration_hint,
                            audition_type:     this.scriptForm.audition_type,
                            image_url:         this.scriptForm.image_url,
                            preview_video_url: this.scriptForm.preview_video_url,
                            script_pdf_url:    this.scriptForm.script_pdf_url,
                            tune_youtube_url:  this.scriptForm.tune_youtube_url,
                            rules:             this.scriptForm.rules,
                            is_active:         1,
                        };
                        this.scripts.push(newScript);
                        this.scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: 'Dialog Audition', image_url: '', preview_video_url: '', script_pdf_url: '', tune_youtube_url: '', tune_youtube_url_1: '', tune_youtube_url_2: '', tune_youtube_url_3: '', rules: '' };
                        this.songEntries = [{ label: '', url: '' }];
                        if (typeof window.setScriptVideo === 'function') window.setScriptVideo('');
                        if (typeof window.setScriptPdf   === 'function') window.setScriptPdf('');
                        if (typeof window.setScriptImage === 'function') window.setScriptImage('');
                        this.showScriptModal = false;
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Failed', 'error');
                    }
                } catch (e) {
                    console.error('[FP3] createScript error:', e);
                    this.showToast('Failed to create script', 'error');
                }
            },

            async updateScript() {
                const formData = new FormData();
                formData.append('csrf_token', this.csrf);
                // Always read from _adminDashboard to get latest uploader values
                const form = (window._adminDashboard && window._adminDashboard.scriptForm) || this.scriptForm;
                // Strip blank lines from tune_youtube_url before sending
                if (form.tune_youtube_url) {
                    form.tune_youtube_url = form.tune_youtube_url.split('\n').map(s => s.trim()).filter(s => s.replace('|','').trim().length > 0).join('\n');
                }
                // tune_youtube_url is already newline-separated (written directly by the dynamic list)
                Object.keys(form).forEach(k => {
                    if (k === 'tune_youtube_url_1' || k === 'tune_youtube_url_2' || k === 'tune_youtube_url_3') return;
                    formData.append(k, form[k] ?? '');
                });
                try {
                    const res  = await fetch('/api/admin/scripts/update/' + this.editingScript, { method: 'POST', body: formData });
                    const text = await res.text();
                    let data;
                    try { data = JSON.parse(text); } catch(pe) { data = { error: 'Server error: ' + text.substring(0, 200) }; }

                    if (data.success) {
                        this.showToast('Script updated successfully');
                        // Update in local array — no page reload needed
                        const idx = this.scripts.findIndex(s => s.id === this.editingScript);
                        if (idx !== -1) {
                            this.scripts[idx] = {
                                ...this.scripts[idx],
                                title:             this.scriptForm.title,
                                content:           this.scriptForm.content,
                                category:          this.scriptForm.category,
                                difficulty:        this.scriptForm.difficulty,
                                duration_hint:     this.scriptForm.duration_hint,
                                audition_type:     this.scriptForm.audition_type,
                                image_url:         this.scriptForm.image_url,
                                preview_video_url: this.scriptForm.preview_video_url,
                                script_pdf_url:    this.scriptForm.script_pdf_url,
                                tune_youtube_url:  this.scriptForm.tune_youtube_url,
                                rules:             this.scriptForm.rules,
                            };
                            // Trigger Alpine reactivity
                            this.scripts = [...this.scripts];
                        }
                        this.editingScript = null;
                        this.scriptForm = { title: '', content: '', category: 'actor', difficulty: 'beginner', duration_hint: '', audition_type: 'Dialog Audition', image_url: '', preview_video_url: '', script_pdf_url: '', tune_youtube_url: '', tune_youtube_url_1: '', tune_youtube_url_2: '', tune_youtube_url_3: '', rules: '' };
                        this.songEntries = [{ label: '', url: '' }];
                        if (typeof window.setScriptVideo === 'function') window.setScriptVideo('');
                        if (typeof window.setScriptPdf   === 'function') window.setScriptPdf('');
                        if (typeof window.setScriptImage === 'function') window.setScriptImage('');
                        this.showScriptModal = false;
                    } else {
                        this.showToast(data.errors?.join(', ') || data.error || 'Update failed', 'error');
                    }
                } catch (e) {
                    console.error('[FP3] updateScript error:', e);
                    this.showToast('Network error', 'error');
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
            
            // Process AI Queue manually
            async processAIQueue() {
                this.processingAI = true;
                this.showToast('Starting AI processing...', 'success');
                
                try {
                    const res = await fetch('/cron/ai-process.php?key=<?= $_ENV["CRON_SECRET_KEY"] ?? "dev" ?>&limit=10');
                    const text = await res.text();
                    
                    if (res.ok) {
                        // Refresh page to show updated videos
                        this.showToast('AI processing completed! Refreshing...', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.showToast('AI processing failed: ' + text.substring(0, 100), 'error');
                        this.processingAI = false;
                    }
                } catch (e) {
                    this.showToast('Failed to trigger AI processing: ' + e.message, 'error');
                    this.processingAI = false;
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
    
    // Migration runner component
    function migrationRunner() {
        return {
            running: false,
            result: null,
            run() {
                this.running = true;
                this.result = null;
                const csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fetch('/api/admin/run-migrations', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(d) { this.result = d; this.running = false; }.bind(this))
                    .catch(function(e) { this.result = { error: e.message }; this.running = false; }.bind(this));
            }
        };
    }

    // Google Settings Component
    function googleSettings() {        return {
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
            // Media browser state
            showMediaBrowser: false,
            mediaImages: [],
            mediaLoading: false,
            mediaSearch: '',

            get filteredMedia() {
                if (!this.mediaSearch) return this.mediaImages;
                const search = this.mediaSearch.toLowerCase();
                return this.mediaImages.filter(img => 
                    img.name.toLowerCase().includes(search) || 
                    img.url.toLowerCase().includes(search)
                );
            },

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
            async openMediaBrowser() {
                this.showMediaBrowser = true;
                await this.loadMediaLibrary();
            },
            closeMediaBrowser() {
                this.showMediaBrowser = false;
                this.mediaSearch = '';
            },
            async loadMediaLibrary() {
                this.mediaLoading = true;
                try {
                    const res = await fetch('/api/admin/media/images');
                    const data = await res.json();
                    if (data.success) {
                        this.mediaImages = data.images;
                    } else {
                        console.error('Failed to load media library:', data.error);
                    }
                } catch (e) {
                    console.error('Error loading media library:', e);
                }
                this.mediaLoading = false;
            },
            selectFromLibrary(imageUrl) {
                this.preview = imageUrl;
                this.filename = imageUrl.split('/').pop();
                // Update the parent form
                const ev = new CustomEvent('image-uploaded', {
                    detail: { field: this.fieldKey, url: imageUrl }
                });
                document.dispatchEvent(ev);
                this.closeMediaBrowser();
            },
            upload(file) {
                const allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif','image/svg+xml','image/bmp'];
                if (!allowed.includes(file.type)) {
                    this.uploadError = 'Only JPG, PNG, WebP, GIF, SVG, or BMP accepted.';
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
                const allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif','image/svg+xml','image/bmp'];
                if (!allowed.includes(file.type)) {
                    this.uploadError = 'Only JPG, PNG, WebP, GIF, SVG, or BMP accepted.';
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
                            // Update parent scriptForm via global bridge
                            if (typeof window.setScriptImage === 'function') {
                                window.setScriptImage(r.url);
                            }
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
                if (typeof window.setScriptImage === 'function') {
                    window.setScriptImage(url);
                }
            },

            async deleteGalleryImage(url) {
                if (!confirm('Permanently delete this image from server?')) return;
                
                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('url', url);
                    
                    const res = await fetch('/api/admin/media/delete-image', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        // Remove from gallery
                        this.galleryImages = this.galleryImages.filter(img => img.url !== url);
                        // Clear selection if deleted image was selected
                        if (window._adminDashboard && window._adminDashboard.scriptForm.image_url === url) {
                            window._adminDashboard.scriptForm.image_url = '';
                        }
                    } else {
                        alert('Failed to delete: ' + (data.error || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Network error while deleting image');
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

    // PDF uploader component for script/lyrics PDF fields
    function pdfUploader(fieldKey, initialUrl) {
        return {
            fieldKey,
            preview:     initialUrl || null,
            filename:    initialUrl ? initialUrl.split('/').pop() : '',
            dragging:    false,
            uploading:   false,
            progress:    0,
            uploadError: '',

            onDrop(e) { this.dragging=false; const f=e.dataTransfer?.files?.[0]; if(f) this.upload(f); },
            onFile(e) { const f=e.target.files?.[0]; if(f) this.upload(f); },
            clearPdf() {
                this.preview=null; this.filename=''; this.uploadError='';
                document.dispatchEvent(new CustomEvent('image-cleared',{detail:{field:this.fieldKey}}));
            },
            upload(file) {
                if(file.type !== 'application/pdf' && !file.name.endsWith('.pdf')) {
                    this.uploadError='Only PDF files accepted.'; return;
                }
                if(file.size > 20*1024*1024) { this.uploadError='PDF must be under 20 MB.'; return; }
                this.uploading=true; this.progress=0; this.uploadError='';
                const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
                const fd=new FormData();
                fd.append('csrf_token',csrf); fd.append('field',this.fieldKey); fd.append('file',file);
                const xhr=new XMLHttpRequest();
                xhr.upload.onprogress=e=>{if(e.lengthComputable)this.progress=Math.round(e.loaded/e.total*100);};
                xhr.onload=()=>{
                    this.uploading=false;
                    try{const r=JSON.parse(xhr.responseText);
                        if(r.success){this.preview=r.url;this.filename=r.url.split('/').pop();
                            document.dispatchEvent(new CustomEvent('image-uploaded',{detail:{field:this.fieldKey,url:r.url}}));
                        }else{this.uploadError=r.error||'Upload failed.';}
                    }catch(e){this.uploadError='Server error.';}
                };
                xhr.onerror=()=>{this.uploading=false;this.uploadError='Network error.';};
                xhr.open('POST','/api/admin/settings/upload-image'); xhr.send(fd);
            }
        };
    }

    // Per-script video uploader — uploads to /api/admin/media/upload-script-file, bridges to scriptForm.preview_video_url
    function scriptVideoUploader() {
        return {
            mode: 'upload', // 'upload' | 'youtube'
            preview: null, filename: '',
            ytUrl: '',
            dragging: false, uploading: false, progress: 0, uploadError: '',
            isYoutube(url) {
                if (!url) return false;
                return /youtu(\.be|be\.com)/.test(url);
            },
            ytEmbedUrl(url) {
                if (!url) return '';
                var m = url.match(/youtu\.be\/([^?&#]+)/);
                if (m) return 'https://www.youtube.com/embed/' + m[1];
                m = url.match(/[?&]v=([^&#]+)/);
                if (m) return 'https://www.youtube.com/embed/' + m[1];
                m = url.match(/\/shorts\/([^?&#]+)/);
                if (m) return 'https://www.youtube.com/embed/' + m[1];
                return url;
            },
            onYtInput() {
                // Always write current ytUrl to scriptForm so it gets saved
                if (window._adminDashboard) {
                    window._adminDashboard.scriptForm.preview_video_url = this.ytUrl || '';
                }
                // Clear any uploaded file when a YouTube URL is set
                if (this.ytUrl) {
                    this.preview = null; this.filename = '';
                }
            },
            clearYt() {
                this.ytUrl = '';
                if (window._adminDashboard) window._adminDashboard.scriptForm.preview_video_url = '';
            },
            init() {
                window.setScriptVideo = (url) => {
                    this.preview = url && !this.isYoutube(url) ? url : null;
                    this.filename = url && !this.isYoutube(url) ? url.split('/').pop() : '';
                    this.ytUrl = url && this.isYoutube(url) ? url : '';
                    this.mode = url && this.isYoutube(url) ? 'youtube' : 'upload';
                    if (window._adminDashboard) window._adminDashboard.scriptForm.preview_video_url = url || '';
                };
            },
            onDrop(e){ this.dragging=false; const f=e.dataTransfer?.files?.[0]; if(f) this.upload(f); },
            onFile(e){ const f=e.target.files?.[0]; if(f) this.upload(f); },
            clear(){
                this.preview=null; this.filename=''; this.uploadError='';
                if (window._adminDashboard) window._adminDashboard.scriptForm.preview_video_url = '';
            },
            upload(file){
                const allowed=['video/mp4','video/quicktime','video/webm'];
                const ext=file.name.split('.').pop().toLowerCase();
                if(!allowed.includes(file.type)&&!['mp4','mov','webm'].includes(ext)){
                    this.uploadError='Only MP4, MOV or WEBM accepted.'; return;
                }
                if(file.size>500*1024*1024){this.uploadError='Video must be under 500 MB.'; return;}
                // Clear YouTube URL when uploading a file
                this.ytUrl = '';
                this.uploading=true; this.progress=0; this.uploadError=''; this.preview='uploading';
                const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
                const fd=new FormData();
                fd.append('csrf_token',csrf); fd.append('file',file);
                const xhr=new XMLHttpRequest();
                xhr.upload.onprogress=e=>{if(e.lengthComputable)this.progress=Math.round(e.loaded/e.total*100);};
                xhr.onload=()=>{
                    this.uploading=false;
                    try{
                        const r=JSON.parse(xhr.responseText);
                        if(r.success){
                            this.preview=r.url; this.filename=r.url.split('/').pop();
                            if (window._adminDashboard) window._adminDashboard.scriptForm.preview_video_url = r.url;
                        }else{this.uploadError=r.error||'Upload failed.'; this.preview=null;}
                    }catch(e){this.uploadError='Server error.'; this.preview=null;}
                };
                xhr.onerror=()=>{this.uploading=false; this.preview=null; this.uploadError='Network error.';};
                xhr.open('POST','/api/admin/media/upload-script-file'); xhr.send(fd);
            }
        };
    }

    // Per-script PDF uploader — uploads to /api/admin/media/upload-script-file, bridges to scriptForm.script_pdf_url
    function scriptPdfUploader() {
        return {
            preview: null, filename: '', dragging: false, uploading: false, progress: 0, uploadError: '',
            init() {
                window.setScriptPdf = (url) => {
                    this.preview  = url || null;
                    this.filename = url ? url.split('/').pop() : '';
                    if (window._adminDashboard) window._adminDashboard.scriptForm.script_pdf_url = url || '';
                };
            },
            onDrop(e){ this.dragging=false; const f=e.dataTransfer?.files?.[0]; if(f) this.upload(f); },
            onFile(e){ const f=e.target.files?.[0]; if(f) this.upload(f); },
            clear(){
                this.preview=null; this.filename=''; this.uploadError='';
                if (window._adminDashboard) window._adminDashboard.scriptForm.script_pdf_url = '';
            },
            upload(file){
                if(file.type!=='application/pdf'&&!file.name.endsWith('.pdf')){
                    this.uploadError='Only PDF files accepted.'; return;
                }
                if(file.size>20*1024*1024){this.uploadError='PDF must be under 20 MB.'; return;}
                this.uploading=true; this.progress=0; this.uploadError='';
                const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
                const fd=new FormData();
                fd.append('csrf_token',csrf); fd.append('file',file);
                const xhr=new XMLHttpRequest();
                xhr.upload.onprogress=e=>{if(e.lengthComputable)this.progress=Math.round(e.loaded/e.total*100);};
                xhr.onload=()=>{
                    this.uploading=false;
                    try{
                        const r=JSON.parse(xhr.responseText);
                        if(r.success){
                            this.preview=r.url; this.filename=r.url.split('/').pop();
                            if (window._adminDashboard) window._adminDashboard.scriptForm.script_pdf_url = r.url;
                        }else{this.uploadError=r.error||'Upload failed.';}
                    }catch(e){this.uploadError='Server error.';}
                };
                xhr.onerror=()=>{this.uploading=false; this.uploadError='Network error.';};
                xhr.open('POST','/api/admin/media/upload-script-file'); xhr.send(fd);
            }
        };
    }

    // Video uploader component for trailer fields (global settings)
    function videoUploader(fieldKey, initialUrl) {
        // Detect if initial value is a YouTube URL
        const isYT = u => /youtu(\.be|be\.com)/i.test(u || '');
        return {
            fieldKey,
            preview:     (!initialUrl || isYT(initialUrl)) ? null : (initialUrl || null),
            filename:    (!initialUrl || isYT(initialUrl)) ? '' : (initialUrl.split('/').pop()),
            ytUrl:       isYT(initialUrl) ? initialUrl : '',
            trailerTab:  isYT(initialUrl) ? 'yt' : 'upload',
            dragging:    false,
            uploading:   false,
            progress:    0,
            uploadError: '',
            ytSaved:     false,

            setYtUrl(val) {
                this.ytUrl = val;
                this._saveYtToDB(val);
            },
            saveYtUrl() {
                // Update the main form with this value
                document.dispatchEvent(new CustomEvent('image-uploaded', {
                    detail: { field: this.fieldKey, url: this.ytUrl }
                }));
                // Trigger the main save function
                this._saveViaBatchEndpoint();
            },
            async _saveViaBatchEndpoint() {
                const event = new CustomEvent('trigger-save-all');
                window.dispatchEvent(event);
                // Show saved feedback
                this.ytSaved = true;
                setTimeout(() => this.ytSaved = false, 2000);
            },
            syncYtToForm() {
                // Keep parent form in sync so Save All Settings includes this value
                document.dispatchEvent(new CustomEvent('image-uploaded', {
                    detail: { field: this.fieldKey, url: this.ytUrl }
                }));
            },
            async _saveYtToDB(val) {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('key', this.fieldKey);
                fd.append('value', val);
                const res = await fetch('/api/admin/settings/landing', { method: 'POST', body: fd, credentials: 'same-origin' });
                if (res.ok) {
                    // Also update parent form so Save All Settings picks it up
                    document.dispatchEvent(new CustomEvent('image-uploaded', {
                        detail: { field: this.fieldKey, url: val }
                    }));
                    this.ytSaved = true;
                    setTimeout(() => this.ytSaved = false, 2000);
                }
            },

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
                this.ytUrl    = '';
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
                site_logo_height:      '<?= addslashes(htmlspecialchars($settingsModel->get('site_logo_height','44'))) ?>',
                landing_header_content: <?= json_encode($settingsModel->get('landing_header_content','')) ?>,
                header_menu_item_1_text: <?= json_encode($settingsModel->get('header_menu_item_1_text','About')) ?>,
                header_menu_item_1_page:  <?= json_encode($settingsModel->get('header_menu_item_1_page','about')) ?>,
                header_menu_item_1_order: <?= json_encode($settingsModel->get('header_menu_item_1_order','1')) ?>,
                header_menu_item_2_text: <?= json_encode($settingsModel->get('header_menu_item_2_text','Writers')) ?>,
                header_menu_item_2_page:  <?= json_encode($settingsModel->get('header_menu_item_2_page','writer')) ?>,
                header_menu_item_2_order: <?= json_encode($settingsModel->get('header_menu_item_2_order','2')) ?>,
                header_menu_item_3_text: <?= json_encode($settingsModel->get('header_menu_item_3_text','Directors')) ?>,
                header_menu_item_3_page:  <?= json_encode($settingsModel->get('header_menu_item_3_page','director')) ?>,
                header_menu_item_3_order: <?= json_encode($settingsModel->get('header_menu_item_3_order','3')) ?>,
                header_menu_item_4_text: <?= json_encode($settingsModel->get('header_menu_item_4_text','Actors')) ?>,
                header_menu_item_4_page:  <?= json_encode($settingsModel->get('header_menu_item_4_page','actor')) ?>,
                header_menu_item_4_order: <?= json_encode($settingsModel->get('header_menu_item_4_order','4')) ?>,
                footer_menu_item_1_text: <?= json_encode($settingsModel->get('footer_menu_item_1_text','About')) ?>,
                footer_menu_item_1_page:  <?= json_encode($settingsModel->get('footer_menu_item_1_page','about')) ?>,
                footer_menu_item_1_order: <?= json_encode($settingsModel->get('footer_menu_item_1_order','1')) ?>,
                footer_menu_item_2_text: <?= json_encode($settingsModel->get('footer_menu_item_2_text','Writers')) ?>,
                footer_menu_item_2_page:  <?= json_encode($settingsModel->get('footer_menu_item_2_page','writer')) ?>,
                footer_menu_item_2_order: <?= json_encode($settingsModel->get('footer_menu_item_2_order','2')) ?>,
                footer_menu_item_3_text: <?= json_encode($settingsModel->get('footer_menu_item_3_text','Directors')) ?>,
                footer_menu_item_3_page:  <?= json_encode($settingsModel->get('footer_menu_item_3_page','director')) ?>,
                footer_menu_item_3_order: <?= json_encode($settingsModel->get('footer_menu_item_3_order','3')) ?>,
                footer_menu_item_4_text: <?= json_encode($settingsModel->get('footer_menu_item_4_text','Actors')) ?>,
                footer_menu_item_4_page:  <?= json_encode($settingsModel->get('footer_menu_item_4_page','actor')) ?>,
                footer_menu_item_4_order: <?= json_encode($settingsModel->get('footer_menu_item_4_order','4')) ?>,
                landing_headline:      <?= json_encode($settingsModel->get('landing_headline','NO FACE. NO CONNECTIONS. JUST TALENT.')) ?>,
                landing_hero_subheading: <?= json_encode($settingsModel->get('landing_hero_subheading','KHATAA OFFICIAL TEASER')) ?>,
                landing_hero_tagline:    <?= json_encode($settingsModel->get('landing_hero_tagline','10 FILMS. 10 RASAS. 10 EMOTIONS. ONE UNIVERSE.')) ?>,
                site_tagline:          <?= json_encode($settingsModel->get('site_tagline',"India's first anonymous film competition — no face, no connections, just raw talent.")) ?>,
                landing_roles_heading:    <?= json_encode($settingsModel->get('landing_roles_heading','Become a Star in 3 Clicks')) ?>,
                landing_roles_subheading: <?= json_encode($settingsModel->get('landing_roles_subheading',"Pick your role. Shoot your video. Submit. That's it.")) ?>,
                landing_footer_content: <?= json_encode($settingsModel->get('landing_footer_content','© 2024 Faceless Pictures. All rights reserved.')) ?>,
                landing_poster_url:    '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_url',''))) ?>',
                landing_poster_title:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_title','Faceless Pictures 3'))) ?>',
                landing_poster_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_subtitle',''))) ?>',
                landing_trailer_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer_url',''))) ?>',
                landing_poster2_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_url',''))) ?>',
                landing_poster2_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_title',''))) ?>',
                landing_poster2_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_subtitle',''))) ?>',
                landing_trailer2_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer2_url',''))) ?>',
                landing_poster3_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_url',''))) ?>',
                landing_poster3_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_title',''))) ?>',
                landing_poster3_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_subtitle',''))) ?>',
                landing_trailer3_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer3_url',''))) ?>',
                landing_poster4_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_url',''))) ?>',
                landing_poster4_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_title',''))) ?>',
                landing_poster4_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_subtitle',''))) ?>',
                landing_trailer4_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer4_url',''))) ?>',
                landing_poster5_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_url',''))) ?>',
                landing_poster5_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_title',''))) ?>',
                landing_poster5_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_subtitle',''))) ?>',
                landing_trailer5_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer5_url',''))) ?>',
                landing_poster6_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_url',''))) ?>',
                landing_poster6_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_title',''))) ?>',
                landing_poster6_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_subtitle',''))) ?>',
                landing_trailer6_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer6_url',''))) ?>',
                landing_poster6_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_btn_label',''))) ?>',
                landing_poster7_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster7_url',''))) ?>',
                landing_poster7_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster7_title',''))) ?>',
                landing_poster7_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster7_subtitle',''))) ?>',
                landing_trailer7_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer7_url',''))) ?>',
                landing_poster7_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster7_btn_label',''))) ?>',
                landing_poster8_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster8_url',''))) ?>',
                landing_poster8_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster8_title',''))) ?>',
                landing_poster8_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster8_subtitle',''))) ?>',
                landing_trailer8_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer8_url',''))) ?>',
                landing_poster8_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster8_btn_label',''))) ?>',
                landing_poster9_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster9_url',''))) ?>',
                landing_poster9_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster9_title',''))) ?>',
                landing_poster9_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster9_subtitle',''))) ?>',
                landing_trailer9_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer9_url',''))) ?>',
                landing_poster9_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster9_btn_label',''))) ?>',
                landing_poster10_url:   '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster10_url',''))) ?>',
                landing_poster10_title: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster10_title',''))) ?>',
                landing_poster10_subtitle: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster10_subtitle',''))) ?>',
                landing_trailer10_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_trailer10_url',''))) ?>',
                landing_poster10_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster10_btn_label',''))) ?>',
                landing_poster_btn_label:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster_btn_label',''))) ?>',
                landing_poster2_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster2_btn_label',''))) ?>',
                landing_poster3_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster3_btn_label',''))) ?>',
                landing_poster4_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster4_btn_label',''))) ?>',
                landing_poster5_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster5_btn_label',''))) ?>',
                landing_poster6_btn_label: '<?= addslashes(htmlspecialchars($settingsModel->get('landing_poster6_btn_label',''))) ?>',
                landing_hero_trailer_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('landing_hero_trailer_url',''))) ?>',
                poster_section_heading:    <?= json_encode($settingsModel->get('poster_section_heading','RASAS REVEALED.')) ?>,
                poster_section_subtitle:   <?= json_encode($settingsModel->get('poster_section_subtitle','Each film is a rasa. Each rasa is a world. All connected to KHAATA.')) ?>,
                
                // Stats section
                stats_number_1: <?= json_encode($settingsModel->get('stats_number_1','10')) ?>,
                stats_label_1:  <?= json_encode($settingsModel->get('stats_label_1','FILMS')) ?>,
                stats_number_2: <?= json_encode($settingsModel->get('stats_number_2','100')) ?>,
                stats_label_2:  <?= json_encode($settingsModel->get('stats_label_2','SCENES')) ?>,
                stats_number_3: <?= json_encode($settingsModel->get('stats_number_3','30')) ?>,
                stats_label_3:  <?= json_encode($settingsModel->get('stats_label_3','DAYS')) ?>,
                stats_number_4: <?= json_encode($settingsModel->get('stats_number_4','20')) ?>,
                stats_label_4:  <?= json_encode($settingsModel->get('stats_label_4','ARTISTS')) ?>,
                stats_number_5: <?= json_encode($settingsModel->get('stats_number_5','150')) ?>,
                stats_label_5:  <?= json_encode($settingsModel->get('stats_label_5','LIVES')) ?>,
                stats_line_1:   <?= json_encode($settingsModel->get('stats_line_1','Many talented people never get their first chance.')) ?>,
                stats_line_2:   <?= json_encode($settingsModel->get('stats_line_2','We are giving them one.')) ?>,
                stats_line_3:   <?= json_encode($settingsModel->get('stats_line_3','We don\'t just make films.')) ?>,
                stats_line_4:   <?= json_encode($settingsModel->get('stats_line_4','We open the door.')) ?>,
                stats_tagline:  <?= json_encode($settingsModel->get('stats_tagline','FACELESS TO STAR.')) ?>,
                
                landing_about_text:    <?= json_encode($settingsModel->get('landing_about_text',"Faceless Pictures is India's first anonymous film competition.")) ?>,
                manifesto_heading:     <?= json_encode($settingsModel->get('manifesto_heading','OUR MANIFESTO')) ?>,
                manifesto_subheading:  <?= json_encode($settingsModel->get('manifesto_subheading','What Faceless Pictures 3 stands for.')) ?>,
                manifesto_video1_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video1_url',''))) ?>',
                manifesto_video1_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video1_title',''))) ?>',
                manifesto_video2_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video2_url',''))) ?>',
                manifesto_video2_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video2_title',''))) ?>',
                manifesto_video3_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video3_url',''))) ?>',
                manifesto_video3_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video3_title',''))) ?>',
                manifesto_video4_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video4_url',''))) ?>',
                manifesto_video4_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video4_title',''))) ?>',
                manifesto_video5_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video5_url',''))) ?>',
                manifesto_video5_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video5_title',''))) ?>',
                manifesto_video6_url:  '<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video6_url',''))) ?>',
                manifesto_video6_title:'<?= addslashes(htmlspecialchars($settingsModel->get('manifesto_video6_title',''))) ?>',
                
                // About section
                about_section_label:   <?= json_encode($settingsModel->get('about_section_label','About')) ?>,
                about_section_heading: <?= json_encode($settingsModel->get('about_section_heading','WHAT IS FACELESS PICTURES?')) ?>,
                
                // Role cards
                role_writer_title:       <?= json_encode($settingsModel->get('role_writer_title','WRITER')) ?>,
                role_writer_icon:        <?= json_encode($settingsModel->get('role_writer_icon','✍️')) ?>,
                role_writer_description: <?= json_encode($settingsModel->get('role_writer_description','Read your script on camera.\nYour words. Your voice. One video.')) ?>,
                role_writer_badge1:      <?= json_encode($settingsModel->get('role_writer_badge1','Script Reading')) ?>,
                role_writer_badge2:      <?= json_encode($settingsModel->get('role_writer_badge2','')) ?>,
                role_writer_button_text: <?= json_encode($settingsModel->get('role_writer_button_text','Click Here →')) ?>,
                role_writer_button_url:  <?= json_encode($settingsModel->get('role_writer_button_url','/writer')) ?>,
                
                role_director_title:       <?= json_encode($settingsModel->get('role_director_title','DIRECTOR')) ?>,
                role_director_icon:        <?= json_encode($settingsModel->get('role_director_icon','🎬')) ?>,
                role_director_description: <?= json_encode($settingsModel->get('role_director_description','Shoot your scene your way.\nOne phone. One take. Your vision.')) ?>,
                role_director_badge1:      <?= json_encode($settingsModel->get('role_director_badge1','Scene Direction')) ?>,
                role_director_badge2:      <?= json_encode($settingsModel->get('role_director_badge2','Pitch')) ?>,
                role_director_button_text: <?= json_encode($settingsModel->get('role_director_button_text','Click Here →')) ?>,
                role_director_button_url:  <?= json_encode($settingsModel->get('role_director_button_url','/director')) ?>,
                
                role_actor_title:       <?= json_encode($settingsModel->get('role_actor_title','ACTOR')) ?>,
                role_actor_icon:        <?= json_encode($settingsModel->get('role_actor_icon','🎭')) ?>,
                role_actor_description: <?= json_encode($settingsModel->get('role_actor_description','Shoot your scene on camera.\nFace hidden. Talent only.')) ?>,
                role_actor_badge1:      <?= json_encode($settingsModel->get('role_actor_badge1','Dialogue')) ?>,
                role_actor_badge2:      <?= json_encode($settingsModel->get('role_actor_badge2','Song')) ?>,
                role_actor_button_text: <?= json_encode($settingsModel->get('role_actor_button_text','Click Here →')) ?>,
                role_actor_button_url:  <?= json_encode($settingsModel->get('role_actor_button_url','/actor')) ?>,
                
                // Marquee items
                marquee_item1:  <?= json_encode($settingsModel->get('marquee_item1','ACTORS')) ?>,
                marquee_item2:  <?= json_encode($settingsModel->get('marquee_item2','DIRECTORS')) ?>,
                marquee_item3:  <?= json_encode($settingsModel->get('marquee_item3','WRITERS')) ?>,
                marquee_item4:  <?= json_encode($settingsModel->get('marquee_item4','NO CONNECTIONS')) ?>,
                marquee_item5:  <?= json_encode($settingsModel->get('marquee_item5','ONE VIDEO')) ?>,
                marquee_item6:  <?= json_encode($settingsModel->get('marquee_item6','ONE CHANCE')) ?>,
                marquee_item7:  <?= json_encode($settingsModel->get('marquee_item7','NOW OPEN')) ?>,
                marquee_item8:  <?= json_encode($settingsModel->get('marquee_item8','NO FACE')) ?>,
                marquee_item9:  <?= json_encode($settingsModel->get('marquee_item9','JUST TALENT')) ?>,
                marquee_item10: <?= json_encode($settingsModel->get('marquee_item10','SUBMIT TODAY')) ?>,
                
                // Role page text settings (Writer, Director, Actor)
                writer_hero_label:       <?= json_encode($settingsModel->get('writer_hero_label','Submissions Now Open')) ?>,
                writer_hero_heading:     <?= json_encode($settingsModel->get('writer_hero_heading','WRITER SUBMISSIONS')) ?>,
                writer_hero_description: <?= json_encode($settingsModel->get('writer_hero_description','READ THE SCENE. WRITE THE NEXT PAGE. RECORD YOUR NARRATION. UPLOAD YOUR VIDEO.')) ?>,
                writer_step1_title:      <?= json_encode($settingsModel->get('writer_step1_title','WHAT WE GIVE')) ?>,
                writer_step1_text:       <?= json_encode($settingsModel->get('writer_step1_text','One page')) ?>,
                writer_step2_title:      <?= json_encode($settingsModel->get('writer_step2_title','WHAT YOU DO')) ?>,
                writer_step2_text:       <?= json_encode($settingsModel->get('writer_step2_text','Continue it. One more page only')) ?>,
                writer_step3_title:      <?= json_encode($settingsModel->get('writer_step3_title','SUBMIT')) ?>,
                writer_step3_text:       <?= json_encode($settingsModel->get('writer_step3_text','Your page PDF plus narration video')) ?>,
                writer_form_heading:     <?= json_encode($settingsModel->get('writer_form_heading','Ready to Write? Submit Your Continuation')) ?>,
                writer_form_description: <?= json_encode($settingsModel->get('writer_form_description','Read the given script, write what happens next, then record yourself narrating it on camera.')) ?>,
                // Writer submission messages
                writer_success_heading: <?= json_encode($settingsModel->get('writer_success_heading','WRITER SUBMISSION RECEIVED!')) ?>,
                writer_success_message: <?= json_encode($settingsModel->get('writer_success_message',"Your writer video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at your email.")) ?>,
                writer_success_pdf_button: <?= json_encode($settingsModel->get('writer_success_pdf_button','Download Writer Brief PDF')) ?>,
                writer_failure_heading: <?= json_encode($settingsModel->get('writer_failure_heading','SUBMISSION FAILED')) ?>,
                writer_failure_message: <?= json_encode($settingsModel->get('writer_failure_message',"We couldn't process your writer video. Please check your file and try again.")) ?>,
                writer_failure_retry_button: <?= json_encode($settingsModel->get('writer_failure_retry_button','Try Again')) ?>,
                
                director_hero_label:       <?= json_encode($settingsModel->get('director_hero_label','Auditions Now Open')) ?>,
                director_hero_heading:     <?= json_encode($settingsModel->get('director_hero_heading','DIRECTOR AUDITIONS')) ?>,
                director_hero_description: <?= json_encode($settingsModel->get('director_hero_description','CAST YOUR ACTOR. SHOOT YOUR SCENE. SHOW US YOUR VISION.')) ?>,
                director_step1_title:      <?= json_encode($settingsModel->get('director_step1_title','WHAT WE GIVE')) ?>,
                director_step1_text:       <?= json_encode($settingsModel->get('director_step1_text','Script and actor')) ?>,
                director_step2_title:      <?= json_encode($settingsModel->get('director_step2_title','WHAT YOU DO')) ?>,
                director_step2_text:       <?= json_encode($settingsModel->get('director_step2_text','Direct the scene')) ?>,
                director_step3_title:      <?= json_encode($settingsModel->get('director_step3_title','SUBMIT')) ?>,
                director_step3_text:       <?= json_encode($settingsModel->get('director_step3_text','Your scene video')) ?>,
                director_form_heading:     <?= json_encode($settingsModel->get('director_form_heading','Ready to Direct? Submit Your Scene')) ?>,
                director_form_description: <?= json_encode($settingsModel->get('director_form_description','Cast your actor, give them the script, shoot the scene, and upload your video.')) ?>,
                // Director submission messages
                director_success_heading: <?= json_encode($settingsModel->get('director_success_heading','DIRECTOR SUBMISSION RECEIVED!')) ?>,
                director_success_message: <?= json_encode($settingsModel->get('director_success_message',"Your director video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at your email.")) ?>,
                director_success_pdf_button: <?= json_encode($settingsModel->get('director_success_pdf_button','Download Director Brief PDF')) ?>,
                director_failure_heading: <?= json_encode($settingsModel->get('director_failure_heading','SUBMISSION FAILED')) ?>,
                director_failure_message: <?= json_encode($settingsModel->get('director_failure_message',"We couldn't process your director video. Please check your file and try again.")) ?>,
                director_failure_retry_button: <?= json_encode($settingsModel->get('director_failure_retry_button','Try Again')) ?>,
                
                actor_hero_label:       <?= json_encode($settingsModel->get('actor_hero_label','Auditions Now Open')) ?>,
                actor_hero_heading:     <?= json_encode($settingsModel->get('actor_hero_heading','ACTOR AUDITIONS')) ?>,
                actor_hero_description: <?= json_encode($settingsModel->get('actor_hero_description','Two auditions, one submission. Read the dialog brief, learn the song, then shoot both videos.')) ?>,
                actor_step1_title:      <?= json_encode($settingsModel->get('actor_step1_title','WHAT WE GIVE')) ?>,
                actor_step1_text:       <?= json_encode($settingsModel->get('actor_step1_text','Dialog brief and song')) ?>,
                actor_step2_title:      <?= json_encode($settingsModel->get('actor_step2_title','WHAT YOU DO')) ?>,
                actor_step2_text:       <?= json_encode($settingsModel->get('actor_step2_text','Perform both auditions')) ?>,
                actor_step3_title:      <?= json_encode($settingsModel->get('actor_step3_title','SUBMIT')) ?>,
                actor_step3_text:       <?= json_encode($settingsModel->get('actor_step3_text','Two audition videos')) ?>,
                actor_form_heading:     <?= json_encode($settingsModel->get('actor_form_heading','Ready to Perform? Submit Your Auditions')) ?>,
                actor_form_description: <?= json_encode($settingsModel->get('actor_form_description','Shoot your dialog scene and song audition, then upload both videos below.')) ?>,
                // Actor submission messages
                actor_success_heading: <?= json_encode($settingsModel->get('actor_success_heading','ACTOR SUBMISSION RECEIVED!')) ?>,
                actor_success_message: <?= json_encode($settingsModel->get('actor_success_message',"Your acting video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at your email.")) ?>,
                actor_success_pdf_button: <?= json_encode($settingsModel->get('actor_success_pdf_button','Download Actor Brief PDF')) ?>,
                actor_failure_heading: <?= json_encode($settingsModel->get('actor_failure_heading','SUBMISSION FAILED')) ?>,
                actor_failure_message: <?= json_encode($settingsModel->get('actor_failure_message',"We couldn't process your acting video. Please check your file and try again.")) ?>,
                actor_failure_retry_button: <?= json_encode($settingsModel->get('actor_failure_retry_button','Try Again')) ?>,
                // Film Song Card (shown on actor page)
                film_song_heading:   <?= json_encode($settingsModel->get('film_song_heading','FILM SONG')) ?>,
                film_song_subtitle:  <?= json_encode($settingsModel->get('film_song_subtitle','Listen to the song before you record your audition')) ?>,
                film_song_btn_label: <?= json_encode($settingsModel->get('film_song_btn_label','Get Song')) ?>,
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
                // Listen for trigger from individual save buttons
                window.addEventListener('trigger-save-all', () => {
                    this.saveLandingSettings();
                });
            },
            async saveLandingSettings() {
                this.saving = true; this.saved = false;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                
                // Send all settings in ONE batch request
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                
                // Add all form fields
                for (const [key, value] of Object.entries(this.form)) {
                    fd.append(key, value);
                }
                
                try {
                    const res = await fetch('/api/admin/settings/landing-batch', {
                        method: 'POST', 
                        body: fd, 
                        credentials: 'same-origin'
                    });
                    
                    if (res.ok) {
                        this.saving = false; 
                        this.saved = true;
                        setTimeout(() => this.saved = false, 2500);
                    } else {
                        const err = await res.json();
                        alert('Save failed: ' + (err.error || 'Unknown error'));
                        this.saving = false;
                    }
                } catch (error) {
                    alert('Network error: ' + error.message);
                    this.saving = false;
                }
            }
        };
    }

    </script>

    <!-- Admin Enhancements: YouTube Guide + Auto-Refresh -->
    <script src="/assets/js/admin-enhancements.js"></script>
    
    <!-- ══ LAZY LOADING FIX ══ -->
    <script>
    // Fix browser lazy loading bug - force load images when visible
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        // Force load by touching src
                        if (img.src) {
                            const src = img.src;
                            img.src = '';
                            img.src = src;
                        }
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px' // Start loading 50px before entering viewport
            });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback: load all images immediately if IntersectionObserver not supported
            lazyImages.forEach(img => {
                if (img.src) {
                    const src = img.src;
                    img.src = '';
                    img.src = src;
                }
            });
        }
    });
    </script>
</body>
</html>>
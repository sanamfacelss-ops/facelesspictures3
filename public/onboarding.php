<?php
require_once __DIR__ . '/../app/config/config.php';

if (!is_logged_in()) redirect('/login');

$user = auth_user();
$userRole = $user['role'] ?? 'actor';
$userCategories = $user['content_categories'] ?? [$userRole];

// Get scripts for user's role
$scriptModel = new App\Models\Script();
$scripts = $scriptModel->byCategory($userRole);

// Get active season
$seasonModel = new App\Models\Season();
$activeSeason = $seasonModel->getActive();

$title = 'Welcome to Faceless Pitcher — ' . APP_NAME;

// Role-specific content
$roleGuides = [
    'actor' => [
        'title' => 'Actor',
        'icon' => '🎭',
        'description' => 'As an actor, you\'ll perform dramatic monologues, character scenes, and emotional pieces. Your voice and expression are your tools.',
        'tips' => [
            'Find a quiet space with good lighting',
            'Practice your script a few times before recording',
            'Focus on emotion and delivery, not perfection',
            'Keep your performance between 60-90 seconds',
            'Use natural pauses for dramatic effect'
        ]
    ],
    'director' => [
        'title' => 'Director',
        'icon' => '🎬',
        'description' => 'As a director, you\'ll pitch your creative vision for scenes, share your directorial approach, and explain how you\'d bring stories to life.',
        'tips' => [
            'Be specific about your creative vision',
            'Explain your choices with confidence',
            'Reference visual styles or influences if relevant',
            'Keep pitches focused and under 2 minutes',
            'Show passion for the story you want to tell'
        ]
    ],
    'writer' => [
        'title' => 'Writer',
        'icon' => '✍️',
        'description' => 'As a writer, you\'ll present your original work, pitch story concepts, or perform readings of your scripts and screenplays.',
        'tips' => [
            'Read your work with conviction',
            'Vary your pacing to maintain interest',
            'Let your unique voice come through',
            'Keep readings concise and impactful',
            'Consider the emotional journey of your piece'
        ]
    ]
];

$guide = $roleGuides[$userRole] ?? $roleGuides['actor'];
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
    </style>
</head>
<body class="bg-cream min-h-screen" x-data="{ showScripts: false }">

    <!-- Header -->
    <header class="bg-white border-b border-dark/5">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <span class="font-display text-[18px] text-dark">FACELESS PITCHER</span>
            <span class="text-[12px] text-dark/40">Welcome, <?= e($user['name']) ?>!</span>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 md:py-12">
        <!-- Welcome Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-dark/5 overflow-hidden mb-8">
            <div class="p-6 md:p-8 text-center border-b border-dark/5 bg-gradient-to-b from-crimson/5 to-transparent">
                <div class="text-5xl mb-4"><?= $guide['icon'] ?></div>
                <h1 class="font-display text-[36px] md:text-[48px] text-dark mb-2">WELCOME, <?= strtoupper(e($user['name'])) ?>!</h1>
                <p class="text-dark/50 text-[14px] max-w-md mx-auto">You've joined as a <span class="text-crimson font-semibold"><?= $guide['title'] ?></span>. Here's everything you need to get started.</p>
            </div>

            <div class="p-6 md:p-8">
                <!-- Role Description -->
                <div class="mb-8">
                    <h2 class="font-display text-[20px] text-dark mb-3">YOUR ROLE: <?= strtoupper($guide['title']) ?></h2>
                    <p class="text-dark/70 text-[14px] leading-relaxed"><?= $guide['description'] ?></p>
                </div>

                <!-- Tips -->
                <div class="mb-8">
                    <h2 class="font-display text-[20px] text-dark mb-3">TIPS FOR SUCCESS</h2>
                    <ul class="space-y-2">
                        <?php foreach ($guide['tips'] as $tip): ?>
                        <li class="flex items-start gap-3 text-[14px] text-dark/70">
                            <span class="text-crimson mt-0.5">✓</span>
                            <span><?= e($tip) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Current Season -->
                <?php if ($activeSeason): ?>
                <div class="bg-cream rounded-xl p-5 mb-8">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="bg-teal-100 text-teal-700 text-[10px] px-2 py-0.5 rounded-full font-medium">ACTIVE SEASON</span>
                    </div>
                    <h3 class="font-display text-[22px] text-dark"><?= e($activeSeason['title']) ?></h3>
                    <?php if ($activeSeason['brief']): ?>
                    <p class="text-dark/50 text-[13px] mt-1"><?= e($activeSeason['brief']) ?></p>
                    <?php endif; ?>
                    <p class="text-[11px] text-dark/30 mt-2">
                        <?= date('M j, Y', strtotime($activeSeason['start_date'])) ?> — <?= date('M j, Y', strtotime($activeSeason['end_date'])) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Scripts Section -->
                <?php if (!empty($scripts)): ?>
                <div class="mb-8">
                    <button @click="showScripts = !showScripts" class="w-full flex items-center justify-between p-4 bg-cream/50 rounded-xl hover:bg-cream transition">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">📜</span>
                            <div class="text-left">
                                <h2 class="font-display text-[18px] text-dark">AVAILABLE SCRIPTS</h2>
                                <p class="text-[12px] text-dark/40"><?= count($scripts) ?> scripts for <?= $guide['title'] ?>s</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-dark/40 transition-transform" :class="showScripts ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div x-show="showScripts" x-cloak x-transition class="mt-4 space-y-3">
                        <?php foreach ($scripts as $script): ?>
                        <div class="bg-white border border-dark/5 rounded-xl p-4">
                            <div class="flex items-start justify-between gap-4 mb-2">
                                <h4 class="font-medium text-dark text-[14px]"><?= e($script['title']) ?></h4>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-[9px] px-1.5 py-0.5 rounded-full <?= $script['difficulty'] === 'beginner' ? 'bg-green-100 text-green-700' : ($script['difficulty'] === 'intermediate' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>"><?= ucfirst($script['difficulty']) ?></span>
                                    <?php if ($script['duration_hint']): ?>
                                    <span class="text-[9px] text-dark/40">⏱ <?= e($script['duration_hint']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-[13px] text-dark/60 leading-relaxed whitespace-pre-wrap"><?= e($script['content']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CTA -->
                <div class="text-center pt-4">
                    <a href="/creator/dashboard" class="inline-flex items-center gap-2 bg-crimson text-white px-8 py-3.5 rounded-xl font-medium hover:bg-crimson/90 transition text-[14px]">
                        Go to Creator Studio
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                    <p class="text-[12px] text-dark/40 mt-3">You can always come back here from the dashboard</p>
                </div>
            </div>
        </div>

        <!-- Multi-category notice -->
        <?php if (count($userCategories) > 1): ?>
        <div class="bg-gold/10 border border-gold/20 rounded-xl p-5 text-center">
            <p class="text-[13px] text-dark/70">
                <span class="font-medium text-gold">🌟 Multi-talented!</span> 
                You've also registered for: 
                <?php 
                $otherCategories = array_filter($userCategories, fn($c) => $c !== $userRole);
                echo implode(', ', array_map(fn($c) => '<span class="font-medium">' . ucfirst($c) . '</span>', $otherCategories));
                ?>. 
                Scripts for all your categories are available in the Creator Studio.
            </p>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>

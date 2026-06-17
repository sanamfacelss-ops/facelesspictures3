<?php
require_once __DIR__ . '/../app/config/config.php';

// Check if this is a Google signup completion (user needs to select role)
$isGoogleSignup = isset($_GET['google']) && isset($_SESSION['google_signup']);
$googleData = $isGoogleSignup ? $_SESSION['google_signup'] : null;

// If not Google signup, require authentication
if (!$isGoogleSignup && !is_authenticated()) {
    redirect('/login');
}

// For Google signup, we'll get user data from session later
$user = null;
$userRole = 'actor';
$userCategories = ['actor'];

if (is_authenticated()) {
    $user = auth_user();
    $userRole = $user['role'] ?? 'actor';
    $userCategories = $user['content_categories'] ?? [$userRole];
}

// Get scripts for user's role (only for authenticated users)
$scripts = [];
$activeSeason = null;

if ($user) {
    $scriptModel = new App\Models\Script();
    $scripts = $scriptModel->byCategory($userRole);
    
    $seasonModel = new App\Models\Season();
    $activeSeason = $seasonModel->getActive();
}

$title = $isGoogleSignup ? 'Complete Your Profile — ' . APP_NAME : 'Welcome to Faceless Pictures — ' . APP_NAME;

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
<body class="bg-cream min-h-screen">

<?php if ($isGoogleSignup): ?>
    <!-- ===================== GOOGLE SIGNUP: ROLE SELECTION ===================== -->
    <div class="min-h-screen flex items-center justify-center px-4 py-12" x-data="googleSignup()">
        <div class="w-full max-w-lg">
            <!-- Logo -->
            <div class="text-center mb-8">
                <span class="font-display text-[28px] text-dark">FACELESS PICTURES</span>
                <span class="inline-flex items-center justify-center bg-crimson text-white text-[12px] font-bold w-6 h-6 rounded-full ml-2">3</span>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-dark/5 p-8">
                <!-- Welcome Message -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="font-display text-[32px] text-dark">ALMOST THERE!</h1>
                    <p class="text-dark/50 text-[14px] mt-2">Welcome, <strong><?= e($googleData['name']) ?></strong>!</p>
                    <p class="text-dark/50 text-[14px]">Just pick your role to complete signup.</p>
                </div>

                <!-- Role Selection -->
                <form @submit.prevent="submitRole()" class="space-y-4">
                    <p class="text-[12px] text-dark/50 font-medium uppercase tracking-wider mb-3">Choose your primary role</p>
                    
                    <label class="block cursor-pointer" :class="role === 'actor' ? 'ring-2 ring-crimson' : ''" @click="role = 'actor'">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 border-dark/10 hover:border-crimson/30 transition" :class="role === 'actor' ? 'border-crimson bg-crimson/5' : ''">
                            <span class="text-3xl">🎭</span>
                            <div>
                                <h3 class="font-semibold text-dark">Actor</h3>
                                <p class="text-[12px] text-dark/50">Perform scripts and showcase your acting talent</p>
                            </div>
                            <div class="ml-auto">
                                <div class="w-5 h-5 rounded-full border-2" :class="role === 'actor' ? 'border-crimson bg-crimson' : 'border-dark/20'">
                                    <svg x-show="role === 'actor'" class="w-full h-full text-white p-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </label>

                    <label class="block cursor-pointer" :class="role === 'director' ? 'ring-2 ring-crimson' : ''" @click="role = 'director'">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 border-dark/10 hover:border-crimson/30 transition" :class="role === 'director' ? 'border-crimson bg-crimson/5' : ''">
                            <span class="text-3xl">🎬</span>
                            <div>
                                <h3 class="font-semibold text-dark">Director</h3>
                                <p class="text-[12px] text-dark/50">Pitch your creative vision and directorial approach</p>
                            </div>
                            <div class="ml-auto">
                                <div class="w-5 h-5 rounded-full border-2" :class="role === 'director' ? 'border-crimson bg-crimson' : 'border-dark/20'">
                                    <svg x-show="role === 'director'" class="w-full h-full text-white p-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </label>

                    <label class="block cursor-pointer" :class="role === 'writer' ? 'ring-2 ring-crimson' : ''" @click="role = 'writer'">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 border-dark/10 hover:border-crimson/30 transition" :class="role === 'writer' ? 'border-crimson bg-crimson/5' : ''">
                            <span class="text-3xl">✍️</span>
                            <div>
                                <h3 class="font-semibold text-dark">Writer</h3>
                                <p class="text-[12px] text-dark/50">Present original work and pitch story concepts</p>
                            </div>
                            <div class="ml-auto">
                                <div class="w-5 h-5 rounded-full border-2" :class="role === 'writer' ? 'border-crimson bg-crimson' : 'border-dark/20'">
                                    <svg x-show="role === 'writer'" class="w-full h-full text-white p-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </label>

                    <!-- Error Message -->
                    <div x-show="error" x-cloak class="bg-red-50 text-red-600 p-3 rounded-lg text-[13px]" x-text="error"></div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-crimson text-white py-4 rounded-xl font-semibold hover:bg-crimson/90 transition flex items-center justify-center gap-2"
                            :disabled="loading">
                        <span x-show="!loading">Complete Signup</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating account...
                        </span>
                    </button>
                </form>

                <p class="text-center text-[12px] text-dark/40 mt-6">
                    You can change your role or add more categories later.
                </p>
            </div>
        </div>
    </div>

    <script>
    function googleSignup() {
        return {
            role: 'actor',
            loading: false,
            error: '',
            
            async submitRole() {
                this.loading = true;
                this.error = '';
                
                try {
                    const formData = new FormData();
                    formData.append('role', this.role);
                    formData.append('categories', JSON.stringify([this.role]));
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    
                    const res = await fetch('/api/auth/google/complete', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await res.json();
                    
                    if (data.success) {
                        window.location.href = data.redirect || '/dashboard';
                    } else {
                        this.error = data.error || 'Failed to complete signup. Please try again.';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            }
        }
    }
    </script>

<?php else: ?>
    <!-- ===================== NORMAL ONBOARDING FOR AUTHENTICATED USERS ===================== -->
    <div x-data="{ showScripts: false }">
        <!-- Header -->
        <header class="bg-white border-b border-dark/5">
            <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
                <span class="font-display text-[18px] text-dark">FACELESS PICTURES</span>
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
    </div>
<?php endif; ?>

</body>
</html>

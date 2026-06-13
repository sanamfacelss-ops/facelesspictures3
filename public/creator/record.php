<?php
require_once __DIR__ . '/../../app/config/config.php';

if (!is_authenticated()) redirect('/login');
$user = auth_user();
$title = 'Record Video - ' . APP_NAME;

$scriptModel = new App\Models\Script();
$seasonModel = new App\Models\Season();
$videoModel = new App\Models\Video();

// Get user's content categories
$userCategories = $user['content_categories'] ?? [$user['role']];
if (is_string($userCategories)) {
    $userCategories = json_decode($userCategories, true) ?? [$user['role']];
}

// Pre-selected category from URL
$selectedCategory = $_GET['category'] ?? $userCategories[0] ?? 'actor';
if (!in_array($selectedCategory, $userCategories)) {
    $selectedCategory = $userCategories[0];
}

// Get scripts for user's categories
$scripts = [];
foreach ($userCategories as $cat) {
    $scripts[$cat] = $scriptModel->byCategory($cat);
}

// Get active seasons
$seasons = $seasonModel->all();

$categoryInfo = [
    'actor' => ['icon' => '🎭', 'label' => 'Acting', 'color' => 'rose', 'gradient' => 'from-rose-500 to-pink-600'],
    'director' => ['icon' => '🎬', 'label' => 'Directing', 'color' => 'amber', 'gradient' => 'from-amber-500 to-orange-600'],
    'writer' => ['icon' => '✍️', 'label' => 'Writing', 'color' => 'blue', 'gradient' => 'from-blue-500 to-indigo-600'],
];

$difficultyColors = [
    'beginner' => 'bg-emerald-500/20 text-emerald-400',
    'intermediate' => 'bg-amber-500/20 text-amber-400',
    'advanced' => 'bg-rose-500/20 text-rose-400',
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
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body class="min-h-screen bg-dark-950 text-white font-sans" x-data="recordPage()">
    <!-- Header -->
    <header class="sticky top-0 z-50 glass bg-dark-950/90 border-b border-white/5">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/creator/dashboard" class="flex items-center gap-2 text-white/60 hover:text-white transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="hidden sm:inline">Back to Dashboard</span>
            </a>
            <h1 class="font-semibold">Create Video</h1>
            <div class="w-20"></div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 pb-32">
        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center gap-2">
                <template x-for="(stepName, i) in ['Category', 'Mode', 'Content', 'Upload']" :key="i">
                    <div class="flex items-center">
                        <div :class="step > i ? 'bg-brand-500' : step === i ? 'bg-brand-500' : 'bg-white/10'" 
                             class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors">
                            <template x-if="step > i">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </template>
                            <template x-if="step <= i">
                                <span x-text="i + 1"></span>
                            </template>
                        </div>
                        <span class="ml-2 text-sm hidden sm:inline" :class="step >= i ? 'text-white' : 'text-white/40'" x-text="stepName"></span>
                        <template x-if="i < 3">
                            <div class="w-8 sm:w-16 h-px mx-2" :class="step > i ? 'bg-brand-500' : 'bg-white/10'"></div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Step 1: Category Selection -->
        <div x-show="step === 0" x-cloak class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold mb-2">What are you creating today?</h2>
                <p class="text-white/60">Select the category that matches your content</p>
            </div>
            <div class="grid gap-4">
                <?php foreach ($userCategories as $cat): 
                    $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'gradient' => 'from-gray-500 to-gray-600'];
                ?>
                <button @click="category = '<?= e($cat) ?>'; step = 1" 
                        class="flex items-center gap-4 p-5 rounded-2xl border border-white/10 hover:border-white/20 bg-dark-900 hover:bg-dark-800 transition-all text-left group">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br <?= $info['gradient'] ?> flex items-center justify-center text-2xl flex-shrink-0 group-hover:scale-110 transition-transform">
                        <?= $info['icon'] ?>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg"><?= $info['label'] ?></h3>
                        <p class="text-white/50 text-sm">
                            <?php if ($cat === 'actor'): ?>Showcase your acting talent with a performance piece
                            <?php elseif ($cat === 'director'): ?>Present your directorial vision and style
                            <?php else: ?>Bring your written work to life on screen<?php endif; ?>
                        </p>
                    </div>
                    <svg class="w-5 h-5 text-white/40 group-hover:text-white group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Mode Selection -->
        <div x-show="step === 1" x-cloak class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold mb-2">Choose Your Mode</h2>
                <p class="text-white/60">How would you like to create your video?</p>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <button @click="mode = 'script'; step = 2" 
                        class="p-6 rounded-2xl border border-white/10 hover:border-brand-500/50 bg-dark-900 hover:bg-dark-800 transition-all text-left group">
                    <div class="w-14 h-14 rounded-xl bg-brand-500/20 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">
                        📝
                    </div>
                    <h3 class="font-semibold text-lg mb-2">Script Mode</h3>
                    <p class="text-white/50 text-sm mb-4">Choose from curated scripts designed to showcase your talent. Perfect if you want guidance.</p>
                    <span class="inline-flex items-center text-brand-400 text-sm font-medium">
                        Browse Scripts
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </button>
                <button @click="mode = 'freeform'; step = 2" 
                        class="p-6 rounded-2xl border border-white/10 hover:border-purple-500/50 bg-dark-900 hover:bg-dark-800 transition-all text-left group">
                    <div class="w-14 h-14 rounded-xl bg-purple-500/20 flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">
                        ✨
                    </div>
                    <h3 class="font-semibold text-lg mb-2">Freeform Mode</h3>
                    <p class="text-white/50 text-sm mb-4">Write your own script or improvise. Complete creative freedom for your unique vision.</p>
                    <span class="inline-flex items-center text-purple-400 text-sm font-medium">
                        Go Freeform
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                </button>
            </div>
            <button @click="step = 0" class="mt-6 w-full py-3 text-white/60 hover:text-white transition">
                ← Back to category selection
            </button>
        </div>

        <!-- Step 3: Script Selection / Freeform Input -->
        <div x-show="step === 2" x-cloak class="max-w-4xl mx-auto">
            <!-- Script Mode -->
            <template x-if="mode === 'script'">
                <div>
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold mb-2">Choose Your Script</h2>
                        <p class="text-white/60">Select a script that resonates with you</p>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4 max-h-[60vh] overflow-y-auto custom-scrollbar pr-2">
                        <?php foreach ($userCategories as $cat): ?>
                            <?php foreach ($scripts[$cat] ?? [] as $script): 
                                $diffColor = $difficultyColors[$script['difficulty']] ?? 'bg-slate-500/20 text-slate-400';
                            ?>
                            <div x-show="category === '<?= e($cat) ?>'" 
                                 @click="selectedScript = <?= htmlspecialchars(json_encode($script), ENT_QUOTES, 'UTF-8') ?>; scriptContent = <?= htmlspecialchars(json_encode($script['content']), ENT_QUOTES, 'UTF-8') ?>"
                                 :class="selectedScript?.id === <?= $script['id'] ?> ? 'border-brand-500 bg-brand-500/10' : 'border-white/10 hover:border-white/20'"
                                 class="p-5 rounded-xl border bg-dark-900 cursor-pointer transition-all">
                                <div class="flex items-start justify-between mb-3">
                                    <h4 class="font-semibold"><?= e($script['title']) ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $diffColor ?>">
                                        <?= ucfirst(e($script['difficulty'])) ?>
                                    </span>
                                </div>
                                <p class="text-white/60 text-sm line-clamp-3 mb-3"><?= e($script['content']) ?></p>
                                <?php if ($script['duration_hint']): ?>
                                <div class="flex items-center gap-1 text-xs text-white/40">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <?= e($script['duration_hint']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Selected Script Preview -->
                    <div x-show="selectedScript" x-cloak class="mt-6 p-6 rounded-xl bg-gradient-to-br from-brand-500/10 to-purple-500/10 border border-brand-500/20">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-semibold">Selected Script</h4>
                            <button @click="selectedScript = null; scriptContent = ''" class="text-white/60 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <h5 class="text-lg font-medium mb-2" x-text="selectedScript?.title"></h5>
                        <p class="text-white/70 whitespace-pre-wrap" x-text="selectedScript?.content"></p>
                    </div>
                </div>
            </template>

            <!-- Freeform Mode -->
            <template x-if="mode === 'freeform'">
                <div>
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold mb-2">Write Your Script</h2>
                        <p class="text-white/60">Enter your script, notes, or topic outline</p>
                    </div>
                    
                    <div class="bg-dark-900 rounded-xl border border-white/10 p-4">
                        <textarea x-model="scriptContent" 
                                  placeholder="Write your script here, or just describe what you'll be performing...&#10;&#10;Tips:&#10;• Keep it under 3 minutes&#10;• Be authentic and show your personality&#10;• Don't worry about being perfect!"
                                  class="w-full h-64 bg-transparent text-white placeholder-white/30 resize-none focus:outline-none"
                        ></textarea>
                        <div class="flex items-center justify-between pt-4 border-t border-white/10">
                            <span class="text-xs text-white/40" x-text="scriptContent.length + ' characters'"></span>
                            <span class="text-xs text-white/40">Optional - you can improvise!</span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Navigation -->
            <div class="flex items-center justify-between mt-6">
                <button @click="step = 1; selectedScript = null" class="py-3 px-6 text-white/60 hover:text-white transition">
                    ← Back
                </button>
                <button @click="step = 3" 
                        :disabled="mode === 'script' && !selectedScript"
                        :class="(mode === 'script' && !selectedScript) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-brand-600'"
                        class="py-3 px-8 bg-brand-500 rounded-xl font-semibold transition">
                    Continue to Upload →
                </button>
            </div>
        </div>

        <!-- Step 4: Upload -->
        <div x-show="step === 3" x-cloak class="max-w-3xl mx-auto">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold mb-2">Upload Your Video</h2>
                <p class="text-white/60">Record now or upload a pre-recorded video</p>
            </div>

            <!-- Script Reference (Collapsible) -->
            <div x-show="scriptContent" class="mb-6">
                <button @click="showScript = !showScript" class="w-full flex items-center justify-between p-4 rounded-xl bg-dark-900 border border-white/10 text-left">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📝</span>
                        <span class="font-medium">Your Script Reference</span>
                    </div>
                    <svg class="w-5 h-5 text-white/60 transition-transform" :class="showScript && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="showScript" x-collapse class="mt-2 p-4 rounded-xl bg-dark-800 border border-white/5">
                    <p class="text-white/70 whitespace-pre-wrap text-sm" x-text="scriptContent"></p>
                </div>
            </div>

            <!-- Upload Form -->
            <form @submit.prevent="submitUpload" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <!-- Video Title -->
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-2">Video Title</label>
                    <input type="text" x-model="formData.title" required minlength="3"
                           placeholder="Give your video a catchy title"
                           class="w-full px-4 py-3 rounded-xl bg-dark-900 border border-white/10 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition placeholder-white/30">
                </div>

                <!-- Season Select -->
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-2">Season</label>
                    <div class="relative">
                        <select x-model="formData.season_id" required
                                class="w-full px-4 py-3 rounded-xl bg-dark-900 border border-white/10 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition appearance-none cursor-pointer">
                            <option value="">Select a season</option>
                            <?php foreach ($seasons as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['title']) ?> (<?= e($s['start_date']) ?> - <?= e($s['end_date']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-white/40 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                <!-- Video Upload Area -->
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-2">Video File</label>
                    <div @dragover.prevent="dragOver = true" 
                         @dragleave.prevent="dragOver = false"
                         @drop.prevent="handleDrop($event)"
                         @click="$refs.fileInput.click()"
                         :class="dragOver ? 'border-brand-500 bg-brand-500/10' : 'border-white/10 hover:border-white/20'"
                         class="relative border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all">
                        
                        <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" 
                               accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" class="hidden">
                        
                        <template x-if="!selectedFile">
                            <div>
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <p class="text-white/60 mb-2">Drag and drop your video here</p>
                                <p class="text-white/40 text-sm">or click to browse</p>
                                <p class="text-white/30 text-xs mt-4">MP4, MOV, AVI, WEBM • Max <?= e(UPLOAD_MAX_SIZE) ?></p>
                            </div>
                        </template>
                        
                        <template x-if="selectedFile">
                            <div class="flex items-center justify-center gap-4">
                                <div class="w-14 h-14 rounded-xl bg-brand-500/20 flex items-center justify-center">
                                    <svg class="w-7 h-7 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="text-left">
                                    <p class="font-medium truncate max-w-xs" x-text="selectedFile.name"></p>
                                    <p class="text-sm text-white/50" x-text="formatFileSize(selectedFile.size)"></p>
                                </div>
                                <button type="button" @click.stop="clearFile()" class="p-2 rounded-lg hover:bg-white/10 text-white/60 hover:text-red-400 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Video Preview -->
                    <template x-if="videoPreviewUrl">
                        <div class="mt-4 rounded-xl overflow-hidden bg-black aspect-video">
                            <video :src="videoPreviewUrl" controls class="w-full h-full object-contain"></video>
                        </div>
                    </template>
                </div>

                <!-- Upload Progress -->
                <template x-if="uploading">
                    <div class="p-4 rounded-xl bg-dark-900 border border-white/10">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">Uploading...</span>
                            <span class="text-sm text-white/60" x-text="uploadProgress + '%'"></span>
                        </div>
                        <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-brand-500 to-purple-500 transition-all duration-300 rounded-full"
                                 :style="'width: ' + uploadProgress + '%'"></div>
                        </div>
                        <p class="text-xs text-white/40 mt-2" x-show="uploadProgress === 100">Processing video...</p>
                    </div>
                </template>

                <!-- Error Messages -->
                <template x-if="errors.length">
                    <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <ul class="text-sm text-rose-300">
                                <template x-for="err in errors" :key="err">
                                    <li x-text="err"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>

                <!-- Success Message -->
                <template x-if="success">
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-sm text-emerald-300" x-text="success"></span>
                        </div>
                    </div>
                </template>

                <!-- Submit -->
                <div class="flex items-center justify-between pt-4">
                    <button type="button" @click="step = 2" class="py-3 px-6 text-white/60 hover:text-white transition">
                        ← Back
                    </button>
                    <button type="submit" :disabled="loading || !selectedFile"
                            :class="(loading || !selectedFile) ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-lg hover:shadow-brand-500/25 hover:-translate-y-0.5'"
                            class="py-3 px-8 bg-gradient-to-r from-brand-500 to-purple-600 rounded-xl font-semibold transition-all">
                        <span x-show="!loading" class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Upload & Submit
                        </span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Uploading...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function recordPage() {
            return {
                step: 0,
                category: '<?= e($selectedCategory) ?>',
                mode: null,
                selectedScript: null,
                scriptContent: '',
                showScript: false,
                
                formData: {
                    title: '',
                    season_id: ''
                },
                
                selectedFile: null,
                videoPreviewUrl: null,
                dragOver: false,
                
                uploading: false,
                uploadProgress: 0,
                loading: false,
                errors: [],
                success: '',
                
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) this.setFile(file);
                },
                
                handleDrop(event) {
                    this.dragOver = false;
                    const file = event.dataTransfer.files[0];
                    if (file && file.type.startsWith('video/')) {
                        this.setFile(file);
                        this.$refs.fileInput.files = event.dataTransfer.files;
                    }
                },
                
                setFile(file) {
                    this.selectedFile = file;
                    if (this.videoPreviewUrl) URL.revokeObjectURL(this.videoPreviewUrl);
                    this.videoPreviewUrl = URL.createObjectURL(file);
                },
                
                clearFile() {
                    this.selectedFile = null;
                    if (this.videoPreviewUrl) URL.revokeObjectURL(this.videoPreviewUrl);
                    this.videoPreviewUrl = null;
                    this.$refs.fileInput.value = '';
                },
                
                formatFileSize(bytes) {
                    if (bytes < 1024) return bytes + ' B';
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
                },

                async submitUpload() {
                    this.loading = true;
                    this.uploading = true;
                    this.errors = [];
                    this.success = '';
                    this.uploadProgress = 0;
                    
                    const formData = new FormData();
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    formData.append('title', this.formData.title);
                    formData.append('season_id', this.formData.season_id);
                    formData.append('content_type', this.category);
                    formData.append('recording_mode', this.mode);
                    formData.append('script_content', this.scriptContent);
                    formData.append('video', this.selectedFile);
                    
                    const xhr = new XMLHttpRequest();
                    
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    });
                    
                    xhr.onload = () => {
                        this.uploading = false;
                        this.loading = false;
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (xhr.status >= 200 && xhr.status < 300) {
                                this.success = response.message || 'Video uploaded! AI review in progress...';
                                setTimeout(() => {
                                    window.location.href = '/creator/dashboard';
                                }, 2000);
                            } else {
                                this.errors = response.errors || [response.error || 'Upload failed'];
                            }
                        } catch (e) {
                            this.errors = ['Server error. Please try again.'];
                        }
                    };
                    
                    xhr.onerror = () => {
                        this.uploading = false;
                        this.loading = false;
                        this.errors = ['Network error. Please check your connection.'];
                    };
                    
                    xhr.open('POST', '/api/upload');
                    xhr.send(formData);
                }
            }
        }
    </script>
</body>
</html>

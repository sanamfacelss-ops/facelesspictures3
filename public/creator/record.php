<?php
require_once __DIR__ . '/../../app/config/config.php';

if (!is_authenticated()) redirect('/login');
$user = auth_user();
$title = 'Create Video - ' . APP_NAME;

$scriptModel = new App\Models\Script();
$seasonModel = new App\Models\Season();

$userCategories = $user['content_categories'] ?? [$user['role']];
if (is_string($userCategories)) {
    $userCategories = json_decode($userCategories, true) ?? [$user['role']];
}

$selectedCategory = $_GET['category'] ?? $userCategories[0] ?? 'actor';
if (!in_array($selectedCategory, $userCategories)) {
    $selectedCategory = $userCategories[0];
}

$scripts = [];
foreach ($userCategories as $cat) {
    $scripts[$cat] = $scriptModel->byCategory($cat);
}

$seasons = $seasonModel->all();

$categoryInfo = [
    'actor' => ['icon' => '🎭', 'label' => 'Acting', 'color' => '#E11D48', 'bg' => '#FFF1F2'],
    'director' => ['icon' => '🎬', 'label' => 'Directing', 'color' => '#D97706', 'bg' => '#FFFBEB'],
    'writer' => ['icon' => '✍️', 'label' => 'Writing', 'color' => '#2563EB', 'bg' => '#EFF6FF'],
];
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
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        .card { transition: all 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px -4px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen" x-data="recordPage()">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/creator/dashboard" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="hidden sm:inline font-medium">Back</span>
            </a>
            <h1 class="font-semibold text-gray-900">Create Video</h1>
            <div class="w-16"></div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <template x-for="(label, i) in ['Category', 'Mode', 'Script', 'Upload']" :key="i">
                    <div class="flex items-center">
                        <div class="flex flex-col items-center">
                            <div :class="step > i ? 'bg-emerald-500 text-white' : step === i ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-500'"
                                 class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-all">
                                <span x-show="step <= i" x-text="i + 1"></span>
                                <svg x-show="step > i" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs mt-1.5 font-medium" :class="step >= i ? 'text-gray-900' : 'text-gray-400'" x-text="label"></span>
                        </div>
                        <div x-show="i < 3" class="w-12 sm:w-20 h-0.5 mx-2 -mt-4" :class="step > i ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Step 1: Category -->
        <div x-show="step === 0" x-cloak>
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">What are you creating?</h2>
                <p class="text-gray-500">Select your content category</p>
            </div>
            <div class="grid gap-4 max-w-lg mx-auto">
                <?php foreach ($userCategories as $cat): 
                    $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'color' => '#6B7280', 'bg' => '#F3F4F6'];
                ?>
                <button @click="category = '<?= e($cat) ?>'; step = 1" 
                        class="flex items-center gap-4 p-5 bg-white rounded-xl border border-gray-200 hover:border-gray-300 hover:shadow-md transition text-left card">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl" style="background: <?= $info['bg'] ?>">
                        <?= $info['icon'] ?>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900"><?= $info['label'] ?></h3>
                        <p class="text-sm text-gray-500">
                            <?php if ($cat === 'actor'): ?>Showcase your acting skills
                            <?php elseif ($cat === 'director'): ?>Present your directorial vision
                            <?php else: ?>Bring your writing to life<?php endif; ?>
                        </p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Mode Selection -->
        <div x-show="step === 1" x-cloak>
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Choose your style</h2>
                <p class="text-gray-500">How do you want to create?</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4 max-w-2xl mx-auto">
                <button @click="mode = 'script'; step = 2" 
                        class="p-6 bg-white rounded-xl border border-gray-200 hover:border-rose-300 hover:shadow-md transition text-left card group">
                    <div class="w-12 h-12 bg-rose-50 rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">📝</div>
                    <h3 class="font-semibold text-gray-900 mb-2">Use a Script</h3>
                    <p class="text-sm text-gray-500 mb-4">Choose from curated scripts designed to showcase your talent</p>
                    <span class="text-sm font-medium text-rose-600">Browse scripts →</span>
                </button>
                <button @click="mode = 'freeform'; step = 2" 
                        class="p-6 bg-white rounded-xl border border-gray-200 hover:border-violet-300 hover:shadow-md transition text-left card group">
                    <div class="w-12 h-12 bg-violet-50 rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">✨</div>
                    <h3 class="font-semibold text-gray-900 mb-2">Go Freeform</h3>
                    <p class="text-sm text-gray-500 mb-4">Write your own or improvise with complete creative freedom</p>
                    <span class="text-sm font-medium text-violet-600">Start creating →</span>
                </button>
            </div>
            <div class="text-center mt-6">
                <button @click="step = 0" class="text-sm text-gray-500 hover:text-gray-700">← Back to categories</button>
            </div>
        </div>

        <!-- Step 3: Script Selection / Freeform -->
        <div x-show="step === 2" x-cloak>
            <!-- Script Mode -->
            <template x-if="mode === 'script'">
                <div>
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Pick your script</h2>
                        <p class="text-gray-500">Select one that resonates with you</p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 mb-6">
                        <?php foreach ($userCategories as $cat): ?>
                            <?php foreach ($scripts[$cat] ?? [] as $script): ?>
                            <div x-show="category === '<?= e($cat) ?>'" 
                                 @click="selectedScript = <?= htmlspecialchars(json_encode($script), ENT_QUOTES) ?>; scriptContent = <?= htmlspecialchars(json_encode($script['content']), ENT_QUOTES) ?>"
                                 :class="selectedScript?.id === <?= $script['id'] ?> ? 'border-rose-500 bg-rose-50' : 'border-gray-200 hover:border-gray-300'"
                                 class="p-5 bg-white rounded-xl border cursor-pointer transition card">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900"><?= e($script['title']) ?></h4>
                                    <span class="px-2 py-0.5 rounded text-xs font-medium 
                                        <?= $script['difficulty'] === 'beginner' ? 'bg-emerald-100 text-emerald-700' : 
                                           ($script['difficulty'] === 'intermediate' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') ?>">
                                        <?= ucfirst($script['difficulty']) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 line-clamp-3 mb-3"><?= e($script['content']) ?></p>
                                <?php if ($script['duration_hint']): ?>
                                <p class="text-xs text-gray-400 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= e($script['duration_hint']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </template>

            <!-- Freeform Mode -->
            <template x-if="mode === 'freeform'">
                <div>
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Your script</h2>
                        <p class="text-gray-500">Write notes or leave blank to improvise</p>
                    </div>
                    <div class="max-w-2xl mx-auto">
                        <textarea x-model="scriptContent" rows="8" placeholder="Write your script, notes, or topic ideas here...

Tips:
• Keep it under 3 minutes
• Be authentic
• Show your personality"
                            class="w-full p-4 bg-white border border-gray-200 rounded-xl focus:border-gray-400 focus:ring-0 outline-none resize-none text-gray-900 placeholder-gray-400"></textarea>
                        <p class="text-sm text-gray-400 mt-2 text-right" x-text="scriptContent.length + ' characters'"></p>
                    </div>
                </div>
            </template>

            <div class="flex items-center justify-between mt-8 max-w-2xl mx-auto">
                <button @click="step = 1; selectedScript = null" class="px-4 py-2 text-gray-600 hover:text-gray-900 transition">← Back</button>
                <button @click="step = 3" 
                        :disabled="mode === 'script' && !selectedScript"
                        :class="(mode === 'script' && !selectedScript) ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-gray-900 text-white hover:bg-gray-800'"
                        class="px-6 py-2.5 rounded-lg font-medium transition">
                    Continue →
                </button>
            </div>
        </div>

        <!-- Step 4: Upload -->
        <div x-show="step === 3" x-cloak>
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Upload your video</h2>
                <p class="text-gray-500">Almost there! Add your video file</p>
            </div>

            <div class="max-w-2xl mx-auto">
                <!-- Script Reference -->
                <div x-show="scriptContent" class="mb-6">
                    <button @click="showScript = !showScript" class="w-full flex items-center justify-between p-4 bg-white border border-gray-200 rounded-xl text-left hover:bg-gray-50 transition">
                        <span class="flex items-center gap-2 font-medium text-gray-900">
                            <span>📝</span> Your Script Reference
                        </span>
                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showScript && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="showScript" x-collapse class="mt-2 p-4 bg-gray-100 rounded-xl">
                        <p class="text-sm text-gray-700 whitespace-pre-wrap" x-text="scriptContent"></p>
                    </div>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-5">
                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Video Title</label>
                        <input type="text" x-model="formData.title" required minlength="3"
                               placeholder="Give your video a title"
                               class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:border-gray-400 focus:ring-0 outline-none">
                    </div>

                    <!-- Season -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Season</label>
                        <select x-model="formData.season_id" required
                                class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:border-gray-400 focus:ring-0 outline-none appearance-none">
                            <option value="">Select season</option>
                            <?php foreach ($seasons as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Upload Area -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Video File</label>
                        <div @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop($event)"
                             @click="$refs.fileInput.click()"
                             :class="dragOver ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400'"
                             class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition">
                            <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" accept="video/*" class="hidden">
                            <template x-if="!selectedFile">
                                <div>
                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </div>
                                    <p class="text-gray-600 mb-1">Drop your video here or click to browse</p>
                                    <p class="text-sm text-gray-400">MP4, MOV, WebM • Max <?= e(UPLOAD_MAX_SIZE) ?></p>
                                </div>
                            </template>
                            <template x-if="selectedFile">
                                <div class="flex items-center justify-center gap-3">
                                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div class="text-left">
                                        <p class="font-medium text-gray-900 truncate max-w-xs" x-text="selectedFile.name"></p>
                                        <p class="text-sm text-gray-500" x-text="formatFileSize(selectedFile.size)"></p>
                                    </div>
                                    <button type="button" @click.stop="clearFile()" class="p-2 text-gray-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Progress -->
                    <div x-show="uploading" class="p-4 bg-gray-100 rounded-xl">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-600">Uploading...</span>
                            <span class="font-medium" x-text="uploadProgress + '%'"></span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-gray-900 rounded-full transition-all" :style="'width:' + uploadProgress + '%'"></div>
                        </div>
                    </div>

                    <!-- Errors -->
                    <div x-show="errors.length" class="p-4 bg-rose-50 border border-rose-100 rounded-xl">
                        <ul class="text-sm text-rose-700">
                            <template x-for="err in errors"><li x-text="err"></li></template>
                        </ul>
                    </div>

                    <!-- Success -->
                    <div x-show="success" class="p-4 bg-emerald-50 border border-emerald-100 rounded-xl">
                        <p class="text-sm text-emerald-700 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="success"></span>
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4">
                        <button type="button" @click="step = 2" class="px-4 py-2 text-gray-600 hover:text-gray-900 transition">← Back</button>
                        <button type="submit" :disabled="loading || !selectedFile"
                                :class="(loading || !selectedFile) ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-gray-900 text-white hover:bg-gray-800'"
                                class="px-6 py-2.5 rounded-lg font-medium transition flex items-center gap-2">
                            <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="loading ? 'Uploading...' : 'Upload Video'"></span>
                        </button>
                    </div>
                </form>
            </div>
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
                formData: { title: '', season_id: '' },
                selectedFile: null,
                dragOver: false,
                uploading: false,
                uploadProgress: 0,
                loading: false,
                errors: [],
                success: '',

                handleFileSelect(e) {
                    if (e.target.files[0]) this.selectedFile = e.target.files[0];
                },
                handleDrop(e) {
                    this.dragOver = false;
                    const file = e.dataTransfer.files[0];
                    if (file?.type.startsWith('video/')) {
                        this.selectedFile = file;
                        this.$refs.fileInput.files = e.dataTransfer.files;
                    }
                },
                clearFile() {
                    this.selectedFile = null;
                    this.$refs.fileInput.value = '';
                },
                formatFileSize(bytes) {
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
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                    };
                    xhr.onload = () => {
                        this.uploading = false;
                        this.loading = false;
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (xhr.status >= 200 && xhr.status < 300) {
                                this.success = res.message || 'Video uploaded! Processing...';
                                setTimeout(() => window.location.href = '/creator/dashboard', 2000);
                            } else {
                                this.errors = res.errors || [res.error || 'Upload failed'];
                            }
                        } catch { this.errors = ['Server error']; }
                    };
                    xhr.onerror = () => {
                        this.uploading = false;
                        this.loading = false;
                        this.errors = ['Network error'];
                    };
                    xhr.open('POST', '/api/upload');
                    xhr.send(formData);
                }
            }
        }
    </script>
</body>
</html>

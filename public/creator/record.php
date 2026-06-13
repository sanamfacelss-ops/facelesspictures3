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

// Get scripts/prompts for each category
$contentByCategory = [];
foreach ($userCategories as $cat) {
    $contentByCategory[$cat] = $scriptModel->byCategory($cat);
}

$activeSeason = $seasonModel->getActive();

$categoryInfo = [
    'actor' => [
        'icon' => '🎭', 
        'label' => 'Acting', 
        'color' => '#E11D48', 
        'bg' => '#FFF1F2',
        'description' => 'Perform the script on camera',
        'instructions' => [
            'Read the script carefully before recording',
            'Show emotion and bring the character to life',
            'Keep your video under 3 minutes',
            'Good lighting and clear audio are essential',
        ],
        'contentLabel' => 'Script to Perform',
    ],
    'director' => [
        'icon' => '🎬', 
        'label' => 'Directing', 
        'color' => '#D97706', 
        'bg' => '#FFFBEB',
        'description' => 'Explain how you would direct this scene',
        'instructions' => [
            'Describe your vision for the scene',
            'Explain camera angles, lighting, mood',
            'Talk about how you would direct the actors',
            'Share your creative interpretation',
        ],
        'contentLabel' => 'Scene to Direct',
    ],
    'writer' => [
        'icon' => '✍️', 
        'label' => 'Writing', 
        'color' => '#2563EB', 
        'bg' => '#EFF6FF',
        'description' => 'Continue the story in your own words',
        'instructions' => [
            'Read the story opening carefully',
            'Continue the narrative in your own style',
            'Be creative - take the story anywhere',
            'Present your continuation on camera',
        ],
        'contentLabel' => 'Story to Continue',
    ],
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
        .tab-active { border-bottom: 3px solid currentColor; }
        .card { transition: all 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px -4px rgba(0,0,0,0.1); }
        .script-card.selected { ring: 2px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen" x-data="recordPage()">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/creator/dashboard" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="hidden sm:inline font-medium">Back to Dashboard</span>
            </a>
            <h1 class="font-semibold text-gray-900">Create Video</h1>
            <div class="w-20"></div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6">
        
        <?php if (count($userCategories) > 1): ?>
        <!-- Category Tabs -->
        <div class="bg-white rounded-xl border border-gray-200 mb-6 overflow-hidden">
            <div class="flex border-b border-gray-200">
                <?php foreach ($userCategories as $index => $cat): 
                    $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'color' => '#6B7280'];
                ?>
                <button 
                    @click="activeTab = '<?= e($cat) ?>'; selectedScript = null"
                    :class="activeTab === '<?= e($cat) ?>' ? 'border-b-2 bg-gray-50' : 'hover:bg-gray-50'"
                    class="flex-1 px-4 py-4 flex items-center justify-center gap-2 transition font-medium"
                    :style="activeTab === '<?= e($cat) ?>' ? 'border-color: <?= $info['color'] ?>; color: <?= $info['color'] ?>' : ''"
                >
                    <span class="text-xl"><?= $info['icon'] ?></span>
                    <span class="hidden sm:inline"><?= $info['label'] ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content for Each Category -->
        <?php foreach ($userCategories as $cat): 
            $info = $categoryInfo[$cat] ?? ['icon' => '📹', 'label' => ucfirst($cat), 'color' => '#6B7280', 'bg' => '#F3F4F6', 'description' => '', 'instructions' => [], 'contentLabel' => 'Content'];
            $scripts = $contentByCategory[$cat] ?? [];
        ?>
        <div x-show="activeTab === '<?= e($cat) ?>'" x-cloak class="space-y-6">
            
            <!-- Mode Header -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl flex-shrink-0" style="background: <?= $info['bg'] ?>">
                        <?= $info['icon'] ?>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-1"><?= $info['label'] ?> Mode</h2>
                        <p class="text-gray-600"><?= $info['description'] ?></p>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="mt-4 p-4 rounded-lg" style="background: <?= $info['bg'] ?>">
                    <h4 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        How it works
                    </h4>
                    <ul class="space-y-1">
                        <?php foreach ($info['instructions'] as $instruction): ?>
                        <li class="text-sm text-gray-700 flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color: <?= $info['color'] ?>" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <?= e($instruction) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Scripts/Content Selection -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900 mb-4"><?= $info['contentLabel'] ?></h3>
                
                <?php if (empty($scripts)): ?>
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-gray-500 mb-2">No <?= strtolower($info['contentLabel']) ?>s available yet</p>
                    <p class="text-sm text-gray-400">Check back soon for new content!</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-3 gap-3 lg:grid-cols-1 lg:gap-4">
                    <?php foreach ($scripts as $script): ?>
                    <div 
                        @click="selectScript(<?= htmlspecialchars(json_encode($script), ENT_QUOTES) ?>, '<?= e($cat) ?>')"
                        :class="selectedScript?.id === <?= $script['id'] ?> && activeTab === '<?= e($cat) ?>' ? 'ring-2 ring-offset-2' : 'hover:border-gray-300'"
                        class="p-3 lg:p-5 bg-white rounded-xl border border-gray-200 cursor-pointer transition card"
                        :style="selectedScript?.id === <?= $script['id'] ?> && activeTab === '<?= e($cat) ?>' ? 'ring-color: <?= $info['color'] ?>' : ''"
                    >
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between mb-2 lg:mb-3">
                            <h4 class="font-semibold text-gray-900 text-sm lg:text-base mb-2 lg:mb-0"><?= e($script['title']) ?></h4>
                            <div class="flex items-center gap-1 lg:gap-2 flex-wrap">
                                <?php if ($script['duration_hint']): ?>
                                <span class="px-1.5 lg:px-2 py-0.5 rounded text-[10px] lg:text-xs bg-gray-100 text-gray-600">
                                    <?= e($script['duration_hint']) ?>
                                </span>
                                <?php endif; ?>
                                <span class="px-1.5 lg:px-2 py-0.5 rounded text-[10px] lg:text-xs font-medium 
                                    <?= $script['difficulty'] === 'beginner' ? 'bg-emerald-100 text-emerald-700' : 
                                       ($script['difficulty'] === 'intermediate' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') ?>">
                                    <?= ucfirst($script['difficulty']) ?>
                                </span>
                            </div>
                        </div>
                        <p class="text-gray-600 text-xs lg:text-sm leading-relaxed whitespace-pre-wrap line-clamp-4 lg:line-clamp-none"><?= e($script['content']) ?></p>
                        
                        <div x-show="selectedScript?.id === <?= $script['id'] ?> && activeTab === '<?= e($cat) ?>'" class="mt-3 lg:mt-4 pt-3 lg:pt-4 border-t border-gray-100">
                            <span class="text-xs lg:text-sm font-medium flex items-center gap-1" style="color: <?= $info['color'] ?>">
                                <svg class="w-3 h-3 lg:w-4 lg:h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span class="hidden lg:inline">Selected - Ready to record</span>
                                <span class="lg:hidden">Selected</span>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Upload Section (shown when script selected) -->
            <div x-show="selectedScript && activeTab === '<?= e($cat) ?>'" x-cloak class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Upload Your Video</h3>
                
                <!-- Selected Script Reference -->
                <div class="mb-6 p-4 rounded-lg border-l-4" style="background: <?= $info['bg'] ?>; border-color: <?= $info['color'] ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Your <?= strtolower($info['contentLabel']) ?>:</span>
                        <button @click="showFullScript = !showFullScript" class="text-sm" style="color: <?= $info['color'] ?>">
                            <span x-text="showFullScript ? 'Hide' : 'Show full'"></span>
                        </button>
                    </div>
                    <h4 class="font-semibold text-gray-900" x-text="selectedScript?.title"></h4>
                    <p x-show="showFullScript" class="text-sm text-gray-600 mt-2 whitespace-pre-wrap" x-text="selectedScript?.content"></p>
                </div>

                <form @submit.prevent="submitUpload('<?= e($cat) ?>')" class="space-y-5">
                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Video Title</label>
                        <input type="text" x-model="formData.title" required minlength="3"
                               placeholder="Give your video a title"
                               class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:border-gray-400 focus:ring-0 outline-none">
                    </div>

                    <!-- Upload Area -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Video File</label>
                        <div @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop($event)"
                             @click="$refs.fileInput_<?= $cat ?>.click()"
                             :class="dragOver ? 'border-gray-900 bg-gray-50' : 'border-gray-300 hover:border-gray-400'"
                             class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition">
                            <input type="file" x-ref="fileInput_<?= $cat ?>" @change="handleFileSelect($event)" accept="video/*" class="hidden">
                            <template x-if="!selectedFile">
                                <div>
                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </div>
                                    <p class="text-gray-600 mb-1">Drop your video here or click to browse</p>
                                    <p class="text-sm text-gray-400">MP4, MOV, WebM • Max 500MB</p>
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

                    <!-- Submit -->
                    <button type="submit" :disabled="loading || !selectedFile"
                            :class="(loading || !selectedFile) ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'hover:opacity-90'"
                            class="w-full py-3 rounded-xl font-semibold text-white transition flex items-center justify-center gap-2"
                            style="background: <?= $info['color'] ?>">
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="loading ? 'Uploading...' : 'Submit Video'"></span>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

    </main>

    <script>
        function recordPage() {
            return {
                activeTab: '<?= e($userCategories[0] ?? 'actor') ?>',
                selectedScript: null,
                showFullScript: false,
                formData: { title: '' },
                selectedFile: null,
                dragOver: false,
                uploading: false,
                uploadProgress: 0,
                loading: false,
                errors: [],
                success: '',

                selectScript(script, category) {
                    if (this.activeTab === category) {
                        this.selectedScript = script;
                        this.formData.title = script.title + ' - My Performance';
                    }
                },
                handleFileSelect(e) {
                    if (e.target.files[0]) this.selectedFile = e.target.files[0];
                },
                handleDrop(e) {
                    this.dragOver = false;
                    const file = e.dataTransfer.files[0];
                    if (file?.type.startsWith('video/')) {
                        this.selectedFile = file;
                    }
                },
                clearFile() {
                    this.selectedFile = null;
                },
                formatFileSize(bytes) {
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
                },
                async submitUpload(category) {
                    this.loading = true;
                    this.uploading = true;
                    this.errors = [];
                    this.success = '';
                    this.uploadProgress = 0;

                    const formData = new FormData();
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    formData.append('title', this.formData.title);
                    formData.append('season_id', '<?= $activeSeason['id'] ?? 1 ?>');
                    formData.append('content_type', category);
                    formData.append('script_id', this.selectedScript?.id || '');
                    formData.append('script_content', this.selectedScript?.content || '');
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
                                this.success = res.message || 'Video uploaded! Redirecting...';
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

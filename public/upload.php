<?php
if (!is_authenticated()) redirect('/login');
$user = auth_user();
$seasonModel = new App\Models\Season();
$userModel = new App\Models\User();
$videoModel = new App\Models\Video();
$seasons = $seasonModel->all();

// Get user's selected content categories
$userCategories = $user['content_categories'] ?? [$user['role']];
if (is_string($userCategories)) {
    $userCategories = json_decode($userCategories, true) ?? [$user['role']];
}

$title = 'Upload - ' . APP_NAME;
require_once __DIR__ . '/partials/head.php';

// Define category info for display
$categoryInfo = [
    'actor' => [
        'icon' => '🎭',
        'title' => 'Acting Performance',
        'description' => 'Upload your acting performance video',
        'color' => 'crimson',
        'bgColor' => 'bg-red-50',
        'borderColor' => 'border-red-200',
        'textColor' => 'text-red-600',
    ],
    'director' => [
        'icon' => '🎬',
        'title' => 'Director Showreel',
        'description' => 'Upload your directorial work',
        'color' => 'gold',
        'bgColor' => 'bg-amber-50',
        'borderColor' => 'border-amber-200',
        'textColor' => 'text-amber-600',
    ],
    'writer' => [
        'icon' => '✍️',
        'title' => 'Written Work',
        'description' => 'Upload a video presentation of your script',
        'color' => 'blue',
        'bgColor' => 'bg-blue-50',
        'borderColor' => 'border-blue-200',
        'textColor' => 'text-blue-600',
    ],
];
?>

<div class="max-w-4xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold mb-2">Upload Your Content</h1>
        <p class="text-gray-600 text-sm">One video per category per season. Max size <?= e(UPLOAD_MAX_SIZE) ?>.</p>
    </div>

    <?php if (empty($userCategories)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
            <div class="text-4xl mb-3">⚠️</div>
            <h3 class="font-semibold text-amber-800 mb-2">No Content Types Selected</h3>
            <p class="text-amber-700 text-sm mb-4">You haven't selected any content types during registration. Please contact support to update your preferences.</p>
        </div>
    <?php else: ?>
        <!-- Category Tabs -->
        <div class="mb-6" x-data="{ activeCategory: '<?= e($userCategories[0]) ?>' }">
            <?php if (count($userCategories) > 1): ?>
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php foreach ($userCategories as $cat): 
                        $info = $categoryInfo[$cat] ?? null;
                        if (!$info) continue;
                    ?>
                        <button 
                            @click="activeCategory = '<?= e($cat) ?>'"
                            :class="activeCategory === '<?= e($cat) ?>' ? '<?= $info['bgColor'] ?> <?= $info['borderColor'] ?> <?= $info['textColor'] ?>' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                            class="px-4 py-2.5 rounded-xl border-2 font-medium text-sm transition-all flex items-center gap-2"
                        >
                            <span><?= $info['icon'] ?></span>
                            <span><?= e($info['title']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Upload Forms for Each Category -->
            <?php foreach ($userCategories as $cat): 
                $info = $categoryInfo[$cat] ?? null;
                if (!$info) continue;
            ?>
                <div x-show="activeCategory === '<?= e($cat) ?>'" x-cloak>
                    <div class="bg-white p-6 rounded-xl shadow border <?= $info['borderColor'] ?>">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="text-3xl"><?= $info['icon'] ?></div>
                            <div>
                                <h2 class="font-bold text-lg"><?= e($info['title']) ?></h2>
                                <p class="text-gray-500 text-sm"><?= e($info['description']) ?></p>
                            </div>
                        </div>

                        <div x-data="uploadForm('<?= e($cat) ?>')">
                            <template x-if="errors.length">
                                <div class="mb-4 bg-red-50 text-red-700 p-3 rounded text-sm">
                                    <ul>
                                        <template x-for="err in errors" :key="err">
                                            <li x-text="err"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            <template x-if="success">
                                <div class="mb-4 bg-green-50 text-green-700 p-3 rounded text-sm">
                                    <p x-text="success"></p>
                                </div>
                            </template>

                            <form @submit.prevent="submitUpload" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="content_type" value="<?= e($cat) ?>">
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Video Title</label>
                                    <input type="text" name="title" required minlength="3" x-model="formData.title"
                                        placeholder="Give your <?= e($cat) ?> submission a title"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Season</label>
                                    <select name="season_id" required x-model="formData.season_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                                        <option value="">Select season</option>
                                        <?php foreach ($seasons as $s):
                                            // Check if user already submitted this category for this season
                                            $alreadySubmitted = $videoModel->existsForSeasonAndType((int) $user['id'], (int) $s['id'], $cat);
                                        ?>
                                            <option value="<?= $s['id'] ?>" <?= $alreadySubmitted ? 'disabled' : '' ?>>
                                                <?= e($s['title']) ?> (<?= e($s['start_date']) ?> - <?= e($s['end_date']) ?>) <?= $alreadySubmitted ? ' [Already Submitted]' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Video File</label>
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition cursor-pointer"
                                         @dragover.prevent="dragOver = true" 
                                         @dragleave.prevent="dragOver = false"
                                         @drop.prevent="handleDrop($event)"
                                         :class="{ 'border-<?= $info['color'] ?> bg-<?= $info['color'] ?>/5': dragOver }"
                                         @click="$refs.fileInput.click()">
                                        <input type="file" name="video" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" required 
                                               x-ref="fileInput" @change="handleFileSelect($event)" class="hidden">
                                        <div x-show="!selectedFile">
                                            <div class="text-4xl mb-2">📹</div>
                                            <p class="text-gray-600 text-sm">Drop your video here or click to browse</p>
                                            <p class="text-xs text-gray-400 mt-1">MP4, MOV, AVI, WEBM accepted</p>
                                        </div>
                                        <div x-show="selectedFile" class="flex items-center justify-center gap-3">
                                            <span class="text-2xl">🎥</span>
                                            <span class="text-gray-700 font-medium" x-text="selectedFile?.name"></span>
                                            <button type="button" @click.stop="clearFile()" class="text-red-500 hover:text-red-700">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div x-show="uploading" class="mb-4">
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Uploading...</span>
                                        <span x-text="uploadProgress + '%'"></span>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-<?= $info['color'] ?> transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                                    </div>
                                </div>
                                
                                <button type="submit" :disabled="loading || !selectedFile" 
                                    class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!loading">Upload <?= e(ucfirst($cat)) ?> Video</span>
                                    <span x-show="loading" class="flex items-center justify-center gap-2">
                                        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Uploading...
                                    </span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Tips Section -->
    <div class="mt-8 bg-gray-50 rounded-xl p-6">
        <h3 class="font-semibold text-gray-800 mb-3">📝 Submission Tips</h3>
        <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start gap-2">
                <span class="text-green-500 mt-0.5">✓</span>
                <span>Keep your video under 3 minutes for best engagement</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-green-500 mt-0.5">✓</span>
                <span>Good lighting and clear audio make a big difference</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-green-500 mt-0.5">✓</span>
                <span>Videos are reviewed before being published to YouTube</span>
            </li>
            <li class="flex items-start gap-2">
                <span class="text-green-500 mt-0.5">✓</span>
                <span>You can submit one video per category per season</span>
            </li>
        </ul>
    </div>
</div>

<script>
    function uploadForm(contentType) {
        return {
            formData: {
                title: '',
                season_id: '',
                content_type: contentType
            },
            errors: [],
            success: '',
            loading: false,
            uploading: false,
            uploadProgress: 0,
            selectedFile: null,
            dragOver: false,
            
            handleFileSelect(event) {
                this.selectedFile = event.target.files[0];
            },
            
            handleDrop(event) {
                this.dragOver = false;
                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    this.selectedFile = files[0];
                    this.$refs.fileInput.files = files;
                }
            },
            
            clearFile() {
                this.selectedFile = null;
                this.$refs.fileInput.value = '';
            },
            
            async submitUpload() {
                this.loading = true;
                this.uploading = true;
                this.errors = [];
                this.success = '';
                this.uploadProgress = 0;
                
                try {
                    const formData = new FormData();
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    formData.append('title', this.formData.title);
                    formData.append('season_id', this.formData.season_id);
                    formData.append('content_type', this.formData.content_type);
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
                                this.success = response.message || 'Video uploaded successfully!';
                                this.formData.title = '';
                                this.formData.season_id = '';
                                this.clearFile();
                                // Reload after short delay to update the form
                                setTimeout(() => window.location.reload(), 2000);
                            } else {
                                this.errors = response.errors || [response.error || 'Upload failed.'];
                            }
                        } catch (e) {
                            this.errors = ['Server error. Please try again.'];
                        }
                    };
                    
                    xhr.onerror = () => {
                        this.uploading = false;
                        this.loading = false;
                        this.errors = ['Network error. Please try again.'];
                    };
                    
                    xhr.open('POST', '/api/upload');
                    xhr.send(formData);
                    
                } catch (err) {
                    this.uploading = false;
                    this.loading = false;
                    this.errors = ['An error occurred. Please try again.'];
                }
            }
        };
    }
</script>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

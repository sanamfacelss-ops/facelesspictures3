<?php
if (!is_authenticated()) redirect('/login');
$user = auth_user();
$seasonModel = new App\Models\Season();
$userModel = new App\Models\User();
$seasons = $seasonModel->all();
$title = 'Upload - ' . APP_NAME;
require_once __DIR__ . '/partials/head.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold mb-2">Upload Video</h1>
    <p class="text-gray-600 text-sm mb-6">One video per season. Max size <?= e(UPLOAD_MAX_SIZE) ?>.</p>

    <div class="bg-white p-6 rounded-xl shadow" x-data="authForm">
        <template x-if="errors.length">
            <div class="mb-4 bg-red-50 text-red-700 p-3 rounded text-sm">
                <ul>
                    <template x-for="err in errors" :key="err">
                        <li x-text="err"></li>
                    </template>
                </ul>
            </div>
        </template>

        <form @submit.prevent="submit('/api/upload')" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Video Title</label>
                <input type="text" name="title" required minlength="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Season</label>
                <select name="season_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                    <option value="">Select season</option>
                    <?php foreach ($seasons as $s):
                        $disabled = $userModel->existsForSeason((int) $user['id'], (int) $s['id']);
                    ?>
                        <option value="<?= $s['id'] ?>" <?= $disabled ? 'disabled' : '' ?>>
                            <?= e($s['title']) ?> (<?= e($s['start_date']) ?> - <?= e($s['end_date']) ?>) <?= $disabled ? ' [Submitted]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Video File</label>
                <input type="file" name="video" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" required class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                <p class="text-xs text-gray-500 mt-1">MP4, MOV, AVI, WEBM accepted.</p>
            </div>
            <button type="submit" :disabled="loading" class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold hover:bg-gray-800 transition disabled:opacity-50">
                <span x-show="!loading">Upload Video</span>
                <span x-show="loading">Uploading...</span>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

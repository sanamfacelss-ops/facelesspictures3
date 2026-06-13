<?php
if (!is_authenticated()) redirect('/login');
$user = auth_user();
$videoModel = new App\Models\Video();
$seasonModel = new App\Models\Season();
$videos = $videoModel->byUser((int) $user['id']);
$seasons = $seasonModel->all();
$title = 'Dashboard - ' . APP_NAME;
require_once __DIR__ . '/partials/head.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <p class="text-gray-600 text-sm mt-1">Manage your submissions and track progress.</p>
        </div>
        <a href="/upload" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 transition">Upload New Video</a>
    </div>

    <?php $msg = flash('success'); if ($msg): ?>
        <div class="mb-6 bg-green-50 text-green-700 p-3 rounded text-sm"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow">
            <p class="text-sm text-gray-500">Total Submissions</p>
            <p class="text-3xl font-bold mt-1"><?= count($videos) ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow">
            <p class="text-sm text-gray-500">Pending</p>
            <p class="text-3xl font-bold mt-1"><?= count(array_filter($videos, fn($v) => $v['status'] === 'pending')) ?></p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow">
            <p class="text-sm text-gray-500">Approved</p>
            <p class="text-3xl font-bold mt-1"><?= count(array_filter($videos, fn($v) => $v['status'] === 'approved')) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold">My Submissions</h2>
        </div>
        <?php if (empty($videos)): ?>
            <div class="p-6 text-center text-gray-500 text-sm">No submissions yet. <a href="/upload" class="text-gray-900 underline">Upload your first video</a>.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Title</th>
                            <th class="px-6 py-3 font-medium">Season</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">YouTube</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($videos as $v): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium"><?= e($v['title']) ?></td>
                            <td class="px-6 py-3"><?= e($v['season_title']) ?></td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium <?= match($v['status']) { 'approved' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700', default => 'bg-yellow-100 text-yellow-700' } ?>">
                                    <?= ucfirst(e($v['status'])) ?>
                                </span>
                                <?php if ($v['status'] === 'rejected' && $v['rejection_reason']): ?>
                                    <span class="block text-xs text-red-600 mt-1"><?= e($v['rejection_reason']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3">
                                <?php if ($v['youtube_id']): ?>
                                    <a href="https://youtube.com/watch?v=<?= e($v['youtube_id']) ?>" target="_blank" class="text-blue-600 hover:underline text-xs">View</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-gray-500"><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

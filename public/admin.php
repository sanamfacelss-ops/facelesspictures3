<?php
if (!is_admin()) redirect('/dashboard');
$videoModel = new App\Models\Video();
$pending = $videoModel->pending();
$title = 'Admin - ' . APP_NAME;
require_once __DIR__ . '/partials/head.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8" x-data="moderation()">
    <h1 class="text-2xl font-bold mb-6">Admin Moderation</h1>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold">Pending Videos (<?= count($pending) ?>)</h2>
            <button @click="fetchPending()" class="text-sm text-gray-600 hover:text-gray-900">Refresh</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-6 py-3 font-medium">ID</th>
                        <th class="px-6 py-3 font-medium">Title</th>
                        <th class="px-6 py-3 font-medium">User</th>
                        <th class="px-6 py-3 font-medium">Season</th>
                        <th class="px-6 py-3 font-medium">Date</th>
                        <th class="px-6 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="v in pendingVideos" :key="v.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3" x-text="v.id"></td>
                            <td class="px-6 py-3 font-medium" x-text="v.title"></td>
                            <td class="px-6 py-3" x-text="v.user_name"></td>
                            <td class="px-6 py-3" x-text="v.season_title"></td>
                            <td class="px-6 py-3 text-gray-500" x-text="new Date(v.created_at).toLocaleDateString()"></td>
                            <td class="px-6 py-3 space-x-2">
                                <button @click="approve(v.id)" class="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition">Approve</button>
                                <button @click="reject(v.id)" class="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">Reject</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="pendingVideos.length === 0">
                        <td colspan="6" class="px-6 py-6 text-center text-gray-500">No pending videos.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="modalOpen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="modalOpen = false">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-2" x-text="modalTitle"></h3>
            <p class="text-sm text-gray-600 mb-4">Provide a reason for this action.</p>
            <textarea x-model="modalReason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent mb-4"></textarea>
            <div class="flex justify-end gap-2">
                <button @click="modalOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                <button @click="confirmAction()" class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800" x-text="modalAction === 'approve' ? 'Approve' : 'Reject'"></button>
            </div>
        </div>
    </div>
</div>

<script>
function moderation() {
    return {
        pendingVideos: <?= json_encode($pending) ?>,
        modalOpen: false,
        modalAction: '',
        modalVideoId: null,
        modalTitle: '',
        modalReason: '',
        fetchPending() {
            fetch('/api/moderation/pending')
                .then(r => r.json())
                .then(data => this.pendingVideos = data)
                .catch(() => alert('Failed to load'));
        },
        approve(id) {
            this.modalAction = 'approve';
            this.modalVideoId = id;
            this.modalTitle = 'Approve Video';
            this.modalReason = 'Approved by admin';
            this.modalOpen = true;
        },
        reject(id) {
            this.modalAction = 'reject';
            this.modalVideoId = id;
            this.modalTitle = 'Reject Video';
            this.modalReason = '';
            this.modalOpen = true;
        },
        confirmAction() {
            const url = this.modalAction === 'approve'
                ? '/api/moderation/approve/' + this.modalVideoId
                : '/api/moderation/reject/' + this.modalVideoId;
            const formData = new FormData();
            formData.append('reason', this.modalReason);
            formData.append('csrf_token', '<?= csrf_token() ?>');
            fetch(url, { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw new Error('Failed');
                    this.modalOpen = false;
                    this.fetchPending();
                })
                .catch(() => alert('Action failed'));
        }
    };
}
</script>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

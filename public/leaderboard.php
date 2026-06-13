<?php
$title = 'Leaderboard - ' . APP_NAME;
$seasonModel = new App\Models\Season();
$seasons = $seasonModel->all();
require_once __DIR__ . '/partials/head.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8" x-data="leaderboard()" x-init="load()">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Leaderboard</h1>
            <p class="text-gray-600 text-sm mt-1">Rankings based on YouTube engagement.</p>
        </div>
        <select x-model="seasonId" @change="load()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">Overall</option>
            <?php foreach ($seasons as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-6 py-3 font-medium w-16">Rank</th>
                        <th class="px-6 py-3 font-medium">Video</th>
                        <th class="px-6 py-3 font-medium">Creator</th>
                        <th class="px-6 py-3 font-medium">Season</th>
                        <th class="px-6 py-3 font-medium">Views</th>
                        <th class="px-6 py-3 font-medium">Likes</th>
                        <th class="px-6 py-3 font-medium">Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-bold text-gray-900" x-text="index + 1"></td>
                            <td class="px-6 py-3">
                                <a :href="'https://youtube.com/watch?v=' + row.youtube_id" target="_blank" class="font-medium text-gray-900 hover:text-blue-600" x-text="row.video_title"></a>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center gap-1">
                                    <span x-text="row.user_name"></span>
                                    <span class="text-xs text-gray-500 capitalize" x-text="'(' + row.user_role + ')'"></span>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-600" x-text="row.season_title"></td>
                            <td class="px-6 py-3" x-text="Number(row.views).toLocaleString()"></td>
                            <td class="px-6 py-3" x-text="Number(row.likes).toLocaleString()"></td>
                            <td class="px-6 py-3 font-bold text-gray-900" x-text="Number(row.score).toLocaleString()"></td>
                        </tr>
                    </template>
                    <tr x-show="rows.length === 0">
                        <td colspan="7" class="px-6 py-6 text-center text-gray-500">No data available yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function leaderboard() {
    return {
        seasonId: '',
        rows: [],
        load() {
            const params = this.seasonId ? '?season_id=' + this.seasonId : '';
            fetch('/api/leaderboard' + params)
                .then(r => r.json())
                .then(data => {
                    this.rows = data.data || [];
                })
                .catch(() => this.rows = []);
        }
    };
}
</script>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

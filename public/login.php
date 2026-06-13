<?php $title = 'Login - ' . APP_NAME; require_once __DIR__ . '/partials/head.php'; ?>

<div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md" x-data="authForm">
        <h2 class="text-2xl font-bold mb-6 text-center">Welcome Back</h2>

        <template x-if="errors.length">
            <div class="mb-4 bg-red-50 text-red-700 p-3 rounded text-sm">
                <ul><li x-text="errors[0]"></li></ul>
            </div>
        </template>

        <form @submit.prevent="submit('/api/login')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <button type="submit" :disabled="loading" class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold hover:bg-gray-800 transition disabled:opacity-50">
                <span x-show="!loading">Sign In</span>
                <span x-show="loading">Signing in...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Don't have an account? <a href="/register" class="text-gray-900 font-medium underline">Register</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

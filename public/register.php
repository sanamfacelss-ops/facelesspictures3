<?php $title = 'Register - ' . APP_NAME; require_once __DIR__ . '/partials/head.php'; ?>

<div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md" x-data="authForm">
        <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>

        <template x-if="errors.length">
            <div class="mb-4 bg-red-50 text-red-700 p-3 rounded text-sm">
                <ul>
                    <template x-for="err in errors" :key="err">
                        <li x-text="err"></li>
                    </template>
                </ul>
            </div>
        </template>

        <form @submit.prevent="submit('/api/register')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent bg-white">
                    <option value="">Select your role</option>
                    <option value="actor">Actor</option>
                    <option value="director">Director</option>
                    <option value="writer">Writer</option>
                </select>
            </div>
            <button type="submit" :disabled="loading" class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold hover:bg-gray-800 transition disabled:opacity-50">
                <span x-show="!loading">Register</span>
                <span x-show="loading">Creating account...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            Already have an account? <a href="/login" class="text-gray-900 font-medium underline">Sign In</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

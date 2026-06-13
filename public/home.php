<?php $title = APP_NAME; require_once __DIR__ . '/partials/head.php'; ?>

<div class="relative bg-gray-900 text-white overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 py-24 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-6">Faceless Pictures 3</h1>
        <p class="text-lg md:text-xl text-gray-300 max-w-2xl mx-auto mb-8">
            A season-based video competition for Actors, Directors, and Writers. Upload your masterpiece, get AI-moderated, and climb the leaderboard.
        </p>
        <div class="flex justify-center gap-4">
            <a href="/register" class="bg-white text-gray-900 px-6 py-3 rounded-lg font-semibold hover:bg-gray-200 transition">Get Started</a>
            <a href="/leaderboard" class="border border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-gray-900 transition">View Leaderboard</a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-16 sm:px-6 lg:px-8">
    <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Upload</h3>
            <p class="text-gray-600 text-sm">Submit one video per season. Supported formats: MP4, MOV, AVI, WEBM.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">AI Moderation</h3>
            <p class="text-gray-600 text-sm">Every video is automatically checked for inappropriate content using local AI models.</p>
        </div>
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Leaderboard</h3>
            <p class="text-gray-600 text-sm">Track YouTube views, likes, and comments in real-time. Rankings updated daily.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/foot.php'; ?>

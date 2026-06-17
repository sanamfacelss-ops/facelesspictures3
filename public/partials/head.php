<!DOCTYPE html>
<html lang="en" x-data="{ open: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <?php if (file_exists(__DIR__ . '/../assets/css/custom.css')): ?>
    <link rel="stylesheet" href="/assets/css/custom.css"><?php endif; ?>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
<nav class="bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <a href="/" class="flex items-center gap-2">
                    <span class="text-xl font-bold tracking-tight">FACELESS PICTURES</span>
                    <span style="display: inline-flex; align-items: center; justify-content: center; background: #D92B3A; color: white; font-size: 11px; font-weight: bold; width: 20px; height: 20px; border-radius: 50%;">3</span>
                </a>
                <div class="hidden md:flex space-x-4">
                    <?php if (is_authenticated()): ?>
                        <a href="/dashboard" class="hover:text-gray-300 transition">Dashboard</a>
                        <a href="/upload" class="hover:text-gray-300 transition">Upload</a>
                        <?php if (is_admin()): ?>
                            <a href="/admin" class="hover:text-gray-300 transition">Admin</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hidden md:flex items-center space-x-4">
                <?php if (is_authenticated()): ?>
                    <span class="text-sm text-gray-300">Hi, <?= e(auth_user()['name']) ?></span>
                    <form action="/api/logout" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="text-sm hover:text-gray-300 transition">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="/login" class="text-sm hover:text-gray-300 transition">Login</a>
                    <a href="/register" class="text-sm bg-white text-gray-900 px-3 py-1.5 rounded hover:bg-gray-200 transition">Register</a>
                <?php endif; ?>
            </div>
            <div class="md:hidden">
                <button @click="open = !open" class="p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </div>
    <div x-show="open" x-cloak class="md:hidden px-4 pb-4 space-y-2">
        <?php if (is_authenticated()): ?>
            <a href="/dashboard" class="block hover:text-gray-300">Dashboard</a>
            <a href="/upload" class="block hover:text-gray-300">Upload</a>
            <?php if (is_admin()): ?>
                <a href="/admin" class="block hover:text-gray-300">Admin</a>
            <?php endif; ?>
            <form action="/api/logout" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="text-sm hover:text-gray-300">Logout</button>
            </form>
        <?php else: ?>
            <a href="/login" class="block hover:text-gray-300">Login</a>
            <a href="/register" class="block hover:text-gray-300">Register</a>
        <?php endif; ?>
    </div>
</nav>
<main class="flex-grow">

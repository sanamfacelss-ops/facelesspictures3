<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            </div>
        </div>
    </div>
</nav>
<main class="flex-grow">

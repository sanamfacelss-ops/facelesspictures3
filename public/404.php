<?php
require_once __DIR__ . '/../app/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="text-center">
        <h1 class="text-6xl font-extrabold text-gray-300">404</h1>
        <p class="text-xl text-gray-600 mt-4">Page not found.</p>
        <a href="/" class="mt-6 inline-block bg-gray-900 text-white px-6 py-3 rounded-lg font-medium hover:bg-gray-800 transition">
            Go Home
        </a>
    </div>
</body>
</html>

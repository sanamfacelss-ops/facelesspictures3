<?php
require_once __DIR__ . '/../app/config/config.php';

// If already logged in as admin, redirect to admin panel
if (is_admin()) {
    header('Location: /admin');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            header('Location: /admin');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } catch (\Exception $e) {
        $error = 'Login error: ' . $e->getMessage();
    }
}

$logoUrl = setting('site_logo_url', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: '#1a1a1a',
                        cream: '#F5F5DC',
                        crimson: '#D92B3A'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .login-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.98) 100%);
            backdrop-filter: blur(20px);
        }
        .input-field {
            transition: all 0.2s ease;
        }
        .input-field:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(217, 43, 58, 0.1);
        }
        .login-btn {
            background: linear-gradient(135deg, #D92B3A 0%, #B91C2E 100%);
            transition: all 0.3s ease;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(217, 43, 58, 0.3);
        }
        .login-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 min-h-screen flex items-center justify-center px-4 relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-72 h-72 bg-crimson/5 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-dark/5 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo Section -->
        <?php if ($logoUrl): ?>
            <div class="text-center mb-8">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= APP_NAME ?>" class="h-16 mx-auto">
            </div>
        <?php else: ?>
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-dark text-white px-6 py-3 rounded-xl shadow-lg">
                    <span class="text-xl font-bold tracking-tight">FACELESS PICTURES</span>
                    <span class="flex items-center justify-center bg-crimson text-white text-xs font-bold w-6 h-6 rounded-full">3</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Login Card -->
        <div class="login-card rounded-2xl shadow-2xl p-8 md:p-10 border border-white/50">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-dark mb-2">Admin Login</h1>
                <p class="text-dark/60 text-sm">Access your dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-crimson rounded-lg">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-crimson flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm text-crimson font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/admin-login" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-dark/80 mb-2">Email Address</label>
                    <input type="email" name="email" required autofocus
                        class="input-field w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-crimson/50 focus:border-crimson bg-white/80 text-dark placeholder-gray-400 transition-all"
                        placeholder="admin@example.com">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-dark/80 mb-2">Password</label>
                    <input type="password" name="password" required
                        class="input-field w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-crimson/50 focus:border-crimson bg-white/80 text-dark placeholder-gray-400 transition-all"
                        placeholder="••••••••••">
                </div>
                
                <button type="submit" 
                    class="login-btn w-full text-white py-4 rounded-xl font-semibold text-base shadow-lg">
                    Login to Admin Panel
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-200/50 text-center">
                <a href="/" class="text-sm text-dark/60 hover:text-crimson transition-colors inline-flex items-center gap-2 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-xs text-dark/40">© <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

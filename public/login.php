<?php
require_once __DIR__ . '/../app/config/config.php';
$title = 'Login — Faceless Pitcher 3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: '#F8F5F0',
                        crimson: '#D92B3A',
                        gold: '#C9943A',
                        dark: '#0D0D0D',
                        charcoal: '#141414',
                    },
                    fontFamily: {
                        display: ['Bebas Neue', 'sans-serif'],
                        body: ['DM Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'DM Sans', sans-serif; 
            -webkit-font-smoothing: antialiased;
        }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        [x-cloak] { display: none !important; }
        
        .input-field { transition: all 0.3s ease; }
        .input-field:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(217, 43, 58, 0.15);
        }
        
        .btn-primary { transition: all 0.3s ease; }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(217, 43, 58, 0.3);
        }
        
        .pattern-bg {
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(217, 43, 58, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(201, 148, 58, 0.03) 0%, transparent 50%);
        }
    </style>
</head>

<body class="min-h-screen bg-cream" x-data="loginForm()">
    <div class="min-h-screen flex">
        
        <!-- LEFT SIDE — Season 3 Info Panel -->
        <div class="hidden lg:flex lg:w-1/2 bg-charcoal relative overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute top-20 left-10 w-32 h-32 bg-crimson/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-40 right-10 w-48 h-48 bg-gold/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-crimson/5 rounded-full blur-3xl"></div>
            </div>
            
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 50px 50px;"></div>
            
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-20">
                <a href="/" class="flex items-center gap-3 mb-12">
                    <span class="font-display text-[24px] tracking-wide text-white">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[10px] font-semibold px-2.5 py-1 rounded-full">S3</span>
                </a>
                
                <div class="mb-8">
                    <span class="inline-flex items-center gap-2 mb-4">
                        <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                        <span class="text-[11px] font-semibold tracking-[3px] uppercase text-white/40">Season 3 — Now Open</span>
                    </span>
                    <h1 class="font-display text-[52px] xl:text-[64px] leading-[0.95] text-white mb-4">
                        NO FACE.<br>
                        JUST <span class="text-crimson">TALENT.</span>
                    </h1>
                    <p class="text-[16px] text-white/50 leading-relaxed max-w-[400px]">
                        India's first anonymous film competition. Actors, Directors & Writers compete purely on talent. The world votes. Winners get cast.
                    </p>
                </div>
                
                <div class="space-y-4 mb-10">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-crimson/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-[15px] mb-1">Submit Your Best Work</h3>
                            <p class="text-white/40 text-[13px]">One video. Under 3 minutes. Shot on any device.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-gold/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-[15px] mb-1">Auto-Published to YouTube</h3>
                            <p class="text-white/40 text-[13px]">Your submission goes live immediately. Views = votes.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-[#4A6CF7]/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#4A6CF7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-[15px] mb-1">Winners Get Cast</h3>
                            <p class="text-white/40 text-[13px]">Top performers from each category join real productions.</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-6 pt-8 border-t border-white/10">
                    <div>
                        <span class="font-display text-[32px] text-white block leading-none">2,400+</span>
                        <span class="text-[12px] text-white/30 mt-1">Submissions</span>
                    </div>
                    <div>
                        <span class="font-display text-[32px] text-crimson block leading-none">6</span>
                        <span class="text-[12px] text-white/30 mt-1">Winners Cast</span>
                    </div>
                    <div>
                        <span class="font-display text-[32px] text-white block leading-none">18</span>
                        <span class="text-[12px] text-white/30 mt-1">Cities</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT SIDE — Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-8 pattern-bg">
            <div class="w-full max-w-[420px]">
                
                <div class="lg:hidden flex items-center justify-center gap-2 mb-8">
                    <a href="/" class="flex items-center gap-2">
                        <span class="font-display text-[20px] tracking-wide text-dark">FACELESS PITCHER</span>
                        <span class="bg-crimson text-white text-[9px] font-semibold px-2 py-0.5 rounded-full">S3</span>
                    </a>
                </div>
                
                <div class="text-center mb-8">
                    <h2 class="font-display text-[40px] sm:text-[48px] leading-none text-dark mb-3">WELCOME BACK</h2>
                    <p class="text-[15px] text-dark/50">Sign in to continue your journey</p>
                </div>
                
                <template x-if="errors.length > 0">
                    <div class="mb-6 bg-crimson/10 border border-crimson/20 rounded-xl p-4" x-cloak>
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-crimson flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <ul class="text-[14px] text-crimson space-y-1">
                                <template x-for="error in errors" :key="error">
                                    <li x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
                
                <form @submit.prevent="submitLogin">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="mb-5">
                        <label class="block text-[13px] font-semibold text-dark/70 mb-2 tracking-wide uppercase">Email Address</label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-dark/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <input 
                                type="email" 
                                name="email" 
                                x-model="formData.email"
                                required 
                                placeholder="you@example.com"
                                class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-4 py-4 text-[15px] text-dark placeholder:text-dark/30 focus:outline-none focus:border-crimson"
                            >
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-[13px] font-semibold text-dark/70 tracking-wide uppercase">Password</label>
                            <a href="/forgot-password" class="text-[12px] text-crimson font-medium hover:underline">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-dark/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input 
                                :type="showPassword ? 'text' : 'password'" 
                                name="password" 
                                x-model="formData.password"
                                required 
                                placeholder="••••••••"
                                class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-12 py-4 text-[15px] text-dark placeholder:text-dark/30 focus:outline-none focus:border-crimson"
                            >
                            <button 
                                type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-dark/30 hover:text-dark/60 transition"
                            >
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button 
                        type="submit" 
                        :disabled="loading"
                        class="btn-primary w-full bg-crimson text-white font-semibold text-[15px] py-4 rounded-xl hover:bg-crimson/90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <span x-show="!loading">Sign In</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Signing in...
                        </span>
                    </button>
                </form>
                
                <div class="flex items-center gap-4 my-8">
                    <div class="flex-1 h-px bg-dark/10"></div>
                    <span class="text-[12px] text-dark/30 font-medium uppercase tracking-wider">or</span>
                    <div class="flex-1 h-px bg-dark/10"></div>
                </div>
                
                <button class="w-full bg-white border-2 border-dark/10 rounded-xl py-4 flex items-center justify-center gap-3 text-[14px] font-medium text-dark hover:border-dark/20 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>
                
                <p class="text-center mt-8 text-[14px] text-dark/50">
                    Don't have an account? 
                    <a href="/register" class="text-crimson font-semibold hover:underline">Join Season 3</a>
                </p>
                
                <div class="text-center mt-6">
                    <a href="/" class="inline-flex items-center gap-2 text-[13px] text-dark/40 hover:text-dark/60 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to home
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginForm', () => ({
                formData: {
                    email: '',
                    password: ''
                },
                errors: [],
                loading: false,
                showPassword: false,
                
                async submitLogin() {
                    this.loading = true;
                    this.errors = [];
                    
                    try {
                        const form = this.$el.querySelector('form');
                        const formData = new FormData(form);
                        
                        const response = await fetch('/api/login', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (!response.ok) {
                            this.errors = data.errors || [data.error || 'Invalid email or password.'];
                        } else if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    } catch (err) {
                        this.errors = ['Network error. Please try again.'];
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>

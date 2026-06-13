<?php
require_once __DIR__ . '/../app/config/config.php';
$title = 'Join Season 3 — Faceless Pitcher';
$preselectedRole = $_GET['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
                        blue: '#4A6CF7',
                    },
                    fontFamily: {
                        display: ['Bebas Neue', 'sans-serif'],
                        body: ['DM Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; -webkit-font-smoothing: antialiased; }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        [x-cloak] { display: none !important; }
        .input-field { transition: all 0.3s ease; }
        .input-field:focus { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(217, 43, 58, 0.15); }
        .btn-primary { transition: all 0.3s ease; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(217, 43, 58, 0.3); }
        .pattern-bg {
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(217, 43, 58, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(201, 148, 58, 0.03) 0%, transparent 50%);
        }
        .role-card { transition: all 0.3s ease; }
        .role-card:hover { transform: translateY(-2px); }
        .role-card.selected { transform: scale(1.02); }
    </style>
</head>

<body class="min-h-screen bg-cream">
    <div class="min-h-screen flex" x-data="registerForm('<?= e($preselectedRole) ?>')"">
        
        <!-- LEFT SIDE — Season 3 Info Panel -->
        <div class="hidden lg:flex lg:w-1/2 bg-charcoal relative overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute top-20 left-10 w-32 h-32 bg-crimson/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-40 right-10 w-48 h-48 bg-gold/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-blue/5 rounded-full blur-3xl"></div>
            </div>
            
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 50px 50px;"></div>
            
            <div class="relative z-10 flex flex-col justify-center px-12 xl:px-20 py-12">
                <a href="/" class="flex items-center gap-3 mb-10">
                    <span class="font-display text-[24px] tracking-wide text-white">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[10px] font-semibold px-2.5 py-1 rounded-full">S3</span>
                </a>
                
                <div class="mb-8">
                    <span class="inline-flex items-center gap-2 mb-4">
                        <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                        <span class="text-[11px] font-semibold tracking-[3px] uppercase text-white/40">Season 3 — Registrations Open</span>
                    </span>
                    <h1 class="font-display text-[44px] xl:text-[56px] leading-[0.95] text-white mb-4">
                        YOUR TALENT.<br>
                        YOUR <span class="text-crimson">MOMENT.</span>
                    </h1>
                    <p class="text-[15px] text-white/50 leading-relaxed max-w-[380px]">
                        Join thousands of actors, directors, and writers competing anonymously. No connections needed — just pure talent.
                    </p>
                </div>
                
                <!-- Season 3 Brief -->
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-crimson/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold tracking-[2px] uppercase text-crimson">Season 3 Brief</span>
                            <h3 class="text-white font-semibold text-[16px]">The Turning Point</h3>
                        </div>
                    </div>
                    <p class="text-white/60 text-[14px] leading-relaxed italic">
                        "Show us the moment everything changed — the split second when your character's world shifts forever. Under 3 minutes. Any genre. Any style."
                    </p>
                </div>

                <!-- What you get -->
                <div class="space-y-3">
                    <h4 class="text-[11px] font-semibold tracking-[2px] uppercase text-white/30 mb-3">What You Get</h4>
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-white/60 text-[14px]">Your video auto-published to YouTube</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-white/60 text-[14px]">Real-time leaderboard ranking</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-white/60 text-[14px]">Winners get cast in real productions</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-white/60 text-[14px]">100% anonymous until you choose to reveal</span>
                    </div>
                </div>
                
                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-4 pt-8 mt-8 border-t border-white/10">
                    <div>
                        <span class="font-display text-[28px] text-white block leading-none">2,400+</span>
                        <span class="text-[11px] text-white/30">Season 2 Entries</span>
                    </div>
                    <div>
                        <span class="font-display text-[28px] text-crimson block leading-none">6</span>
                        <span class="text-[11px] text-white/30">Winners Cast</span>
                    </div>
                    <div>
                        <span class="font-display text-[28px] text-white block leading-none">FREE</span>
                        <span class="text-[11px] text-white/30">To Enter</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT SIDE — Registration Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-8 pattern-bg overflow-y-auto">
            <div class="w-full max-w-[440px] py-8">
                
                <div class="lg:hidden flex items-center justify-center gap-2 mb-6">
                    <a href="/" class="flex items-center gap-2">
                        <span class="font-display text-[20px] tracking-wide text-dark">FACELESS PITCHER</span>
                        <span class="bg-crimson text-white text-[9px] font-semibold px-2 py-0.5 rounded-full">S3</span>
                    </a>
                </div>
                
                <div class="text-center mb-6">
                    <h2 class="font-display text-[36px] sm:text-[44px] leading-none text-dark mb-2">JOIN SEASON 3</h2>
                    <p class="text-[14px] text-dark/50">Create your account and start competing</p>
                </div>

                <!-- Error Messages -->
                <template x-if="errors.length > 0">
                    <div class="mb-5 bg-crimson/10 border border-crimson/20 rounded-xl p-4" x-cloak>
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-crimson flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <ul class="text-[13px] text-crimson space-y-1">
                                <template x-for="error in errors" :key="error">
                                    <li x-text="error"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
                
                <form @submit.prevent="submitRegister" x-ref="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <!-- Role Selection -->
                    <div class="mb-5">
                        <label class="block text-[12px] font-semibold text-dark/70 mb-3 tracking-wide uppercase">Choose Your Role</label>
                        <div class="grid grid-cols-3 gap-3">
                            <!-- Actor -->
                            <label class="role-card cursor-pointer">
                                <input type="radio" name="role" value="actor" x-model="formData.role" class="hidden">
                                <div class="border-2 rounded-xl p-3 text-center transition-all"
                                     :class="formData.role === 'actor' ? 'border-crimson bg-crimson/5 selected' : 'border-dark/10 bg-white hover:border-crimson/30'">
                                    <span class="text-2xl block mb-1">🎭</span>
                                    <span class="font-display text-[18px] block" :class="formData.role === 'actor' ? 'text-crimson' : 'text-dark'">ACTOR</span>
                                    <span class="text-[10px] text-dark/40">Perform</span>
                                </div>
                            </label>
                            <!-- Director -->
                            <label class="role-card cursor-pointer">
                                <input type="radio" name="role" value="director" x-model="formData.role" class="hidden">
                                <div class="border-2 rounded-xl p-3 text-center transition-all"
                                     :class="formData.role === 'director' ? 'border-gold bg-gold/5 selected' : 'border-dark/10 bg-white hover:border-gold/30'">
                                    <span class="text-2xl block mb-1">🎬</span>
                                    <span class="font-display text-[18px] block" :class="formData.role === 'director' ? 'text-gold' : 'text-dark'">DIRECTOR</span>
                                    <span class="text-[10px] text-dark/40">Direct</span>
                                </div>
                            </label>
                            <!-- Writer -->
                            <label class="role-card cursor-pointer">
                                <input type="radio" name="role" value="writer" x-model="formData.role" class="hidden">
                                <div class="border-2 rounded-xl p-3 text-center transition-all"
                                     :class="formData.role === 'writer' ? 'border-blue bg-blue/5 selected' : 'border-dark/10 bg-white hover:border-blue/30'">
                                    <span class="text-2xl block mb-1">✍️</span>
                                    <span class="font-display text-[18px] block" :class="formData.role === 'writer' ? 'text-blue' : 'text-dark'">WRITER</span>
                                    <span class="text-[10px] text-dark/40">Write</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="mb-4">
                        <label class="block text-[12px] font-semibold text-dark/70 mb-2 tracking-wide uppercase">Full Name</label>
                        <div class="relative">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-dark/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                name="name" 
                                x-model="formData.name"
                                required 
                                placeholder="Your full name"
                                class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-4 py-3.5 text-[14px] text-dark placeholder:text-dark/30 focus:outline-none focus:border-crimson"
                            >
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-4">
                        <label class="block text-[12px] font-semibold text-dark/70 mb-2 tracking-wide uppercase">Email Address</label>
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
                                class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-4 py-3.5 text-[14px] text-dark placeholder:text-dark/30 focus:outline-none focus:border-crimson"
                            >
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-4">
                        <label class="block text-[12px] font-semibold text-dark/70 mb-2 tracking-wide uppercase">Password</label>
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
                                minlength="6"
                                placeholder="Min 6 characters"
                                class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-12 py-3.5 text-[14px] text-dark placeholder:text-dark/30 focus:outline-none focus:border-crimson"
                            >
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-dark/30 hover:text-dark/60 transition">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <!-- Password strength indicator -->
                        <div class="mt-2 h-1 bg-dark/10 rounded-full overflow-hidden" x-show="formData.password.length > 0">
                            <div class="h-full transition-all duration-300 rounded-full"
                                 :class="{
                                     'w-1/4 bg-crimson': formData.password.length < 6,
                                     'w-2/4 bg-gold': formData.password.length >= 6 && formData.password.length < 10,
                                     'w-3/4 bg-blue': formData.password.length >= 10 && formData.password.length < 14,
                                     'w-full bg-green-500': formData.password.length >= 14
                                 }"></div>
                        </div>
                    </div>

                    <!-- Terms Agreement -->
                    <div class="mb-6">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" x-model="formData.terms" class="mt-1 w-4 h-4 rounded border-dark/20 text-crimson focus:ring-crimson">
                            <span class="text-[13px] text-dark/60 leading-relaxed">
                                I agree to the <a href="#" class="text-crimson hover:underline">Terms of Service</a> and <a href="#" class="text-crimson hover:underline">Privacy Policy</a>. I understand my submission will be published anonymously.
                            </span>
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        :disabled="loading || !formData.role || !formData.terms"
                        class="btn-primary w-full bg-crimson text-white font-semibold text-[15px] py-4 rounded-xl hover:bg-crimson/90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <span x-show="!loading">Create Account & Join Season 3</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating account...
                        </span>
                    </button>
                </form>
                
                <!-- Divider -->
                <div class="flex items-center gap-4 my-6">
                    <div class="flex-1 h-px bg-dark/10"></div>
                    <span class="text-[11px] text-dark/30 font-medium uppercase tracking-wider">or</span>
                    <div class="flex-1 h-px bg-dark/10"></div>
                </div>
                
                <!-- Social Signup -->
                <button class="w-full bg-white border-2 border-dark/10 rounded-xl py-3.5 flex items-center justify-center gap-3 text-[13px] font-medium text-dark hover:border-dark/20 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign up with Google
                </button>
                
                <!-- Login Link -->
                <p class="text-center mt-6 text-[13px] text-dark/50">
                    Already have an account? 
                    <a href="/login" class="text-crimson font-semibold hover:underline">Sign In</a>
                </p>
                
                <!-- Back to home -->
                <div class="text-center mt-4">
                    <a href="/" class="inline-flex items-center gap-2 text-[12px] text-dark/40 hover:text-dark/60 transition">
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
            Alpine.data('registerForm', (preselectedRole) => ({
                formData: {
                    name: '',
                    email: '',
                    password: '',
                    role: preselectedRole || '',
                    terms: false
                },
                errors: [],
                loading: false,
                showPassword: false,
                
                async submitRegister() {
                    this.loading = true;
                    this.errors = [];
                    
                    // Client-side validation
                    if (!this.formData.role) {
                        this.errors.push('Please select a role.');
                        this.loading = false;
                        return;
                    }
                    
                    if (!this.formData.terms) {
                        this.errors.push('Please agree to the terms and conditions.');
                        this.loading = false;
                        return;
                    }
                    
                    try {
                        const form = this.$refs.registerForm;
                        const formData = new FormData(form);
                        
                        const response = await fetch('/api/register', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (!response.ok) {
                            this.errors = data.errors || [data.error || 'Registration failed. Please try again.'];
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

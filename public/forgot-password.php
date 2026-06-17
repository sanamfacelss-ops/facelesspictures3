<?php
require_once __DIR__ . '/../app/config/config.php';
$title = 'Forgot Password — Faceless Pictures 3';
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
        body { font-family: 'DM Sans', sans-serif; -webkit-font-smoothing: antialiased; }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        [x-cloak] { display: none !important; }
        .input-field { transition: all 0.3s ease; }
        .input-field:focus { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(217, 43, 58, 0.15); }
    </style>
</head>

<body class="min-h-screen bg-cream flex items-center justify-center p-6" x-data="forgotPassword()">
    <div class="w-full max-w-[420px]">
        
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center gap-2">
                <span class="font-display text-[22px] text-dark">FACELESS PICTURES</span>
                <span style="display: inline-flex; align-items: center; justify-content: center; background: #D92B3A; color: white; font-size: 12px; font-weight: bold; width: 24px; height: 24px; border-radius: 50%;">3</span>
            </a>
        </div>

        <!-- Step 1: Enter Email -->
        <div x-show="step === 1" class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-crimson/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <h2 class="font-display text-[32px] text-dark">FORGOT PASSWORD</h2>
                <p class="text-[14px] text-dark/50 mt-1">Enter your email to receive a reset OTP</p>
            </div>
            
            <template x-if="error">
                <div class="mb-4 bg-crimson/10 border border-crimson/20 rounded-xl p-3 text-[13px] text-crimson" x-text="error"></div>
            </template>
            
            <template x-if="message">
                <div class="mb-4 bg-green-50 border border-green-200 rounded-xl p-3 text-[13px] text-green-700" x-text="message"></div>
            </template>
            
            <form @submit.prevent="sendOTP">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="mb-5">
                    <label class="block text-[12px] font-semibold text-dark/70 mb-2 uppercase tracking-wide">Email Address</label>
                    <div class="relative">
                        <div class="absolute left-4 top-1/2 -translate-y-1/2 text-dark/30">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="email" x-model="email" required placeholder="you@example.com"
                               class="input-field w-full bg-white border-2 border-dark/10 rounded-xl pl-12 pr-4 py-4 text-[15px] focus:outline-none focus:border-crimson">
                    </div>
                </div>
                
                <button type="submit" :disabled="loading" 
                        class="w-full bg-crimson text-white font-semibold py-4 rounded-xl hover:bg-crimson/90 disabled:opacity-50 transition">
                    <span x-show="!loading">Send OTP</span>
                    <span x-show="loading">Sending...</span>
                </button>
            </form>
            
            <p class="text-center mt-6 text-[13px] text-dark/50">
                Remember your password? <a href="/login" class="text-crimson font-semibold hover:underline">Sign In</a>
            </p>
        </div>

        <!-- Step 2: Enter OTP -->
        <div x-show="step === 2" x-cloak class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-gold/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="font-display text-[32px] text-dark">ENTER OTP</h2>
                <p class="text-[14px] text-dark/50 mt-1">Check your email for the 6-digit code</p>
                <p class="text-[12px] text-dark/30 mt-2" x-text="email"></p>
            </div>
            
            <template x-if="error">
                <div class="mb-4 bg-crimson/10 border border-crimson/20 rounded-xl p-3 text-[13px] text-crimson" x-text="error"></div>
            </template>
            
            <form @submit.prevent="verifyOTP">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="mb-5">
                    <label class="block text-[12px] font-semibold text-dark/70 mb-2 uppercase tracking-wide text-center">Enter 6-Digit OTP</label>
                    <div class="flex justify-center gap-2">
                        <template x-for="(digit, index) in otpDigits" :key="index">
                            <input type="text" maxlength="1" 
                                   x-model="otpDigits[index]"
                                   @input="handleOTPInput($event, index)"
                                   @keydown.backspace="handleBackspace($event, index)"
                                   @paste="handlePaste($event)"
                                   class="w-12 h-14 text-center text-[24px] font-bold border-2 border-dark/10 rounded-xl focus:outline-none focus:border-crimson transition"
                                   :id="'otp-' + index">
                        </template>
                    </div>
                </div>
                
                <button type="submit" :disabled="loading || otpDigits.join('').length !== 6" 
                        class="w-full bg-crimson text-white font-semibold py-4 rounded-xl hover:bg-crimson/90 disabled:opacity-50 transition">
                    <span x-show="!loading">Verify OTP</span>
                    <span x-show="loading">Verifying...</span>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <button @click="step = 1; error = ''" class="text-[13px] text-dark/50 hover:text-dark transition">
                    ← Change email
                </button>
            </div>
            
            <div class="text-center mt-4">
                <button @click="resendOTP" :disabled="resendCooldown > 0" class="text-[13px] text-crimson hover:underline disabled:text-dark/30 disabled:no-underline">
                    <span x-show="resendCooldown === 0">Resend OTP</span>
                    <span x-show="resendCooldown > 0">Resend in <span x-text="resendCooldown"></span>s</span>
                </button>
            </div>
        </div>

        <!-- Step 3: New Password -->
        <div x-show="step === 3" x-cloak class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="font-display text-[32px] text-dark">NEW PASSWORD</h2>
                <p class="text-[14px] text-dark/50 mt-1">Create a strong new password</p>
            </div>
            
            <template x-if="error">
                <div class="mb-4 bg-crimson/10 border border-crimson/20 rounded-xl p-3 text-[13px] text-crimson" x-text="error"></div>
            </template>
            
            <form @submit.prevent="resetPassword">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="mb-4">
                    <label class="block text-[12px] font-semibold text-dark/70 mb-2 uppercase tracking-wide">New Password</label>
                    <input type="password" x-model="password" required minlength="6" placeholder="Min 6 characters"
                           class="input-field w-full bg-white border-2 border-dark/10 rounded-xl px-4 py-4 text-[15px] focus:outline-none focus:border-crimson">
                </div>
                
                <div class="mb-5">
                    <label class="block text-[12px] font-semibold text-dark/70 mb-2 uppercase tracking-wide">Confirm Password</label>
                    <input type="password" x-model="confirmPassword" required placeholder="Re-enter password"
                           class="input-field w-full bg-white border-2 border-dark/10 rounded-xl px-4 py-4 text-[15px] focus:outline-none focus:border-crimson">
                </div>
                
                <button type="submit" :disabled="loading || password.length < 6 || password !== confirmPassword" 
                        class="w-full bg-crimson text-white font-semibold py-4 rounded-xl hover:bg-crimson/90 disabled:opacity-50 transition">
                    <span x-show="!loading">Reset Password</span>
                    <span x-show="loading">Resetting...</span>
                </button>
            </form>
        </div>
        
        <!-- Back to home -->
        <div class="text-center mt-6">
            <a href="/" class="inline-flex items-center gap-2 text-[13px] text-dark/40 hover:text-dark/60 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to home
            </a>
        </div>
    </div>

    <script>
    function forgotPassword() {
        return {
            step: 1,
            email: '',
            otpDigits: ['', '', '', '', '', ''],
            password: '',
            confirmPassword: '',
            error: '',
            message: '',
            loading: false,
            resendCooldown: 0,
            
            async sendOTP() {
                this.loading = true;
                this.error = '';
                this.message = '';
                
                try {
                    const formData = new FormData();
                    formData.append('email', this.email);
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    
                    const response = await fetch('/api/forgot-password', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.step = 2;
                        this.startResendCooldown();
                    } else {
                        this.error = data.error || 'Failed to send OTP.';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },
            
            async verifyOTP() {
                this.loading = true;
                this.error = '';
                
                const otp = this.otpDigits.join('');
                
                try {
                    const formData = new FormData();
                    formData.append('email', this.email);
                    formData.append('otp', otp);
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    
                    const response = await fetch('/api/verify-otp', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.step = 3;
                    } else {
                        this.error = data.error || 'Invalid OTP.';
                        this.otpDigits = ['', '', '', '', '', ''];
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },
            
            async resetPassword() {
                this.loading = true;
                this.error = '';
                
                if (this.password !== this.confirmPassword) {
                    this.error = 'Passwords do not match.';
                    this.loading = false;
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('password', this.password);
                    formData.append('confirm_password', this.confirmPassword);
                    formData.append('csrf_token', '<?= csrf_token() ?>');
                    
                    const response = await fetch('/api/reset-password', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = data.redirect || '/login';
                    } else {
                        this.error = data.error || 'Failed to reset password.';
                    }
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },
            
            async resendOTP() {
                if (this.resendCooldown > 0) return;
                await this.sendOTP();
            },
            
            startResendCooldown() {
                this.resendCooldown = 60;
                const interval = setInterval(() => {
                    this.resendCooldown--;
                    if (this.resendCooldown <= 0) clearInterval(interval);
                }, 1000);
            },
            
            handleOTPInput(event, index) {
                const value = event.target.value;
                if (value && index < 5) {
                    document.getElementById('otp-' + (index + 1)).focus();
                }
            },
            
            handleBackspace(event, index) {
                if (!this.otpDigits[index] && index > 0) {
                    document.getElementById('otp-' + (index - 1)).focus();
                }
            },
            
            handlePaste(event) {
                event.preventDefault();
                const paste = (event.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, 6).split('');
                digits.forEach((digit, i) => {
                    if (i < 6) this.otpDigits[i] = digit;
                });
            }
        };
    }
    </script>
</body>
</html>

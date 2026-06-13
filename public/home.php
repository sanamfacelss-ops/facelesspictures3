<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faceless Pitcher 3 — No Face. Just Talent.</title>
    
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
    
    <!-- GSAP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js"></script>

    <style>
        :root {
            --cream: #F8F5F0;
            --crimson: #D92B3A;
            --gold: #C9943A;
            --dark: #0D0D0D;
            --charcoal: #141414;
            --line: rgba(13,13,13,0.09);
            --blue: #4A6CF7;
        }
        
        * { box-sizing: border-box; }
        html { scroll-behavior: auto; }
        body { 
            font-family: 'DM Sans', sans-serif; 
            background: var(--cream); 
            color: var(--dark);
            -webkit-font-smoothing: antialiased;
        }
        .font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: 0.02em; }
        
        /* Reveal animations */
        .rv { opacity: 0; transform: translateY(32px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .rv.in { opacity: 1; transform: translateY(0); }
        .rv-d1 { transition-delay: 0.1s; }
        .rv-d2 { transition-delay: 0.2s; }
        .rv-d3 { transition-delay: 0.3s; }
        .rv-d4 { transition-delay: 0.4s; }
        
        /* Hero word clip reveal */
        .hero-word { 
            display: block; 
            clip-path: inset(100% 0 0 0);
            transform: translateY(100%);
        }
        .hero-word.revealed {
            clip-path: inset(0 0 0 0);
            transform: translateY(0);
            transition: clip-path 0.7s cubic-bezier(0.77, 0, 0.175, 1), transform 0.7s cubic-bezier(0.77, 0, 0.175, 1);
        }
        
        /* Marquee */
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-track { animation: marquee 40s linear infinite; }
        .marquee-container:hover .marquee-track { animation-play-state: paused; }
        
        /* Role card hover bar */
        .role-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .role-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        
        /* Film card */
        .film-card { transition: transform 0.35s ease; }
        .film-card:hover { transform: scale(1.015); }
        .film-card .play-btn { opacity: 0; transform: scale(0.85); transition: all 0.35s ease; }
        .film-card:hover .play-btn { opacity: 1; transform: scale(1); }
        .film-card:hover .overlay { background: rgba(0,0,0,0.7); }
        
        /* Course card */
        .course-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .course-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(13,13,13,0.08); }
        .course-card .enroll-link { letter-spacing: 0.5px; transition: letter-spacing 0.3s ease; }
        .course-card:hover .enroll-link { letter-spacing: 1.5px; }
        
        /* Nav shadow */
        .nav-scrolled { box-shadow: 0 1px 20px rgba(13,13,13,0.08); }
        
        /* Arrow hover */
        .arrow-link:hover .arrow { transform: translateX(6px); }
        .arrow { transition: transform 0.25s ease; }
    </style>
</head>

<body x-data="{ 
    filmModal: false, 
    currentFilm: null,
    openFilm(film) { this.currentFilm = film; this.filmModal = true; },
    closeFilm() { this.filmModal = false; }
}">

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 1 — STICKY NAV -->
<!-- ═══════════════════════════════════════ -->
<nav id="nav" class="fixed top-0 left-0 right-0 z-50 h-[62px] bg-[rgba(248,245,240,0.9)] backdrop-blur-[20px] border-b border-[rgba(13,13,13,0.06)] transition-shadow duration-300">
    <div class="max-w-[1200px] mx-auto px-6 h-full flex items-center justify-between">
        <!-- Logo -->
        <a href="/" class="flex items-center gap-2">
            <span class="font-display text-[19px] tracking-wide">FACELESS PITCHER</span>
            <span class="bg-crimson text-white text-[9px] font-semibold px-2 py-0.5 rounded-full">S3</span>
        </a>
        
        <!-- Center Links (desktop) -->
        <div class="hidden md:flex items-center gap-8">
            <a href="#how" class="nav-link text-[13px] font-medium text-dark/60 hover:text-dark transition">How It Works</a>
            <a href="#roles" class="nav-link text-[13px] font-medium text-dark/60 hover:text-dark transition">Roles</a>
            <a href="#films" class="nav-link text-[13px] font-medium text-dark/60 hover:text-dark transition">Films</a>
            <a href="#brief" class="nav-link text-[13px] font-medium text-dark/60 hover:text-dark transition">Brief</a>
            <a href="#courses" class="nav-link text-[13px] font-medium text-dark/60 hover:text-dark transition">Courses</a>
        </div>
        
        <!-- CTA -->
        <a href="/register" class="hidden md:inline-flex bg-crimson text-white text-[12px] font-semibold px-5 py-2.5 rounded-[3px] hover:bg-crimson/90 transition">
            Join Season 3
        </a>
        
        <!-- Mobile menu button -->
        <button class="md:hidden p-2" @click="$refs.mobileMenu.classList.toggle('hidden')">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    
    <!-- Mobile menu -->
    <div x-ref="mobileMenu" class="hidden md:hidden bg-cream border-t border-[rgba(13,13,13,0.06)] px-6 py-4 space-y-3">
        <a href="#how" class="nav-link block text-[13px] text-dark/60 py-2">How It Works</a>
        <a href="#roles" class="nav-link block text-[13px] text-dark/60 py-2">Roles</a>
        <a href="#films" class="nav-link block text-[13px] text-dark/60 py-2">Films</a>
        <a href="#brief" class="nav-link block text-[13px] text-dark/60 py-2">Brief</a>
        <a href="#courses" class="nav-link block text-[13px] text-dark/60 py-2">Courses</a>
        <a href="/register" class="block bg-crimson text-white text-[12px] font-semibold px-5 py-2.5 rounded-[3px] text-center mt-4">Join Season 3</a>
    </div>
</nav>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 2 — HERO (TWO COLUMN) -->
<!-- ═══════════════════════════════════════ -->
<section class="min-h-screen pt-[62px] bg-cream relative overflow-hidden">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 min-h-[calc(100vh-62px)] flex items-center">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center w-full py-16 md:py-20 lg:py-12">
            
            <!-- LEFT COLUMN — Content -->
            <div class="order-1 text-center lg:text-left">
                <!-- Eyebrow -->
                <div class="flex items-center justify-center lg:justify-start gap-3 mb-4 hero-eyebrow opacity-0">
                    <span class="w-2 h-2 bg-crimson rounded-full animate-pulse"></span>
                    <span class="text-[12px] font-semibold tracking-[3px] uppercase text-dark/50">Season 3 — Now Open</span>
                </div>
                
                <!-- H1 -->
                <h1 class="font-display leading-[0.92] mb-5">
                    <span class="hero-word block text-[44px] sm:text-[56px] md:text-[68px] lg:text-[80px] xl:text-[96px]">NO FACE.</span>
                    <span class="hero-word block text-[44px] sm:text-[56px] md:text-[68px] lg:text-[80px] xl:text-[96px]">NO CONNECTIONS.</span>
                    <span class="hero-word block text-[44px] sm:text-[56px] md:text-[68px] lg:text-[80px] xl:text-[96px] text-crimson">JUST TALENT.</span>
                </h1>
                
                <!-- Subtext -->
                <p class="hero-sub text-[16px] sm:text-[17px] font-light text-dark/70 max-w-[520px] mx-auto lg:mx-0 leading-relaxed mb-6 opacity-0">
                    India's first anonymous film competition. Actors, Directors & Writers compete purely on talent. The world votes. Winners get cast.
                </p>
                
                <!-- CTAs -->
                <div class="hero-cta flex flex-col sm:flex-row flex-wrap justify-center lg:justify-start gap-3 mb-6 opacity-0">
                    <a href="/register" class="inline-flex items-center justify-center gap-2 bg-crimson text-white text-[15px] font-semibold px-7 py-4 rounded-[6px] hover:bg-crimson/90 transition shadow-lg shadow-crimson/20">
                        Enter the Competition
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="#how" class="nav-link inline-flex items-center justify-center gap-2 border-2 border-dark/80 text-dark text-[15px] font-semibold px-7 py-4 rounded-[6px] hover:bg-dark hover:text-cream transition">
                        How It Works
                    </a>
                </div>
                
                <!-- Stats row - Full width, bigger text -->
                <div class="hero-stats w-full pt-6 border-t border-dark/10 opacity-0">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-y-5 gap-x-4">
                        <div class="text-center lg:text-left">
                            <span class="font-display text-[40px] sm:text-[44px] lg:text-[36px] leading-none block">2,400+</span>
                            <p class="text-[14px] text-dark/50 mt-1">Submissions</p>
                        </div>
                        <div class="text-center lg:text-left">
                            <span class="font-display text-[40px] sm:text-[44px] lg:text-[36px] leading-none block">18</span>
                            <p class="text-[14px] text-dark/50 mt-1">Cities</p>
                        </div>
                        <div class="text-center lg:text-left">
                            <span class="font-display text-[40px] sm:text-[44px] lg:text-[36px] leading-none text-crimson block">6</span>
                            <p class="text-[14px] text-dark/50 mt-1">Winners Cast</p>
                        </div>
                        <div class="text-center lg:text-left">
                            <span class="font-display text-[40px] sm:text-[44px] lg:text-[36px] leading-none block">100%</span>
                            <p class="text-[14px] text-dark/50 mt-1">Anonymous</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- RIGHT COLUMN — Role Showcase Slider -->
            <div class="order-2 hero-visual opacity-0 mt-8 lg:mt-0">
                <div class="relative max-w-[320px] sm:max-w-[360px] lg:max-w-[400px] mx-auto" x-data="{ activeRole: 0 }" x-init="setInterval(() => activeRole = (activeRole + 1) % 3, 4000)">
                    
                    <!-- Role Cards with WHITE bg and colored border -->
                    <div class="relative">
                        
                        <!-- ACTOR Slide -->
                        <div class="transition-all duration-700"
                             :class="activeRole === 0 ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 absolute inset-0 pointer-events-none'">
                            <div class="bg-white border-[3px] border-crimson rounded-[16px] sm:rounded-[20px] p-5 sm:p-6 lg:p-7 shadow-xl shadow-crimson/10">
                                <div class="flex items-center gap-3 sm:gap-4 mb-4">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-crimson/10 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-[32px] sm:text-[40px] leading-none">🎭</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] sm:text-[11px] font-bold tracking-[3px] uppercase text-crimson">Role 1 of 3</span>
                                        <h3 class="font-display text-[32px] sm:text-[40px] leading-none text-dark">ACTOR</h3>
                                    </div>
                                </div>
                                <p class="text-[14px] sm:text-[15px] text-dark/70 leading-relaxed mb-4">
                                    Perform the scene on camera. Your face stays hidden — only your talent speaks.
                                </p>
                                <div class="bg-crimson/5 border border-crimson/20 rounded-lg p-3 sm:p-4">
                                    <p class="text-[11px] sm:text-[12px] text-crimson font-medium mb-1">Current Brief</p>
                                    <p class="text-[13px] sm:text-[14px] italic text-dark/80">"Show us the moment your world shifts — in under 3 minutes."</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- DIRECTOR Slide -->
                        <div class="transition-all duration-700"
                             :class="activeRole === 1 ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 absolute inset-0 pointer-events-none'">
                            <div class="bg-white border-[3px] border-gold rounded-[16px] sm:rounded-[20px] p-5 sm:p-6 lg:p-7 shadow-xl shadow-gold/10">
                                <div class="flex items-center gap-3 sm:gap-4 mb-4">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gold/10 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-[32px] sm:text-[40px] leading-none">🎬</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] sm:text-[11px] font-bold tracking-[3px] uppercase text-gold">Role 2 of 3</span>
                                        <h3 class="font-display text-[32px] sm:text-[40px] leading-none text-dark">DIRECTOR</h3>
                                    </div>
                                </div>
                                <p class="text-[14px] sm:text-[15px] text-dark/70 leading-relaxed mb-4">
                                    Same scene, your vision. Frame it, light it, pace it — one phone, endless possibilities.
                                </p>
                                <div class="bg-gold/5 border border-gold/20 rounded-lg p-3 sm:p-4">
                                    <p class="text-[11px] sm:text-[12px] text-gold font-medium mb-1">Current Brief</p>
                                    <p class="text-[13px] sm:text-[14px] italic text-dark/80">"One actor, one phone, one life-changing moment. Your lens."</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- WRITER Slide -->
                        <div class="transition-all duration-700"
                             :class="activeRole === 2 ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 absolute inset-0 pointer-events-none'">
                            <div class="bg-white border-[3px] border-[#4A6CF7] rounded-[16px] sm:rounded-[20px] p-5 sm:p-6 lg:p-7 shadow-xl shadow-[#4A6CF7]/10">
                                <div class="flex items-center gap-3 sm:gap-4 mb-4">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-[#4A6CF7]/10 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-[32px] sm:text-[40px] leading-none">✍️</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] sm:text-[11px] font-bold tracking-[3px] uppercase text-[#4A6CF7]">Role 3 of 3</span>
                                        <h3 class="font-display text-[32px] sm:text-[40px] leading-none text-dark">WRITER</h3>
                                    </div>
                                </div>
                                <p class="text-[14px] sm:text-[15px] text-dark/70 leading-relaxed mb-4">
                                    We give you Scene 1 — you write what happens next. Great scripts become great films.
                                </p>
                                <div class="bg-[#4A6CF7]/5 border border-[#4A6CF7]/20 rounded-lg p-3 sm:p-4">
                                    <p class="text-[11px] sm:text-[12px] text-[#4A6CF7] font-medium mb-1">Current Brief</p>
                                    <p class="text-[13px] sm:text-[14px] italic text-dark/80">"Scene 1 ends with 'Hello?' — Write Scene 2."</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dot indicators -->
                    <div class="flex justify-center gap-3 mt-5">
                        <button @click="activeRole = 0" 
                                class="h-2.5 rounded-full transition-all duration-300"
                                :class="activeRole === 0 ? 'bg-crimson w-8' : 'bg-dark/20 hover:bg-dark/40 w-2.5'"></button>
                        <button @click="activeRole = 1" 
                                class="h-2.5 rounded-full transition-all duration-300"
                                :class="activeRole === 1 ? 'bg-gold w-8' : 'bg-dark/20 hover:bg-dark/40 w-2.5'"></button>
                        <button @click="activeRole = 2" 
                                class="h-2.5 rounded-full transition-all duration-300"
                                :class="activeRole === 2 ? 'bg-[#4A6CF7] w-8' : 'bg-dark/20 hover:bg-dark/40 w-2.5'"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scroll indicator -->
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden lg:flex flex-col items-center gap-2 text-dark/30">
        <span class="text-[9px] font-semibold tracking-[3px] uppercase">Scroll</span>
        <div class="w-5 h-8 border-2 border-dark/20 rounded-full flex justify-center pt-1">
            <div class="w-1 h-2 bg-crimson rounded-full animate-bounce"></div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 3 — MARQUEE TICKER -->
<!-- ═══════════════════════════════════════ -->
<section class="bg-white border-y border-[rgba(13,13,13,0.09)] py-4 overflow-hidden marquee-container">
    <div class="marquee-track flex whitespace-nowrap">
        <div class="flex items-center gap-8 px-4">
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ACTORS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">DIRECTORS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">WRITERS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">NO CONNECTIONS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ONE VIDEO</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ONE SEASON</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">SEASON 3 OPEN</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">AUTO-PUBLISHED TO YOUTUBE</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
        </div>
        <div class="flex items-center gap-8 px-4">
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ACTORS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">DIRECTORS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">WRITERS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">NO CONNECTIONS</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ONE VIDEO</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">ONE SEASON</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">SEASON 3 OPEN</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
            <span class="font-display text-[15px] tracking-[2px] text-dark/70">AUTO-PUBLISHED TO YOUTUBE</span>
            <span class="w-1.5 h-1.5 bg-crimson rounded-full"></span>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 4 — STATS ROW (Removed - now in hero) -->
<!-- ═══════════════════════════════════════ -->

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 5 — THREE ROLES -->
<!-- ═══════════════════════════════════════ -->
<section id="roles" class="py-20 md:py-28 bg-cream">
    <div class="max-w-[1200px] mx-auto px-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12 rv">
            <div>
                <span class="text-[10px] font-semibold tracking-[3px] uppercase text-crimson">Choose Your Path</span>
                <h2 class="font-display text-[48px] md:text-[64px] leading-none mt-2">THREE ROLES</h2>
            </div>
            <p class="text-[13px] text-dark/50 max-w-[300px]">One role per season. Pick the craft you want to prove.</p>
        </div>
        
        <!-- Cards Grid -->
        <div class="grid md:grid-cols-3 gap-4 md:gap-6">
            
            <!-- ACTOR -->
            <div class="role-card relative bg-white border-2 border-crimson rounded-[12px] p-6 md:p-8 rv">
                <!-- Background letter -->
                <div class="absolute top-4 right-4 font-display text-[140px] md:text-[180px] leading-none text-crimson/[0.05] select-none pointer-events-none">A</div>
                
                <div class="relative">
                    <!-- Tag -->
                    <span class="inline-block bg-crimson text-white text-[10px] font-semibold tracking-wider uppercase px-3 py-1.5 rounded-[4px] mb-5">Actor</span>
                    
                    <!-- Title -->
                    <h3 class="font-display text-[36px] md:text-[44px] leading-none mb-4 text-dark">ACTOR</h3>
                    
                    <!-- Description -->
                    <p class="text-[14px] md:text-[15px] text-dark/70 leading-relaxed mb-5">
                        Bring the character to life. Perform the scene on camera — your face hidden, your talent on display.
                    </p>
                    
                    <!-- Brief preview -->
                    <div class="bg-crimson/5 border border-crimson/20 rounded-[6px] p-4 mb-5">
                        <span class="text-[10px] font-semibold tracking-wider uppercase text-crimson block mb-2">Current Brief</span>
                        <p class="text-[13px] md:text-[14px] italic text-dark/80">"Show us the moment your world shifts — in under 3 minutes."</p>
                    </div>
                    
                    <!-- CTA -->
                    <a href="/register?role=actor" class="arrow-link inline-flex items-center gap-2 text-crimson text-[13px] font-semibold">
                        Register as Actor <span class="arrow">→</span>
                    </a>
                </div>
            </div>
            
            <!-- DIRECTOR -->
            <div class="role-card relative bg-white border-2 border-gold rounded-[12px] p-6 md:p-8 rv rv-d1">
                <div class="absolute top-4 right-4 font-display text-[140px] md:text-[180px] leading-none text-gold/[0.08] select-none pointer-events-none">D</div>
                
                <div class="relative">
                    <span class="inline-block bg-gold text-white text-[10px] font-semibold tracking-wider uppercase px-3 py-1.5 rounded-[4px] mb-5">Director</span>
                    <h3 class="font-display text-[36px] md:text-[44px] leading-none mb-4 text-dark">DIRECTOR</h3>
                    <p class="text-[14px] md:text-[15px] text-dark/70 leading-relaxed mb-5">
                        Direct your vision. Same scene, your colour — framing, pacing, mood. One take, one phone, one lens.
                    </p>
                    <div class="bg-gold/5 border border-gold/20 rounded-[6px] p-4 mb-5">
                        <span class="text-[10px] font-semibold tracking-wider uppercase text-gold block mb-2">Current Brief</span>
                        <p class="text-[13px] md:text-[14px] italic text-dark/80">"One actor, one phone, one life-changing moment. Your lens."</p>
                    </div>
                    <a href="/register?role=director" class="arrow-link inline-flex items-center gap-2 text-gold text-[13px] font-semibold">
                        Register as Director <span class="arrow">→</span>
                    </a>
                </div>
            </div>
            
            <!-- WRITER -->
            <div class="role-card relative bg-white border-2 border-[#4A6CF7] rounded-[12px] p-6 md:p-8 rv rv-d2">
                <div class="absolute top-4 right-4 font-display text-[140px] md:text-[180px] leading-none text-[#4A6CF7]/[0.06] select-none pointer-events-none">W</div>
                
                <div class="relative">
                    <span class="inline-block bg-[#4A6CF7] text-white text-[10px] font-semibold tracking-wider uppercase px-3 py-1.5 rounded-[4px] mb-5">Writer</span>
                    <h3 class="font-display text-[36px] md:text-[44px] leading-none mb-4 text-dark">WRITER</h3>
                    <p class="text-[14px] md:text-[15px] text-dark/70 leading-relaxed mb-5">
                        We give you Scene 1 — you write what happens next. From great script to great outcome.
                    </p>
                    <div class="bg-[#4A6CF7]/5 border border-[#4A6CF7]/20 rounded-[6px] p-4 mb-5">
                        <span class="text-[10px] font-semibold tracking-wider uppercase text-[#4A6CF7] block mb-2">Current Brief</span>
                        <p class="text-[13px] md:text-[14px] italic text-dark/80">"Scene 1 ends with 'Hello?' — Write Scene 2."</p>
                    </div>
                    <a href="/register?role=writer" class="arrow-link inline-flex items-center gap-2 text-[#4A6CF7] text-[13px] font-semibold">
                        Register as Writer <span class="arrow">→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 6 — HOW IT WORKS -->
<!-- ═══════════════════════════════════════ -->
<section id="how" class="py-20 md:py-28 bg-charcoal">
    <div class="max-w-[1200px] mx-auto px-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12 rv">
            <div>
                <span class="text-[10px] font-semibold tracking-[3px] uppercase text-gold">The Process</span>
                <h2 class="font-display text-[48px] md:text-[64px] leading-none mt-2 text-white">HOW IT WORKS</h2>
            </div>
            <p class="text-[13px] text-white/30 max-w-[280px] text-right">Four steps. Fully automated. No gatekeeping.</p>
        </div>
        
        <!-- Steps Grid -->
        <div class="grid md:grid-cols-4 gap-px bg-white/[0.06]">
            
            <!-- Step 01 -->
            <div class="relative bg-charcoal p-6 md:p-8 rv">
                <!-- Faded number -->
                <div class="absolute top-4 right-4 font-display text-[100px] md:text-[120px] leading-none text-white/[0.04] select-none">01</div>
                <!-- Red line on right (not on last) -->
                <div class="hidden md:block absolute top-1/2 right-0 w-px h-1/2 bg-gradient-to-b from-crimson/50 to-transparent -translate-y-1/2"></div>
                
                <div class="relative">
                    <div class="w-10 h-10 border border-white/10 rounded-[6px] flex items-center justify-center mb-5">
                        <span class="text-lg">🎯</span>
                    </div>
                    <h3 class="text-[15px] font-semibold text-white mb-2">Pick Your Role</h3>
                    <p class="text-[13px] text-white/40 leading-relaxed">Register as Actor, Director, or Writer. One role per season. Own your craft.</p>
                </div>
            </div>
            
            <!-- Step 02 -->
            <div class="relative bg-charcoal p-6 md:p-8 rv rv-d1">
                <div class="absolute top-4 right-4 font-display text-[100px] md:text-[120px] leading-none text-white/[0.04] select-none">02</div>
                <div class="hidden md:block absolute top-1/2 right-0 w-px h-1/2 bg-gradient-to-b from-crimson/50 to-transparent -translate-y-1/2"></div>
                
                <div class="relative">
                    <div class="w-10 h-10 border border-white/10 rounded-[6px] flex items-center justify-center mb-5">
                        <span class="text-lg">📤</span>
                    </div>
                    <h3 class="text-[15px] font-semibold text-white mb-2">Upload Your Work</h3>
                    <p class="text-[13px] text-white/40 leading-relaxed">Record your scene (max 3 min). AI checks quality instantly — no human delay.</p>
                </div>
            </div>
            
            <!-- Step 03 -->
            <div class="relative bg-charcoal p-6 md:p-8 rv rv-d2">
                <div class="absolute top-4 right-4 font-display text-[100px] md:text-[120px] leading-none text-white/[0.04] select-none">03</div>
                <div class="hidden md:block absolute top-1/2 right-0 w-px h-1/2 bg-gradient-to-b from-crimson/50 to-transparent -translate-y-1/2"></div>
                
                <div class="relative">
                    <div class="w-10 h-10 border border-white/10 rounded-[6px] flex items-center justify-center mb-5">
                        <span class="text-lg">▶️</span>
                    </div>
                    <h3 class="text-[15px] font-semibold text-white mb-2">Goes to YouTube</h3>
                    <p class="text-[13px] text-white/40 leading-relaxed">Your video publishes on our channel. No face, no name. Pure craft on display.</p>
                </div>
            </div>
            
            <!-- Step 04 -->
            <div class="relative bg-charcoal p-6 md:p-8 rv rv-d3">
                <div class="absolute top-4 right-4 font-display text-[100px] md:text-[120px] leading-none text-white/[0.04] select-none">04</div>
                
                <div class="relative">
                    <div class="w-10 h-10 border border-white/10 rounded-[6px] flex items-center justify-center mb-5">
                        <span class="text-lg">🏆</span>
                    </div>
                    <h3 class="text-[15px] font-semibold text-white mb-2">World Decides</h3>
                    <p class="text-[13px] text-white/40 leading-relaxed">Views, likes, comments = your score. Updated daily. Top performers get cast.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 7 — THE FILMS -->
<!-- ═══════════════════════════════════════ -->
<section id="films" class="py-20 md:py-28 bg-cream">
    <div class="max-w-[1200px] mx-auto px-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12 rv">
            <div>
                <span class="text-[10px] font-semibold tracking-[3px] uppercase text-gold">Win a Role In</span>
                <h2 class="font-display text-[48px] md:text-[64px] leading-none mt-2">THE FILMS</h2>
            </div>
            <p class="text-[13px] text-dark/50 max-w-[320px] md:text-right">These are the productions. Win your category — get cast. Click any film to watch the trailer.</p>
        </div>
        
        <!-- Films Grid -->
        <div class="grid md:grid-cols-3 gap-[3px] bg-[rgba(13,13,13,0.09)]">
            
            <!-- FEATURED: PROJECT NOOR -->
            <div class="film-card md:col-span-2 relative min-h-[280px] md:min-h-[380px] cursor-pointer overflow-hidden rv"
                 style="background: #140a0b;"
                 @click="openFilm({title:'PROJECT NOOR',genre:'Drama',lang:'Hindi',year:'2026',format:'Feature Film',roles:['Actor','Director']})">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[140px] md:text-[180px] text-white/[0.04] select-none">NOOR</span>
                </div>
                <div class="overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-all duration-300"></div>
                
                <!-- Play button -->
                <div class="play-btn absolute inset-0 flex items-center justify-center z-10">
                    <div class="w-16 h-16 bg-crimson rounded-full flex items-center justify-center shadow-lg shadow-crimson/30">
                        <svg class="w-6 h-6 text-white ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
                
                <!-- Info -->
                <div class="absolute bottom-6 left-6 right-6 z-10">
                    <p class="text-[9px] uppercase tracking-wider text-white/40 mb-1">Drama · Hindi</p>
                    <h3 class="font-display text-[32px] md:text-[36px] text-white leading-none mb-3">PROJECT NOOR</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="text-[9px] font-medium uppercase px-2 py-1 rounded-sm bg-crimson/20 text-crimson">Actor</span>
                        <span class="text-[9px] font-medium uppercase px-2 py-1 rounded-sm bg-gold/20 text-gold">Director</span>
                    </div>
                    <p class="text-[10px] text-white/40">2026 · Feature Film · 2 roles open</p>
                </div>
            </div>
            
            <!-- PROJECT AKAASH -->
            <div class="film-card relative min-h-[280px] cursor-pointer overflow-hidden rv rv-d1"
                 style="background: #0a0e14;"
                 @click="openFilm({title:'PROJECT AKAASH',genre:'Thriller',lang:'Hinglish',year:'2026',format:'Short Film',roles:['Writer','Director']})">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[80px] md:text-[100px] text-white/[0.04] select-none">AKAASH</span>
                </div>
                <div class="overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-all duration-300"></div>
                
                <div class="play-btn absolute inset-0 flex items-center justify-center z-10">
                    <div class="w-12 h-12 bg-crimson rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
                
                <div class="absolute bottom-5 left-5 right-5 z-10">
                    <p class="text-[9px] uppercase tracking-wider text-white/40 mb-1">Thriller · Hinglish</p>
                    <h3 class="font-display text-[24px] md:text-[26px] text-white leading-none mb-2">PROJECT AKAASH</h3>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-[#4A6CF7]/20 text-[#7B93F7]">Writer</span>
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-gold/20 text-gold">Director</span>
                    </div>
                    <p class="text-[9px] text-white/40">2026 · Short Film · 2 roles open</p>
                </div>
            </div>
            
            <!-- PROJECT ZARA -->
            <div class="film-card relative min-h-[280px] cursor-pointer overflow-hidden rv rv-d2"
                 style="background: #0c0a14;"
                 @click="openFilm({title:'PROJECT ZARA',genre:'Romance',lang:'Hindi',year:'2026',format:'Web Series',roles:['Actor']})">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[80px] md:text-[100px] text-white/[0.04] select-none">ZARA</span>
                </div>
                <div class="overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-all duration-300"></div>
                
                <div class="play-btn absolute inset-0 flex items-center justify-center z-10">
                    <div class="w-12 h-12 bg-crimson rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
                
                <div class="absolute bottom-5 left-5 right-5 z-10">
                    <p class="text-[9px] uppercase tracking-wider text-white/40 mb-1">Romance · Hindi</p>
                    <h3 class="font-display text-[24px] md:text-[26px] text-white leading-none mb-2">PROJECT ZARA</h3>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-crimson/20 text-crimson">Actor</span>
                    </div>
                    <p class="text-[9px] text-white/40">2026 · Web Series · 1 role open</p>
                </div>
            </div>
            
            <!-- PROJECT MITTI -->
            <div class="film-card relative min-h-[280px] cursor-pointer overflow-hidden rv rv-d3"
                 style="background: #0f0c08;"
                 @click="openFilm({title:'PROJECT MITTI',genre:'Drama',lang:'Hindi',year:'2026',format:'Feature Film',roles:['Actor','Writer']})">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[80px] md:text-[100px] text-white/[0.04] select-none">MITTI</span>
                </div>
                <div class="overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-all duration-300"></div>
                
                <div class="play-btn absolute inset-0 flex items-center justify-center z-10">
                    <div class="w-12 h-12 bg-crimson rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
                
                <div class="absolute bottom-5 left-5 right-5 z-10">
                    <p class="text-[9px] uppercase tracking-wider text-white/40 mb-1">Drama · Hindi</p>
                    <h3 class="font-display text-[24px] md:text-[26px] text-white leading-none mb-2">PROJECT MITTI</h3>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-crimson/20 text-crimson">Actor</span>
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-[#4A6CF7]/20 text-[#7B93F7]">Writer</span>
                    </div>
                    <p class="text-[9px] text-white/40">2026 · Feature Film · 2 roles open</p>
                </div>
            </div>
            
            <!-- PROJECT RAAKH -->
            <div class="film-card relative min-h-[280px] cursor-pointer overflow-hidden rv"
                 style="background: #100a0a;"
                 @click="openFilm({title:'PROJECT RAAKH',genre:'Action',lang:'Hindi',year:'2026',format:'Short Film',roles:['Director']})">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[80px] md:text-[100px] text-white/[0.04] select-none">RAAKH</span>
                </div>
                <div class="overlay absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent transition-all duration-300"></div>
                
                <div class="play-btn absolute inset-0 flex items-center justify-center z-10">
                    <div class="w-12 h-12 bg-crimson rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
                
                <div class="absolute bottom-5 left-5 right-5 z-10">
                    <p class="text-[9px] uppercase tracking-wider text-white/40 mb-1">Action · Hindi</p>
                    <h3 class="font-display text-[24px] md:text-[26px] text-white leading-none mb-2">PROJECT RAAKH</h3>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="text-[8px] font-medium uppercase px-1.5 py-0.5 rounded-sm bg-gold/20 text-gold">Director</span>
                    </div>
                    <p class="text-[9px] text-white/40">2026 · Short Film · 1 role open</p>
                </div>
            </div>
            
            <!-- COMING SOON -->
            <div class="film-card relative min-h-[280px] overflow-hidden rv rv-d1" style="background: #181818;">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="font-display text-[60px] md:text-[80px] text-white/[0.04] select-none">???</span>
                </div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-12 h-12 border border-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-lg">🎬</span>
                        </div>
                        <h3 class="font-display text-[24px] text-white/30 leading-none mb-2">COMING SOON</h3>
                        <p class="text-[10px] text-white/20">More productions announced soon</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════ -->
<!-- SECTION 8 — CURRENT BRIEF -->
<!-- ═══════════════════════════════════════ -->
<section id="brief" class="py-20 md:py-28 bg-white border-y border-[rgba(13,13,13,0.09)]">
    <div class="max-w-[1200px] mx-auto px-6">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            
            <!-- LEFT — Content -->
            <div class="rv">
                <span class="text-[10px] font-semibold tracking-[3px] uppercase text-crimson">Season 3 Brief</span>
                <h2 class="font-display text-[48px] md:text-[64px] leading-none mt-2 mb-6">THE PHONE CALL</h2>
                
                <p class="text-[15px] text-dark/60 leading-relaxed mb-8">
                    A phone rings. Someone answers. In under 3 minutes, show us what that call changes forever.
                    This is your scene. This is your moment. Make it count.
                </p>
                
                <!-- Meta tags -->
                <div class="flex flex-wrap gap-2 mb-8">
                    <span class="inline-flex items-center gap-1.5 bg-cream px-3 py-1.5 rounded-[3px] text-[11px] font-medium">
                        <span class="text-crimson">⏱</span> Max 3 minutes
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-cream px-3 py-1.5 rounded-[3px] text-[11px] font-medium">
                        <span class="text-crimson">🗣</span> Hindi / Hinglish
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-cream px-3 py-1.5 rounded-[3px] text-[11px] font-medium">
                        <span class="text-crimson">1️⃣</span> One submission
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-cream px-3 py-1.5 rounded-[3px] text-[11px] font-medium">
                        <span class="text-crimson">▶️</span> Auto-published
                    </span>
                </div>
                
                <!-- CTAs -->
                <div class="flex flex-wrap gap-3">
                    <a href="/brief" class="inline-flex items-center gap-2 bg-dark text-white text-[12px] font-semibold px-6 py-3 rounded-[3px] hover:bg-dark/90 transition">
                        Read Full Brief
                        <span>→</span>
                    </a>
                    <a href="/register" class="inline-flex items-center gap-2 border border-dark text-dark text-[12px] font-semibold px-6 py-3 rounded-[3px] hover:bg-dark hover:text-white transition">
                        Submit Your Entry
                    </a>
                </div>
            </div>
            
            <!-- RIGHT — Visual -->
            <div class="rv rv-d1">
                <div class="relative">
                    <!-- Quote card -->
                    <div class="bg-cream border border-[rgba(13,13,13,0.09)] rounded-[6px] p-8">
                        <div class="font-display text-[120px] leading-none text-crimson/10 absolute top-4 left-6">"</div>
                        <div class="relative">
                            <p class="text-[20px] md:text-[24px] font-light leading-relaxed text-dark/80 mb-6">
                                The phone rings at 2 AM. You answer. 
                                <span class="text-crimson font-medium">"Hello?"</span>
                            </p>
                            <p class="text-[13px] text-dark/50">
                                — What happens next is up to you.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Floating badge -->
                    <div class="absolute -bottom-4 -right-4 bg-crimson text-white px-4 py-2 rounded-[4px] shadow-lg">
                        <span class="font-display text-[14px] tracking-wider">DEADLINE: 30 JUNE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════ -->
<!-- SECTION 9 — COURSES -->
<!-- ═══════════════════════════════════════ -->
<section id="courses" class="py-20 md:py-28 bg-cream">
    <div class="max-w-[1200px] mx-auto px-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12 rv">
            <div>
                <span class="text-[10px] font-semibold tracking-[3px] uppercase text-crimson">Level Up</span>
                <h2 class="font-display text-[48px] md:text-[64px] leading-none mt-2">COURSES</h2>
            </div>
            <p class="text-[13px] text-dark/50 max-w-[280px] md:text-right">Didn't win? That's okay. Level up your craft and dominate next season.</p>
        </div>
        
        <!-- Coming Soon Banner -->
        <div class="bg-charcoal rounded-[6px] p-6 mb-8 rv">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-2 h-2 bg-gold rounded-full animate-pulse"></span>
                    <span class="text-white text-[13px] font-medium">Courses launching soon — Get notified when enrollment opens</span>
                </div>
                <a href="/register" class="text-gold text-[12px] font-semibold hover:text-gold/80 transition">Join waitlist →</a>
            </div>
        </div>
        
        <!-- Course Cards -->
        <div class="grid md:grid-cols-3 gap-6">
            
            <!-- Course 1 — FREE -->
            <div class="course-card bg-white border border-[rgba(13,13,13,0.09)] rounded-[6px] overflow-hidden rv">
                <!-- Image placeholder -->
                <div class="relative h-[180px] bg-gradient-to-br from-crimson/10 to-crimson/5 flex items-center justify-center">
                    <span class="font-display text-[60px] text-crimson/10">📹</span>
                    <div class="absolute top-4 left-4">
                        <span class="bg-green-500 text-white text-[9px] font-bold uppercase px-2.5 py-1 rounded-sm">Free</span>
                    </div>
                </div>
                
                <div class="p-6">
                    <span class="text-[9px] font-semibold tracking-wider uppercase text-dark/40 block mb-2">For Actors</span>
                    <h3 class="font-display text-[24px] leading-tight mb-3">CAMERA ACTING FUNDAMENTALS</h3>
                    <p class="text-[13px] text-dark/50 leading-relaxed mb-4">
                        Learn the basics of performing for camera. Eye-lines, framing awareness, and emotional continuity.
                    </p>
                    
                    <div class="flex items-center justify-between pt-4 border-t border-[rgba(13,13,13,0.06)]">
                        <span class="font-display text-[20px] text-green-600">FREE</span>
                        <span class="enroll-link text-[11px] font-semibold text-dark/40 uppercase tracking-wider">Coming Soon</span>
                    </div>
                </div>
            </div>
            
            <!-- Course 2 — ₹1,299 -->
            <div class="course-card bg-white border border-[rgba(13,13,13,0.09)] rounded-[6px] overflow-hidden rv rv-d1">
                <div class="relative h-[180px] bg-gradient-to-br from-gold/10 to-gold/5 flex items-center justify-center">
                    <span class="font-display text-[60px] text-gold/10">🎬</span>
                    <div class="absolute top-4 left-4">
                        <span class="bg-gold text-white text-[9px] font-bold uppercase px-2.5 py-1 rounded-sm">Popular</span>
                    </div>
                </div>
                
                <div class="p-6">
                    <span class="text-[9px] font-semibold tracking-wider uppercase text-dark/40 block mb-2">For Directors</span>
                    <h3 class="font-display text-[24px] leading-tight mb-3">VISUAL STORYTELLING ON MOBILE</h3>
                    <p class="text-[13px] text-dark/50 leading-relaxed mb-4">
                        Master composition, lighting, and pacing using just your phone. Cinematic results, zero budget.
                    </p>
                    
                    <div class="flex items-center justify-between pt-4 border-t border-[rgba(13,13,13,0.06)]">
                        <span class="font-display text-[20px]">₹1,299</span>
                        <span class="enroll-link text-[11px] font-semibold text-dark/40 uppercase tracking-wider">Coming Soon</span>
                    </div>
                </div>
            </div>
            
            <!-- Course 3 — ₹999 -->
            <div class="course-card bg-white border border-[rgba(13,13,13,0.09)] rounded-[6px] overflow-hidden rv rv-d2">
                <div class="relative h-[180px] bg-gradient-to-br from-[#4A6CF7]/10 to-[#4A6CF7]/5 flex items-center justify-center">
                    <span class="font-display text-[60px] text-[#4A6CF7]/10">✍️</span>
                </div>
                
                <div class="p-6">
                    <span class="text-[9px] font-semibold tracking-wider uppercase text-dark/40 block mb-2">For Writers</span>
                    <h3 class="font-display text-[24px] leading-tight mb-3">SCENE WRITING FOR HINDI CINEMA</h3>
                    <p class="text-[13px] text-dark/50 leading-relaxed mb-4">
                        Write scenes that directors fight to shoot. Dialogue, subtext, and emotional beats — the Bollywood way.
                    </p>
                    
                    <div class="flex items-center justify-between pt-4 border-t border-[rgba(13,13,13,0.06)]">
                        <span class="font-display text-[20px]">₹999</span>
                        <span class="enroll-link text-[11px] font-semibold text-dark/40 uppercase tracking-wider">Coming Soon</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════ -->
<!-- SECTION 10 — CTA BAND -->
<!-- ═══════════════════════════════════════ -->
<section class="bg-crimson py-16 md:py-20">
    <div class="max-w-[1200px] mx-auto px-6 text-center rv">
        <h2 class="font-display text-[40px] md:text-[56px] lg:text-[72px] text-white leading-none mb-4">
            READY TO PROVE YOUR TALENT?
        </h2>
        <p class="text-[15px] text-white/60 max-w-[500px] mx-auto mb-8">
            Season 3 is open. No face, no connections, no excuses. Just you and your craft against the world.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="/register" class="inline-flex items-center gap-2 bg-white text-crimson text-[13px] font-semibold px-8 py-4 rounded-[3px] hover:bg-cream transition">
                Enter the Competition
                <span>→</span>
            </a>
            <a href="/leaderboard" class="inline-flex items-center gap-2 border-2 border-white text-white text-[13px] font-semibold px-8 py-4 rounded-[3px] hover:bg-white hover:text-crimson transition">
                View Leaderboard
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════ -->
<!-- SECTION 11 — FOOTER -->
<!-- ═══════════════════════════════════════ -->
<footer class="bg-dark py-16 md:py-20">
    <div class="max-w-[1200px] mx-auto px-6">
        <div class="grid md:grid-cols-5 gap-12 md:gap-8 mb-12">
            
            <!-- Logo & Tagline -->
            <div class="md:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <span class="font-display text-[22px] text-white tracking-wide">FACELESS PITCHER</span>
                    <span class="bg-crimson text-white text-[9px] font-semibold px-2 py-0.5 rounded-full">S3</span>
                </div>
                <p class="text-[14px] text-white/40 leading-relaxed max-w-[280px] mb-6">
                    No face. No connections. Just talent. India's first anonymous film competition.
                </p>
                <div class="flex gap-4">
                    <a href="#" class="w-9 h-9 bg-white/5 rounded-full flex items-center justify-center hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-white/60" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 bg-white/5 rounded-full flex items-center justify-center hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-white/60" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" class="w-9 h-9 bg-white/5 rounded-full flex items-center justify-center hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-white/60" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="font-display text-[14px] text-white/60 tracking-wider mb-4">COMPETE</h4>
                <ul class="space-y-3">
                    <li><a href="/register" class="text-[13px] text-white/40 hover:text-white transition">Register</a></li>
                    <li><a href="/brief" class="text-[13px] text-white/40 hover:text-white transition">Current Brief</a></li>
                    <li><a href="/leaderboard" class="text-[13px] text-white/40 hover:text-white transition">Leaderboard</a></li>
                    <li><a href="/winners" class="text-[13px] text-white/40 hover:text-white transition">Past Winners</a></li>
                </ul>
            </div>
            
            <!-- Learn -->
            <div>
                <h4 class="font-display text-[14px] text-white/60 tracking-wider mb-4">LEARN</h4>
                <ul class="space-y-3">
                    <li><a href="/courses" class="text-[13px] text-white/40 hover:text-white transition">All Courses</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Acting</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Directing</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Writing</a></li>
                </ul>
            </div>
            
            <!-- Company -->
            <div>
                <h4 class="font-display text-[14px] text-white/60 tracking-wider mb-4">COMPANY</h4>
                <ul class="space-y-3">
                    <li><a href="/about" class="text-[13px] text-white/40 hover:text-white transition">About FP3</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Privacy Policy</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Terms of Service</a></li>
                    <li><a href="#" class="text-[13px] text-white/40 hover:text-white transition">Contact</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Bottom bar -->
        <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[12px] text-white/30">© 2026 Faceless Pictures. All rights reserved.</p>
            <p class="text-[12px] text-white/30">Built by <a href="https://serenitystudios.in" class="text-white/50 hover:text-white transition">Serenity Studios</a></p>
        </div>
    </div>
</footer>


<!-- ═══════════════════════════════════════ -->
<!-- FILM MODAL -->
<!-- ═══════════════════════════════════════ -->
<div x-show="filmModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     @keydown.escape.window="closeFilm()"
     style="display: none;">
    
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/90" @click="closeFilm()"></div>
    
    <!-- Modal Content -->
    <div x-show="filmModal"
         x-transition:enter="transition ease-out duration-300 delay-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-charcoal rounded-[8px] w-full max-w-[900px] overflow-hidden">
        
        <!-- Close button -->
        <button @click="closeFilm()" class="absolute top-4 right-4 z-10 w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        
        <!-- Video placeholder -->
        <div class="aspect-video bg-black flex items-center justify-center">
            <div class="text-center">
                <div class="w-20 h-20 bg-crimson/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-crimson ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <p class="text-white/40 text-[13px]">Trailer coming soon</p>
            </div>
        </div>
        
        <!-- Film Info -->
        <div class="p-6" x-show="currentFilm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-white/40 mb-1" x-text="currentFilm?.genre + ' · ' + currentFilm?.lang"></p>
                    <h3 class="font-display text-[32px] text-white leading-none mb-3" x-text="currentFilm?.title"></h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-for="role in currentFilm?.roles || []" :key="role">
                            <span class="text-[9px] font-medium uppercase px-2 py-1 rounded-sm"
                                  :class="{
                                      'bg-crimson/20 text-crimson': role === 'Actor',
                                      'bg-gold/20 text-gold': role === 'Director',
                                      'bg-[#4A6CF7]/20 text-[#7B93F7]': role === 'Writer'
                                  }"
                                  x-text="role"></span>
                        </template>
                    </div>
                    <p class="text-[12px] text-white/40" x-text="currentFilm?.year + ' · ' + currentFilm?.format"></p>
                </div>
                <a href="/register" class="inline-flex items-center gap-2 bg-crimson text-white text-[12px] font-semibold px-5 py-2.5 rounded-[3px] hover:bg-crimson/90 transition">
                    Compete for This Film
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════ -->
<!-- JAVASCRIPT -->
<!-- ═══════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // Register GSAP plugins
    gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);
    
    // ─────────────────────────────────────
    // HERO ANIMATION
    // ─────────────────────────────────────
    const heroWords = document.querySelectorAll('.hero-word');
    const heroEyebrow = document.querySelector('.hero-eyebrow');
    const heroSub = document.querySelector('.hero-sub');
    const heroCta = document.querySelector('.hero-cta');
    const heroStats = document.querySelector('.hero-stats');
    const heroVisual = document.querySelector('.hero-visual');
    
    // Timeline for hero
    const heroTL = gsap.timeline({ delay: 0.2 });
    
    // Eyebrow fade in
    heroTL.to(heroEyebrow, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out'
    });
    
    // Stagger reveal hero words
    heroTL.to(heroWords, {
        clipPath: 'inset(0 0 0 0)',
        y: 0,
        duration: 0.7,
        stagger: 0.12,
        ease: 'power3.out'
    }, '-=0.2');
    
    // Fade in visual
    heroTL.to(heroVisual, {
        opacity: 1,
        scale: 1,
        duration: 0.8,
        ease: 'power2.out'
    }, '-=0.5');
    
    // Fade in subtext
    heroTL.to(heroSub, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out'
    }, '-=0.4');
    
    // Fade in CTA
    heroTL.to(heroCta, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out'
    }, '-=0.3');
    
    // Fade in stats
    heroTL.to(heroStats, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out'
    }, '-=0.2');
    
    // ─────────────────────────────────────
    // SCROLL REVEAL
    // ─────────────────────────────────────
    const revealElements = document.querySelectorAll('.rv');
    
    revealElements.forEach(el => {
        ScrollTrigger.create({
            trigger: el,
            start: 'top 85%',
            onEnter: () => el.classList.add('in'),
            once: true
        });
    });
    
    // ─────────────────────────────────────
    // NAV SHADOW ON SCROLL
    // ─────────────────────────────────────
    const nav = document.getElementById('nav');
    
    ScrollTrigger.create({
        start: 'top -50',
        onUpdate: (self) => {
            if (self.scroll() > 50) {
                nav.classList.add('nav-scrolled');
            } else {
                nav.classList.remove('nav-scrolled');
            }
        }
    });
    
    // ─────────────────────────────────────
    // SMOOTH SCROLL NAV LINKS
    // ─────────────────────────────────────
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    gsap.to(window, {
                        duration: 1,
                        scrollTo: { y: target, offsetY: 80 },
                        ease: 'power2.inOut'
                    });
                }
            }
        });
    });
    
    // ─────────────────────────────────────
    // ROLE CARD HOVER
    // ─────────────────────────────────────
    document.querySelectorAll('.role-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            gsap.to(card, { y: -4, duration: 0.3, ease: 'power2.out' });
        });
        card.addEventListener('mouseleave', () => {
            gsap.to(card, { y: 0, duration: 0.3, ease: 'power2.out' });
        });
    });
    
});
</script>

</body>
</html>
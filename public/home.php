<?php
require_once __DIR__ . '/../app/config/config.php';
$settingsModel = new App\Models\Settings();

// Admin-editable content
$heroTitle    = $settingsModel->get('landing_hero_title', "NO FACE.\nNO CONNECTIONS.\nJUST TALENT.");
$heroSubtitle = $settingsModel->get('landing_hero_subtitle', "India's first anonymous film competition. Actors, Directors & Writers compete purely on talent.");
$posterUrl    = $settingsModel->get('landing_poster_url', '');
$trailerUrl   = $settingsModel->get('landing_trailer_url', '');
$aboutText    = $settingsModel->get('landing_about_text', "Faceless Pictures is India's first anonymous film competition where talent speaks without a face. We believe the best stories deserve to be told — regardless of who you know or what you look like.");

// Parse hero title lines
$heroLines = array_filter(array_map('trim', explode("\n", $heroTitle)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faceless Pictures 3 — No Face. Just Talent.</title>
<meta name="description" content="India's first anonymous film competition. No face, no connections — just raw talent. Actor, Director & Writer auditions open now.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                ink:    '#0A0E1A',
                deep:   '#111827',
                amber:  '#E6A817',
                warm:   '#F0EBE0',
                muted:  '#8B92A5',
                panel:  '#161C2D',
                border: '#1F2840',
            },
            fontFamily: {
                display: ['Bebas Neue', 'sans-serif'],
                body:    ['DM Sans', 'sans-serif'],
            }
        }
    }
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #0A0E1A;
    color: #F0EBE0;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
}
.font-display { font-family: 'Bebas Neue', sans-serif; letter-spacing: .02em; }
[x-cloak] { display: none !important; }

/* ── NAV ── */
.fp-nav {
    background: rgba(10,14,26,.88);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid #1F2840;
}

/* ── HERO HEADLINE ── */
.hero-line {
    display: block;
    overflow: hidden;
}
.hero-line span {
    display: block;
    transform: translateY(110%);
    opacity: 0;
    animation: slideUp .8s cubic-bezier(.22,1,.36,1) forwards;
}
.hero-line:nth-child(1) span { animation-delay: .1s; }
.hero-line:nth-child(2) span { animation-delay: .22s; }
.hero-line:nth-child(3) span { animation-delay: .34s; color: #E6A817; }

@keyframes slideUp {
    to { transform: translateY(0); opacity: 1; }
}
.fade-in {
    opacity: 0;
    animation: fadeIn .7s ease forwards;
}
@keyframes fadeIn { to { opacity: 1; } }

/* ── POSTER CARD ── */
.poster-wrap {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    background: #161C2D;
    border: 1px solid #1F2840;
    aspect-ratio: 2/3;
    max-height: 520px;
    box-shadow: 0 40px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.04);
}
.poster-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.poster-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(145deg, #161C2D 0%, #0D1220 100%);
    flex-direction: column; gap: 12px;
}
.play-btn {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10,14,26,.35);
    backdrop-filter: blur(2px);
    opacity: 0;
    transition: opacity .3s;
    cursor: pointer;
}
.poster-wrap:hover .play-btn { opacity: 1; }
.play-icon {
    width: 72px; height: 72px;
    background: rgba(230,168,23,.92);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 0 16px rgba(230,168,23,.15);
    transition: transform .25s;
}
.play-btn:hover .play-icon { transform: scale(1.1); }

/* ── ROLE CARDS ── */
.role-card {
    background: #161C2D;
    border: 1px solid #1F2840;
    border-radius: 16px;
    padding: 1.75rem;
    transition: border-color .25s, transform .25s, box-shadow .25s;
    text-decoration: none;
    display: block;
    color: inherit;
}
.role-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.role-card.actor:hover  { border-color: #E6A817; }
.role-card.director:hover { border-color: #7C5CBF; }
.role-card.writer:hover { border-color: #2563EB; }

/* ── MARQUEE ── */
@keyframes marquee { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
.marquee-track { animation: marquee 35s linear infinite; display: flex; white-space: nowrap; }
.marquee-wrap:hover .marquee-track { animation-play-state: paused; }

/* ── VIDEO MODAL ── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.88);
    backdrop-filter: blur(8px);
    z-index: 100;
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem;
}
.modal-video {
    width: 100%; max-width: 900px;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 40px 100px rgba(0,0,0,.8);
}
.modal-video iframe,
.modal-video video {
    width: 100%; height: 100%; border: none;
}
.modal-close {
    position: absolute; top: 1.5rem; right: 1.5rem;
    width: 40px; height: 40px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s;
    color: #F0EBE0;
}
.modal-close:hover { background: rgba(255,255,255,.2); }

/* ── ABOUT ── */
.about-section { background: #111827; border-top: 1px solid #1F2840; border-bottom: 1px solid #1F2840; }

/* ── FOOTER ── */
.fp-footer { background: #0A0E1A; border-top: 1px solid #1F2840; }
</style>
</head>

<body x-data="{
    trailerOpen: false,
    trailerUrl: '<?= addslashes(htmlspecialchars($trailerUrl)) ?>',
    openTrailer() { if(this.trailerUrl) this.trailerOpen = true; },
    closeTrailer() { this.trailerOpen = false; }
}" @keydown.escape.window="closeTrailer()">

<!-- ═══════════ NAV ═══════════ -->
<nav class="fp-nav fixed top-0 left-0 right-0 z-50 h-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">
        <a href="/" class="flex items-center gap-2">
            <span class="font-display text-[18px] text-warm tracking-wide">FACELESS PICTURES</span>
            <span class="inline-flex items-center justify-center bg-amber text-ink text-[10px] font-bold w-5 h-5 rounded-full flex-shrink-0">3</span>
        </a>
        <div class="flex items-center gap-3 sm:gap-5">
            <a href="#about" class="text-muted text-xs hover:text-warm transition hidden sm:block uppercase tracking-widest">About</a>
            <a href="/actor"    class="text-muted text-xs hover:text-warm transition uppercase tracking-widest">Actors</a>
            <a href="/director" class="text-muted text-xs hover:text-warm transition uppercase tracking-widest">Directors</a>
            <a href="/writer"   class="text-muted text-xs hover:text-warm transition uppercase tracking-widest">Writers</a>
        </div>
    </div>
</nav>

<!-- ═══════════ HERO ═══════════ -->
<section class="min-h-screen pt-14 flex items-center relative overflow-hidden">

    <!-- Background texture -->
    <div class="absolute inset-0 opacity-[.03]" style="background-image:radial-gradient(circle at 25% 35%, #E6A817 0%, transparent 50%), radial-gradient(circle at 75% 65%, #7C5CBF 0%, transparent 50%);"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 w-full py-16 lg:py-20">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

            <!-- LEFT: Text + CTAs -->
            <div>
                <!-- Eyebrow -->
                <div class="flex items-center gap-3 mb-5 fade-in" style="animation-delay:.05s">
                    <span class="w-2 h-2 bg-amber rounded-full animate-pulse flex-shrink-0"></span>
                    <span class="text-[11px] font-semibold tracking-[3px] uppercase text-muted">Auditions Now Open</span>
                </div>

                <!-- Headline -->
                <h1 class="font-display leading-none mb-6">
                    <?php foreach ($heroLines as $i => $line): ?>
                    <span class="hero-line">
                        <span class="text-[52px] sm:text-[68px] md:text-[80px] lg:text-[72px] xl:text-[90px]"><?= htmlspecialchars($line) ?></span>
                    </span>
                    <?php endforeach; ?>
                </h1>

                <!-- Subtitle -->
                <p class="text-muted text-[16px] sm:text-[17px] leading-relaxed max-w-md mb-8 fade-in" style="animation-delay:.5s">
                    <?= htmlspecialchars($heroSubtitle) ?>
                </p>

                <!-- Role CTAs — "2 clicks to stardom" -->
                <div class="fade-in" style="animation-delay:.6s">
                    <p class="text-[11px] font-semibold tracking-[2px] uppercase text-muted mb-3">Choose your path</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="/actor" class="group flex items-center gap-2.5 bg-amber text-ink font-bold text-[14px] px-5 py-3 rounded-lg hover:bg-amber/90 transition">
                            <span>🎭</span> I'm an Actor
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="/director" class="group flex items-center gap-2.5 bg-panel border border-border text-warm font-semibold text-[14px] px-5 py-3 rounded-lg hover:border-amber/50 transition">
                            <span>🎬</span> I'm a Director
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                        <a href="/writer" class="group flex items-center gap-2.5 bg-panel border border-border text-warm font-semibold text-[14px] px-5 py-3 rounded-lg hover:border-amber/50 transition">
                            <span>✍️</span> I'm a Writer
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                    <p class="text-muted text-xs mt-3 opacity-60">Faceless to Star in 2 clicks — no signup required</p>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-6 mt-10 pt-8 border-t border-border fade-in" style="animation-delay:.7s">
                    <div>
                        <span class="font-display text-[36px] sm:text-[44px] leading-none text-warm block">2,400+</span>
                        <p class="text-muted text-[13px] mt-1">Submissions</p>
                    </div>
                    <div>
                        <span class="font-display text-[36px] sm:text-[44px] leading-none text-amber block">6</span>
                        <p class="text-muted text-[13px] mt-1">Winners Cast</p>
                    </div>
                    <div>
                        <span class="font-display text-[36px] sm:text-[44px] leading-none text-warm block">100%</span>
                        <p class="text-muted text-[13px] mt-1">Anonymous</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Poster + Play -->
            <div class="flex justify-center lg:justify-end fade-in" style="animation-delay:.3s">
                <div class="poster-wrap w-full max-w-[300px] sm:max-w-[340px] lg:max-w-[380px]">
                    <?php if ($posterUrl): ?>
                        <img src="<?= htmlspecialchars($posterUrl) ?>" alt="Faceless Pictures 3 Poster">
                    <?php else: ?>
                        <div class="poster-placeholder">
                            <span class="font-display text-[80px] leading-none text-border">FP</span>
                            <span class="text-muted text-sm tracking-widest uppercase">Poster</span>
                            <span class="text-[11px] text-border">Set in Admin → Landing Page</span>
                        </div>
                    <?php endif; ?>

                    <!-- Play button overlay -->
                    <?php if ($trailerUrl): ?>
                    <div class="play-btn" @click="openTrailer()">
                        <div class="play-icon">
                            <svg class="w-8 h-8 text-ink ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="play-btn" style="opacity:.4;cursor:default;pointer-events:none;">
                        <div class="play-icon" style="background:rgba(230,168,23,.4);">
                            <svg class="w-8 h-8 text-ink ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Trailer label -->
                    <div class="absolute bottom-4 left-0 right-0 text-center">
                        <span class="inline-block bg-ink/70 backdrop-blur-sm text-warm text-[11px] font-semibold tracking-widest uppercase px-3 py-1.5 rounded-full border border-border">
                            <?= $trailerUrl ? 'Watch Trailer' : 'Trailer Coming Soon' ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════ MARQUEE ═══════════ -->
<div class="border-y border-border py-4 overflow-hidden marquee-wrap bg-panel">
    <div class="marquee-track">
        <?php for ($i = 0; $i < 2; $i++): ?>
        <div class="flex items-center gap-8 px-4">
            <?php foreach (['ACTORS','DIRECTORS','WRITERS','NO CONNECTIONS','ONE VIDEO','ONE CHANCE','NOW OPEN','SUBMIT TODAY'] as $word): ?>
                <span class="font-display text-[13px] tracking-[2px] text-muted"><?= $word ?></span>
                <span class="w-1.5 h-1.5 bg-amber rounded-full flex-shrink-0"></span>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- ═══════════ ROLE CARDS ═══════════ -->
<section class="py-20 sm:py-28 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-[11px] font-semibold tracking-[3px] uppercase text-amber mb-2">Choose Your Path</p>
            <h2 class="font-display text-[44px] sm:text-[60px] leading-none text-warm">THREE ROLES</h2>
            <p class="text-muted mt-3 text-sm">Pick your craft. Fill in your details. Upload your take. Done.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-5">

            <a href="/actor" class="role-card actor">
                <div class="text-3xl mb-3">🎭</div>
                <div class="inline-flex items-center gap-1.5 mb-3">
                    <span class="bg-amber/10 text-amber text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Dialog Audition</span>
                    <span class="bg-purple-500/10 text-purple-400 text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Song Audition</span>
                </div>
                <h3 class="font-display text-[36px] leading-none text-warm mb-2">ACTOR</h3>
                <p class="text-muted text-sm leading-relaxed mb-4">Perform the scene on camera. Face hidden. Talent front and centre.</p>
                <span class="text-amber text-sm font-semibold">Audition now →</span>
            </a>

            <a href="/director" class="role-card director">
                <div class="text-3xl mb-3">🎬</div>
                <div class="inline-flex items-center gap-1.5 mb-3">
                    <span class="bg-amber/10 text-amber text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Scene Direction</span>
                    <span class="bg-blue-500/10 text-blue-400 text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Vision Pitch</span>
                </div>
                <h3 class="font-display text-[36px] leading-none text-warm mb-2">DIRECTOR</h3>
                <p class="text-muted text-sm leading-relaxed mb-4">Same scene, your vision. Frame it, pace it, make it yours.</p>
                <span class="text-amber text-sm font-semibold">Submit now →</span>
            </a>

            <a href="/writer" class="role-card writer">
                <div class="text-3xl mb-3">✍️</div>
                <div class="inline-flex items-center gap-1.5 mb-3">
                    <span class="bg-purple-500/10 text-purple-400 text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Script Submission</span>
                    <span class="bg-amber/10 text-amber text-[10px] font-bold tracking-widest uppercase px-2 py-0.5 rounded">Script Reading</span>
                </div>
                <h3 class="font-display text-[36px] leading-none text-warm mb-2">WRITER</h3>
                <p class="text-muted text-sm leading-relaxed mb-4">We give you Scene 1. You write what happens next.</p>
                <span class="text-amber text-sm font-semibold">Submit now →</span>
            </a>

        </div>
    </div>
</section>

<!-- ═══════════ ABOUT ═══════════ -->
<section id="about" class="about-section py-20 sm:py-28 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="grid sm:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-[11px] font-semibold tracking-[3px] uppercase text-amber mb-3">About</p>
                <h2 class="font-display text-[44px] sm:text-[56px] leading-none text-warm mb-6">WHAT IS<br>FACELESS<br>PICTURES?</h2>
                <p class="text-muted text-base leading-relaxed mb-5"><?= htmlspecialchars($aboutText) ?></p>
                <p class="text-muted text-sm leading-relaxed opacity-70">The best performances, directions, and scripts are selected anonymously — your work is judged only on its merit. Winners get cast in real productions.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-panel border border-border rounded-xl p-5 text-center">
                    <span class="font-display text-[48px] leading-none text-amber block">3</span>
                    <p class="text-muted text-xs uppercase tracking-widest mt-1">Roles</p>
                </div>
                <div class="bg-panel border border-border rounded-xl p-5 text-center">
                    <span class="font-display text-[48px] leading-none text-warm block">∞</span>
                    <p class="text-muted text-xs uppercase tracking-widest mt-1">Possibilities</p>
                </div>
                <div class="bg-panel border border-border rounded-xl p-5 text-center">
                    <span class="font-display text-[48px] leading-none text-warm block">0</span>
                    <p class="text-muted text-xs uppercase tracking-widest mt-1">Bias</p>
                </div>
                <div class="bg-panel border border-border rounded-xl p-5 text-center">
                    <span class="font-display text-[48px] leading-none text-amber block">2</span>
                    <p class="text-muted text-xs uppercase tracking-widest mt-1">Clicks to submit</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ FOOTER ═══════════ -->
<footer class="fp-footer py-10 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 mb-8">
            <a href="/" class="flex items-center gap-2">
                <span class="font-display text-[22px] text-warm">FACELESS PICTURES</span>
                <span class="inline-flex items-center justify-center bg-amber text-ink text-[10px] font-bold w-5 h-5 rounded-full">3</span>
            </a>
            <div class="flex flex-wrap justify-center gap-6 text-muted text-sm">
                <a href="/actor"    class="hover:text-warm transition">Actor Auditions</a>
                <a href="/director" class="hover:text-warm transition">Director Auditions</a>
                <a href="/writer"   class="hover:text-warm transition">Writer Submissions</a>
                <a href="#about"    class="hover:text-warm transition">About</a>
            </div>
        </div>
        <div class="border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-muted text-xs">
            <span>© <?= date('Y') ?> Faceless Pictures. All rights reserved.</span>
            <span>India's first anonymous film competition.</span>
        </div>
    </div>
</footer>

<!-- ═══════════ TRAILER MODAL ═══════════ -->
<div x-show="trailerOpen" x-cloak class="modal-backdrop" @click.self="closeTrailer()" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div style="position:relative;width:100%;max-width:900px;">
        <button class="modal-close" @click="closeTrailer()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="modal-video">
            <template x-if="trailerOpen">
                <?php if ($trailerUrl && (strpos($trailerUrl, 'youtube.com') !== false || strpos($trailerUrl, 'youtu.be') !== false)): ?>
                <iframe src="<?= htmlspecialchars($trailerUrl) ?>?autoplay=1" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                <?php elseif ($trailerUrl): ?>
                <video src="<?= htmlspecialchars($trailerUrl) ?>" controls autoplay></video>
                <?php endif; ?>
            </template>
        </div>
    </div>
</div>

</body>
</html>

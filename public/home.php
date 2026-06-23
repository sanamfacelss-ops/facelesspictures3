<?php
require_once __DIR__ . '/../app/config/config.php';
$settingsModel = new App\Models\Settings();

$heroTitle    = $settingsModel->get('landing_hero_title', "NO FACE.\nNO CONNECTIONS.\nJUST TALENT.");
$heroSubtitle = $settingsModel->get('landing_hero_subtitle', "India's first anonymous film competition. Actors, Directors & Writers compete purely on talent.");
$posterUrl    = $settingsModel->get('landing_poster_url', '');
$trailerUrl   = $settingsModel->get('landing_trailer_url', '');
$aboutText    = $settingsModel->get('landing_about_text', "Faceless Pictures is India's first anonymous film competition where talent speaks without a face. We believe the best stories deserve to be told — regardless of who you know or what you look like.");
$heroLines    = array_values(array_filter(array_map('trim', explode("\n", $heroTitle))));

// Check for logo file
$logoFile     = file_exists(__DIR__ . '/assets/logo.png') ? '/assets/logo.png' : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faceless Pictures 3 — No Face. Just Talent.</title>
<meta name="description" content="India's first anonymous film competition. No face, no connections — just raw talent.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased;overflow-x:hidden}
.font-display{font-family:'Bebas Neue',sans-serif;letter-spacing:.02em}
[x-cloak]{display:none!important}

/* NAV */
.fp-nav{background:rgba(255,255,255,.96);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:64px}

/* HERO */
.hero-line{display:block;overflow:hidden}
.hero-line span{display:block;transform:translateY(110%);opacity:0;animation:slideUp .8s cubic-bezier(.22,1,.36,1) forwards}
.hero-line:nth-child(1) span{animation-delay:.08s}
.hero-line:nth-child(2) span{animation-delay:.2s}
.hero-line:nth-child(3) span{animation-delay:.32s;color:#1a1a1a}
@keyframes slideUp{to{transform:translateY(0);opacity:1}}
.fade-in{opacity:0;animation:fadeIn .7s ease forwards}
@keyframes fadeIn{to{opacity:1}}

/* POSTER CARD */
.poster-card{position:relative;border-radius:14px;overflow:hidden;background:#f3f4f6;aspect-ratio:2/3;box-shadow:0 4px 24px rgba(0,0,0,.10);transition:transform .3s,box-shadow .3s}
.poster-card:hover{transform:translateY(-6px);box-shadow:0 16px 48px rgba(0,0,0,.16)}
.poster-card img{width:100%;height:100%;object-fit:cover;display:block}
.poster-placeholder-inner{width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;background:linear-gradient(145deg,#e5e7eb,#f9fafb)}
.play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.32);opacity:0;transition:opacity .3s;cursor:pointer}
.poster-card:hover .play-overlay{opacity:1}
.play-circle{width:60px;height:60px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 24px rgba(0,0,0,.3);transition:transform .2s}
.play-overlay:hover .play-circle{transform:scale(1.1)}
.poster-label{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(0,0,0,.75) 0%,transparent 100%);padding:1.25rem .75rem .75rem;color:#fff;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em}

/* ROLE CARDS */
.role-card{border:2px solid #e5e7eb;border-radius:14px;padding:1.5rem;text-decoration:none;color:inherit;display:block;background:#fff;transition:border-color .2s,transform .2s,box-shadow .2s}
.role-card:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(0,0,0,.08)}
.role-card.actor:hover{border-color:#111}
.role-card.director:hover{border-color:#111}
.role-card.writer:hover{border-color:#111}

/* BADGE */
.badge{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:3px 8px;border-radius:4px;border:1px solid currentColor}

/* AUDITION CTA BTN */
.btn-primary{background:#111;color:#fff;font-weight:700;border-radius:8px;padding:.7rem 1.5rem;font-size:.875rem;display:inline-flex;align-items:center;gap:.5rem;transition:background .2s,transform .1s;text-decoration:none}
.btn-primary:hover{background:#333}
.btn-secondary{background:#fff;color:#111;font-weight:600;border:2px solid #d1d5db;border-radius:8px;padding:.65rem 1.4rem;font-size:.875rem;display:inline-flex;align-items:center;gap:.5rem;transition:border-color .2s,background .2s;text-decoration:none}
.btn-secondary:hover{border-color:#111;background:#f9fafb}

/* MARQUEE */
@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.marquee-track{animation:marquee 35s linear infinite;display:flex;white-space:nowrap}
.marquee-wrap:hover .marquee-track{animation-play-state:paused}

/* MODAL */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);z-index:100;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.modal-video{width:100%;max-width:900px;aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden;box-shadow:0 40px 80px rgba(0,0,0,.6)}
.modal-video iframe,.modal-video video{width:100%;height:100%;border:none}
.modal-close{position:absolute;top:1rem;right:1rem;width:40px;height:40px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;transition:background .2s}
.modal-close:hover{background:rgba(255,255,255,.25)}

/* DIVIDER */
.section-divider{border:none;border-top:1px solid #e5e7eb;margin:0}

/* ABOUT */
.about-section{background:#f9fafb;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb}
.stat-box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1.25rem;text-align:center}

/* FOOTER */
.fp-footer{background:#111;color:#fff}
</style>
</head>

<body x-data="{
    trailerOpen:false,
    trailerUrl:'<?= addslashes(htmlspecialchars($trailerUrl)) ?>',
    openTrailer(){if(this.trailerUrl)this.trailerOpen=true},
    closeTrailer(){this.trailerOpen=false}
}" @keydown.escape.window="closeTrailer()">

<!-- NAV -->
<nav class="fp-nav">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">

    <!-- Logo -->
    <a href="/" class="flex items-center gap-3 flex-shrink-0">
      <?php if ($logoFile): ?>
        <img src="<?= $logoFile ?>" alt="Faceless Pictures 3" class="h-9 w-auto" style="mix-blend-mode:multiply">
      <?php else: ?>
        <!-- Text logo — replace with <img> once transparent PNG is uploaded to public/assets/logo.png -->
        <span style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:.04em;color:#111;line-height:1">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
      <?php endif; ?>
    </a>

    <!-- Nav links -->
    <div class="flex items-center gap-5 sm:gap-7">
      <a href="#about"    class="text-gray-500 text-xs font-medium hover:text-gray-900 transition hidden sm:block uppercase tracking-widest">About</a>
      <a href="/actor"    class="text-gray-500 text-xs font-medium hover:text-gray-900 transition uppercase tracking-widest">Actors</a>
      <a href="/director" class="text-gray-500 text-xs font-medium hover:text-gray-900 transition uppercase tracking-widest">Directors</a>
      <a href="/writer"   class="text-gray-500 text-xs font-medium hover:text-gray-900 transition uppercase tracking-widest">Writers</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="pt-16 pb-0 overflow-hidden">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">

    <!-- Top: headline + subtitle + CTAs -->
    <div class="pt-14 pb-12 max-w-3xl">
      <div class="flex items-center gap-2 mb-5 fade-in" style="animation-delay:.05s">
        <span class="w-1.5 h-1.5 bg-gray-900 rounded-full animate-pulse"></span>
        <span class="text-[11px] font-semibold tracking-[3px] uppercase text-gray-400">Auditions Now Open</span>
      </div>

      <h1 class="font-display leading-none mb-6">
        <?php foreach ($heroLines as $i => $line): ?>
        <span class="hero-line">
          <span class="text-[56px] sm:text-[72px] md:text-[88px] lg:text-[96px] text-gray-900"><?= htmlspecialchars($line) ?></span>
        </span>
        <?php endforeach; ?>
      </h1>

      <p class="text-gray-500 text-[16px] sm:text-[17px] leading-relaxed max-w-xl mb-8 fade-in" style="animation-delay:.45s">
        <?= htmlspecialchars($heroSubtitle) ?>
      </p>

      <div class="flex flex-wrap gap-3 fade-in" style="animation-delay:.55s">
        <a href="/actor"    class="btn-primary">🎭 I'm an Actor <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></a>
        <a href="/director" class="btn-secondary">🎬 I'm a Director <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></a>
        <a href="/writer"   class="btn-secondary">✍️ I'm a Writer <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></a>
      </div>
      <p class="text-gray-400 text-xs mt-3">Faceless to Star in 2 clicks — no signup required</p>
    </div>

    <!-- POSTER ROW — one row of boxes with play button -->
    <div class="pb-16">
      <p class="text-[11px] font-semibold tracking-[3px] uppercase text-gray-400 mb-4">Current Productions</p>
      <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory" style="-webkit-overflow-scrolling:touch;scrollbar-width:none">

        <!-- Poster 1: Main poster (admin-set) or placeholder -->
        <div class="snap-start flex-shrink-0 w-[180px] sm:w-[200px]">
          <div class="poster-card">
            <?php if ($posterUrl): ?>
              <img src="<?= htmlspecialchars($posterUrl) ?>" alt="Faceless Pictures 3">
            <?php else: ?>
              <div class="poster-placeholder-inner">
                <span style="font-family:'Bebas Neue',sans-serif;font-size:48px;color:#d1d5db">FP</span>
                <span class="text-[10px] text-gray-400 uppercase tracking-widest">Poster</span>
              </div>
            <?php endif; ?>
            <?php if ($trailerUrl): ?>
            <div class="play-overlay" @click="openTrailer()">
              <div class="play-circle">
                <svg class="w-6 h-6 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </div>
            </div>
            <?php else: ?>
            <div class="play-overlay" style="opacity:.35;cursor:default">
              <div class="play-circle" style="opacity:.6">
                <svg class="w-6 h-6 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </div>
            </div>
            <?php endif; ?>
            <div class="poster-label"><?= $trailerUrl ? 'Watch Trailer' : 'Coming Soon' ?></div>
          </div>
          <a href="/actor" class="mt-3 btn-primary w-full justify-center text-xs py-2.5">Audition →</a>
        </div>

        <!-- Actor placeholder card -->
        <div class="snap-start flex-shrink-0 w-[180px] sm:w-[200px]">
          <div class="poster-card" style="cursor:default">
            <div class="poster-placeholder-inner">
              <div style="font-size:48px;line-height:1">🎭</div>
              <span style="font-family:'Bebas Neue',sans-serif;font-size:22px;color:#374151;letter-spacing:.05em">ACTOR</span>
              <span class="text-[10px] text-gray-400">Dialog · Song</span>
            </div>
            <div class="poster-label">Acting Auditions</div>
          </div>
          <a href="/actor" class="mt-3 btn-primary w-full justify-center text-xs py-2.5">Audition →</a>
        </div>

        <!-- Director placeholder card -->
        <div class="snap-start flex-shrink-0 w-[180px] sm:w-[200px]">
          <div class="poster-card" style="cursor:default">
            <div class="poster-placeholder-inner">
              <div style="font-size:48px;line-height:1">🎬</div>
              <span style="font-family:'Bebas Neue',sans-serif;font-size:22px;color:#374151;letter-spacing:.05em">DIRECTOR</span>
              <span class="text-[10px] text-gray-400">Scene · Pitch</span>
            </div>
            <div class="poster-label">Director Auditions</div>
          </div>
          <a href="/director" class="mt-3 btn-primary w-full justify-center text-xs py-2.5">Submit →</a>
        </div>

        <!-- Writer placeholder card -->
        <div class="snap-start flex-shrink-0 w-[180px] sm:w-[200px]">
          <div class="poster-card" style="cursor:default">
            <div class="poster-placeholder-inner">
              <div style="font-size:48px;line-height:1">✍️</div>
              <span style="font-family:'Bebas Neue',sans-serif;font-size:22px;color:#374151;letter-spacing:.05em">WRITER</span>
              <span class="text-[10px] text-gray-400">Script · Reading</span>
            </div>
            <div class="poster-label">Writer Submissions</div>
          </div>
          <a href="/writer" class="mt-3 btn-primary w-full justify-center text-xs py-2.5">Submit →</a>
        </div>

      </div>
    </div>
  </div>
</section>

<hr class="section-divider">

<!-- MARQUEE -->
<div class="py-3 overflow-hidden marquee-wrap bg-gray-50 border-b border-gray-200">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
    <div class="flex items-center gap-6 px-4">
      <?php foreach (['ACTORS','DIRECTORS','WRITERS','NO CONNECTIONS','ONE VIDEO','ONE CHANCE','NOW OPEN','SUBMIT TODAY','NO FACE','JUST TALENT'] as $w): ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:13px;letter-spacing:.15em;color:#9ca3af"><?= $w ?></span>
        <span style="width:4px;height:4px;background:#111;border-radius:50%;flex-shrink:0;display:inline-block"></span>
      <?php endforeach; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- STATS ROW -->
<section class="py-12 px-4 border-b border-gray-100">
  <div class="max-w-4xl mx-auto grid grid-cols-3 gap-6 text-center">
    <div>
      <span class="font-display text-[48px] sm:text-[56px] leading-none text-gray-900 block">2,400+</span>
      <p class="text-gray-400 text-sm mt-1">Submissions</p>
    </div>
    <div>
      <span class="font-display text-[48px] sm:text-[56px] leading-none text-gray-900 block">6</span>
      <p class="text-gray-400 text-sm mt-1">Winners Cast</p>
    </div>
    <div>
      <span class="font-display text-[48px] sm:text-[56px] leading-none text-gray-900 block">100%</span>
      <p class="text-gray-400 text-sm mt-1">Anonymous</p>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="about-section py-16 sm:py-24 px-4">
  <div class="max-w-5xl mx-auto">
    <div class="grid sm:grid-cols-2 gap-12 items-center">
      <div>
        <p class="text-[11px] font-semibold tracking-[3px] uppercase text-gray-400 mb-3">About</p>
        <h2 class="font-display text-[44px] sm:text-[56px] leading-none text-gray-900 mb-6">WHAT IS<br>FACELESS<br>PICTURES?</h2>
        <p class="text-gray-500 text-base leading-relaxed mb-4"><?= htmlspecialchars($aboutText) ?></p>
        <p class="text-gray-400 text-sm leading-relaxed">Performances, directions and scripts are judged anonymously — your work on its own merit. Winners get cast in real productions.</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div class="stat-box"><span class="font-display text-[48px] leading-none text-gray-900 block">3</span><p class="text-gray-400 text-xs uppercase tracking-widest mt-1">Roles</p></div>
        <div class="stat-box"><span class="font-display text-[48px] leading-none text-gray-900 block">∞</span><p class="text-gray-400 text-xs uppercase tracking-widest mt-1">Possibilities</p></div>
        <div class="stat-box"><span class="font-display text-[48px] leading-none text-gray-900 block">0</span><p class="text-gray-400 text-xs uppercase tracking-widest mt-1">Bias</p></div>
        <div class="stat-box"><span class="font-display text-[48px] leading-none text-gray-900 block">2</span><p class="text-gray-400 text-xs uppercase tracking-widest mt-1">Clicks to submit</p></div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="fp-footer py-10 px-4">
  <div class="max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-6 mb-6">
      <a href="/" class="flex items-center gap-2">
        <?php if ($logoFile): ?>
          <img src="<?= $logoFile ?>" alt="Faceless Pictures 3" class="h-8 w-auto" style="filter:brightness(10)">
        <?php else: ?>
          <span style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:.04em;color:#fff">FACELESS PICTURES</span>
          <span style="background:#fff;color:#111;font-size:10px;font-weight:700;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
        <?php endif; ?>
      </a>
      <div class="flex flex-wrap justify-center gap-6 text-sm text-gray-400">
        <a href="/actor"    class="hover:text-white transition">Actor Auditions</a>
        <a href="/director" class="hover:text-white transition">Director Auditions</a>
        <a href="/writer"   class="hover:text-white transition">Writer Submissions</a>
        <a href="#about"    class="hover:text-white transition">About</a>
      </div>
    </div>
    <div class="border-t border-white/10 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-gray-500 text-xs">
      <span>© <?= date('Y') ?> Faceless Pictures. All rights reserved.</span>
      <span>India's first anonymous film competition.</span>
    </div>
  </div>
</footer>

<!-- TRAILER MODAL -->
<div x-show="trailerOpen" x-cloak class="modal-backdrop" @click.self="closeTrailer()"
  x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
  <div style="position:relative;width:100%;max-width:900px">
    <button class="modal-close" @click="closeTrailer()">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="modal-video">
      <template x-if="trailerOpen">
        <?php if ($trailerUrl && (str_contains($trailerUrl,'youtube.com') || str_contains($trailerUrl,'youtu.be'))): ?>
        <iframe src="<?= htmlspecialchars($trailerUrl) ?>?autoplay=1" allow="autoplay;encrypted-media" allowfullscreen></iframe>
        <?php elseif ($trailerUrl): ?>
        <video src="<?= htmlspecialchars($trailerUrl) ?>" controls autoplay></video>
        <?php endif; ?>
      </template>
    </div>
  </div>
</div>

</body>
</html>

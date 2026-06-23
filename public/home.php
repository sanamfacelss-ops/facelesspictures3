<?php
require_once __DIR__ . '/../app/config/config.php';
$settingsModel = new App\Models\Settings();

$trailerUrl  = $settingsModel->get('landing_trailer_url', '');
$aboutText   = $settingsModel->get('landing_about_text', "Faceless Pictures is India's first anonymous film competition where talent speaks without a face.");

// Up to 3 poster slots — admin sets these in Settings tab
$posters = [
    [
        'url'   => $settingsModel->get('landing_poster_url',   ''),
        'title' => $settingsModel->get('landing_poster_title', 'Faceless Pictures 3'),
        'trailer' => $settingsModel->get('landing_trailer_url', ''),
    ],
    [
        'url'   => $settingsModel->get('landing_poster2_url',   ''),
        'title' => $settingsModel->get('landing_poster2_title', ''),
        'trailer' => $settingsModel->get('landing_trailer2_url', ''),
    ],
    [
        'url'   => $settingsModel->get('landing_poster3_url',   ''),
        'title' => $settingsModel->get('landing_poster3_title', ''),
        'trailer' => $settingsModel->get('landing_trailer3_url', ''),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faceless Pictures 3 — No Face. Just Talent.</title>
<meta name="description" content="India's first anonymous film competition. Actor, Director & Writer auditions open now.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased;overflow-x:hidden}
[x-cloak]{display:none!important}

/* NAV */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s}
.nav-link:hover{color:#111}

/* POSTER CARD */
.poster-card{position:relative;border-radius:12px;overflow:hidden;background:#f3f4f6;aspect-ratio:2/3;box-shadow:0 2px 16px rgba(0,0,0,.10);transition:transform .3s,box-shadow .3s;display:block}
.poster-card:hover{transform:translateY(-5px);box-shadow:0 12px 40px rgba(0,0,0,.16)}
.poster-card img{width:100%;height:100%;object-fit:cover;display:block}
.poster-empty{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:linear-gradient(145deg,#e5e7eb,#f9fafb)}
.play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3);opacity:0;transition:opacity .25s;cursor:pointer}
.poster-card:hover .play-overlay{opacity:1}
.play-circle{width:56px;height:56px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.25);transition:transform .2s}
.play-overlay:hover .play-circle{transform:scale(1.1)}
.poster-title-bar{position:absolute;bottom:0;left:0;right:0;padding:.875rem .75rem .6rem;background:linear-gradient(to top,rgba(0,0,0,.7),transparent);color:#fff;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}

/* ROLE CARD */
.role-card{border:2px solid #e5e7eb;border-radius:12px;padding:1.5rem 1.25rem;text-align:center;text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;background:#fff;transition:border-color .2s,transform .2s,box-shadow .2s}
.role-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.07);border-color:#111}
.role-icon{width:56px;height:56px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:.875rem}
.role-name{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:.04em;color:#111;margin-bottom:.25rem}
.role-desc{font-size:.8rem;color:#6b7280;margin-bottom:1rem;line-height:1.5}
.role-badges{display:flex;flex-wrap:wrap;justify-content:center;gap:.35rem;margin-bottom:1rem}
.badge{font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 7px;border-radius:4px;border:1px solid #d1d5db;color:#374151;background:#f9fafb}

/* BUTTONS */
.btn-black{background:#111;color:#fff;font-weight:700;border:none;border-radius:7px;padding:.65rem 1.4rem;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:background .2s;width:100%;justify-content:center}
.btn-black:hover{background:#333}

/* SECTION LABEL */
.section-label{font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:1.25rem}

/* MARQUEE */
@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.marquee-track{animation:marquee 40s linear infinite;display:flex;white-space:nowrap}
.marquee-wrap:hover .marquee-track{animation-play-state:paused}

/* MODAL */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.87);backdrop-filter:blur(8px);z-index:200;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.modal-box{width:100%;max-width:880px;aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.6);position:relative}
.modal-box iframe,.modal-box video{width:100%;height:100%;border:none}
.modal-close-btn{position:absolute;top:-.75rem;right:-.75rem;width:36px;height:36px;background:#fff;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:#111;box-shadow:0 2px 10px rgba(0,0,0,.2);transition:background .2s}
.modal-close-btn:hover{background:#f3f4f6}

/* FOOTER */
.fp-footer{background:#111;color:#fff;padding:2.5rem 1rem}
</style>
</head>

<body x-data="{
    activeTrailer: '',
    openTrailer(url){ if(url){ this.activeTrailer=url } },
    closeTrailer(){ this.activeTrailer='' }
}" @keydown.escape.window="closeTrailer()">

<!-- ── NAV ── -->
<nav class="fp-nav">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">
    <a href="/" class="nav-logo">
      FACELESS PICTURES <span class="nav-badge">3</span>
    </a>
    <div class="flex items-center gap-4 sm:gap-6">
      <a href="#about"    class="nav-link hidden sm:block">About</a>
      <a href="/actor"    class="nav-link">Actors</a>
      <a href="/director" class="nav-link">Directors</a>
      <a href="/writer"   class="nav-link">Writers</a>
    </div>
  </div>
</nav>

<!-- ── MAIN CONTENT ── -->
<main class="pt-[60px]">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-14">

    <!-- ══ ROW 1: FILM POSTER BOXES ══ -->
    <p class="section-label">Now Showing — Auditions Open</p>
    <div class="grid grid-cols-3 gap-4 sm:gap-6 mb-14">

      <?php foreach ($posters as $idx => $poster): ?>
      <div>
        <div class="poster-card"
          <?php if ($poster['trailer']): ?>
            @click="openTrailer('<?= addslashes(htmlspecialchars($poster['trailer'])) ?>')"
            style="cursor:pointer"
          <?php else: ?>
            style="cursor:default"
          <?php endif; ?>>

          <?php if ($poster['url']): ?>
            <img src="<?= htmlspecialchars($poster['url']) ?>"
                 alt="<?= htmlspecialchars($poster['title'] ?: 'Film Poster') ?>">
          <?php else: ?>
            <div class="poster-empty">
              <svg width="36" height="36" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              <span style="font-size:.68rem;color:#9ca3af;letter-spacing:.08em;text-transform:uppercase">
                <?= $idx === 0 ? 'Set poster in Admin' : 'Poster ' . ($idx+1) ?>
              </span>
            </div>
          <?php endif; ?>

          <!-- Play overlay — only if trailer set -->
          <?php if ($poster['trailer']): ?>
          <div class="play-overlay">
            <div class="play-circle">
              <svg width="22" height="22" fill="#111" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($poster['title']): ?>
          <div class="poster-title-bar"><?= htmlspecialchars($poster['title']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Audition CTA below poster -->
        <a href="/actor" class="btn-black mt-3">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
          Audition for this film
        </a>
      </div>
      <?php endforeach; ?>

    </div>

    <!-- ══ ROW 2: ROLE BOXES ══ -->
    <p class="section-label">Choose Your Role</p>
    <div class="grid grid-cols-3 gap-4 sm:gap-6">

      <!-- ACTOR -->
      <div class="role-card">
        <div class="role-icon">🎭</div>
        <p class="role-name">ACTOR</p>
        <p class="role-desc">Perform on camera. Face hidden.<br>Talent front and centre.</p>
        <div class="role-badges">
          <span class="badge">Dialog Audition</span>
          <span class="badge">Song Audition</span>
        </div>
        <a href="/actor" class="btn-black">Audition Now →</a>
      </div>

      <!-- DIRECTOR -->
      <div class="role-card">
        <div class="role-icon">🎬</div>
        <p class="role-name">DIRECTOR</p>
        <p class="role-desc">Same scene, your vision.<br>Frame it and make it yours.</p>
        <div class="role-badges">
          <span class="badge">Scene Direction</span>
          <span class="badge">Vision Pitch</span>
        </div>
        <a href="/director" class="btn-black">Submit Now →</a>
      </div>

      <!-- WRITER -->
      <div class="role-card">
        <div class="role-icon">✍️</div>
        <p class="role-name">WRITER</p>
        <p class="role-desc">We give you Scene 1.<br>You write what happens next.</p>
        <div class="role-badges">
          <span class="badge">Script Submission</span>
          <span class="badge">Script Reading</span>
        </div>
        <a href="/writer" class="btn-black">Submit Now →</a>
      </div>

    </div>

  </div><!-- /max-w -->
</main>

<!-- ── MARQUEE ── -->
<div class="marquee-wrap overflow-hidden border-y border-gray-100 py-3 bg-gray-50">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
    <div class="flex items-center gap-6 px-4">
      <?php foreach (['ACTORS','DIRECTORS','WRITERS','NO CONNECTIONS','ONE VIDEO','ONE CHANCE','NOW OPEN','NO FACE','JUST TALENT','SUBMIT TODAY'] as $w): ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:12px;letter-spacing:.18em;color:#9ca3af"><?= $w ?></span>
        <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;flex-shrink:0"></span>
      <?php endforeach; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- ── ABOUT ── -->
<section id="about" class="py-16 px-4 bg-white border-t border-gray-100">
  <div class="max-w-3xl mx-auto text-center">
    <p style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:.75rem">About</p>
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(36px,5vw,52px);letter-spacing:.02em;color:#111;margin-bottom:1rem">WHAT IS FACELESS PICTURES?</h2>
    <p style="color:#6b7280;font-size:.95rem;line-height:1.75;max-width:600px;margin:0 auto"><?= htmlspecialchars($aboutText) ?></p>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer class="fp-footer">
  <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
    <a href="/" style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#fff;text-decoration:none;display:flex;align-items:center;gap:6px">
      FACELESS PICTURES
      <span style="background:#fff;color:#111;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
    </a>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;justify-content:center">
      <a href="/actor"    style="color:#9ca3af;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'">Actors</a>
      <a href="/director" style="color:#9ca3af;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'">Directors</a>
      <a href="/writer"   style="color:#9ca3af;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'">Writers</a>
      <a href="#about"    style="color:#9ca3af;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#9ca3af'">About</a>
    </div>
    <p style="color:#4b5563;font-size:.75rem">© <?= date('Y') ?> Faceless Pictures. All rights reserved.</p>
  </div>
</footer>

<!-- ── TRAILER MODAL ── -->
<div x-show="activeTrailer" x-cloak class="modal-bg"
  @click.self="closeTrailer()"
  x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
  <div style="position:relative;width:100%;max-width:880px">
    <button class="modal-close-btn" @click="closeTrailer()">✕</button>
    <div class="modal-box">
      <template x-if="activeTrailer">
        <template x-if="activeTrailer.includes('youtube.com') || activeTrailer.includes('youtu.be')">
          <iframe :src="activeTrailer + '?autoplay=1'" allow="autoplay;encrypted-media" allowfullscreen></iframe>
        </template>
      </template>
      <template x-if="activeTrailer && !activeTrailer.includes('youtube.com') && !activeTrailer.includes('youtu.be')">
        <video :src="activeTrailer" controls autoplay></video>
      </template>
    </div>
  </div>
</div>

</body>
</html>

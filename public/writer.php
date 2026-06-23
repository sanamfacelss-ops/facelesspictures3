<?php
require_once __DIR__ . '/../app/config/config.php';

$settingsModel = new App\Models\Settings();
$writerBrief   = $settingsModel->get('writer_brief',
    'Scene 1 ends with the line: "I never thought you\'d come back." Write Scene 2 — 1 to 3 pages. Proper screenplay format. Record yourself reading it on video.');

$logoUrl   = $settingsModel->get('site_logo_url', '');
$pageTitle = 'Writer Submissions — Faceless Pictures 3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
.badge-script{background:#111;color:#fff;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .75rem;border-radius:20px;display:inline-block}
.badge-reading{background:#374151;color:#fff;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .75rem;border-radius:20px;display:inline-block}
.audition-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;transition:border-color .2s,box-shadow .2s}
.audition-card:hover{border-color:#d1d5db;box-shadow:0 4px 20px rgba(0,0,0,.05)}
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;transition:border-color .2s,background .2s;cursor:pointer}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f9fafb}
.fp-input{background:#fff;border:1.5px solid #d1d5db;border-radius:8px;color:#111;padding:.65rem .875rem;width:100%;font-size:.9375rem;transition:border-color .2s,box-shadow .2s;outline:none;-webkit-appearance:none}
.fp-input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.07)}
.fp-input::placeholder{color:#9ca3af}
.fp-label{display:block;font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#374151;margin-bottom:.4rem}
.btn-amber,.btn-main{background:#111;color:#fff;font-weight:700;border-radius:8px;padding:.75rem 1.5rem;border:none;font-size:.9375rem;cursor:pointer;transition:background .2s,transform .1s;display:inline-flex;align-items:center;gap:.4rem}
.btn-amber:hover,.btn-main:hover{background:#333}
.btn-amber:disabled,.btn-main:disabled{opacity:.4;cursor:not-allowed}
.script-block{background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:1.25rem;font-size:.9rem;line-height:1.7;color:#374151}
.btn-pdf{display:inline-flex;align-items:center;gap:.4rem;background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;border-radius:6px;padding:.35rem .7rem;font-size:.72rem;font-weight:600;cursor:pointer;transition:background .2s;white-space:nowrap}
.btn-pdf:hover{background:#e5e7eb}
.success-box{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;color:#166534}
.error-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;color:#991b1b}
.progress-bar{height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden}
.progress-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
.sec-label{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#9ca3af}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .45s ease forwards}
</style>
</head>
<body>
<nav class="fp-nav">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">
    <a href="/" style="text-decoration:none;display:flex;align-items:center;gap:8px">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:32px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;align-items:center;gap:1.25rem">
      <a href="/actor"    style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none;border-bottom:2px solid #111;padding-bottom:2px">Writer</a>
    </div>
  </div>
</nav>

<section class="pt-24 pb-12 px-4">
  <div class="max-w-4xl mx-auto text-center fade-up">
    <span class="inline-block bg-gray-100 text-gray-500 text-[11px] font-semibold tracking-[3px] uppercase px-4 py-1.5 rounded-full mb-4">Now Open</span>
    <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(48px,8vw,88px);letter-spacing:.02em;line-height:.95;color:#111;margin-bottom:1rem">WRITER<br>SUBMISSIONS</h1>
    <p style="color:#6b7280;font-size:1rem;max-width:440px;margin:0 auto;line-height:1.65">
      We give you Scene 1. You write what happens next. Great scripts become great films.
    </p>
  </div>
</section>

<section class="pb-20 px-4">
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- SCRIPT SUBMISSION CARD -->
    <div class="audition-card p-6 md:p-8" x-data="submissionForm('writer','Script Submission','Script Submission',<?= json_encode($writerBrief) ?>)">
      <div class="flex items-start gap-4 mb-6">
        <div class="badge-script text-white text-[11px] font-bold tracking-widest uppercase px-3 py-1.5 rounded-full flex-shrink-0 mt-0.5">Script Submission</div>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:.02em;color:#111">SCRIPT SUBMISSION</h2>
          <p style="color:#6b7280;font-size:.85rem;margin-top:.2rem">Write Scene 2. Submit as a video reading.</p>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <p class="text-[11px] font-semibold tracking-[2px] uppercase text-amber">The Brief</p>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Script Submission',auditionType:'Script Submission',content:<?= json_encode($writerBrief) ?>};downloadBriefPDF()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block"><?= nl2br(htmlspecialchars($writerBrief)) ?></div>
      </div>
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Full Name *</label><input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name"></div>
          <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Email *</label><input type="email" x-model="form.email" class="fp-input" placeholder="your@email.com" required autocomplete="email"></div>
        </div>
        <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Phone Number *</label><input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel"></div>
        <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Notes <span class="normal-case font-normal">(optional)</span></label><textarea x-model="form.notes" class="fp-input" rows="2" placeholder="Your approach to Scene 2, genre choices, tone..."></textarea></div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Upload Script Reading Video <span class="text-amber">*</span></label>
          <p class="text-[11px] text-muted mb-2 opacity-60">Required · Record yourself reading your script · MP4, MOV, WEBM · max 500 MB · will be published to YouTube</p>
          <div class="upload-zone p-6 text-center" :class="dragOver?'drag':''" @dragover.prevent="dragOver=true" @dragleave="dragOver=false" @drop.prevent="handleDrop($event)" @click="$refs.file1.click()">
            <input type="file" x-ref="file1" class="hidden" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file"><div><svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><p class="text-muted text-sm">Drop video or <span class="text-amber">click to browse</span></p><p class="text-muted text-xs mt-1">MP4 · MOV · WEBM · max 500 MB</p></div></template>
            <template x-if="file"><div class="flex items-center justify-center gap-3"><svg class="w-5 h-5 text-amber flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><span class="text-warm text-sm font-medium" x-text="file.name"></span><button type="button" @click.stop="file=null" class="text-muted hover:text-warm">✕</button></div></template>
          </div>
        </div>
        <template x-if="uploading"><div><div class="flex justify-between text-xs text-muted mb-1"><span>Uploading...</span><span x-text="progress+'%'"></span></div><div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div></div></template>
        <template x-if="errors.length"><div class="error-box p-3 text-sm text-red-300"><ul class="space-y-1"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul></div></template>
        <template x-if="success"><div class="success-box p-4 text-green-300 text-sm" x-text="success"></div></template>
        <button type="submit" class="btn-amber w-full sm:w-auto px-8 py-3 text-[15px]" :disabled="loading">
          <span x-show="!loading">Submit Script →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

    <!-- SCRIPT READING CARD -->
    <div class="audition-card p-6 md:p-8" x-data="submissionForm('writer','Script Reading','Script Reading','Record yourself reading a scene or monologue you\'ve written. Let your voice carry the story — no acting required, just conviction. 60–90 seconds. Any phone camera. Your words, your read.')">
      <div class="flex items-start gap-4 mb-6">
        <div class="badge-reading text-ink text-[11px] font-bold tracking-widest uppercase px-3 py-1.5 rounded-full flex-shrink-0 mt-0.5">Script Reading</div>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:.02em;color:#111">SCRIPT READING</h2>
          <p style="color:#6b7280;font-size:.85rem;margin-top:.2rem">Perform a reading of your original work on camera.</p>
        </div>
      </div>
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <p class="text-[11px] font-semibold tracking-[2px] uppercase text-amber">The Brief</p>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Script Reading',auditionType:'Script Reading',content:'Record yourself reading a scene or monologue you\'ve written. Let your voice carry the story — no acting required, just conviction. 60–90 seconds. Any phone camera. Your words, your read.'};downloadBriefPDF()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block">Record yourself reading a scene or monologue you've written. Let your voice carry the story — no acting required, just conviction. 60–90 seconds. Any phone camera. Your words, your read.</div>
      </div>
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Full Name *</label><input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name"></div>
          <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Email *</label><input type="email" x-model="form.email" class="fp-input" placeholder="your@email.com" required autocomplete="email"></div>
        </div>
        <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Phone Number *</label><input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel"></div>
        <div><label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">About Your Work <span class="normal-case font-normal">(optional)</span></label><textarea x-model="form.notes" class="fp-input" rows="2" placeholder="Title of the piece, genre, what inspired it..."></textarea></div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Upload Reading Video <span class="text-amber">*</span></label>
          <p class="text-[11px] text-muted mb-2 opacity-60">Required · MP4, MOV, WEBM · max 500 MB · will be published to YouTube</p>
          <div class="upload-zone p-6 text-center" :class="dragOver?'drag':''" @dragover.prevent="dragOver=true" @dragleave="dragOver=false" @drop.prevent="handleDrop($event)" @click="$refs.file2.click()">
            <input type="file" x-ref="file2" class="hidden" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file"><div><svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg><p class="text-muted text-sm">Drop video or <span class="text-amber">click to browse</span></p><p class="text-muted text-xs mt-1">MP4 · MOV · WEBM · max 500 MB</p></div></template>
            <template x-if="file"><div class="flex items-center justify-center gap-3"><svg class="w-5 h-5 text-amber flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><span class="text-warm text-sm font-medium" x-text="file.name"></span><button type="button" @click.stop="file=null" class="text-muted hover:text-warm">✕</button></div></template>
          </div>
        </div>
        <template x-if="uploading"><div><div class="flex justify-between text-xs text-muted mb-1"><span>Uploading...</span><span x-text="progress+'%'"></span></div><div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div></div></template>
        <template x-if="errors.length"><div class="error-box p-3 text-sm text-red-300"><ul class="space-y-1"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul></div></template>
        <template x-if="success"><div class="success-box p-4 text-green-300 text-sm" x-text="success"></div></template>
        <button type="submit" class="btn-amber w-full sm:w-auto px-8 py-3 text-[15px]" :disabled="loading">
          <span x-show="!loading">Submit Reading →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

  </div>
</section>

<footer style="border-top:1px solid #e5e7eb;padding:1.75rem 1rem;background:#fff">
  <div class="max-w-5xl mx-auto" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:28px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:17px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;gap:1.25rem">
      <a href="/actor"    style="color:#6b7280;font-size:.8rem;text-decoration:none">Actor</a>
      <a href="/director" style="color:#6b7280;font-size:.8rem;text-decoration:none">Director</a>
      <a href="/writer"   style="color:#6b7280;font-size:.8rem;text-decoration:none">Writer</a>
    </div>
    <span style="color:#9ca3af;font-size:.75rem">No face. Just talent.</span>
  </div>
</footer>

<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
</body>
</html>

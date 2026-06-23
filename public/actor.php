<?php
require_once __DIR__ . '/../app/config/config.php';

$scriptModel    = new App\Models\Script();
$settingsModel  = new App\Models\Settings();
$actorScripts   = $scriptModel->byCategory('actor');

// Admin-editable content (with fallbacks)
$dialogScript = $settingsModel->get('actor_dialog_script',
    'Perform the following scene with full emotion. The scene: You receive a call that changes everything. Show shock, then resolve — all in under 90 seconds.');
$songScript   = $settingsModel->get('actor_song_script',
    'Choose any song that represents a character going through transformation. Perform a 60-second version showing emotional range — just your voice.');

$pageTitle = 'Actor Auditions — Faceless Pictures 3';
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
                display: ['Bebas Neue','sans-serif'],
                body:    ['DM Sans','sans-serif'],
            }
        }
    }
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}
/* NAV */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb}
/* BADGES */
.badge-dialog{background:#111;color:#fff}
.badge-song{background:#374151;color:#fff}
/* CARDS */
.audition-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;transition:border-color .2s,box-shadow .2s}
.audition-card:hover{border-color:#111;box-shadow:0 6px 28px rgba(0,0,0,.07)}
/* UPLOAD ZONE */
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;transition:border-color .2s,background .2s;cursor:pointer}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f9fafb}
/* INPUTS */
.fp-input{background:#fff;border:1.5px solid #d1d5db;border-radius:8px;color:#111;padding:.625rem .875rem;width:100%;font-size:15px;transition:border-color .2s}
.fp-input:focus{outline:none;border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.06)}
.fp-input::placeholder{color:#9ca3af}
/* BUTTON */
.btn-amber{background:#111;color:#fff;font-weight:700;border-radius:8px;padding:.75rem 1.5rem;border:none;transition:background .2s,transform .1s;cursor:pointer}
.btn-amber:hover{background:#333}
.btn-amber:active{transform:scale(.98)}
.btn-amber:disabled{opacity:.4;cursor:not-allowed}
/* SCRIPT BLOCK */
.script-block{background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:1.25rem;font-size:.9rem;line-height:1.7;color:#374151}
/* PDF BUTTON */
.btn-pdf{display:inline-flex;align-items:center;gap:.4rem;background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;border-radius:6px;padding:.35rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn-pdf:hover{background:#e5e7eb}
/* SUCCESS / ERROR */
.success-box{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px}
.error-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;color:#991b1b}
/* PROGRESS */
.progress-bar{height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden}
.progress-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
/* SCRIPT PICKER */
.script-pick-btn{background:#f9fafb;border:1.5px solid #e5e7eb;color:#374151}
.script-pick-btn.selected{border-color:#111;background:#111;color:#fff}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .5s ease forwards}
</style>
</head>
<body>
<nav class="fp-nav fixed top-0 left-0 right-0 z-50 h-14">
  <div class="max-w-6xl mx-auto px-4 h-full flex items-center justify-between">
    <a href="/" class="flex items-center gap-2" style="text-decoration:none">
      <span style="font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
      <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
    </a>
    <div class="flex items-center gap-4">
      <a href="/actor"    style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Writer</a>
    </div>
  </div>
</nav>/* override any leftover dark-theme Tailwind utility colours */
.text-muted, .text-warm { color: #6b7280 !important; }
.text-amber { color: #374151 !important; }
.border-border { border-color: #e5e7eb !important; }
.bg-ink\/40, .bg-panel { background: #f9fafb !important; }
.border-border { border-color: #e5e7eb !important; }
.text-warm { color: #111 !important; }
/* form labels */
label { color: #374151; }
/* upload zone icon */
.upload-zone svg { color: #9ca3af; }
/* script pick buttons */
button.text-left.p-3 { background:#f9fafb; border-color:#e5e7eb; color:#374151 }
button.text-left.p-3 span.text-warm { color:#111 !important; }
/* normal-case note */
.normal-case { color:#9ca3af !important; font-weight:400 !important; }
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .5s ease forwards}
</style>
</head>

<body x-data="actorPage()">

<!-- HERO -->
<section class="pt-24 pb-12 px-4">
  <div class="max-w-4xl mx-auto text-center fade-up">
    <span class="inline-block bg-gray-100 text-gray-500 text-[11px] font-semibold tracking-[3px] uppercase px-4 py-1.5 rounded-full mb-4">Now Open</span>
    <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(48px,8vw,88px);letter-spacing:.02em;line-height:.95;color:#111;margin-bottom:1rem">ACTOR<br>AUDITIONS</h1>
    <p style="color:#6b7280;font-size:1rem;max-width:440px;margin:0 auto;line-height:1.65">
      No face. Just raw talent. Choose your audition type, read the brief, upload your take.
    </p>
  </div>
</section>

<!-- AUDITION CARDS -->
<section class="pb-20 px-4">
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- DIALOG AUDITION CARD -->
    <div class="audition-card p-6 md:p-8" x-data="submissionForm('actor','Dialog Audition','Dialog Audition',<?= json_encode($dialogScript) ?>)">
      <div class="flex items-start gap-4 mb-6">
        <div class="badge-dialog text-white text-[11px] font-bold tracking-widest uppercase px-3 py-1.5 rounded-full flex-shrink-0 mt-0.5">
          Dialog Audition
        </div>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:.02em;color:#111">DIALOG AUDITION</h2>
          <p style="color:#6b7280;font-size:.85rem;margin-top:.2rem">Perform the scene. Show us your emotional range.</p>
        </div>
      </div>

      <!-- Script + PDF download -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <p style="font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#374151">The Brief</p>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Dialog Audition',auditionType:'Dialog Audition',content:<?= json_encode($dialogScript) ?>};downloadBriefPDF()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block"><?= nl2br(htmlspecialchars($dialogScript)) ?></div>
      </div>

      <?php if (!empty($actorScripts)): ?>
      <div class="mb-6">
        <p class="text-[11px] font-semibold tracking-[2px] uppercase text-muted mb-2">Or pick a script</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <?php foreach ($actorScripts as $s): ?>
          <button type="button"
            @click="selectedScript = (selectedScript === <?= $s['id'] ?> ? null : <?= $s['id'] ?>); selectedScriptTitle = (selectedScript ? '<?= addslashes(htmlspecialchars($s['title'])) ?>' : '')"
            :class="selectedScript === <?= $s['id'] ?> ? 'border-amber text-amber' : 'border-border text-muted hover:border-amber/40'"
            class="text-left p-3 rounded-lg border bg-ink/40 transition text-sm">
            <span class="font-semibold block text-warm text-[13px]"><?= htmlspecialchars($s['title']) ?></span>
            <span class="text-[11px]"><?= htmlspecialchars(ucfirst($s['difficulty'])) ?> · <?= htmlspecialchars($s['duration_hint'] ?? '') ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Full Name *</label>
            <input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name">
          </div>
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Email *</label>
            <input type="email" x-model="form.email" class="fp-input" placeholder="your@email.com" required autocomplete="email">
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Phone Number *</label>
          <input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel">
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Notes <span class="normal-case font-normal">(optional)</span></label>
          <textarea x-model="form.notes" class="fp-input" rows="2" placeholder="Anything you'd like us to know..."></textarea>
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Upload Your Video <span class="text-amber">*</span></label>
          <p class="text-[11px] text-muted mb-2 opacity-60">Required · MP4, MOV, WEBM · max 500 MB · will be published to YouTube</p>
          <div class="upload-zone p-6 text-center" :class="dragOver ? 'drag' : ''"
            @dragover.prevent="dragOver=true" @dragleave="dragOver=false"
            @drop.prevent="handleDrop($event)" @click="$refs.fileD.click()">
            <input type="file" x-ref="fileD" class="hidden" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file">
              <div>
                <svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p class="text-muted text-sm">Drop video or <span class="text-amber">click to browse</span></p>
                <p class="text-muted text-xs mt-1">MP4 · MOV · WEBM · max 500 MB</p>
              </div>
            </template>
            <template x-if="file">
              <div class="flex items-center justify-center gap-3">
                <svg class="w-5 h-5 text-amber flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span class="text-warm text-sm font-medium" x-text="file.name"></span>
                <button type="button" @click.stop="file=null" class="text-muted hover:text-warm">✕</button>
              </div>
            </template>
          </div>
        </div>
        <template x-if="uploading">
          <div>
            <div class="flex justify-between text-xs text-muted mb-1">
              <span>Uploading...</span><span x-text="progress+'%'"></span>
            </div>
            <div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div>
          </div>
        </template>
        <template x-if="errors.length">
          <div class="error-box p-3 text-sm text-red-300">
            <ul class="space-y-1"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
          </div>
        </template>
        <template x-if="success">
          <div class="success-box p-4 text-green-300 text-sm" x-text="success"></div>
        </template>
        <button type="submit" class="btn-amber w-full sm:w-auto px-8 py-3 text-[15px]" :disabled="loading">
          <span x-show="!loading">Submit Dialog Audition →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

    <!-- SONG AUDITION CARD -->
    <div class="audition-card p-6 md:p-8" x-data="submissionForm('actor','Song Audition','Song Audition',<?= json_encode($songScript) ?>)">
      <div class="flex items-start gap-4 mb-6">
        <div class="badge-song text-white text-[11px] font-bold tracking-widest uppercase px-3 py-1.5 rounded-full flex-shrink-0 mt-0.5">
          Song Audition
        </div>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:.02em;color:#111">SONG AUDITION</h2>
          <p style="color:#6b7280;font-size:.85rem;margin-top:.2rem">Let your voice tell the story.</p>
        </div>
      </div>

      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <p style="font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#374151">The Brief</p>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Song Audition',auditionType:'Song Audition',content:<?= json_encode($songScript) ?>};downloadBriefPDF()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block"><?= nl2br(htmlspecialchars($songScript)) ?></div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Full Name *</label>
            <input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name">
          </div>
          <div>
            <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Email *</label>
            <input type="email" x-model="form.email" class="fp-input" placeholder="your@email.com" required autocomplete="email">
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Phone Number *</label>
          <input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel">
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Song Choice <span class="normal-case font-normal">(optional)</span></label>
          <input type="text" x-model="form.notes" class="fp-input" placeholder="Song name + artist you're performing">
        </div>
        <div>
          <label class="block text-[11px] font-semibold uppercase tracking-widest text-muted mb-1.5">Upload Your Video <span class="text-amber">*</span></label>
          <p class="text-[11px] text-muted mb-2 opacity-60">Required · MP4, MOV, WEBM · max 500 MB · will be published to YouTube</p>
          <div class="upload-zone p-6 text-center" :class="dragOver ? 'drag' : ''"
            @dragover.prevent="dragOver=true" @dragleave="dragOver=false"
            @drop.prevent="handleDrop($event)" @click="$refs.fileS.click()">
            <input type="file" x-ref="fileS" class="hidden" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file">
              <div>
                <svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p class="text-muted text-sm">Drop video or <span class="text-amber">click to browse</span></p>
                <p class="text-muted text-xs mt-1">MP4 · MOV · WEBM · max 500 MB</p>
              </div>
            </template>
            <template x-if="file">
              <div class="flex items-center justify-center gap-3">
                <svg class="w-5 h-5 text-amber flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span class="text-warm text-sm font-medium" x-text="file.name"></span>
                <button type="button" @click.stop="file=null" class="text-muted hover:text-warm">✕</button>
              </div>
            </template>
          </div>
        </div>
        <template x-if="uploading">
          <div>
            <div class="flex justify-between text-xs text-muted mb-1"><span>Uploading...</span><span x-text="progress+'%'"></span></div>
            <div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div>
          </div>
        </template>
        <template x-if="errors.length">
          <div class="error-box p-3 text-sm text-red-300">
            <ul class="space-y-1"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
          </div>
        </template>
        <template x-if="success">
          <div class="success-box p-4 text-green-300 text-sm" x-text="success"></div>
        </template>
        <button type="submit" class="btn-amber w-full sm:w-auto px-8 py-3 text-[15px]" :disabled="loading">
          <span x-show="!loading">Submit Song Audition →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

  </div><!-- /max-w -->
</section>

<!-- FOOTER -->
<footer style="border-top:1px solid #e5e7eb;padding:2rem 1rem;background:#fff">
  <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4" style="color:#6b7280;font-size:.85rem">
    <a href="/" style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px">
      FACELESS PICTURES <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
    </a>
    <div class="flex gap-5">
      <a href="/actor"    style="color:#6b7280;text-decoration:none">Actor</a>
      <a href="/director" style="color:#6b7280;text-decoration:none">Director</a>
      <a href="/writer"   style="color:#6b7280;text-decoration:none">Writer</a>
    </div>
    <span>No face. Just talent.</span>
  </div>
</footer>

<script>
function actorPage() { return {}; }
</script>
<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
</body>
</html>

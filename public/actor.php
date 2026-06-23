<?php
require_once __DIR__ . '/../app/config/config.php';

$scriptModel   = new App\Models\Script();
$settingsModel = new App\Models\Settings();
$actorScripts  = $scriptModel->byCategory('actor');

$dialogScript = $settingsModel->get('actor_dialog_script',
    'Perform the following scene with full emotion. You receive a call that changes everything. Show shock, then resolve — all in under 90 seconds.');
$songScript = $settingsModel->get('actor_song_script',
    'Choose any song that represents a character going through transformation. Perform a 60-second version showing emotional range — just your voice.');

$logoUrl   = $settingsModel->get('site_logo_url', '');
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
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}
/* NAV */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
/* BADGES */
.badge-dialog{background:#111;color:#fff;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .75rem;border-radius:20px;display:inline-block}
.badge-song{background:#374151;color:#fff;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .75rem;border-radius:20px;display:inline-block}
/* CARDS */
.audition-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;transition:border-color .2s,box-shadow .2s}
.audition-card:hover{border-color:#d1d5db;box-shadow:0 4px 20px rgba(0,0,0,.05)}
/* UPLOAD ZONE */
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;transition:border-color .2s,background .2s;cursor:pointer}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f9fafb}
/* INPUTS */
.fp-input{background:#fff;border:1.5px solid #d1d5db;border-radius:8px;color:#111;padding:.65rem .875rem;width:100%;font-size:.9375rem;transition:border-color .2s,box-shadow .2s;outline:none;-webkit-appearance:none;appearance:none}
.fp-input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.07)}
.fp-input::placeholder{color:#9ca3af}
/* LABEL */
.fp-label{display:block;font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#374151;margin-bottom:.4rem}
/* BUTTON */
.btn-main{background:#111;color:#fff;font-weight:700;border-radius:8px;padding:.75rem 1.5rem;border:none;font-size:.9375rem;cursor:pointer;transition:background .2s,transform .1s;display:inline-flex;align-items:center;gap:.4rem}
.btn-main:hover{background:#333}
.btn-main:active{transform:scale(.98)}
.btn-main:disabled{opacity:.4;cursor:not-allowed}
/* SCRIPT BLOCK */
.script-block{background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:10px;padding:1.25rem;font-size:.9rem;line-height:1.7;color:#374151}
/* PDF BUTTON */
.btn-pdf{display:inline-flex;align-items:center;gap:.4rem;background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;border-radius:6px;padding:.35rem .7rem;font-size:.72rem;font-weight:600;cursor:pointer;transition:background .2s;white-space:nowrap}
.btn-pdf:hover{background:#e5e7eb}
/* SUCCESS / ERROR */
.success-box{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;color:#166534}
.error-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;color:#991b1b}
/* PROGRESS */
.progress-bar{height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden}
.progress-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
/* SECTION LABEL */
.sec-label{font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#9ca3af}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .45s ease forwards}
</style>
</head>
<body x-data="actorPage()">

<!-- NAV -->
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
      <a href="/actor"    style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none;border-bottom:2px solid #111;padding-bottom:2px">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Writer</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section style="padding:5.5rem 1rem 2rem">
  <div class="max-w-5xl mx-auto text-center fade-up">
    <span style="display:inline-block;background:#f3f4f6;color:#6b7280;font-size:.68rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;padding:.35rem 1rem;border-radius:20px;margin-bottom:1rem">Auditions Now Open</span>
    <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(52px,9vw,96px);letter-spacing:.02em;line-height:.92;color:#111;margin-bottom:.875rem">ACTOR<br>AUDITIONS</h1>
    <p style="color:#6b7280;font-size:1rem;max-width:420px;margin:0 auto;line-height:1.65">No face. Just raw talent. Pick your audition type, read the brief, upload your take.</p>
  </div>
</section>

<!-- AUDITION CARDS -->
<section style="padding:0 1rem 5rem">
  <div class="max-w-5xl mx-auto" style="display:flex;flex-direction:column;gap:1.5rem">

    <!-- ── DIALOG AUDITION ── -->
    <div class="audition-card" style="padding:1.75rem 2rem" x-data="submissionForm('actor','Dialog Audition','Dialog Audition',<?= json_encode($dialogScript) ?>)">
      <div style="display:flex;align-items:flex-start;gap:.875rem;margin-bottom:1.5rem">
        <span class="badge-dialog">Dialog Audition</span>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(24px,4vw,32px);letter-spacing:.02em;color:#111;line-height:1">DIALOG AUDITION</h2>
          <p style="color:#6b7280;font-size:.8rem;margin-top:.2rem">Perform the scene. Show us your emotional range.</p>
        </div>
      </div>

      <!-- Brief -->
      <div style="margin-bottom:1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
          <span class="sec-label">The Brief</span>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Dialog Audition',auditionType:'Dialog Audition',content:<?= json_encode($dialogScript) ?>};downloadBriefPDF()">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block"><?= nl2br(htmlspecialchars($dialogScript)) ?></div>
      </div>

      <?php if (!empty($actorScripts)): ?>
      <div style="margin-bottom:1.25rem">
        <p class="sec-label" style="margin-bottom:.5rem">Or pick a script</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem">
          <?php foreach ($actorScripts as $s): ?>
          <button type="button"
            @click="selectedScript=(selectedScript===<?= $s['id'] ?>?null:<?= $s['id'] ?>)"
            :class="selectedScript===<?= $s['id'] ?>?'selected':''"
            style="text-align:left;padding:.75rem;border-radius:8px;border:1.5px solid #e5e7eb;background:#f9fafb;cursor:pointer;transition:border-color .2s,background .2s"
            :style="selectedScript===<?= $s['id'] ?>?'border-color:#111;background:#111;color:#fff':''">
            <span style="font-weight:600;font-size:.8rem;display:block"><?= htmlspecialchars($s['title']) ?></span>
            <span style="font-size:.7rem;opacity:.6"><?= htmlspecialchars(ucfirst($s['difficulty'])) ?> · <?= htmlspecialchars($s['duration_hint'] ?? '') ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:1rem">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem">
          <div>
            <label class="fp-label">Full Name *</label>
            <input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name">
          </div>
          <div>
            <label class="fp-label">Email *</label>
            <input type="email" x-model="form.email" class="fp-input" placeholder="you@email.com" required autocomplete="email">
          </div>
        </div>
        <div>
          <label class="fp-label">Phone *</label>
          <input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel">
        </div>
        <div>
          <label class="fp-label">Notes <span style="text-transform:none;font-weight:400;color:#9ca3af">(optional)</span></label>
          <textarea x-model="form.notes" class="fp-input" rows="2" placeholder="Anything you'd like us to know..." style="resize:vertical"></textarea>
        </div>
        <div>
          <label class="fp-label">Upload Your Video *</label>
          <p style="font-size:.72rem;color:#9ca3af;margin-bottom:.5rem">Required · MP4 MOV WEBM · max 500 MB · published to YouTube after approval</p>
          <div class="upload-zone" style="padding:1.5rem;text-align:center" :class="dragOver?'drag':''"
            @dragover.prevent="dragOver=true" @dragleave="dragOver=false" @drop.prevent="handleDrop($event)" @click="$refs.fileD.click()">
            <input type="file" x-ref="fileD" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file">
              <div>
                <svg style="width:32px;height:32px;color:#9ca3af;margin:0 auto .5rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p style="color:#6b7280;font-size:.875rem">Drop video or <span style="color:#111;font-weight:600">click to browse</span></p>
                <p style="color:#9ca3af;font-size:.75rem;margin-top:.25rem">MP4 · MOV · WEBM · max 500 MB</p>
              </div>
            </template>
            <template x-if="file">
              <div style="display:flex;align-items:center;justify-content:center;gap:.75rem">
                <svg style="width:18px;height:18px;color:#111;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span style="color:#111;font-size:.875rem;font-weight:500" x-text="file.name"></span>
                <button type="button" @click.stop="file=null" style="color:#9ca3af;background:none;border:none;cursor:pointer;font-size:1rem;line-height:1">✕</button>
              </div>
            </template>
          </div>
        </div>
        <template x-if="uploading">
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.75rem;color:#6b7280;margin-bottom:.25rem">
              <span>Uploading...</span><span x-text="progress+'%'"></span>
            </div>
            <div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div>
          </div>
        </template>
        <template x-if="errors.length">
          <div class="error-box" style="padding:.75rem 1rem;font-size:.875rem">
            <ul><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
          </div>
        </template>
        <button type="submit" class="btn-main" :disabled="loading" style="align-self:flex-start">
          <span x-show="!loading">Submit Dialog Audition →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

    <!-- ── SONG AUDITION ── -->
    <div class="audition-card" style="padding:1.75rem 2rem" x-data="submissionForm('actor','Song Audition','Song Audition',<?= json_encode($songScript) ?>)">
      <div style="display:flex;align-items:flex-start;gap:.875rem;margin-bottom:1.5rem">
        <span class="badge-song">Song Audition</span>
        <div>
          <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(24px,4vw,32px);letter-spacing:.02em;color:#111;line-height:1">SONG AUDITION</h2>
          <p style="color:#6b7280;font-size:.8rem;margin-top:.2rem">Let your voice tell the story.</p>
        </div>
      </div>
      <div style="margin-bottom:1.25rem">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
          <span class="sec-label">The Brief</span>
          <button type="button" class="btn-pdf" onclick="window._briefForPDF={title:'Song Audition',auditionType:'Song Audition',content:<?= json_encode($songScript) ?>};downloadBriefPDF()">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Brief PDF
          </button>
        </div>
        <div class="script-block"><?= nl2br(htmlspecialchars($songScript)) ?></div>
      </div>
      <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:1rem">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem">
          <div><label class="fp-label">Full Name *</label><input type="text" x-model="form.name" class="fp-input" placeholder="Your full name" required autocomplete="name"></div>
          <div><label class="fp-label">Email *</label><input type="email" x-model="form.email" class="fp-input" placeholder="you@email.com" required autocomplete="email"></div>
        </div>
        <div><label class="fp-label">Phone *</label><input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel"></div>
        <div>
          <label class="fp-label">Song Choice <span style="text-transform:none;font-weight:400;color:#9ca3af">(optional)</span></label>
          <input type="text" x-model="form.notes" class="fp-input" placeholder="Song name + artist">
        </div>
        <div>
          <label class="fp-label">Upload Your Video *</label>
          <p style="font-size:.72rem;color:#9ca3af;margin-bottom:.5rem">Required · MP4 MOV WEBM · max 500 MB · published to YouTube after approval</p>
          <div class="upload-zone" style="padding:1.5rem;text-align:center" :class="dragOver?'drag':''"
            @dragover.prevent="dragOver=true" @dragleave="dragOver=false" @drop.prevent="handleDrop($event)" @click="$refs.fileS.click()">
            <input type="file" x-ref="fileS" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
            <template x-if="!file">
              <div>
                <svg style="width:32px;height:32px;color:#9ca3af;margin:0 auto .5rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p style="color:#6b7280;font-size:.875rem">Drop video or <span style="color:#111;font-weight:600">click to browse</span></p>
                <p style="color:#9ca3af;font-size:.75rem;margin-top:.25rem">MP4 · MOV · WEBM · max 500 MB</p>
              </div>
            </template>
            <template x-if="file">
              <div style="display:flex;align-items:center;justify-content:center;gap:.75rem">
                <svg style="width:18px;height:18px;color:#111;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span style="color:#111;font-size:.875rem;font-weight:500" x-text="file.name"></span>
                <button type="button" @click.stop="file=null" style="color:#9ca3af;background:none;border:none;cursor:pointer;font-size:1rem">✕</button>
              </div>
            </template>
          </div>
        </div>
        <template x-if="uploading">
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.75rem;color:#6b7280;margin-bottom:.25rem"><span>Uploading...</span><span x-text="progress+'%'"></span></div>
            <div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div>
          </div>
        </template>
        <template x-if="errors.length">
          <div class="error-box" style="padding:.75rem 1rem;font-size:.875rem">
            <ul><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
          </div>
        </template>
        <button type="submit" class="btn-main" :disabled="loading" style="align-self:flex-start">
          <span x-show="!loading">Submit Song Audition →</span>
          <span x-show="loading">Uploading...</span>
        </button>
      </form>
    </div>

  </div>
</section>

<!-- FOOTER -->
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

<script>
function actorPage() { return {}; }
</script>
<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
</body>
</html>

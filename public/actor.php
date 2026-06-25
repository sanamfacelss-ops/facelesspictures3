<?php
require_once __DIR__ . '/../app/config/config.php';
$settingsModel = new App\Models\Settings();
$scriptModel   = new App\Models\Script();

$logoUrl = $settingsModel->get('site_logo_url', '');

// Load all actor scripts (dialog + song)
$actorScripts = $scriptModel->byCategory('actor');

// Fallback global settings (used only if no scripts uploaded yet)
$globalDialogBrief = $settingsModel->get('actor_dialog_script', 'Perform the following scene with full emotion.');
$globalSongBrief   = $settingsModel->get('actor_song_script', 'Perform a 60-second song showing emotional range.');

// Split scripts by audition_type: dialog vs song
$dialogScripts = array_values(array_filter($actorScripts, fn($s) => stripos($s['audition_type'] ?? '', 'song') === false));
$songScripts   = array_values(array_filter($actorScripts, fn($s) => stripos($s['audition_type'] ?? '', 'song') !== false));

// If no song scripts, put all in dialog
if (empty($songScripts) && !empty($dialogScripts)) {
    // keep as is — two columns both dialog if needed
}

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
html,body{width:100%;overflow-x:hidden}
body{font-family:'DM Sans',sans-serif;background:#f9fafb;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
.brief-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:1280px;margin:0 auto;padding:0 1.5rem 1.75rem}
@media(max-width:768px){.brief-grid{grid-template-columns:1fr;padding:0 1rem 1.5rem}}
.brief-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);overflow:hidden;display:flex;flex-direction:column}
.card-sec{padding:1rem 1.125rem;border-bottom:1px solid #f0f0f0}
.card-sec:last-child{border-bottom:none}
.card-sec.tinted{background:#f9fafb}
.sec-label{display:flex;align-items:center;gap:.45rem;font-size:.6rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#9ca3af;margin-bottom:.625rem}
.sec-label::before{content:'';display:inline-block;width:3px;height:10px;border-radius:2px;background:#111;flex-shrink:0}
.preview-video{width:100%;display:block;background:#000}
.preview-video-wrap{position:relative;width:100%;padding-bottom:177.78%;background:#000;overflow:hidden}
.preview-video-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;display:block}
.video-placeholder{width:100%;padding-bottom:177.78%;position:relative;background:#1a1a2e}
.video-placeholder-inner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:rgba(255,255,255,.3);font-size:.72rem;letter-spacing:.06em;text-transform:uppercase}
.portrait-img-wrap{position:relative;width:100%;padding-bottom:177.78%;overflow:hidden;background:#f3f4f6}
.portrait-img-wrap img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;display:block}
.portrait-placeholder{width:100%;height:180px;background:#f3f4f6;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#9ca3af;font-size:.72rem}
.btn-outline{display:inline-flex;align-items:center;gap:.35rem;background:#fff;border:1.5px solid #e5e7eb;color:#374151;border-radius:8px;padding:.42rem .825rem;font-size:.73rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;transition:border-color .15s,background .15s;white-space:nowrap}
.btn-outline:hover{border-color:#111;background:#f9fafb}
.btn-outline.disabled{opacity:.45;pointer-events:none;cursor:not-allowed}
.btn-tune{display:inline-flex;align-items:center;gap:.35rem;background:#111;color:#fff;border:none;border-radius:8px;padding:.42rem .825rem;font-size:.73rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;white-space:nowrap}
.btn-tune:hover{background:#333}
.btn-tune:disabled{opacity:.45;cursor:not-allowed}
.btn-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.rule-row{display:flex;align-items:flex-start;gap:.4rem;font-size:.78rem;color:#374151;line-height:1.55;padding:.15rem 0}
.rule-dot{width:3px;height:3px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.55rem}
#tuneModal{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.9);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:1.5rem}
#tuneModal.open{display:flex}
.tune-box{background:#161C2D;border:1px solid #1F2840;border-radius:16px;width:100%;max-width:720px;padding:1.25rem;position:relative}
.tune-close{position:absolute;top:.75rem;right:.75rem;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:50%;width:32px;height:32px;color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.tune-close:hover{background:rgba(255,255,255,.18)}
.tune-wrap{position:relative;width:100%;padding-bottom:56.25%;margin-top:.5rem;border-radius:8px;overflow:hidden;background:#000}
.tune-wrap iframe,.tune-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:0}
.submit-card{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:14px;max-width:1280px;margin:0 auto 5rem;padding:2.25rem 2rem}
@media(max-width:768px){.submit-card{margin:0 1rem 4rem;padding:1.5rem 1.25rem;border-radius:12px}}
.fp-label-dark{display:block;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin-bottom:.28rem}
.fp-input-dark{background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;color:#111;padding:.55rem .8rem;width:100%;font-size:.875rem;outline:none;font-family:inherit;-webkit-appearance:none;transition:border-color .2s}
.fp-input-dark:focus{border-color:#111;box-shadow:0 0 0 2px rgba(17,17,17,.07)}
.fp-input-dark::placeholder{color:#9ca3af}
.form3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1.25rem}
@media(max-width:640px){.form3{grid-template-columns:1fr}}
.divider-dark{height:1px;background:#e5e7eb;margin:1.25rem 0}
.upload2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:580px){.upload2{grid-template-columns:1fr}}
.uzone{border:2px dashed #d1d5db;border-radius:10px;cursor:pointer;background:#fff;text-align:center;padding:1.25rem 1rem;transition:border-color .2s,background .2s}
.uzone:hover,.uzone.drag{border-color:#111;background:#f9fafb}
.uzone.has-file{border-color:#16a34a;border-style:solid;background:#f0fdf4}
.prog-bar{height:3px;background:#e5e7eb;border-radius:2px;overflow:hidden;margin-top:.4rem}
.prog-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
.err-dark{background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;color:#991b1b;padding:.6rem .875rem;font-size:.8rem;margin-top:.75rem}
.btn-go{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;background:#111;color:#fff;font-weight:700;border:none;border-radius:9px;padding:.9rem 1.5rem;font-size:.95rem;cursor:pointer;font-family:inherit;letter-spacing:.01em;transition:background .15s;margin-top:1.25rem}
.btn-go:hover{background:#333}
.btn-go:disabled{opacity:.4;cursor:not-allowed}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .4s ease forwards}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<!-- NAV -->
<nav class="fp-nav">
  <div style="max-width:1280px;margin:0 auto;padding:0 1.5rem;height:100%;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:7px;text-decoration:none">
      <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" style="height:44px;width:auto">
      <?php else: ?><span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span><span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span><?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem;align-items:center">
      <a href="/actor"    style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none;border-bottom:2px solid #111;padding-bottom:1px">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Writer</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section style="padding:4.5rem 1.5rem 1.75rem;text-align:center" class="fade-up">
  <p style="font-size:.63rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:.4rem">Auditions Now Open</p>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(36px,5vw,56px);letter-spacing:.04em;line-height:1;color:#111;margin-bottom:.5rem;white-space:nowrap">ACTOR AUDITIONS</h1>
  <p style="color:#6b7280;font-size:.85rem;max-width:420px;margin:0 auto;line-height:1.55">Two auditions, one submission. Read the dialog brief, learn the song, then shoot both videos.</p>
</section>

<!-- TWO BRIEF CARDS (Dialog + Song) -->
<div class="brief-grid">

<?php
// Helper to render a script brief card
function renderActorBriefCard(array $sc, string $fallbackBrief, bool $isSong = false): void {
    $previewUrl  = $sc['preview_video_url'] ?? '';
    $imageUrl    = $sc['image_url']         ?? '';
    $pdfUrl      = $sc['script_pdf_url']    ?? '';
    $tuneUrl     = $sc['tune_youtube_url']  ?? '';
    $title       = htmlspecialchars($sc['title']);
    $audType     = htmlspecialchars($sc['audition_type'] ?? ($isSong ? 'Song Audition' : 'Dialog Audition'));
    $brief       = htmlspecialchars($sc['content'] ?: $fallbackBrief);
    $rulesRaw    = $sc['rules'] ?? "Video under 3 minutes\nFace must not be visible\nClear audio required";
    $ruleList    = array_filter(array_map('trim', explode("\n", $rulesRaw)));
    $dataId      = (int)$sc['id'];
?>
  <div class="brief-card">
    <div class="card-sec" style="padding:0">
      <?php if ($previewUrl): ?>
        <div class="preview-video-wrap">
          <video controls muted preload="metadata"><source src="<?= htmlspecialchars($previewUrl) ?>" type="video/mp4">Your browser does not support video.</video>
        </div>
      <?php else: ?>
        <div class="video-placeholder">
          <div class="video-placeholder-inner">
            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Preview video coming soon
          </div>
        </div>
      <?php endif; ?>
    </div>
    <div class="card-sec" style="border-bottom:none;padding-bottom:.5rem">
      <div class="sec-label"><?= $audType ?></div>
      <p style="font-family:'Bebas Neue',sans-serif;font-size:1.35rem;letter-spacing:.03em;color:#111"><?= $title ?></p>
      <p style="font-size:.78rem;color:#6b7280;margin-top:.3rem;line-height:1.5"><?= $brief ?></p>
    </div>
    <?php if ($imageUrl): ?>
    <div class="card-sec" style="padding:0">
      <div class="portrait-img-wrap">
        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= $isSong ? 'Song lyrics' : 'Dialog script' ?>">
      </div>
    </div>
    <?php endif; ?>
    <div class="card-sec">
      <div class="sec-label"><?= $isSong ? 'Lyrics &amp; Tune' : 'Script' ?></div>
      <div class="btn-row">
        <?php if ($pdfUrl): ?>
          <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener" class="btn-outline">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <?= $isSong ? 'Download Lyrics PDF' : 'Download Script PDF' ?>
          </a>
        <?php else: ?>
          <span class="btn-outline disabled"><?= $isSong ? 'Lyrics' : 'Script' ?> PDF not available yet</span>
        <?php endif; ?>
        <?php if ($isSong): ?>
          <button type="button" class="btn-tune <?= $tuneUrl ? '' : 'disabled' ?>" <?= $tuneUrl ? 'onclick="openTuneModal('.json_encode($tuneUrl).')"' : 'disabled' ?>>▶ Get Tune</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-sec tinted" style="flex:1">
      <div class="sec-label">Rules &amp; Limits</div>
      <?php foreach ($ruleList as $r): ?><div class="rule-row"><span class="rule-dot"></span><span><?= htmlspecialchars($r) ?></span></div><?php endforeach; ?>
    </div>
  </div>
<?php
}

// Render dialog scripts
if (!empty($dialogScripts)) {
    foreach ($dialogScripts as $sc) renderActorBriefCard($sc, $globalDialogBrief, false);
} else {
    // Fallback placeholder card
    echo '<div class="brief-card"><div class="card-sec"><div class="sec-label">Dialog Audition</div><p style="color:#6b7280;font-size:.85rem">' . htmlspecialchars($globalDialogBrief) . '</p></div></div>';
}

// Render song scripts
if (!empty($songScripts)) {
    foreach ($songScripts as $sc) renderActorBriefCard($sc, $globalSongBrief, true);
} else {
    echo '<div class="brief-card"><div class="card-sec"><div class="sec-label">Song Audition</div><p style="color:#6b7280;font-size:.85rem">' . htmlspecialchars($globalSongBrief) . '</p></div></div>';
}
?>

</div><!-- /brief-grid -->

<!-- SUBMISSION CARD (full width, dark) -->
<div style="max-width:1280px;margin:0 auto;padding:0 1.5rem 5rem">
  <div class="submit-card" x-data="actorSubmit()">
    <p style="font-family:'Bebas Neue',sans-serif;font-size:clamp(22px,3.5vw,32px);letter-spacing:.04em;color:#111;margin-bottom:.3rem">Ready to Audition? Submit Both Videos</p>
    <p style="font-size:.85rem;color:#6b7280;margin-bottom:1.5rem;line-height:1.55">Both dialog and song videos are required for a complete submission. One form, two videos, one chance.</p>

    <!-- Contact -->
    <div class="form3">
      <div><label class="fp-label-dark">Name *</label><input type="text" x-model="form.name" class="fp-input-dark" placeholder="Your full name" required autocomplete="name"></div>
      <div><label class="fp-label-dark">Email *</label><input type="email" x-model="form.email" class="fp-input-dark" placeholder="you@email.com" required autocomplete="email"></div>
      <div><label class="fp-label-dark">Phone *</label><input type="tel" x-model="form.phone" class="fp-input-dark" placeholder="+91 98765 43210" required autocomplete="tel"></div>
    </div>

    <div class="divider-dark"></div>

    <!-- Dual upload -->
    <div class="upload2">
      <div>
        <label class="fp-label-dark" style="margin-bottom:.5rem">Dialog Audition Video *</label>
        <div class="uzone" :class="[dragD?'drag':'',dialogFile?'has-file':'']" @click="$refs.dv.click()" @dragover.prevent="dragD=true" @dragleave="dragD=false" @drop.prevent="dropD($event)">
          <input type="file" x-ref="dv" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="dialogFile=$event.target.files[0]">
          <svg style="width:24px;height:24px;color:#9ca3af;margin:0 auto .5rem;display:block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          <p style="color:#6b7280;font-size:.8rem">Drop or <strong style="color:#fff;text-decoration:underline">browse</strong> dialog video</p>
          <p style="color:#9ca3af;font-size:.7rem;margin-top:.2rem">MP4 · MOV · WEBM · max 500 MB</p>
          <p x-show="dialogFile" x-text="'✓ '+(dialogFile?dialogFile.name:'')" style="display:none;color:#16a34a;font-size:.75rem;font-weight:600;margin-top:.4rem"></p>
        </div>
      </div>
      <div>
        <label class="fp-label-dark" style="margin-bottom:.5rem">Song Audition Video *</label>
        <div class="uzone" :class="[dragS?'drag':'',songFile?'has-file':'']" @click="$refs.sv.click()" @dragover.prevent="dragS=true" @dragleave="dragS=false" @drop.prevent="dropS($event)">
          <input type="file" x-ref="sv" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="songFile=$event.target.files[0]">
          <svg style="width:24px;height:24px;color:#9ca3af;margin:0 auto .5rem;display:block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          <p style="color:#6b7280;font-size:.8rem">Drop or <strong style="color:#fff;text-decoration:underline">browse</strong> song video</p>
          <p style="color:#9ca3af;font-size:.7rem;margin-top:.2rem">MP4 · MOV · WEBM · max 500 MB</p>
          <p x-show="songFile" x-text="'✓ '+(songFile?songFile.name:'')" style="display:none;color:#16a34a;font-size:.75rem;font-weight:600;margin-top:.4rem"></p>
        </div>
      </div>
    </div>

    <!-- Progress -->
    <div x-show="loading" style="display:none;margin-top:.75rem">
      <div style="display:flex;justify-content:space-between;font-size:.7rem;color:#9ca3af;margin-bottom:.25rem"><span>Uploading both videos…</span><span x-text="progress+'%'"></span></div>
      <div class="prog-bar"><div class="prog-fill" :style="'width:'+progress+'%'"></div></div>
    </div>

    <!-- Errors -->
    <div x-show="errors.length" style="display:none" class="err-dark">
      <ul style="list-style:none"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
    </div>

    <!-- Submit -->
    <button type="button" class="btn-go" @click="submit()" :disabled="loading">
      Submit Both Auditions →
    </button>
  </div>
</div>

<!-- FOOTER -->
<footer style="border-top:1px solid #e5e7eb;padding:1.75rem 1.5rem;background:#fff">
  <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" style="height:36px;width:auto">
      <?php else: ?><span style="font-family:'Bebas Neue',sans-serif;font-size:16px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span><span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:17px;height:17px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span><?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem">
      <a href="/actor" style="color:#6b7280;font-size:.8rem;text-decoration:none">Actor</a>
      <a href="/director" style="color:#6b7280;font-size:.8rem;text-decoration:none">Director</a>
      <a href="/writer" style="color:#6b7280;font-size:.8rem;text-decoration:none">Writer</a>
    </div>
    <span style="color:#9ca3af;font-size:.75rem">No face. Just talent.</span>
  </div>
</footer>

<!-- TUNE MODAL -->
<div id="tuneModal">
  <div class="tune-box">
    <button class="tune-close" onclick="closeTuneModal()">✕</button>
    <p style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:.04em;color:#fff;margin-bottom:.75rem">▶ Song Tune</p>
    <div class="tune-wrap">
      <iframe id="tuneIframe" src="" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen title="Song tune"></iframe>
    </div>
  </div>
</div>

<script>
function _embedUrl(u){
    if(!u) return '';
    var m=u.match(/youtu\.be\/([^?&#]+)/);
    if(m) return 'https://www.youtube.com/embed/'+m[1];
    m=u.match(/[?&]v=([^&#]+)/);
    if(m) return 'https://www.youtube.com/embed/'+m[1];
    // YouTube Shorts
    m=u.match(/\/shorts\/([^?&#]+)/);
    if(m) return 'https://www.youtube.com/embed/'+m[1];
    return u;
}
function openTuneModal(url){
    var e=_embedUrl(url);
    if(!e) return;
    document.getElementById('tuneIframe').src=e+'?autoplay=1';
    document.getElementById('tuneModal').style.display='flex';
    document.body.style.overflow='hidden';
}
function closeTuneModal(){
    document.getElementById('tuneIframe').src='';
    document.getElementById('tuneModal').style.display='none';
    document.body.style.overflow='';
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeTuneModal();});
document.getElementById('tuneModal').addEventListener('click',function(e){if(e.target===this)closeTuneModal();});

function actorSubmit(){
    return{
        dialogFile:null,songFile:null,
        loading:false,progress:0,errors:[],
        dragD:false,dragS:false,
        form:{name:'',email:'',phone:''},
        dropD(e){this.dragD=false;var f=e.dataTransfer.files[0];if(f)this.dialogFile=f;},
        dropS(e){this.dragS=false;var f=e.dataTransfer.files[0];if(f)this.songFile=f;},
        submit(){
            this.errors=[];
            if(!this.form.name.trim()) this.errors.push('Name is required.');
            if(!this.form.email.trim()) this.errors.push('Email is required.');
            if(!this.form.phone.trim()) this.errors.push('Phone is required.');
            if(!this.dialogFile) this.errors.push('Dialog audition video is required.');
            if(!this.songFile)   this.errors.push('Song audition video is required.');
            if(this.errors.length) return;
            this.loading=true; this.progress=0;
            var fd=new FormData();
            fd.append('role','actor');
            fd.append('audition_type','Actor Audition');
            fd.append('name',this.form.name);
            fd.append('email',this.form.email);
            fd.append('phone',this.form.phone);
            fd.append('dialog_video',this.dialogFile);
            fd.append('song_video',this.songFile);
            var self=this;
            var xhr=new XMLHttpRequest();
            xhr.upload.onprogress=function(e){if(e.lengthComputable)self.progress=Math.round(e.loaded/e.total*100);};
            xhr.onload=function(){
                self.loading=false;
                try{
                    var r=JSON.parse(xhr.responseText);
                    if(xhr.status>=200&&xhr.status<300&&r.success){
                        self.form={name:'',email:'',phone:''};
                        self.dialogFile=null;self.songFile=null;self.errors=[];
                        if(typeof showSuccessModal==='function') showSuccessModal(r);
                    }else{self.errors=r.errors||[r.error||'Submission failed.'];}
                }catch(err){self.errors=['Server error. Please try again.'];}
            };
            xhr.onerror=function(){self.loading=false;self.errors=['Network error.'];};
            xhr.open('POST','/api/submit/actor');
            xhr.send(fd);
        }
    };
}
</script>
<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
</body>
</html>

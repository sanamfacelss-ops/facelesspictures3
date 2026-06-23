<?php
require_once __DIR__ . '/../app/config/config.php';

$scriptModel   = new App\Models\Script();
$settingsModel = new App\Models\Settings();
$logoUrl       = $settingsModel->get('site_logo_url', '');
$actorScripts  = $scriptModel->byCategory('actor');

if (empty($actorScripts)) {
    $actorScripts = [
        ['id'=>'dialog','title'=>'Dialog Audition','content'=>$settingsModel->get('actor_dialog_script','Perform the following scene with full emotion. You receive a call that changes everything. Show shock, then resolve — all in under 90 seconds.'),'audition_type'=>'Dialog Audition','difficulty'=>'beginner','duration_hint'=>'60-90 seconds','image_url'=>'','rules'=>"Video under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required"],
        ['id'=>'song','title'=>'Song Audition','content'=>$settingsModel->get('actor_song_script','Choose any song representing a character in transformation. Perform 60 seconds of emotional range — just your voice.'),'audition_type'=>'Song Audition','difficulty'=>'beginner','duration_hint'=>'60 seconds','image_url'=>'','rules'=>"Video under 2 minutes\nShoot on any device\nJust your voice\nClear audio required"],
    ];
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

/* ── NAV ── */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}

/* ── CARD GRID: 3 columns on desktop ── */
.cards-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:1.5rem;
    max-width:1280px;
    margin:0 auto;
    padding:0 1.5rem 5rem;
}
@media(max-width:960px){ .cards-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:580px){ .cards-grid{grid-template-columns:1fr;padding:0 1rem 4rem} }

/* ── VERTICAL CARD ── */
.vcard{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);
    overflow:hidden;
    display:flex;
    flex-direction:column;
}

/* ── POSTER: image always fully visible, never cropped ── */
.vcard-poster{
    position:relative;
    width:100%;
    background:#1a1a2e;
    /* padding-bottom creates the aspect ratio box */
    padding-bottom:66%;
    overflow:hidden;
    flex-shrink:0;
}
.vcard-poster img{
    position:absolute;
    top:0; left:0;
    width:100%; height:100%;
    /* contain = full image always visible, never cropped */
    object-fit:contain;
    object-position:center center;
    display:block;
}
.vcard-poster-ph{
    position:absolute;
    inset:0;
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;
}
.vcard-badge{
    position:absolute;top:10px;left:10px;z-index:2;
    font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
    padding:.25rem .65rem;border-radius:20px;
    background:rgba(17,17,17,.85);color:#fff;
    border:1px solid rgba(255,255,255,.2);
    white-space:nowrap;
}

/* ── CARD BODY ── */
.vcard-body{
    flex:1;
    padding:1.125rem;
    display:flex;
    flex-direction:column;
    gap:.75rem;
}

.vcard-title{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.03em;color:#111;line-height:1.05}
.dur-pill{display:inline-flex;align-items:center;gap:.3rem;font-size:.6rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:.18rem .5rem;border-radius:20px;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;margin-top:.3rem}

/* Brief */
.brief-preview{font-size:.84rem;color:#4b5563;line-height:1.7}
.brief-extra{
    max-height:0;overflow:hidden;
    transition:max-height .4s cubic-bezier(.4,0,.2,1),opacity .35s ease;
    opacity:0;font-size:.84rem;color:#4b5563;line-height:1.7;
}
.brief-extra.open{max-height:800px;opacity:1}

/* Brief actions row */
.brief-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.4rem}
.brief-toggle{
    display:inline-flex;align-items:center;gap:.25rem;
    font-size:.73rem;font-weight:700;color:#374151;
    background:#f3f4f6;border:1.5px solid #e5e7eb;border-radius:8px;
    padding:.32rem .7rem;cursor:pointer;font-family:inherit;
    transition:background .15s;white-space:nowrap;
}
.brief-toggle:hover{background:#e5e7eb}
.brief-toggle .arr{display:inline-block;transition:transform .3s cubic-bezier(.4,0,.2,1);font-size:.75rem}
.brief-toggle.open .arr{transform:rotate(180deg)}

.btn-pdf{display:inline-flex;align-items:center;gap:.3rem;background:#fff;border:1.5px solid #e5e7eb;color:#374151;border-radius:8px;padding:.32rem .7rem;font-size:.73rem;font-weight:600;cursor:pointer;font-family:inherit;transition:border-color .15s;white-space:nowrap}
.btn-pdf:hover{border-color:#111}

/* Divider */
.div{height:1px;background:#f0f0f0}

/* Rules */
.rule-row{display:flex;align-items:flex-start;gap:.4rem;font-size:.76rem;color:#374151;line-height:1.5;padding:.1rem 0}
.rule-dot{width:3px;height:3px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.5rem}

/* Form */
.fp-label{display:block;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin-bottom:.28rem}
.fp-input{background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;color:#111;padding:.52rem .75rem;width:100%;font-size:.875rem;outline:none;font-family:inherit;-webkit-appearance:none;transition:border-color .2s}
.fp-input:focus{border-color:#111;box-shadow:0 0 0 2px rgba(17,17,17,.07)}
.fp-input::placeholder{color:#9ca3af}
.form2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}

/* Upload */
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;cursor:pointer;background:#fafafa;text-align:center;padding:.875rem .75rem;transition:border-color .2s,background .2s}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f5f5f5}
.upload-icon{display:block;margin:0 auto .4rem}
.upload-main{color:#6b7280;font-size:.82rem}
.upload-hint{color:#9ca3af;font-size:.72rem;margin-top:.2rem}

/* Progress */
.prog-bar{height:3px;background:#e5e7eb;border-radius:2px;overflow:hidden;margin-top:.35rem}
.prog-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
.err-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;color:#991b1b;padding:.5rem .75rem;font-size:.78rem}

/* Submit */
.btn-submit{
    display:block;width:100%;
    background:#111;color:#fff;
    font-weight:700;border:none;
    border-radius:9px;padding:.75rem;
    font-size:.9rem;cursor:pointer;
    text-align:center;font-family:inherit;
}
.btn-submit:hover{background:#333}
.btn-submit:disabled{opacity:.5;cursor:not-allowed}
</style>
</head>
<body>

<!-- NAV -->
<nav class="fp-nav">
  <div style="max-width:1280px;margin:0 auto;padding:0 1.5rem;height:100%;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:7px;text-decoration:none">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:30px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem;align-items:center">
      <a href="/actor"    style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none;border-bottom:2px solid #111;padding-bottom:1px">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Writer</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section style="padding:5.5rem 1.5rem 2rem;text-align:center">
  <p style="font-size:.65rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:.5rem">Auditions Now Open</p>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(44px,7vw,76px);letter-spacing:.02em;line-height:.92;color:#111;margin-bottom:.75rem">ACTOR<br>AUDITIONS</h1>
  <p style="color:#6b7280;font-size:.9rem;max-width:360px;margin:0 auto;line-height:1.6">Pick an audition. Read the brief. Shoot your video. Submit.</p>
</section>

<!-- CARDS GRID -->
<div class="cards-grid">

<?php foreach ($actorScripts as $i => $script):
    $atype    = htmlspecialchars($script['audition_type'] ?? 'Audition');
    $rules    = $script['rules'] ?? "Video under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required";
    $ruleList = array_filter(array_map('trim', explode("\n", $rules)));
    $scriptId = is_numeric($script['id']) ? (int)$script['id'] : 0;

    $briefFull    = $script['content'] ?? '';
    $briefPreview = mb_substr($briefFull, 0, 150);
    $briefRest    = mb_strlen($briefFull) > 150 ? mb_substr($briefFull, 150) : '';
    $expandId     = 'bx_' . $i;
    $btnId        = 'bb_' . $i;
?>

  <!-- Card -->
  <div class="vcard" x-data="auCard(<?= $scriptId ?>, '<?= addslashes($atype) ?>', 'actor', <?= json_encode($script['title'] ?? '') ?>, <?= json_encode($briefFull) ?>)">

    <!-- POSTER: full image always visible, never cropped -->
    <div class="vcard-poster">
      <?php if (!empty($script['image_url'])): ?>
        <img src="<?= htmlspecialchars($script['image_url']) ?>"
             alt="<?= htmlspecialchars($script['title'] ?? '') ?>">
      <?php else: ?>
        <div class="vcard-poster-ph">
          <svg width="32" height="32" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span style="font-size:.58rem;color:rgba(255,255,255,.2);letter-spacing:.1em;text-transform:uppercase">Add image in Admin</span>
        </div>
      <?php endif; ?>
      <span class="vcard-badge"><?= $atype ?></span>
    </div>

    <!-- BODY -->
    <div class="vcard-body">

      <!-- Title + duration -->
      <div>
        <div class="vcard-title"><?= htmlspecialchars($script['title'] ?? '') ?></div>
        <?php if (!empty($script['duration_hint'])): ?>
        <div><span class="dur-pill">⏱ <?= htmlspecialchars($script['duration_hint']) ?></span></div>
        <?php endif; ?>
      </div>

      <!-- Brief: preview + expand + actions always same row -->
      <div>
        <p class="fp-label" style="margin-bottom:.3rem">The Brief</p>
        <p style="font-size:.78rem;font-weight:700;color:#111;margin-bottom:.25rem"><?= htmlspecialchars($script['title'] ?? '') ?></p>
        <p class="brief-preview"><?= htmlspecialchars($briefPreview) ?><?= $briefRest ? '<span style="color:#9ca3af"> …</span>' : '' ?></p>
        <!-- Expandable rest — slides in above action row -->
        <?php if ($briefRest): ?>
        <div class="brief-extra" id="<?= $expandId ?>"><?= nl2br(htmlspecialchars($briefRest)) ?></div>
        <?php endif; ?>
        <!-- Action row: always same line -->
        <div class="brief-actions">
          <?php if ($briefRest): ?>
          <button class="brief-toggle" id="<?= $btnId ?>" onclick="toggleBrief('<?= $expandId ?>','<?= $btnId ?>')">
            <span class="arr">▾</span> Read full brief
          </button>
          <?php endif; ?>
          <button class="btn-pdf" @click="downloadPDF()">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download PDF
          </button>
        </div>
      </div>

      <div class="div"></div>

      <!-- Rules -->
      <div>
        <p class="fp-label" style="margin-bottom:.35rem">Rules &amp; Limits</p>
        <?php foreach ($ruleList as $r): ?>
        <div class="rule-row"><span class="rule-dot"></span><span><?= htmlspecialchars($r) ?></span></div>
        <?php endforeach; ?>
      </div>

      <div class="div"></div>

      <!-- Form -->
      <form @submit.prevent="submit()">
        <p class="fp-label" style="margin-bottom:.5rem">Your Details</p>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <div class="form2">
            <div><label class="fp-label">Name *</label><input type="text" x-model="form.name" class="fp-input" placeholder="Full name" required autocomplete="name"></div>
            <div><label class="fp-label">Email *</label><input type="email" x-model="form.email" class="fp-input" placeholder="you@email.com" required autocomplete="email"></div>
          </div>
          <div><label class="fp-label">Phone *</label><input type="tel" x-model="form.phone" class="fp-input" placeholder="+91 98765 43210" required autocomplete="tel"></div>

          <div class="div"></div>

          <!-- Upload -->
          <div>
            <label class="fp-label">Video * <span style="text-transform:none;font-weight:400;color:#9ca3af;letter-spacing:0;font-size:.68rem">MP4 MOV WEBM · max 500 MB</span></label>
            <div class="upload-zone" @click="$refs.vid.click()" @dragover.prevent="drag=true" @dragleave="drag=false" @drop.prevent="onDrop($event)" :class="drag?'drag':''">
              <input type="file" x-ref="vid" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="onFile($event)">
              <svg class="upload-icon" width="22" height="22" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              <p class="upload-main">Drop or <strong style="color:#111;text-decoration:underline">click to browse</strong></p>
              <p class="upload-hint">Shoot on any phone · published to YouTube</p>
              <p x-show="file" x-text="'✓ '+(file?file.name:'')" style="display:none;color:#111;font-size:.78rem;margin-top:.35rem;font-weight:600"></p>
            </div>
            <div x-show="uploading" style="display:none">
              <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#6b7280;margin-top:.35rem;margin-bottom:.15rem"><span>Uploading…</span><span x-text="progress+'%'"></span></div>
              <div class="prog-bar"><div class="prog-fill" :style="'width:'+progress+'%'"></div></div>
            </div>
          </div>

          <!-- Errors -->
          <div x-show="errors.length" style="display:none" class="err-box">
            <ul style="list-style:none"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
          </div>

          <button type="submit" class="btn-submit" :disabled="loading">Submit <?= $atype ?> →</button>
        </div>
      </form>

    </div><!-- /vcard-body -->
  </div><!-- /vcard -->

<?php endforeach; ?>

</div><!-- /cards-grid -->

<!-- FOOTER -->
<footer style="border-top:1px solid #e5e7eb;padding:1.75rem 1.5rem;background:#fff">
  <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" style="height:26px;width:auto">
      <?php else: ?><span style="font-family:'Bebas Neue',sans-serif;font-size:16px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span><span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:17px;height:17px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span><?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem">
      <a href="/actor"    style="color:#6b7280;font-size:.8rem;text-decoration:none">Actor</a>
      <a href="/director" style="color:#6b7280;font-size:.8rem;text-decoration:none">Director</a>
      <a href="/writer"   style="color:#6b7280;font-size:.8rem;text-decoration:none">Writer</a>
    </div>
    <span style="color:#9ca3af;font-size:.75rem">No face. Just talent.</span>
  </div>
</footer>

<script>
function toggleBrief(extraId, btnId) {
    const el  = document.getElementById(extraId);
    const btn = document.getElementById(btnId);
    if (!el || !btn) return;
    const opening = !el.classList.contains('open');
    el.classList.toggle('open', opening);
    btn.classList.toggle('open', opening);
    // Update label
    const label = btn.childNodes[btn.childNodes.length - 1];
    if (label && label.nodeType === 3) {
        label.textContent = opening ? ' Show less' : ' Read full brief';
    }
}

function auCard(scriptId, auditionType, role, scriptTitle, briefContent) {
    return {
        scriptId, auditionType, role, scriptTitle, briefContent,
        file: null, drag: false,
        loading: false, uploading: false, progress: 0,
        errors: [], form: { name: '', email: '', phone: '' },
        onFile(e) { this.file = e.target.files[0] || null; },
        onDrop(e) { this.drag = false; const f = e.dataTransfer.files[0]; if (f) this.file = f; },
        downloadPDF() {
            window._briefForPDF = { title: this.scriptTitle, auditionType: this.auditionType, content: this.briefContent };
            if (typeof downloadBriefPDF === 'function') downloadBriefPDF();
        },
        async submit() {
            this.errors = [];
            if (!this.file) { this.errors = ['Please select a video file.']; return; }
            this.loading = true; this.uploading = true; this.progress = 0;
            const fd = new FormData();
            fd.append('role', this.role);
            fd.append('audition_type', this.auditionType);
            fd.append('script_id', this.scriptId || '');
            fd.append('name',  this.form.name);
            fd.append('email', this.form.email);
            fd.append('phone', this.form.phone);
            fd.append('file',  this.file);
            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = e => { if (e.lengthComputable) this.progress = Math.round(e.loaded / e.total * 100); };
            xhr.onload = () => {
                this.loading = false; this.uploading = false;
                try {
                    const r = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && r.success) {
                        this.form = { name: '', email: '', phone: '' }; this.file = null;
                        if (typeof showSuccessModal === 'function') showSuccessModal(r);
                    } else { this.errors = r.errors || [r.error || 'Submission failed.']; }
                } catch(err) { this.errors = ['Server error. Please try again.']; }
            };
            xhr.onerror = () => { this.loading = false; this.uploading = false; this.errors = ['Network error.']; };
            xhr.open('POST', '/api/submit');
            xhr.send(fd);
        }
    };
}
</script>
<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
</body>
</html>

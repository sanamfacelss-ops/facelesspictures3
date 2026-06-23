<?php
require_once __DIR__ . '/../app/config/config.php';

$scriptModel   = new App\Models\Script();
$settingsModel = new App\Models\Settings();
$logoUrl       = $settingsModel->get('site_logo_url', '');
$actorScripts  = $scriptModel->byCategory('actor');

if (empty($actorScripts)) {
    $actorScripts = [
        ['id'=>'dialog','title'=>'Dialog Audition','content'=>$settingsModel->get('actor_dialog_script','Perform the following scene with full emotion. You receive a call that changes everything. Show shock, then resolve — all in under 90 seconds.'),'audition_type'=>'Dialog Audition','difficulty'=>'beginner','duration_hint'=>'60-90 seconds','image_url'=>'','rules'=>"Video must be under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required"],
        ['id'=>'song','title'=>'Song Audition','content'=>$settingsModel->get('actor_song_script','Choose any song that represents a character going through transformation. Perform a 60-second version showing emotional range — just your voice.'),'audition_type'=>'Song Audition','difficulty'=>'beginner','duration_hint'=>'60 seconds','image_url'=>'','rules'=>"Video must be under 2 minutes\nShoot on any device\nJust your voice\nClear audio required"],
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

.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
.fp-logo{display:flex;align-items:center;gap:7px;text-decoration:none}
.fp-logo-text{font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111}
.fp-logo-badge{background:#111;color:#fff;font-size:9px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.fp-nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;text-decoration:none;color:#9ca3af}
.fp-nav-link.active{color:#111;border-bottom:2px solid #111;padding-bottom:1px;font-weight:700}

/* Card: poster + 2-col content on desktop */
.script-card{
    display:flex;
    flex-direction:row;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:16px;
    box-shadow:0 2px 12px rgba(0,0,0,.07);
    overflow:hidden;
}
/* Poster — left, fixed width, full height */
.card-poster{
    position:relative;
    width:260px;
    min-width:260px;
    flex-shrink:0;
    background:linear-gradient(160deg,#1a1a2e,#16213e,#0f3460);
    overflow:hidden;
    min-height:420px;
}
.card-poster img{
    display:block;
    width:100%;
    height:100%;
    min-height:420px;
    object-fit:cover;
    object-position:center center;
}
.poster-ph{width:100%;height:100%;min-height:420px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:2rem 1rem}
.poster-badge{position:absolute;top:12px;left:12px;z-index:2;font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.28rem .7rem;border-radius:20px;background:rgba(17,17,17,.85);color:#fff;border:1px solid rgba(255,255,255,.2);white-space:nowrap}

/* Content area: 2 equal columns */
.card-body{
    flex:1;
    min-width:0;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:0;
}
.card-col{
    padding:1.5rem;
    display:flex;
    flex-direction:column;
    gap:1rem;
}
.card-col + .card-col{
    border-left:1px solid #f0f0f0;
}

/* Responsive: stack everything below 860px */
@media(max-width:860px){
    .script-card{ flex-direction:column; }
    .card-poster{
        width:100% !important;
        min-width:0 !important;
        height:auto !important;
        min-height:0 !important;
        aspect-ratio:3/2;
    }
    /* On mobile: contain so full image is always visible, no crop */
    .card-poster img{
        object-fit:contain !important;
        object-position:center center;
        background:#1a1a2e;
    }
    .card-body{ grid-template-columns:1fr; }
    .card-col + .card-col{ border-left:none; border-top:1px solid #f0f0f0; }
}
@media(max-width:480px){
    .form2{ grid-template-columns:1fr !important; }
    .card-poster{ aspect-ratio:4/3; }
}

/* 3-column content grid */
.card-cols{
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:1rem;
    padding:1.375rem;
    flex:1;
    min-width:0;
}
.col-divider{width:1px;background:#f0f0f0;align-self:stretch}

/* Brief expandable — smooth slide */
.brief-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-top:.5rem}
.brief-expand-btn{
    display:inline-flex;align-items:center;gap:.3rem;
    font-size:.75rem;font-weight:700;color:#374151;
    background:#f3f4f6;border:1.5px solid #e5e7eb;
    border-radius:8px;padding:.35rem .75rem;
    cursor:pointer;font-family:inherit;
    transition:background .15s,border-color .15s;
    white-space:nowrap;
}
.brief-expand-btn:hover{background:#e5e7eb;border-color:#d1d5db}
.brief-expand-btn .arr{
    display:inline-block;
    transition:transform .3s cubic-bezier(.4,0,.2,1);
    font-size:.8rem;line-height:1;
}
.brief-expand-btn.open .arr{transform:rotate(180deg)}
/* The expandable section */
.brief-extra{
    max-height:0;
    overflow:hidden;
    transition:max-height .35s cubic-bezier(.4,0,.2,1), opacity .3s ease;
    opacity:0;
    font-size:.85rem;color:#4b5563;line-height:1.72;
}
.brief-extra.open{
    max-height:600px;
    opacity:1;
}
.card-title{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.03em;color:#111;line-height:1.05}
.dur-pill{display:inline-flex;align-items:center;gap:.3rem;font-size:.62rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:.2rem .55rem;border-radius:20px;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;margin-top:.35rem}

.btn-pdf{display:inline-flex;align-items:center;gap:.35rem;background:#fff;border:1.5px solid #e5e7eb;color:#374151;border-radius:8px;padding:.4rem .8rem;font-size:.75rem;font-weight:600;cursor:pointer;font-family:inherit;transition:border-color .2s}
.btn-pdf:hover{border-color:#111}

.div{height:1px;background:#f0f0f0}
.rule-row{display:flex;align-items:flex-start;gap:.45rem;font-size:.78rem;color:#374151;line-height:1.5;padding:.1rem 0}
.rule-dot{width:4px;height:4px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.45rem}

.fp-label{display:block;font-size:.63rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin-bottom:.3rem}
.fp-input{background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;color:#111;padding:.55rem .75rem;width:100%;font-size:.9rem;outline:none;font-family:inherit;-webkit-appearance:none;transition:border-color .2s}
.fp-input:focus{border-color:#111;box-shadow:0 0 0 2px rgba(17,17,17,.07)}
.fp-input::placeholder{color:#9ca3af}
.form2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}

/* Upload zone — all text static, always visible */
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;cursor:pointer;background:#fafafa;text-align:center;padding:1.1rem 1rem;transition:border-color .2s,background .2s}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f5f5f5}
.upload-icon{display:block;margin:0 auto .5rem}
.upload-main{color:#6b7280;font-size:.85rem}
.upload-hint{color:#9ca3af;font-size:.75rem;margin-top:.2rem}

.prog-bar{height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;margin-top:.4rem}
.prog-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
.err-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;color:#991b1b;padding:.6rem .875rem;font-size:.8rem}

/* Submit — text is plain HTML, always white on black */
.btn-submit{
    display:block;width:100%;
    background:#111;color:#fff;
    font-weight:700;border:none;
    border-radius:9px;padding:.8rem 1.5rem;
    font-size:.95rem;cursor:pointer;
    text-align:center;font-family:inherit;
    letter-spacing:.02em;
}
.btn-submit:hover{background:#333}
.btn-submit:disabled{opacity:.5;cursor:not-allowed}

@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .4s ease forwards}
</style>
</head>
<body>

<!-- NAV -->
<nav class="fp-nav">
  <div style="max-width:1100px;margin:0 auto;padding:0 1.5rem;height:100%;display:flex;align-items:center;justify-content:space-between">
    <a href="/" class="fp-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:30px;width:auto">
      <?php else: ?>
        <span class="fp-logo-text">FACELESS PICTURES</span>
        <span class="fp-logo-badge">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem;align-items:center">
      <a href="/actor"    class="fp-nav-link active">Actor</a>
      <a href="/director" class="fp-nav-link">Director</a>
      <a href="/writer"   class="fp-nav-link">Writer</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section style="padding:5.5rem 1.5rem 2rem;text-align:center" class="fade-up">
  <p style="font-size:.65rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:.6rem">Auditions Now Open</p>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(48px,8vw,80px);letter-spacing:.02em;line-height:.92;color:#111;margin-bottom:.75rem">ACTOR<br>AUDITIONS</h1>
  <p style="color:#6b7280;font-size:.9rem;max-width:380px;margin:0 auto;line-height:1.65">Pick an audition. Read the brief. Shoot your video. Submit.</p>
</section>

<!-- CARDS -->
<section style="padding:0 1.5rem 5rem">
  <div style="max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:1.75rem">

<?php foreach ($actorScripts as $i => $script):
    $atype    = htmlspecialchars($script['audition_type'] ?? 'Audition');
    $rules    = $script['rules'] ?? "Video under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required";
    $ruleList = array_filter(array_map('trim', explode("\n", $rules)));
    $scriptId = is_numeric($script['id']) ? (int)$script['id'] : 0;
    $cardId   = 'card_' . $i;
?>
    <!-- Card <?= $i+1 ?>: <?= $atype ?> -->
    <div class="script-card" id="<?= $cardId ?>">

      <!-- POSTER -->
      <div class="card-poster">
        <?php if (!empty($script['image_url'])): ?>
          <img src="<?= htmlspecialchars($script['image_url']) ?>" alt="<?= htmlspecialchars($script['title'] ?? '') ?>">
        <?php else: ?>
          <div class="poster-ph">
            <svg width="32" height="32" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span style="font-size:.58rem;color:rgba(255,255,255,.2);letter-spacing:.1em;text-transform:uppercase;text-align:center">Add image in Admin</span>
          </div>
        <?php endif; ?>
        <span class="poster-badge"><?= $atype ?></span>
      </div>

      <!-- CONTENT: 2 columns -->
      <div class="card-body" x-data="auCard(<?= $scriptId ?>, '<?= addslashes($atype) ?>', 'actor', <?= json_encode($script['title'] ?? '') ?>, <?= json_encode($script['content'] ?? '') ?>)">

        <!-- COL 1: Script info -->
        <div class="card-col">

          <div>
            <div class="card-title"><?= htmlspecialchars($script['title'] ?? '') ?></div>
            <?php if (!empty($script['duration_hint'])): ?>
            <div style="margin-top:.4rem"><span class="dur-pill">⏱ <?= htmlspecialchars($script['duration_hint']) ?></span></div>
            <?php endif; ?>
          </div>

          <!-- Brief — preview always visible, expanded content slides above actions row -->
          <div>
            <p class="fp-label" style="margin-bottom:.35rem">The Brief</p>
            <?php
              $briefFull    = $script['content'] ?? '';
              $briefPreview = mb_substr($briefFull, 0, 160);
              $briefRest    = mb_strlen($briefFull) > 160 ? mb_substr($briefFull, 160) : '';
              $hasMore      = !empty($briefRest);
              $briefExpandId = 'brief_' . $i;
            ?>
            <!-- Script name always shown -->
            <p style="font-size:.78rem;font-weight:700;color:#111;margin-bottom:.3rem"><?= htmlspecialchars($script['title'] ?? '') ?></p>
            <!-- Preview text always shown -->
            <p style="font-size:.85rem;color:#4b5563;line-height:1.72"><?= htmlspecialchars($briefPreview) ?><?= $hasMore ? '<span style="color:#9ca3af"> …</span>' : '' ?></p>
            <!-- Expandable extra — slides open above actions -->
            <?php if ($hasMore): ?>
            <div class="brief-extra" id="<?= $briefExpandId ?>"><?= nl2br(htmlspecialchars($briefRest)) ?></div>
            <?php endif; ?>
            <!-- Actions row: Read more + Download PDF always on same line -->
            <div class="brief-actions">
              <?php if ($hasMore): ?>
              <button class="brief-expand-btn" onclick="toggleBrief('<?= $briefExpandId ?>', this)">
                <span class="arr">▾</span> Read full brief
              </button>
              <?php endif; ?>
              <button class="btn-pdf" @click="downloadPDF()">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download Brief PDF
              </button>
            </div>
          </div>

          <div class="div"></div>

          <div>
            <p class="fp-label" style="margin-bottom:.5rem">Rules &amp; Limits</p>
            <?php foreach ($ruleList as $r): ?>
            <div class="rule-row"><span class="rule-dot"></span><span><?= htmlspecialchars($r) ?></span></div>
            <?php endforeach; ?>
          </div>

        </div><!-- /col 1 -->

        <!-- COL 2: Form + Upload + Submit -->
        <div class="card-col">

          <form @submit.prevent="submit()" style="display:flex;flex-direction:column;gap:.75rem;height:100%">

            <div>
              <p class="fp-label" style="margin-bottom:.6rem">Your Details</p>
              <div style="display:flex;flex-direction:column;gap:.55rem">
                <div class="form2">
                  <div>
                    <label class="fp-label">Name *</label>
                    <input type="text" x-model="form.name" class="fp-input" placeholder="Full name" required autocomplete="name">
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
              </div>
            </div>

            <div class="div"></div>

            <!-- Upload zone -->
            <div style="flex:1">
              <label class="fp-label" style="margin-bottom:.4rem">Your Video * <span style="text-transform:none;font-weight:400;color:#9ca3af;letter-spacing:0;font-size:.7rem">MP4 MOV WEBM · max 500 MB</span></label>
              <div class="upload-zone"
                @click="$refs.vid.click()"
                @dragover.prevent="drag=true"
                @dragleave="drag=false"
                @drop.prevent="onDrop($event)"
                :class="drag?'drag':''">
                <input type="file" x-ref="vid" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="onFile($event)">
                <svg class="upload-icon" width="26" height="26" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p class="upload-main">Drop video or <strong style="color:#111;text-decoration:underline">click to browse</strong></p>
                <p class="upload-hint">Shoot on any phone · published to YouTube after approval</p>
                <p x-show="file" x-text="'✓ ' + (file ? file.name : '')" style="display:none;color:#111;font-size:.8rem;margin-top:.4rem;font-weight:600"></p>
              </div>
              <div x-show="uploading" style="display:none;margin-top:.4rem">
                <div style="display:flex;justify-content:space-between;font-size:.7rem;color:#6b7280;margin-bottom:.2rem">
                  <span>Uploading…</span><span x-text="progress+'%'"></span>
                </div>
                <div class="prog-bar"><div class="prog-fill" :style="'width:'+progress+'%'"></div></div>
              </div>
            </div>

            <!-- Errors -->
            <div x-show="errors.length" style="display:none" class="err-box">
              <ul style="list-style:none"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
            </div>

            <!-- Submit — plain text, always white -->
            <button type="submit" class="btn-submit" :disabled="loading">
              Submit <?= $atype ?> →
            </button>

          </form>

        </div><!-- /col 2 -->

      </div><!-- /card-body -->
    </div><!-- /script-card -->

<?php endforeach; ?>

  </div><!-- /cards -->
</section>

<!-- FOOTER -->
<footer style="border-top:1px solid #e5e7eb;padding:1.75rem 1.5rem;background:#fff">
  <div style="max-width:1100px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" class="fp-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" style="height:26px;width:auto">
      <?php else: ?>
        <span class="fp-logo-text" style="font-size:16px">FACELESS PICTURES</span>
        <span class="fp-logo-badge" style="width:17px;height:17px">3</span>
      <?php endif; ?>
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
function toggleBrief(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const isOpen = el.classList.contains('open');
    el.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
    btn.querySelector('.arr').style.transform = isOpen ? '' : 'rotate(180deg)';
    const spans = btn.querySelectorAll('span:last-child');
    // Update button text after arrow span
    const textNode = [...btn.childNodes].find(n => n.nodeType === 3);
    if (textNode) textNode.textContent = isOpen ? ' Read full brief' : ' Show less';
}

function auCard(scriptId, auditionType, role, scriptTitle, briefContent) {
    return {
        scriptId, auditionType, role, scriptTitle, briefContent,
        expanded: false,
        file: null,
        drag: false,
        loading: false,
        uploading: false,
        progress: 0,
        errors: [],
        form: { name: '', email: '', phone: '' },

        onFile(e) {
            this.file = e.target.files[0] || null;
        },
        onDrop(e) {
            this.drag = false;
            const f = e.dataTransfer.files[0];
            if (f) this.file = f;
        },
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
                        this.form = { name: '', email: '', phone: '' };
                        this.file = null;
                        if (typeof showSuccessModal === 'function') showSuccessModal(r);
                    } else {
                        this.errors = r.errors || [r.error || 'Submission failed.'];
                    }
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

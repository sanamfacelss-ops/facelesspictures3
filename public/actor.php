<?php
require_once __DIR__ . '/../app/config/config.php';

$scriptModel  = new App\Models\Script();
$settingsModel = new App\Models\Settings();
$logoUrl      = $settingsModel->get('site_logo_url', '');

// Get actor scripts from DB
$actorScripts = $scriptModel->byCategory('actor');

// Fallback: if no DB scripts, use the two admin-editable briefs as virtual cards
if (empty($actorScripts)) {
    $actorScripts = [
        [
            'id'           => 'dialog',
            'title'        => 'Dialog Audition',
            'content'      => $settingsModel->get('actor_dialog_script', 'Perform the following scene with full emotion. You receive a call that changes everything. Show shock, then resolve — all in under 90 seconds.'),
            'audition_type'=> 'Dialog Audition',
            'difficulty'   => 'beginner',
            'duration_hint'=> '60-90 seconds',
            'image_url'    => '',
            'rules'        => "Video must be under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required",
        ],
        [
            'id'           => 'song',
            'title'        => 'Song Audition',
            'content'      => $settingsModel->get('actor_song_script', 'Choose any song that represents a character going through transformation. Perform a 60-second version showing emotional range — just your voice.'),
            'audition_type'=> 'Song Audition',
            'difficulty'   => 'beginner',
            'duration_hint'=> '60 seconds',
            'image_url'    => '',
            'rules'        => "Video must be under 2 minutes\nShoot on any device\nJust your voice — no backing track needed\nClear audio required",
        ],
    ];
}

$diffColors = [
    'beginner'     => ['bg' => '#f0fdf4', 'text' => '#166534', 'border' => '#bbf7d0'],
    'intermediate' => ['bg' => '#fff7ed', 'text' => '#9a3412', 'border' => '#fed7aa'],
    'advanced'     => ['bg' => '#fef2f2', 'text' => '#991b1b', 'border' => '#fecaca'],
];

$pageTitle = 'Actor Auditions — Faceless Pictures 3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f8f8f8;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}

/* NAV */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}

/* SCRIPT CARD */
.script-card{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.04);border:1px solid #e5e7eb;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s}
.script-card:hover{box-shadow:0 4px 24px rgba(0,0,0,.10);transform:translateY(-2px)}

/* POSTER TOP */
.card-poster{position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);overflow:hidden;flex-shrink:0}
.card-poster img{width:100%;height:100%;object-fit:cover;display:block}
.card-poster-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px}
.poster-badge{position:absolute;top:10px;left:10px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:.3rem .7rem;border-radius:20px;color:#fff;backdrop-filter:blur(8px)}
.badge-dialog{background:rgba(17,17,17,.8);border:1px solid rgba(255,255,255,.2)}
.badge-song{background:rgba(88,80,236,.85);border:1px solid rgba(255,255,255,.2)}
.diff-pip{position:absolute;top:10px;right:10px;font-size:.58rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:.2rem .55rem;border-radius:10px}

/* CARD BODY */
.card-body{padding:1.125rem 1.25rem;flex:1;display:flex;flex-direction:column;gap:.875rem}

/* SCRIPT TITLE + META */
.card-title{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.03em;color:#111;line-height:1.05}
.card-meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.meta-pill{font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:.2rem .55rem;border-radius:8px;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}

/* SCRIPT CONTENT — Read More */
.script-text{font-size:.825rem;color:#4b5563;line-height:1.65;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;transition:all .3s}
.script-text.expanded{display:block;-webkit-line-clamp:unset}
.read-more-btn{font-size:.72rem;font-weight:600;color:#111;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;text-underline-offset:3px;margin-top:.15rem;display:inline-flex;align-items:center;gap:.25rem}

/* RULES */
.rules-block{background:#f9fafb;border-radius:8px;padding:.75rem;border:1px solid #f0f0f0}
.rule-item{display:flex;align-items:flex-start;gap:.5rem;font-size:.75rem;color:#374151;line-height:1.4;padding:.2rem 0}
.rule-dot{width:4px;height:4px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.4rem}

/* DIVIDER */
.card-divider{height:1px;background:#f0f0f0;margin:0 -.125rem}

/* PDF DOWNLOAD */
.btn-pdf{display:inline-flex;align-items:center;gap:.375rem;background:#fff;border:1.5px solid #e5e7eb;color:#374151;border-radius:8px;padding:.5rem .875rem;font-size:.75rem;font-weight:600;cursor:pointer;transition:border-color .2s,background .2s;white-space:nowrap}
.btn-pdf:hover{border-color:#111;background:#f9fafb}
.btn-pdf svg{width:13px;height:13px;flex-shrink:0}

/* FORM INPUTS */
.fp-label{display:block;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin-bottom:.3rem}
.fp-input{background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;color:#111;padding:.55rem .8rem;width:100%;font-size:.875rem;transition:border-color .2s,box-shadow .2s;outline:none;-webkit-appearance:none}
.fp-input:focus{border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.06)}
.fp-input::placeholder{color:#9ca3af}

/* UPLOAD ZONE */
.upload-zone{border:2px dashed #d1d5db;border-radius:10px;transition:border-color .2s,background .2s;cursor:pointer;background:#fafafa}
.upload-zone:hover,.upload-zone.drag{border-color:#111;background:#f9fafb}
.upload-zone.has-file{border-color:#111;border-style:solid;background:#f9fafb}

/* SUBMIT BTN */
.btn-submit{background:#111;color:#fff;font-weight:700;border:none;border-radius:9px;padding:.8rem 1.5rem;font-size:.9rem;cursor:pointer;width:100%;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:.25rem}
.btn-submit:hover{background:#333}
.btn-submit:disabled{opacity:.4;cursor:not-allowed}

/* PROGRESS */
.progress-bar{height:3px;background:#e5e7eb;border-radius:2px;overflow:hidden}
.progress-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}

/* MESSAGES */
.success-box{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;color:#166534;padding:.875rem 1rem;font-size:.8rem}
.error-box{background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;color:#991b1b;padding:.875rem 1rem;font-size:.8rem}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .4s ease forwards}
</style>
</head>

<body>

<!-- NAV -->
<nav class="fp-nav">
  <div style="max-width:1200px;margin:0 auto;padding:0 1.25rem;height:100%;display:flex;align-items:center;justify-content:space-between">
    <a href="/" style="display:flex;align-items:center;gap:8px;text-decoration:none">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:30px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;gap:1.25rem">
      <a href="/actor"    style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#111;text-decoration:none;border-bottom:2px solid #111;padding-bottom:2px">Actor</a>
      <a href="/director" style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Director</a>
      <a href="/writer"   style="font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;text-decoration:none">Writer</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section style="padding:5rem 1.25rem 2rem;text-align:center" class="fade-up">
  <p style="font-size:.65rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:.6rem">Auditions Now Open</p>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(48px,8vw,80px);letter-spacing:.02em;line-height:.92;color:#111;margin-bottom:.75rem">ACTOR<br>AUDITIONS</h1>
  <p style="color:#6b7280;font-size:.9rem;max-width:380px;margin:0 auto;line-height:1.6">Pick an audition. Read the brief. Shoot your video. Submit.</p>
</section>

<!-- SCRIPT CARDS GRID -->
<section style="padding:0 1.25rem 5rem">
  <div style="max-width:1200px;margin:0 auto">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem">

    <?php foreach ($actorScripts as $i => $script):
        $atype    = $script['audition_type'] ?? ($script['category'] === 'actor' ? 'Dialog Audition' : 'Audition');
        $isSong   = stripos($atype, 'song') !== false;
        $diff     = $script['difficulty'] ?? 'beginner';
        $dc       = $diffColors[$diff] ?? $diffColors['beginner'];
        $rules    = $script['rules'] ?? "Video under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required";
        $ruleList = array_filter(array_map('trim', explode("\n", $rules)));
        $scriptId = is_numeric($script['id']) ? (int)$script['id'] : 0;
        $briefJson = json_encode($script['content'] ?? '');
        $titleJson = json_encode($script['title'] ?? '');
    ?>

      <!-- CARD <?= $i+1 ?> -->
      <div class="script-card" x-data="auCard(<?= $scriptId ?>, <?= json_encode($atype) ?>, <?= $titleJson ?>, <?= $briefJson ?>)">

        <!-- ── POSTER IMAGE ── -->
        <div class="card-poster">
          <?php if (!empty($script['image_url'])): ?>
            <img src="<?= htmlspecialchars($script['image_url']) ?>" alt="<?= htmlspecialchars($script['title']) ?>">
          <?php else: ?>
            <div class="card-poster-placeholder">
              <svg width="32" height="32" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <span style="font-size:.65rem;color:rgba(255,255,255,.3);letter-spacing:.1em;text-transform:uppercase">Add image in Admin</span>
            </div>
          <?php endif; ?>
          <!-- Badge -->
          <span class="poster-badge <?= $isSong ? 'badge-song' : 'badge-dialog' ?>"><?= htmlspecialchars($atype) ?></span>
          <!-- Difficulty -->
          <span class="diff-pip" style="background:<?= $dc['bg'] ?>;color:<?= $dc['text'] ?>;border:1px solid <?= $dc['border'] ?>"><?= ucfirst($diff) ?></span>
        </div>

        <!-- ── CARD BODY ── -->
        <div class="card-body">

          <!-- Title + Meta -->
          <div>
            <div class="card-title"><?= htmlspecialchars($script['title']) ?></div>
            <div class="card-meta" style="margin-top:.35rem">
              <?php if ($script['duration_hint']): ?>
              <span class="meta-pill">⏱ <?= htmlspecialchars($script['duration_hint']) ?></span>
              <?php endif; ?>
              <span class="meta-pill"><?= ucfirst($diff) ?></span>
            </div>
          </div>

          <!-- Script Content + Read More -->
          <div>
            <p class="fp-label" style="margin-bottom:.375rem">The Brief</p>
            <div class="script-text" :class="expanded ? 'expanded' : ''" x-ref="scriptText"><?= nl2br(htmlspecialchars($script['content'])) ?></div>
            <button class="read-more-btn" @click="expanded = !expanded">
              <span x-text="expanded ? 'Show less ↑' : 'Read more ↓'"></span>
            </button>
          </div>

          <!-- Download PDF -->
          <div>
            <button class="btn-pdf" @click="downloadPDF()">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Download Brief PDF
            </button>
          </div>

          <div class="card-divider"></div>

          <!-- Rules -->
          <div>
            <p class="fp-label" style="margin-bottom:.375rem">Rules &amp; Limits</p>
            <div class="rules-block">
              <?php foreach ($ruleList as $rule): ?>
              <div class="rule-item"><span class="rule-dot"></span><span><?= htmlspecialchars($rule) ?></span></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card-divider"></div>

          <!-- Contact Form -->
          <div>
            <p class="fp-label" style="margin-bottom:.625rem">Your Details</p>
            <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:.625rem">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.625rem">
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
              <div>
                <label class="fp-label">Notes <span style="text-transform:none;font-weight:400;color:#9ca3af">(optional)</span></label>
                <textarea x-model="form.notes" class="fp-input" rows="2" placeholder="Anything you'd like us to know..." style="resize:vertical"></textarea>
              </div>

              <div class="card-divider"></div>

              <!-- Upload Zone -->
              <div>
                <label class="fp-label">Your Video *</label>
                <p style="font-size:.7rem;color:#9ca3af;margin-bottom:.5rem">Shoot on any phone · MP4 MOV WEBM · max 500 MB · published to YouTube after approval</p>
                <div class="upload-zone" style="padding:1.25rem;text-align:center" :class="[dragOver?'drag':'', file?'has-file':'']"
                  @dragover.prevent="dragOver=true" @dragleave="dragOver=false"
                  @drop.prevent="handleDrop($event)" @click="$refs.vidFile.click()">
                  <input type="file" x-ref="vidFile" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="handleFile($event)">
                  <template x-if="!file">
                    <div>
                      <svg style="width:28px;height:28px;color:#9ca3af;margin:0 auto .5rem;display:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                      <p style="color:#6b7280;font-size:.8rem">Drop video or <span style="color:#111;font-weight:600;text-decoration:underline">browse</span></p>
                    </div>
                  </template>
                  <template x-if="file">
                    <div style="display:flex;align-items:center;justify-content:center;gap:.625rem">
                      <svg style="width:16px;height:16px;color:#111;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      <span style="color:#111;font-size:.8rem;font-weight:500;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="file.name"></span>
                      <button type="button" @click.stop="file=null;dragOver=false" style="color:#9ca3af;background:none;border:none;cursor:pointer;font-size:.875rem;line-height:1;flex-shrink:0">✕</button>
                    </div>
                  </template>
                </div>
                <!-- Upload progress -->
                <template x-if="uploading">
                  <div style="margin-top:.5rem">
                    <div style="display:flex;justify-content:space-between;font-size:.7rem;color:#6b7280;margin-bottom:.2rem">
                      <span>Uploading your video...</span><span x-text="progress+'%'"></span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" :style="'width:'+progress+'%'"></div></div>
                  </div>
                </template>
              </div>

              <!-- Errors -->
              <template x-if="errors.length">
                <div class="error-box">
                  <ul style="list-style:none"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
                </div>
              </template>

              <!-- Submit -->
              <button type="submit" class="btn-submit" :disabled="loading">
                <template x-if="!loading">
                  <span style="display:flex;align-items:center;gap:.4rem">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    Submit <?= htmlspecialchars($atype) ?>
                  </span>
                </template>
                <template x-if="loading">
                  <span>Uploading...</span>
                </template>
              </button>
            </form>
          </div>

        </div><!-- /card-body -->
      </div><!-- /script-card -->

    <?php endforeach; ?>

    </div><!-- /grid -->
  </div>
</section>

<!-- FOOTER -->
<footer style="border-top:1px solid #e5e7eb;padding:1.5rem 1.25rem;background:#fff">
  <div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:26px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:16px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span style="background:#111;color:#fff;font-size:9px;font-weight:700;width:17px;height:17px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
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
function auCard(scriptId, auditionType, scriptTitle, briefContent) {
    // Set brief for PDF
    window._briefForPDF = { title: scriptTitle, auditionType, content: briefContent };
    return {
        scriptId, auditionType, scriptTitle, briefContent,
        expanded: false,
        form: { name:'', email:'', phone:'', notes:'' },
        file: null, dragOver: false,
        loading: false, uploading: false, progress: 0,
        errors: [],

        handleFile(e) { this.file = e.target.files[0] || null; },
        handleDrop(e) { this.dragOver = false; const f = e.dataTransfer.files[0]; if (f) this.file = f; },

        downloadPDF() {
            window._briefForPDF = { title: this.scriptTitle, auditionType: this.auditionType, content: this.briefContent };
            if (typeof downloadBriefPDF === 'function') downloadBriefPDF();
        },

        async submit() {
            this.errors = [];
            if (!this.file) { this.errors = ['Please select your video file before submitting.']; return; }
            const allowed = ['video/mp4','video/quicktime','video/webm','video/x-msvideo','video/mpeg'];
            if (this.file.type && !allowed.includes(this.file.type) && !this.file.name.match(/\.(mp4|mov|webm|avi|mpeg)$/i)) {
                this.errors = ['Only video files accepted (MP4, MOV, WEBM).']; return;
            }
            this.loading = true; this.uploading = true; this.progress = 0;
            const fd = new FormData();
            fd.append('role', 'actor');
            fd.append('audition_type', this.auditionType);
            fd.append('script_id', this.scriptId || '');
            fd.append('name',  this.form.name);
            fd.append('email', this.form.email);
            fd.append('phone', this.form.phone);
            fd.append('notes', this.form.notes);
            fd.append('file',  this.file);
            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = e => { if (e.lengthComputable) this.progress = Math.round(e.loaded/e.total*100); };
            xhr.onload = () => {
                this.loading = false; this.uploading = false;
                try {
                    const r = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && r.success) {
                        this.form = {name:'',email:'',phone:'',notes:''}; this.file = null;
                        showSuccessModal(r);
                    } else { this.errors = r.errors || [r.error || 'Submission failed.']; }
                } catch(e) { this.errors = ['Server error. Please try again.']; }
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

<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/settings_helper.php';
$scriptModel   = new App\Models\Script();

$logoUrl       = setting('site_logo_url', '');
$writerBrief   = setting('writer_brief', 'We give you the first half of a script. Your job: continue the story, write the second half, then record yourself narrating/performing it on camera.');
$writerScripts = $scriptModel->byCategory('writer');
$pageTitle = 'Writer Submissions — Faceless Pictures 3';

// Get cache version for asset cache-busting
$cacheVersion = '1';
$versionFile = __DIR__ . '/../cache/.version';
if (file_exists($versionFile)) {
    $cacheVersion = trim(file_get_contents($versionFile)) ?: '1';
}

// Page text settings
$heroLabel       = setting('writer_hero_label', 'Submissions Now Open');
$heroHeading     = setting('writer_hero_heading', 'WRITER SUBMISSIONS');
$heroDescription = setting('writer_hero_description', 'READ THE SCENE. WRITE THE NEXT PAGE. RECORD YOUR NARRATION. UPLOAD YOUR VIDEO.');
$step1Title      = setting('writer_step1_title', 'WHAT WE GIVE');
$step1Text       = setting('writer_step1_text', 'One page');
$step2Title      = setting('writer_step2_title', 'WHAT YOU DO');
$step2Text       = setting('writer_step2_text', 'Continue it. One more page only');
$step3Title      = setting('writer_step3_title', 'SUBMIT');
$step3Text       = setting('writer_step3_text', 'Your page PDF plus narration video');
$formHeading     = setting('writer_form_heading', 'Ready to Write? Submit Your Continuation');
$formDescription = setting('writer_form_description', 'Read the given script, write what happens next, then record yourself narrating it on camera.');

// Form field labels and placeholders
$fieldNameLabel = setting('writer_field_name_label', 'Name *');
$fieldNamePlaceholder = setting('writer_field_name_placeholder', 'Your full name');
$fieldEmailLabel = setting('writer_field_email_label', 'Email *');
$fieldEmailPlaceholder = setting('writer_field_email_placeholder', 'you@email.com');
$fieldPhoneLabel = setting('writer_field_phone_label', 'Phone *');
$fieldPhonePlaceholder = setting('writer_field_phone_placeholder', '+91 98765 43210');
$fieldVideoLabel = setting('writer_field_video_label', 'Narration Video *');
$fieldVideoHint = setting('writer_field_video_hint', 'your narration video');
$termsText = setting('writer_terms_text', 'I agree to the <a href="/legal" target="_blank" style="color:#2563eb;text-decoration:underline;font-weight:600">Terms & Conditions</a> and confirm all information is accurate');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<!-- Cache busting - forces browser to reload on settings change -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;700&family=Noto+Sans+Bengali:wght@400;700&family=Noto+Sans+Tamil:wght@400;700&family=Noto+Sans+Telugu:wght@400;700&family=Noto+Sans+Kannada:wght@400;700&family=Noto+Sans+Malayalam:wght@400;700&family=Noto+Sans+Gujarati:wght@400;700&family=Noto+Sans+Gurmukhi:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;overflow-x:hidden}
body{font-family:'DM Sans','Noto Sans Devanagari','Noto Sans Bengali','Noto Sans Tamil','Noto Sans Telugu','Noto Sans Kannada','Noto Sans Malayalam','Noto Sans Gujarati','Noto Sans Gurmukhi',sans-serif;background:#f9fafb;color:#111;-webkit-font-smoothing:antialiased}
[x-cloak]{display:none!important}
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50}
.brief-grid{display:grid;grid-template-columns:1fr;gap:1.5rem;max-width:640px;margin:0 auto;padding:0 1.5rem 1.75rem}
@media(max-width:768px){.brief-grid{padding:0 1rem 1.5rem}}
.side-by-side{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:stretch}
@media(max-width:860px){.side-by-side{grid-template-columns:1fr}}
.brief-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);overflow:hidden;display:flex;flex-direction:column}
.card-sec{padding:1rem 1.125rem;border-bottom:1px solid #f0f0f0}
.card-sec:last-child{border-bottom:none}
.card-sec.tinted{background:#f9fafb}
.sec-label{display:flex;align-items:center;gap:.45rem;font-size:.6rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#9ca3af;margin-bottom:.625rem}
/* Equal height: submit card fills its grid cell */
.side-by-side>.submit-card{display:flex;flex-direction:column;margin:0}
.sec-label::before{content:'';display:inline-block;width:3px;height:10px;border-radius:2px;background:#111;flex-shrink:0}
.preview-video{width:100%;display:block;background:#000;max-height:260px}
.video-placeholder{width:100%;height:160px;background:#1a1a2e;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:rgba(255,255,255,.3);font-size:.72rem;letter-spacing:.06em;text-transform:uppercase}
.portrait-img{width:100%;max-height:300px;object-fit:contain;background:#f3f4f6;display:block}
.portrait-placeholder{width:100%;height:180px;background:#f3f4f6;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#9ca3af;font-size:.72rem}
.btn-outline{display:flex;align-items:center;justify-content:center;gap:.5rem;background:#fff;border:2px solid #111;color:#111;border-radius:9px;padding:.7rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;transition:background .15s,color .15s;white-space:nowrap}
.btn-outline:hover{background:#111;color:#fff}
.btn-outline.disabled{opacity:.4;pointer-events:none;cursor:not-allowed;border-color:#d1d5db;color:#9ca3af}
.rule-row{display:flex;align-items:flex-start;gap:.4rem;font-size:.78rem;color:#374151;line-height:1.55;padding:.15rem 0}
.rule-dot{width:3px;height:3px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.55rem}
.submit-card{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);max-width:1280px;margin:0 auto 5rem;padding:2.25rem 2rem}
@media(max-width:768px){.submit-card{margin:0 1rem 4rem;padding:1.5rem 1.25rem;border-radius:12px}}
.fp-label-dark{display:block;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin-bottom:.28rem}
.fp-input-dark{background:#fff;border:1.5px solid #e5e7eb;border-radius:8px;color:#111;padding:.55rem .8rem;width:100%;font-size:.875rem;outline:none;font-family:inherit;-webkit-appearance:none;transition:border-color .2s}
.fp-input-dark:focus{border-color:#111;box-shadow:0 0 0 2px rgba(17,17,17,.07)}
.fp-input-dark::placeholder{color:#9ca3af}
.form3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1.25rem}
@media(max-width:640px){.form3{grid-template-columns:1fr}}
.divider-dark{height:1px;background:#e5e7eb;margin:1.25rem 0}
.uzone{border:2px dashed #d1d5db;border-radius:10px;cursor:pointer;background:#fff;text-align:center;padding:1.25rem 1rem;transition:border-color .2s,background .2s}
.uzone:hover,.uzone.drag{border-color:#111;background:#f9fafb}
.uzone.has-file{border-color:#16a34a;border-style:solid;background:#f0fdf4}
.prog-bar{height:3px;background:#e5e7eb;border-radius:2px;overflow:hidden;margin-top:.4rem}
.prog-fill{height:100%;background:#111;border-radius:2px;transition:width .3s}
.err-dark{background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;color:#991b1b;padding:.6rem .875rem;font-size:.8rem;margin-top:.75rem}
.btn-go{display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;background:#111;color:#fff;font-weight:700;border:none;border-radius:9px;padding:.9rem 1.5rem;font-size:.95rem;cursor:pointer;font-family:inherit;letter-spacing:.01em;transition:background .15s;margin-top:1.25rem}
.btn-go:hover{background:#333}
.btn-go:disabled{opacity:.4;cursor:not-allowed}
.pill-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .85rem;border:1.5px solid #111;border-radius:999px;font-size:.75rem;color:#111;text-decoration:none;font-weight:600;background:#fff;transition:all .2s;white-space:nowrap;cursor:pointer}
.pill-btn:hover{background:#111;color:#fff}
.pill-btn svg{transition:all .2s}
.pill-btn:hover svg{color:#fff}
.terms-box{margin-top:1.25rem;border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.25rem;background:#fafafa;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.terms-label{display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;user-select:none;flex:1}
.terms-label input{width:18px;height:18px;margin-top:2px;cursor:pointer;accent-color:#111;flex-shrink:0}
.terms-label span{color:#111;font-size:.95rem;font-weight:500;line-height:1.5}
.terms-link{display:inline-flex;align-items:center;gap:.4rem;font-size:.875rem;color:#6b7280;text-decoration:none;font-weight:500;white-space:nowrap;transition:color .2s}
@media(max-width:768px){
  .terms-box{padding:.85rem 1rem;gap:.75rem}
  .terms-label{flex:1 1 100%;min-width:100%}
  .terms-label span{font-size:.875rem}
  .terms-link{flex:1 1 100%;width:100%;justify-content:center;font-size:.8rem}
}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .4s ease forwards}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<?php 
// Get the header height from nav-frontend.php calculation
$headerHeight = (int)setting('site_logo_height', '44') + 16;
?>

<?php require_once __DIR__ . '/partials/nav-frontend.php'; ?>

<!-- HERO -->
<section style="padding:<?= $headerHeight + 32 ?>px 1.5rem 3rem;text-align:center" class="fade-up">
  <?php if (!empty($heroLabel)): ?>
  <p style="font-size:.63rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:1rem"><?= htmlspecialchars($heroLabel) ?></p>
  <?php endif; ?>
  <?php if (!empty($heroHeading)): ?>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(28px,4vw,40px);letter-spacing:.04em;line-height:1;color:#111;margin-bottom:.65rem;white-space:nowrap"><?= htmlspecialchars($heroHeading) ?></h1>
  <?php endif; ?>
  <?php if (!empty($heroDescription)): ?>
  <p style="color:#6b7280;font-size:.85rem;max-width:480px;margin:0 auto;line-height:1.55"><?= nl2br(htmlspecialchars($heroDescription)) ?></p>
  <?php endif; ?>
</section>

<!-- BRIEF CARD + SUBMISSION — side by side on desktop -->
<div class="side-by-side" style="max-width:1280px;margin:0 auto;padding:0 1.5rem 5rem">

<?php
$sc = !empty($writerScripts) ? $writerScripts[0] : null;
$previewUrl  = $sc['preview_video_url'] ?? '';
$scriptImage = $sc['image_url']         ?? '';
$pdfUrl      = $sc['script_pdf_url']    ?? '';
$rulesTxt    = $sc['rules'] ?? "Face MUST be visible on camera\nClear audio required\nShoot on any device\nVideo under 5 minutes\nRead/narrate your continuation clearly";
$cardTitle   = $sc ? htmlspecialchars($sc['title']) : 'Script Continuation';
$audType     = $sc ? htmlspecialchars($sc['audition_type'] ?? 'Writer Brief') : 'Writer Brief';
$brief       = $sc ? htmlspecialchars($sc['content'] ?: $writerBrief) : htmlspecialchars($writerBrief);
$ruleList    = array_filter(array_map('trim', explode("\n", $rulesTxt)));
?>

  <!-- LEFT: Brief card -->
  <div class="brief-card">
    
    <!-- 3-STEP PROCESS BAR (at top) -->
    <div class="card-sec" style="background:#fafafa;border-bottom:1px solid #e5e7eb">
      <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,1fr);gap:1.25rem">
        
        <!-- STEP 1 -->
        <div style="display:flex;align-items:flex-start;gap:.75rem;min-width:0">
          <div style="width:40px;height:40px;background:#fff;border:1px solid #e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" fill="none" stroke="#6b7280" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
          <div style="min-width:0;flex:1">
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.05em;color:#111;margin-bottom:.25rem;line-height:1.1"><?= htmlspecialchars($step1Title) ?></h4>
            <p style="color:#6b7280;font-size:.78rem;line-height:1.4;overflow-wrap:break-word"><?= htmlspecialchars($step1Text) ?></p>
          </div>
        </div>

        <!-- STEP 2 -->
        <div style="display:flex;align-items:flex-start;gap:.75rem;min-width:0">
          <div style="width:40px;height:40px;background:#fff;border:1px solid #e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" fill="none" stroke="#6b7280" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          </div>
          <div style="min-width:0;flex:1">
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.05em;color:#111;margin-bottom:.25rem;line-height:1.1"><?= htmlspecialchars($step2Title) ?></h4>
            <p style="color:#6b7280;font-size:.78rem;line-height:1.4;overflow-wrap:break-word"><?= htmlspecialchars($step2Text) ?></p>
          </div>
        </div>

        <!-- STEP 3 -->
        <div style="display:flex;align-items:flex-start;gap:.75rem;min-width:0">
          <div style="width:40px;height:40px;background:#fff;border:1px solid #e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" fill="none" stroke="#6b7280" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          </div>
          <div style="min-width:0;flex:1">
            <h4 style="font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.05em;color:#111;margin-bottom:.25rem;line-height:1.1"><?= htmlspecialchars($step3Title) ?></h4>
            <p style="color:#6b7280;font-size:.78rem;line-height:1.4;overflow-wrap:break-word"><?= htmlspecialchars($step3Text) ?></p>
          </div>
        </div>

      </div>
      
      <!-- Mobile: stack vertically -->
      <style>
        @media (max-width: 768px) {
          .brief-card .card-sec > div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
          }
        }
      </style>
    </div>
    
    <div class="card-sec" style="padding:0 0 1.5rem">
      <?php 
      $isYT = preg_match('/youtu(\.be|be\.com)/i', $previewUrl);
      if ($previewUrl && $isYT): 
        // YouTube embed
        $embedUrl = $previewUrl;
        if (preg_match('/youtu\.be\/([^?&#]+)/', $previewUrl, $m)) {
          $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
        } elseif (preg_match('/[?&]v=([^&#]+)/', $previewUrl, $m)) {
          $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
        } elseif (preg_match('/\/shorts\/([^?&#]+)/', $previewUrl, $m)) {
          $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
        }
      ?>
        <div style="width:100%;aspect-ratio:16/9;position:relative;overflow:hidden;background:#000">
          <iframe src="<?= htmlspecialchars($embedUrl) ?>?rel=0&modestbranding=1&showinfo=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%"></iframe>
        </div>
      <?php elseif ($previewUrl): ?>
        <video class="preview-video" controls muted preload="metadata" style="width:100%;max-height:400px;object-fit:contain;background:#000"><source src="<?= htmlspecialchars($previewUrl) ?>" type="video/mp4">Your browser does not support video.</video>
      <?php else: ?>
        <div class="video-placeholder">
          <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          Preview video coming soon
        </div>
      <?php endif; ?>
    </div>
    
    <!-- Card heading + subheading — moved below video -->
    <div class="card-sec" style="background:#f3f4f6;border-bottom:1px solid #e5e7eb;padding:1.25rem 1.25rem 1rem;text-align:center">
      <p style="font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.08em;color:#111;line-height:1;margin-bottom:.45rem"><?= strtoupper($cardTitle) ?></p>
      <?php if ($sc && !empty($sc['content'])): ?>
      <p style="font-size:.85rem;font-weight:500;color:#6b7280;line-height:1.5"><?= htmlspecialchars($sc['content']) ?></p>
      <?php endif; ?>
    </div>
    
    <?php if ($scriptImage): ?>
    <div class="card-sec" style="padding:1.25rem 0 0;border-top:1px solid #e5e7eb;background:#fff">
      <img src="<?= htmlspecialchars($scriptImage) ?>" alt="Writer script" style="width:100%;height:auto;display:block;object-fit:contain;background:#fff">
    </div>
    <?php endif; ?>
    <div class="card-sec">
      <?php if ($pdfUrl): ?>
        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener" class="btn-outline" style="width:100%">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Download Script PDF
        </a>
      <?php else: ?>
        <button class="btn-outline disabled" disabled style="width:100%">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Script PDF not available yet
        </button>
      <?php endif; ?>
    </div>
    
    <div class="card-sec tinted" style="flex:1"></div>
  </div><!-- /brief-card -->

  <!-- RIGHT: SUBMISSION CARD (direct grid child) -->
  <div class="submit-card" style="margin:0" x-data="writerSubmit()">
    
    <?php if (!empty($formHeading)): ?>
    <p style="font-family:'Bebas Neue',sans-serif;font-size:clamp(20px,2.5vw,28px);letter-spacing:.04em;color:#111;margin-bottom:1.25rem"><?= htmlspecialchars($formHeading) ?></p>
    <?php endif; ?>

    <!-- Contact -->
    <div class="form3">
      <div><label class="fp-label-dark"><?= htmlspecialchars($fieldNameLabel) ?></label><input type="text" x-model="form.name" class="fp-input-dark" placeholder="<?= htmlspecialchars($fieldNamePlaceholder) ?>" required autocomplete="name"></div>
      <div><label class="fp-label-dark"><?= htmlspecialchars($fieldEmailLabel) ?></label><input type="email" x-model="form.email" class="fp-input-dark" placeholder="<?= htmlspecialchars($fieldEmailPlaceholder) ?>" required autocomplete="email"></div>
      <div><label class="fp-label-dark"><?= htmlspecialchars($fieldPhoneLabel) ?></label><input type="tel" x-model="form.phone" class="fp-input-dark" placeholder="<?= htmlspecialchars($fieldPhonePlaceholder) ?>" required autocomplete="tel"></div>
    </div>

    <div class="divider-dark"></div>

    <!-- Single upload -->
    <div>
      <label class="fp-label-dark" style="margin-bottom:.5rem"><?= htmlspecialchars($fieldVideoLabel) ?></label>
      <div class="uzone" :class="[drag?'drag':'',videoFile?'has-file':'']" @click="$refs.wv.click()" @dragover.prevent="drag=true" @dragleave="drag=false" @drop.prevent="onDrop($event)">
        <input type="file" x-ref="wv" name="writer_video" style="display:none" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo,video/mpeg" @change="videoFile=$event.target.files[0]">
        <svg style="width:24px;height:24px;color:#9ca3af;margin:0 auto .5rem;display:block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        <p style="color:#6b7280;font-size:.8rem">Drop or <strong>browse</strong> <?= htmlspecialchars($fieldVideoHint) ?></p>
        <p style="color:#9ca3af;font-size:.7rem;margin-top:.2rem">MP4 · MOV · WEBM · max 500 MB</p>
        <p x-show="videoFile" x-text="'✓ '+(videoFile?videoFile.name:'')" style="display:none;color:#16a34a;font-size:.75rem;font-weight:600;margin-top:.4rem"></p>
      </div>
    </div>

    <!-- Progress -->
    <div x-show="loading" style="display:none;margin-top:.75rem">
      <div style="display:flex;justify-content:space-between;font-size:.7rem;color:#9ca3af;margin-bottom:.25rem"><span>Uploading video…</span><span x-text="progress+'%'"></span></div>
      <div class="prog-bar"><div class="prog-fill" :style="'width:'+progress+'%'"></div></div>
    </div>

    <!-- Terms & Conditions Checkbox -->
    <div class="terms-box">
      <label class="terms-label">
        <input type="checkbox" x-model="termsAccepted">
        <span>By clicking submit I agree to all terms and conditions</span>
      </label>
      <a href="/legal" target="_blank" class="terms-link">
        <svg style="width:16px;height:16px" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        Click here to <span style="color:#111;font-weight:600">Read terms</span>
      </a>
    </div>

    <!-- Errors -->
    <div x-show="errors.length" style="display:none" class="err-dark">
      <ul style="list-style:none"><template x-for="e in errors" :key="e"><li x-text="'• '+e"></li></template></ul>
    </div>

    <!-- Submit -->
    <button type="button" class="btn-go" @click="submit()" :disabled="loading">
      Submit Writer Narration →
    </button>
  </div><!-- /submit-card -->
</div><!-- /side-by-side grid -->

<!-- FOOTER -->
<?php require_once __DIR__ . '/partials/footer-frontend.php'; ?>

<script>
function writerSubmit(){
    return{
        videoFile:null,
        loading:false,progress:0,errors:[],
        drag:false,
        termsAccepted:false,
        form:{name:'',email:'',phone:''},
        onDrop(e){this.drag=false;var f=e.dataTransfer.files[0];if(f)this.videoFile=f;},
        submit(){
            this.errors=[];
            if(!this.form.name.trim()) this.errors.push('Name is required.');
            if(!this.form.email.trim()) this.errors.push('Email is required.');
            if(!this.form.phone.trim()) this.errors.push('Phone is required.');
            if(!this.videoFile) this.errors.push('Narration video is required.');
            if(!this.termsAccepted) this.errors.push('You must accept the terms and conditions.');
            if(this.errors.length) return;
            this.loading=true; this.progress=0;
            var fd=new FormData();
            fd.append('role','writer');
            fd.append('audition_type','Script Continuation');
            fd.append('name',this.form.name);
            fd.append('email',this.form.email);
            fd.append('phone',this.form.phone);
            fd.append('writer_video',this.videoFile);
            var self=this;
            var xhr=new XMLHttpRequest();
            xhr.upload.onprogress=function(e){if(e.lengthComputable)self.progress=Math.round(e.loaded/e.total*100);};
            xhr.onload=function(){
                self.loading=false;
                try{
                    var r=JSON.parse(xhr.responseText);
                    if(xhr.status>=200&&xhr.status<300&&r.success){
                        self.form={name:'',email:'',phone:''};
                        self.videoFile=null;self.errors=[];
                        if(typeof showSuccessModal==='function') showSuccessModal(r);
                    }else{self.errors=r.errors||[r.error||'Submission failed.'];}
                }catch(err){self.errors=['Server error. Please try again.'];}
            };
            xhr.onerror=function(){self.loading=false;self.errors=['Network error.'];};
            xhr.open('POST','/api/submit');
            xhr.send(fd);
        }
    };
}
</script>
<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
<?php include __DIR__ . '/partials/language-switcher.php'; ?>
</body>
</html>


<!-- Inject role-specific submission messages -->
<script>
window._submissionMessages = {
    writer_success_heading: <?= json_encode($settingsModel->get('writer_success_heading', 'WRITER SUBMISSION RECEIVED!')) ?>,
    writer_success_message: <?= json_encode($settingsModel->get('writer_success_message', "Your writer video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at your email.")) ?>,
    writer_success_pdf_button: <?= json_encode($settingsModel->get('writer_success_pdf_button', 'Download Writer Brief PDF')) ?>,
    writer_failure_heading: <?= json_encode($settingsModel->get('writer_failure_heading', 'SUBMISSION FAILED')) ?>,
    writer_failure_message: <?= json_encode($settingsModel->get('writer_failure_message', "We couldn't process your writer video. Please check your file and try again.")) ?>,
    writer_failure_retry_button: <?= json_encode($settingsModel->get('writer_failure_retry_button', 'Try Again')) ?>
};
</script>

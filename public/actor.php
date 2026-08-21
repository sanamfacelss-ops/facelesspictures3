<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/settings_helper.php';

$scriptModel = new App\Models\Script();

$logoUrl = setting('site_logo_url', '');

// Load all actor scripts (dialog + song)
$actorScripts = $scriptModel->byCategory('actor');

// Fallback global settings (used only if no scripts uploaded yet)
$globalDialogBrief = setting('actor_dialog_script', 'Perform the following scene with full emotion.');
$globalSongBrief   = setting('actor_song_script', 'Perform a 60-second song showing emotional range.');

// Split scripts by audition_type: dialog vs song
$dialogScripts = array_values(array_filter($actorScripts, fn($s) => stripos($s['audition_type'] ?? '', 'song') === false));
$songScripts   = array_values(array_filter($actorScripts, fn($s) => stripos($s['audition_type'] ?? '', 'song') !== false));

// If no song scripts, put all in dialog
if (empty($songScripts) && !empty($dialogScripts)) {
    // keep as is — two columns both dialog if needed
}

$pageTitle = 'Actor Auditions — Faceless Pictures 3';

// Page text settings
$heroLabel       = setting('actor_hero_label', 'Auditions Now Open');
$heroHeading     = setting('actor_hero_heading', 'ACTOR AUDITIONS');
$heroDescription = setting('actor_hero_description', 'Two auditions, one submission. Read the dialog brief, learn the song, then shoot both videos.');
$formHeading     = setting('actor_form_heading', 'Ready to Perform? Submit Your Auditions');
$formDescription = setting('actor_form_description', 'Shoot your dialog scene and song audition, then upload both videos below.');

// Film Song card text (admin-editable)
$filmSongHeading  = setting('film_song_heading',  'FILM SONG');
$filmSongSubtitle = setting('film_song_subtitle', 'Listen to the song before you record your audition');
$filmSongBtnLabel = setting('film_song_btn_label', 'Get Song');
if (empty($filmSongHeading))  $filmSongHeading  = 'FILM SONG';
if (empty($filmSongSubtitle)) $filmSongSubtitle = 'Listen to the song before you record your audition';
if (empty($filmSongBtnLabel)) $filmSongBtnLabel = 'Get Song';

// Collect all tune URLs across all song scripts for the Film Song card
$allTuneUrls = [];
foreach ($songScripts as $sc) {
    $raw = $sc['tune_youtube_url'] ?? '';
    foreach (array_filter(array_map('trim', explode("\n", $raw))) as $line) {
        $sep = strpos($line, '|');
        if ($sep !== false) {
            $allTuneUrls[] = ['label' => trim(substr($line, 0, $sep)), 'url' => trim(substr($line, $sep + 1))];
        } elseif ($line) {
            $allTuneUrls[] = ['label' => '', 'url' => $line];
        }
    }
}
$allTuneUrls = array_values(array_filter($allTuneUrls, fn($t) => !empty($t['url'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
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
.brief-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:1280px;margin:0 auto;padding:0 1.5rem 1.75rem}
.brief-grid>.brief-card:last-child:nth-child(odd){grid-column:1/-1}
@media(max-width:768px){.brief-grid{grid-template-columns:1fr;padding:0 1rem 1.5rem}
.brief-grid>.brief-card:last-child:nth-child(odd){grid-column:auto}}
.brief-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);overflow:hidden;display:flex;flex-direction:column}
.card-sec{padding:1rem 1.125rem;border-bottom:1px solid #f0f0f0}
.card-sec:last-child{border-bottom:none}
.card-sec.tinted{background:#f9fafb}
/* Mobile/tablet: shrink manifesto heading */
@media(max-width:900px){
  .brief-card-header p:first-child{font-size:1.45rem !important}
  .brief-card-header p:last-child{font-size:.8rem !important}
}
.sec-label{display:flex;align-items:center;gap:.45rem;font-size:.6rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#9ca3af;margin-bottom:.625rem}
.sec-label::before{content:'';display:inline-block;width:3px;height:10px;border-radius:2px;background:#111;flex-shrink:0}
.preview-video{width:100%;display:block;background:#000}
/* 9:16 media container — for portrait/vertical videos */
.media-9-16{width:100%;overflow:hidden;background:#000;aspect-ratio:9/16;position:relative}
/* 16:9 media container — for landscape YouTube embeds */
.media-16-9{width:100%;overflow:hidden;background:#000;aspect-ratio:16/9;position:relative}
/* local video: contain — letterbox if not exactly 9:16 */
.media-9-16 video{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;display:block;border:0;background:#000}
/* YouTube iframe: fills the box */
.media-9-16 iframe,.media-16-9 iframe{position:absolute;inset:0;width:100%;height:100%;border:0;display:block}
/* image: cover fill — poster is always 9:16 */
.media-9-16 img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;border:0}
/* placeholder inside 9:16 box */
.media-9-16.placeholder-bg,.media-16-9.placeholder-bg{background:#1a1a2e;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:rgba(255,255,255,.3);font-size:.72rem;letter-spacing:.06em;text-transform:uppercase}/* Overlapping audition type badge */
.media-badge{position:absolute;top:.75rem;left:.75rem;z-index:4;background:rgba(0,0,0,.72);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:#fff;font-size:.58rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;padding:.3rem .65rem;border-radius:20px;border:1px solid rgba(255,255,255,.18);pointer-events:none}
/* Custom video player for local files */
.pv-wrap{position:relative;width:100%;overflow:hidden}
.pv-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);transition:background .2s;z-index:2}
.pv-wrap:hover .pv-overlay{background:rgba(0,0,0,.38)}
.pv-play{width:60px;height:60px;background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.5);border-radius:50%;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(6px);transition:transform .2s,background .2s}
.pv-wrap:hover .pv-play{transform:scale(1.08);background:rgba(255,255,255,.28)}
.pv-play svg{width:26px;height:26px;color:#fff;margin-left:4px}
.pv-playing .pv-overlay{opacity:0}
.pv-playing:hover .pv-overlay{opacity:1}
.pv-bar{position:absolute;bottom:0;left:0;right:0;padding:.5rem .75rem .6rem;background:linear-gradient(to top,rgba(0,0,0,.75),transparent);display:flex;flex-direction:column;gap:.35rem;opacity:0;transition:opacity .2s;z-index:3}
.pv-wrap:hover .pv-bar{opacity:1}
.pv-progress{height:3px;background:rgba(255,255,255,.25);border-radius:2px;cursor:pointer;position:relative}
.pv-progress:hover{height:5px}
.pv-played{height:100%;background:#fff;border-radius:2px;pointer-events:none}
.pv-row{display:flex;align-items:center;gap:.5rem}
.pv-btn{background:none;border:none;cursor:pointer;color:#fff;opacity:.85;padding:2px;display:flex;align-items:center;transition:opacity .15s}
.pv-btn:hover{opacity:1}
.pv-btn svg{width:18px;height:18px}
.pv-time{font-size:.65rem;color:rgba(255,255,255,.7);font-variant-numeric:tabular-nums;white-space:nowrap;margin-left:auto}
/* 9:16 script image — natural height, no padding, image drives the size */
.portrait-img-wrap{width:100%;background:#fff;cursor:zoom-in;display:block;line-height:0;position:relative}
.portrait-img-wrap img{width:100%;height:auto;display:block;background:#fff}
.portrait-img-wrap::after{content:'🔍  Tap to zoom';position:absolute;bottom:.6rem;right:.6rem;background:rgba(0,0,0,.55);color:#fff;font-size:.65rem;padding:.2rem .5rem;border-radius:5px;pointer-events:none;opacity:0;transition:opacity .2s}
.portrait-img-wrap:hover::after{opacity:1}
/* Image Lightbox */
#imgLightbox{display:none;position:fixed;inset:0;z-index:300;background:rgba(0,0,0,.95);align-items:center;justify-content:center;touch-action:none}
#imgLightbox.open{display:flex}
#imgLightbox .lb-close{position:absolute;top:1rem;right:1rem;width:40px;height:40px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:50%;color:#fff;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;transition:background .2s}
#imgLightbox .lb-close:hover{background:rgba(255,255,255,.24)}
#imgLightbox .lb-hint{position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);font-size:.65rem;color:rgba(255,255,255,.4);letter-spacing:.06em;text-transform:uppercase;pointer-events:none;white-space:nowrap}
#lb-img-wrap{position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:grab}
#lb-img-wrap.dragging{cursor:grabbing}
#lb-img{max-width:100%;max-height:100%;display:block;transform-origin:center center;transition:transform .15s ease;user-select:none;-webkit-user-drag:none;pointer-events:none}
.portrait-placeholder{width:100%;height:180px;background:#f3f4f6;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#9ca3af;font-size:.72rem}
.btn-outline{display:flex;align-items:center;justify-content:center;gap:.5rem;background:#fff;border:2px solid #111;color:#111;border-radius:9px;padding:.7rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;transition:background .15s,color .15s;white-space:nowrap;flex:1;min-width:0}
.btn-outline:hover{background:#111;color:#fff}
.btn-outline.disabled{opacity:.4;pointer-events:none;cursor:not-allowed;border-color:#d1d5db;color:#9ca3af}
.btn-tune{display:flex;align-items:center;justify-content:center;gap:.5rem;background:#FF0000;color:#fff;border:none;border-radius:9px;padding:.7rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;white-space:nowrap;flex:1;min-width:0}
.btn-tune:hover{background:#cc0000}
.btn-tune:disabled{opacity:.4;cursor:not-allowed}
.btn-row{display:flex;flex-direction:column;gap:.625rem}
.btn-row-split{display:flex;gap:.625rem}
.rule-row{display:flex;align-items:flex-start;gap:.4rem;font-size:.78rem;color:#374151;line-height:1.55;padding:.15rem 0}
.rule-dot{width:3px;height:3px;border-radius:50%;background:#9ca3af;flex-shrink:0;margin-top:.55rem}
#tuneModal{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.9);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:1.5rem}
#tuneModal.open{display:flex}
.tune-box{background:#161C2D;border:1px solid #1F2840;border-radius:16px;width:100%;max-width:720px;padding:1.25rem;position:relative}
.tune-close{position:absolute;top:.75rem;right:.75rem;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:50%;width:32px;height:32px;color:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.tune-close:hover{background:rgba(255,255,255,.18)}
.tune-wrap{position:relative;width:100%;padding-bottom:56.25%;margin-top:.5rem;border-radius:8px;overflow:hidden;background:#000}
.tune-wrap iframe,.tune-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:0}
.submit-card{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.05);max-width:1280px;margin:0 auto 5rem;padding:2.25rem 2rem}
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

<?php 
// Get the header height from nav-frontend.php calculation
$headerHeight = (int)setting('site_logo_height', '44') + 16;
?>

<?php require_once __DIR__ . '/partials/nav-frontend.php'; ?>

<!-- HERO -->
<section style="padding:<?= $headerHeight + 8 ?>px 1.5rem 1rem;text-align:center" class="fade-up">
  <?php if (!empty($heroLabel)): ?>
  <p style="font-size:.63rem;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9ca3af;margin-bottom:.4rem"><?= htmlspecialchars($heroLabel) ?></p>
  <?php endif; ?>
  <?php if (!empty($heroHeading)): ?>
  <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(28px,4vw,40px);letter-spacing:.04em;line-height:1;color:#111;margin-bottom:.5rem"><?= htmlspecialchars($heroHeading) ?></h1>
  <?php endif; ?>
  <?php if (!empty($heroDescription)): ?>
  <p style="color:#6b7280;font-size:.85rem;max-width:420px;margin:0 auto;line-height:1.55"><?= nl2br(htmlspecialchars($heroDescription)) ?></p>
  <?php endif; ?>
</section>

<!-- TWO BRIEF CARDS (Dialog + Song) -->
<div class="brief-grid">

<?php
// Helper to render a script brief card
function isYouTubeUrl(string $url): bool {
    return (bool)preg_match('/youtu(\.be|be\.com)/i', $url);
}

function getYouTubeEmbed(string $url): string {
    if (preg_match('/youtu\.be\/([^?&#]+)/', $url, $m)) return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('/[?&]v=([^&#]+)/', $url, $m))       return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('/\/shorts\/([^?&#]+)/', $url, $m))  return 'https://www.youtube.com/embed/' . $m[1];
    return $url;
}

function renderActorBriefCard(array $sc, string $fallbackBrief, bool $isSong = false): void {
    $previewUrl  = $sc['preview_video_url'] ?? '';
    $imageUrl    = $sc['image_url']         ?? '';
    $pdfUrl      = $sc['script_pdf_url']    ?? '';
    $tuneRaw     = $sc['tune_youtube_url']  ?? '';
    $title       = htmlspecialchars($sc['title']);
    $audType     = htmlspecialchars($sc['audition_type'] ?? ($isSong ? 'Song Audition' : 'Dialog Audition'));
    $brief       = htmlspecialchars($sc['content'] ?: $fallbackBrief);
    $rulesRaw    = $sc['rules'] ?? "Video under 3 minutes\nFace must not be visible\nClear audio required";
    $ruleList    = array_filter(array_map('trim', explode("\n", $rulesRaw)));
    $dataId      = (int)$sc['id'];
    $isYT        = $previewUrl && isYouTubeUrl($previewUrl);
    $embedUrl    = $isYT ? getYouTubeEmbed($previewUrl) : '';
    $uid         = 'pv_' . $dataId . '_' . ($isSong ? 's' : 'd');

    // Multiple tune entries — each line is "label|url" or just "url"
    $tuneUrls = [];
    if ($isSong && !empty($tuneRaw)) {
        foreach (array_filter(array_map('trim', explode("\n", $tuneRaw))) as $line) {
            $sep = strpos($line, '|');
            if ($sep !== false) {
                $tuneUrls[] = ['label' => trim(substr($line, 0, $sep)), 'url' => trim(substr($line, $sep + 1))];
            } else {
                $tuneUrls[] = ['label' => '', 'url' => $line];
            }
        }
    }

    // Card heading from script title, subheading from script content (brief)
    $cardHeading    = !empty($sc['title'])   ? strtoupper($sc['title'])   : ($isSong ? 'SONG AUDITION'     : 'DIALOGUE AUDITION');
    $cardSubheading = !empty($sc['content']) ? $sc['content']             : ($isSong ? $fallbackBrief       : $fallbackBrief);
?>
  <div class="brief-card">
    <!-- Card heading + subheading — both admin-editable via Scripts tab -->
    <div class="card-sec brief-card-header" style="background:#f3f4f6;border-bottom:1px solid #e5e7eb;padding:1.25rem 1.25rem 1rem;text-align:center">
      <p style="font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.08em;color:#111;line-height:1;margin-bottom:.45rem"><?= htmlspecialchars($cardHeading) ?></p>
      <p style="font-size:.85rem;font-weight:500;color:#6b7280;line-height:1.5"><?= htmlspecialchars($cardSubheading) ?></p>
    </div>
    <div class="card-sec" style="padding:0 0 1.5rem">
      <?php if ($previewUrl && $isYT): ?>
        <div class="media-16-9">
          <iframe src="<?= htmlspecialchars($embedUrl) ?>?rel=0&modestbranding=1"
            allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
            allowfullscreen title="<?= $title ?> preview"></iframe>
        </div>
      <?php elseif ($previewUrl): ?>
        <div class="media-9-16 pv-wrap" id="<?= $uid ?>" onclick="pvToggle('<?= $uid ?>')" style="cursor:pointer">
          <video id="<?= $uid ?>_v" preload="metadata" playsinline
            ontimeupdate="pvTimeUpdate('<?= $uid ?>')"
            onended="pvEnded('<?= $uid ?>')"
            onloadedmetadata="pvMeta('<?= $uid ?>')">
            <source src="<?= htmlspecialchars($previewUrl) ?>" type="video/mp4">
          </video>
          <div class="pv-overlay">
            <div class="pv-play" id="<?= $uid ?>_btn">
              <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
          <div class="pv-bar">
            <div class="pv-progress" id="<?= $uid ?>_prog" onclick="pvSeek(event,'<?= $uid ?>')">
              <div class="pv-played" id="<?= $uid ?>_played" style="width:0%"></div>
            </div>
            <div class="pv-row">
              <button class="pv-btn" onclick="pvToggle('<?= $uid ?>');event.stopPropagation()">
                <svg id="<?= $uid ?>_icon_play" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <svg id="<?= $uid ?>_icon_pause" fill="currentColor" viewBox="0 0 24 24" style="display:none"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
              </button>
              <button class="pv-btn" onclick="pvToggleMute('<?= $uid ?>');event.stopPropagation()" title="Mute/Unmute">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/></svg>
              </button>
              <span class="pv-time" id="<?= $uid ?>_time">0:00 / 0:00</span>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="media-9-16 placeholder-bg">
          <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          Preview video coming soon
        </div>
      <?php endif; ?>
    </div>
    <?php if ($imageUrl): ?>
    <div class="card-sec" style="padding:1.25rem 0 0;border-top:1px solid #e5e7eb;background:#fff">
      <div class="portrait-img-wrap" onclick="openImgLightbox('<?= addslashes(htmlspecialchars($imageUrl)) ?>')">
        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= $isSong ? 'Song lyrics' : 'Dialog script' ?>">
      </div>
    </div>
    <?php endif; ?>
    <div class="card-sec">
      <div class="sec-label"><?= $isSong ? 'Lyrics' : 'Script' ?></div>
      <div class="btn-row">
        <?php if ($isSong): ?>
          <?php if ($pdfUrl): ?>
            <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener" class="btn-outline" style="width:100%">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Download Lyrics PDF
            </a>
          <?php else: ?>
            <span class="btn-outline disabled" style="width:100%">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Lyrics PDF not available yet
            </span>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($pdfUrl): ?>
            <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener" class="btn-outline">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Download Script PDF
            </a>
          <?php else: ?>
            <span class="btn-outline disabled">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Script PDF not available yet
            </span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-sec tinted" style="flex:1"></div>
  </div>
<?php
}

// Render dialog scripts — only if scripts exist
if (!empty($dialogScripts)) {
    foreach ($dialogScripts as $sc) renderActorBriefCard($sc, $globalDialogBrief, false);
}

// Render song scripts — only if scripts exist
if (!empty($songScripts)) {
    foreach ($songScripts as $sc) renderActorBriefCard($sc, $globalSongBrief, true);
}
?>

</div><!-- /brief-grid -->

<?php if (!empty($allTuneUrls)): ?>
<?php $filmSongJson = htmlspecialchars(json_encode($allTuneUrls), ENT_QUOTES); ?>
<!-- FILM SONG CARD -->
<style>
.film-song-inner{background:#111;border-radius:14px;padding:1.5rem 1.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.film-song-btn{display:flex;align-items:center;justify-content:center;gap:.55rem;background:#fff;color:#111;border:none;border-radius:9px;padding:.75rem 2.5rem;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;transition:background .15s;flex-shrink:0;min-width:200px}
.film-song-btn:hover{background:#e5e7eb}
@media(max-width:640px){
  .film-song-inner{flex-direction:column;align-items:center;text-align:center;padding:1.25rem 1.25rem}
  .film-song-btn{width:100%;min-width:0;padding:.85rem 1rem}
}
</style>
<div style="max-width:1280px;margin:0 auto;padding:0 1.5rem 1.75rem">
  <div class="film-song-inner">
    <div>
      <p style="font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.08em;color:#fff;line-height:1;margin-bottom:.3rem"><?= htmlspecialchars($filmSongHeading) ?></p>
      <p style="font-size:.8rem;color:rgba(255,255,255,.5);line-height:1.45"><?= htmlspecialchars($filmSongSubtitle) ?></p>
    </div>
    <button type="button" class="film-song-btn"
      onclick="openSongSlider(<?= $filmSongJson ?>)">
      <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      <?= htmlspecialchars($filmSongBtnLabel) ?>
    </button>
  </div>
</div>
<?php endif; ?>

<!-- SUBMISSION CARD (full width, dark) -->
<div style="max-width:1280px;margin:0 auto;padding:0 1.5rem 5rem">
  <div class="submit-card" id="submit-form" x-data="actorSubmit()">
    <?php if (!empty($formHeading)): ?>
    <p style="font-family:'Bebas Neue',sans-serif;font-size:clamp(22px,3.5vw,32px);letter-spacing:.04em;color:#111;margin-bottom:.3rem"><?= htmlspecialchars($formHeading) ?></p>
    <?php endif; ?>
    <?php if (!empty($formDescription)): ?>
    <p style="font-size:.85rem;color:#6b7280;margin-bottom:1.5rem;line-height:1.55"><?= nl2br(htmlspecialchars($formDescription)) ?></p>
    <?php endif; ?>

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
<footer style="border-top:1px solid #e5e7eb;padding:1.75rem 1.5rem;background:#f3f4f6">
  <div style="max-width:1280px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
    <a href="/" style="display:flex;align-items:center;gap:6px;text-decoration:none">
      <?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl) ?>" style="height:44px;width:auto">
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

<!-- SONG SLIDER MODAL -->
<div id="tuneModal">
  <div class="tune-box">
    <button class="tune-close" onclick="closeTuneModal()" aria-label="Close">✕</button>

    <!-- Header -->
    <p style="font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:.06em;color:#fff;margin-bottom:.85rem">
      <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px"><path d="M8 5v14l11-7z"/></svg>
      Choose &amp; Play Song
    </p>

    <!-- Song tabs / slider -->
    <div id="songTabs" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.85rem"></div>

    <!-- Player -->
    <div class="tune-wrap">
      <iframe id="tuneIframe" src="" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen title="Song tune"></iframe>
    </div>

    <!-- Song title label -->
    <p id="songLabel" style="margin-top:.65rem;font-size:.78rem;color:rgba(255,255,255,.55);text-align:center;min-height:1.1em"></p>
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

// ── Inline preview player helpers ──
function pvFmt(s){if(!s||isNaN(s))return'0:00';var m=Math.floor(s/60);return m+':'+(Math.floor(s%60)+'').padStart(2,'0');}
function pvToggle(id){
    var v=document.getElementById(id+'_v');if(!v)return;
    var w=document.getElementById(id);
    if(v.paused){v.play();w.classList.add('pv-playing');}
    else{v.pause();w.classList.remove('pv-playing');}
    document.getElementById(id+'_icon_play').style.display=v.paused?'':'none';
    document.getElementById(id+'_icon_pause').style.display=v.paused?'none':'';
}
function pvEnded(id){
    var w=document.getElementById(id);w&&w.classList.remove('pv-playing');
    var ip=document.getElementById(id+'_icon_play');var ipa=document.getElementById(id+'_icon_pause');
    if(ip)ip.style.display='';if(ipa)ipa.style.display='none';
}
function pvTimeUpdate(id){
    var v=document.getElementById(id+'_v');if(!v||!v.duration)return;
    var pct=(v.currentTime/v.duration*100).toFixed(1)+'%';
    var p=document.getElementById(id+'_played');if(p)p.style.width=pct;
    var t=document.getElementById(id+'_time');if(t)t.textContent=pvFmt(v.currentTime)+' / '+pvFmt(v.duration);
}
function pvMeta(id){
    var v=document.getElementById(id+'_v');
    var t=document.getElementById(id+'_time');if(t&&v)t.textContent='0:00 / '+pvFmt(v.duration);
}
function pvSeek(e,id){
    e.stopPropagation();
    var v=document.getElementById(id+'_v');if(!v)return;
    var r=document.getElementById(id+'_prog').getBoundingClientRect();
    var p=Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));
    v.currentTime=p*v.duration;
}
function pvToggleMute(id){
    var v=document.getElementById(id+'_v');if(v)v.muted=!v.muted;
}
function openTuneModal(url){
    // legacy single-url call → wrap in array
    openSongSlider([{label:'',url:url}]);
}
var _songSliderTunes = [];
var _songSliderActive = -1;
function openSongSlider(tunes){
    _songSliderTunes = tunes.filter(function(t){return t.url;});
    if(!_songSliderTunes.length) return;
    // Build tab buttons
    var tabsEl = document.getElementById('songTabs');
    tabsEl.innerHTML = '';
    _songSliderTunes.forEach(function(t, i){
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = t.label || ('Song '+(i+1));
        btn.style.cssText = 'background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:20px;padding:.32rem .85rem;font-size:.72rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s,border-color .15s;white-space:nowrap';
        btn.dataset.idx = i;
        btn.addEventListener('click', function(){ songSliderPlay(i); });
        tabsEl.appendChild(btn);
    });
    // auto-play first
    songSliderPlay(0);
    document.getElementById('tuneModal').style.display='flex';
    document.body.style.overflow='hidden';
}
function songSliderPlay(idx){
    var tunes = _songSliderTunes;
    if(!tunes[idx]) return;
    _songSliderActive = idx;
    // update iframe
    var e = _embedUrl(tunes[idx].url);
    document.getElementById('tuneIframe').src = e ? e+'?autoplay=1' : '';
    // label
    document.getElementById('songLabel').textContent = tunes[idx].label || '';
    // highlight active tab
    var tabs = document.getElementById('songTabs').querySelectorAll('button');
    tabs.forEach(function(b, i){
        if(i===idx){
            b.style.background='#FF0000';
            b.style.borderColor='#FF0000';
        } else {
            b.style.background='rgba(255,255,255,.1)';
            b.style.borderColor='rgba(255,255,255,.2)';
        }
    });
}
function closeTuneModal(){
    document.getElementById('tuneIframe').src='';
    document.getElementById('tuneModal').style.display='none';
    document.body.style.overflow='';
    _songSliderActive = -1;
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
<!-- IMAGE LIGHTBOX -->
<div id="imgLightbox" role="dialog" aria-modal="true" aria-label="Script image viewer">
  <button class="lb-close" onclick="closeImgLightbox()" aria-label="Close">✕</button>
  <div id="lb-img-wrap">
    <img id="lb-img" src="" alt="Script image">
  </div>
  <p class="lb-hint">Pinch or scroll to zoom · Drag to pan · Esc to close</p>
</div>

<script>
// ── Image Lightbox with pinch-zoom (mobile) + scroll-zoom (desktop) + drag-to-pan ──
(function(){
  var lb=document.getElementById('imgLightbox');
  var wrap=document.getElementById('lb-img-wrap');
  var img=document.getElementById('lb-img');
  var scale=1, minScale=1, maxScale=5;
  var tx=0, ty=0;
  var dragging=false, lastX=0, lastY=0;
  // Pinch state
  var initDist=0, initScale=1;
  var initMidX=0, initMidY=0;
  var initTx=0, initTy=0;

  window.openImgLightbox = function(src) {
    img.src = src;
    scale=1; tx=0; ty=0;
    applyTransform();
    lb.classList.add('open');
    document.body.style.overflow='hidden';
    img.onload = function(){ minScale=1; };
  };
  window.closeImgLightbox = function() {
    lb.classList.remove('open');
    document.body.style.overflow='';
    img.src='';
  };

  document.addEventListener('keydown', function(e){ if(e.key==='Escape') window.closeImgLightbox(); });
  lb.addEventListener('click', function(e){ if(e.target===lb||e.target===wrap) window.closeImgLightbox(); });

  function applyTransform() {
    img.style.transform = 'translate('+tx+'px,'+ty+'px) scale('+scale+')';
  }
  function clampPan() {
    var r = wrap.getBoundingClientRect();
    var iw = img.naturalWidth || img.offsetWidth;
    var ih = img.naturalHeight || img.offsetHeight;
    var sw = iw * scale; var sh = ih * scale;
    var maxTx = Math.max(0, (sw - r.width) / 2);
    var maxTy = Math.max(0, (sh - r.height) / 2);
    tx = Math.max(-maxTx, Math.min(maxTx, tx));
    ty = Math.max(-maxTy, Math.min(maxTy, ty));
  }

  // Desktop scroll-to-zoom
  wrap.addEventListener('wheel', function(e) {
    e.preventDefault();
    var delta = e.deltaY > 0 ? -0.15 : 0.15;
    scale = Math.max(minScale, Math.min(maxScale, scale + delta));
    if (scale === minScale) { tx=0; ty=0; }
    clampPan();
    applyTransform();
  }, { passive: false });

  // Desktop drag-to-pan
  wrap.addEventListener('mousedown', function(e) {
    if (scale <= 1) return;
    dragging=true; lastX=e.clientX; lastY=e.clientY;
    wrap.classList.add('dragging');
    e.preventDefault();
  });
  document.addEventListener('mousemove', function(e) {
    if (!dragging) return;
    tx += e.clientX - lastX; ty += e.clientY - lastY;
    lastX=e.clientX; lastY=e.clientY;
    clampPan(); applyTransform();
  });
  document.addEventListener('mouseup', function() {
    dragging=false; wrap.classList.remove('dragging');
  });

  // Mobile pinch-to-zoom + drag-to-pan
  wrap.addEventListener('touchstart', function(e) {
    if (e.touches.length === 2) {
      // Pinch start
      e.preventDefault();
      var t1=e.touches[0], t2=e.touches[1];
      initDist = Math.hypot(t2.clientX-t1.clientX, t2.clientY-t1.clientY);
      initScale = scale;
      initMidX = (t1.clientX+t2.clientX)/2;
      initMidY = (t1.clientY+t2.clientY)/2;
      initTx = tx; initTy = ty;
    } else if (e.touches.length === 1 && scale > 1) {
      // Pan start
      dragging=true; lastX=e.touches[0].clientX; lastY=e.touches[0].clientY;
    }
  }, { passive: false });

  wrap.addEventListener('touchmove', function(e) {
    e.preventDefault();
    if (e.touches.length === 2) {
      var t1=e.touches[0], t2=e.touches[1];
      var dist = Math.hypot(t2.clientX-t1.clientX, t2.clientY-t1.clientY);
      scale = Math.max(minScale, Math.min(maxScale, initScale * (dist / initDist)));
      if (scale === minScale) { tx=0; ty=0; }
      clampPan(); applyTransform();
    } else if (e.touches.length === 1 && dragging) {
      tx += e.touches[0].clientX - lastX;
      ty += e.touches[0].clientY - lastY;
      lastX=e.touches[0].clientX; lastY=e.touches[0].clientY;
      clampPan(); applyTransform();
    }
  }, { passive: false });

  wrap.addEventListener('touchend', function(e) {
    if (e.touches.length < 2) dragging = false;
  });

  // Double-tap to zoom in/out on mobile
  var lastTap = 0;
  wrap.addEventListener('touchend', function(e) {
    var now = Date.now();
    if (now - lastTap < 300) {
      scale = scale > 1 ? minScale : 2.5;
      if (scale === minScale) { tx=0; ty=0; }
      clampPan(); applyTransform();
    }
    lastTap = now;
  });
})();
</script>

<?php require_once __DIR__ . '/partials/submission-shared.php'; ?>
<?php include __DIR__ . '/partials/language-switcher.php'; ?>
</body>
</html>


<!-- Inject role-specific submission messages -->
<script>
window._submissionMessages = {
    actor_success_heading: <?= json_encode($settingsModel->get('actor_success_heading', 'ACTOR SUBMISSION RECEIVED!')) ?>,
    actor_success_message: <?= json_encode($settingsModel->get('actor_success_message', "Your acting video is in the queue for AI review and will be published to YouTube once approved. We'll be in touch at your email.")) ?>,
    actor_success_pdf_button: <?= json_encode($settingsModel->get('actor_success_pdf_button', 'Download Actor Brief PDF')) ?>,
    actor_failure_heading: <?= json_encode($settingsModel->get('actor_failure_heading', 'SUBMISSION FAILED')) ?>,
    actor_failure_message: <?= json_encode($settingsModel->get('actor_failure_message', "We couldn't process your acting video. Please check your file and try again.")) ?>,
    actor_failure_retry_button: <?= json_encode($settingsModel->get('actor_failure_retry_button', 'Try Again')) ?>
};
</script>

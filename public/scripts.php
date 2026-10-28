<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/settings_helper.php';

$logoUrl   = setting('site_logo_url', '');
$pageTitle = 'Scripts — Faceless Pictures 3';

// Hero (blank fields are hidden)
$heroHeading = trim(setting('scripts_page_heading', 'SCRIPTS'));
$heroTagline = trim(setting('scripts_page_tagline', ''));
$heroImage   = trim(setting('scripts_page_hero_image', ''));
$taglineItems = array_values(array_filter(array_map('trim', explode('$', $heroTagline)), fn($t) => $t !== ''));

// Cards — stored under doctrine_{actor|director|writer}_* (card 1, 2, 3)
$cards = [];
foreach (['actor', 'director', 'writer'] as $key) {
    $title = trim(setting("doctrine_{$key}_title", ''));
    $desc  = trim(setting("doctrine_{$key}_description", ''));
    $price = trim(setting("doctrine_{$key}_price", ''));
    if ($title === '' && $desc === '' && $price === '') continue; // empty card → hidden

    $align = setting("doctrine_{$key}_align", 'center');
    if (!in_array($align, ['left', 'center', 'right'], true)) $align = 'center';

    // Description blocks: "- " / "• " / "* " lines become bullets, blank line starts a new paragraph,
    // other lines stay on their own line inside the paragraph.
    $blocks = [];
    foreach (preg_split('/\r\n|\r|\n/', $desc) as $line) {
        $line = trim($line);
        if ($line === '') { $blocks[] = ['type' => 'break']; continue; }
        $last = count($blocks) - 1;
        if (preg_match('/^(?:[-•*])\s*(.+)$/u', $line, $mm)) {
            if ($last >= 0 && $blocks[$last]['type'] === 'ul') $blocks[$last]['items'][] = $mm[1];
            else $blocks[] = ['type' => 'ul', 'items' => [$mm[1]]];
        } else {
            if ($last >= 0 && $blocks[$last]['type'] === 'p') $blocks[$last]['lines'][] = $line;
            else $blocks[] = ['type' => 'p', 'lines' => [$line]];
        }
    }
    $blocks = array_values(array_filter($blocks, fn($b) => $b['type'] !== 'break'));

    // One CTA at a time: PDF (new tab) or video (popup)
    $ctaType = setting("doctrine_{$key}_cta_type", 'none');
    $ctaText = trim(setting("doctrine_{$key}_cta_text", '')) ?: 'Learn More';
    $ctaUrl  = '';
    if ($ctaType === 'pdf')   $ctaUrl = trim(setting("doctrine_{$key}_pdf_url", ''));
    if ($ctaType === 'video') $ctaUrl = trim(setting("doctrine_{$key}_video_url", ''));
    if ($ctaUrl === '') $ctaType = 'none';

    $cards[] = compact('title', 'blocks', 'price', 'align', 'ctaType', 'ctaText', 'ctaUrl');
}

$headerHeight = (int)setting('site_logo_height', '44') + 16;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css">
<script defer src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;overflow-x:hidden}
body{font-family:'DM Sans','Noto Sans Devanagari',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased}

.sp-hero{text-align:center;padding:<?= $headerHeight + 40 ?>px 1.5rem 2rem}
.sp-heading{font-family:'Bebas Neue',sans-serif;font-size:clamp(3.5rem,10vw,6.5rem);line-height:1;letter-spacing:.06em;color:#000;text-transform:uppercase;font-weight:400}
.sp-tagline{margin-top:1rem;font-size:.75rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#111}
.sp-tagline .dot{margin:0 .75rem;color:#6b7280}
.sp-hero-img{max-width:1100px;margin:2rem auto 0;padding:0 1.5rem}
.sp-hero-img img{width:100%;height:auto;max-height:420px;object-fit:cover;display:block}

.sp-cards{max-width:1200px;margin:0 auto;padding:2.5rem 1.5rem 5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.75rem}
.sp-card{background:#fff;border:1px solid #eceef1;border-radius:4px;box-shadow:0 2px 14px rgba(0,0,0,.06);padding:2.25rem 1.75rem;display:flex;flex-direction:column}
.sp-card.al-left{text-align:left}.sp-card.al-center{text-align:center}.sp-card.al-right{text-align:right}
.sp-card-title{font-family:'Bebas Neue',sans-serif;font-weight:400;font-size:1.6rem;letter-spacing:.06em;line-height:1.1;text-transform:uppercase;color:#111;margin-bottom:1.25rem}
.sp-card-desc{flex:1}
.sp-card-desc p{font-size:.88rem;line-height:2.15;color:#1f2937;margin-bottom:.5rem}
.sp-card-desc ul{list-style:disc;padding-left:1.25rem;margin-bottom:.5rem;font-size:.88rem;line-height:1.9;color:#1f2937}
.sp-card.al-center .sp-card-desc ul,.sp-card.al-right .sp-card-desc ul{list-style-position:inside;padding-left:0}
.sp-card-price{font-weight:700;font-size:.95rem;color:#111;margin-top:.5rem}
.sp-cta{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;margin-top:1.5rem;padding:.8rem 1.4rem;background:#111;color:#fff;border:none;border-radius:6px;font-family:inherit;font-size:.78rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;text-decoration:none;cursor:pointer;transition:background .2s,transform .2s}
.sp-cta:hover{background:#374151;transform:translateY(-1px)}
.sp-cta:focus-visible{outline:2px solid #111;outline-offset:3px}
.sp-card.al-center .sp-cta{align-self:center}.sp-card.al-left .sp-cta{align-self:flex-start}.sp-card.al-right .sp-cta{align-self:flex-end}

@media(max-width:768px){
  .sp-hero{padding:<?= $headerHeight + 28 ?>px 1rem 1.5rem}
  .sp-tagline{font-size:.62rem;letter-spacing:.16em}
  .sp-tagline .dot{margin:0 .4rem}
  .sp-hero-img{padding:0 1rem;margin-top:1.5rem}
  .sp-cards{padding:2rem 1rem 4rem;gap:1.25rem}
  .sp-card{padding:1.75rem 1.25rem}
}

/* Video popups */
@keyframes spin{to{transform:rotate(360deg)}}
#spVideoModal,#spYtModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;padding:1rem}
.sp-modal-box{position:relative;width:100%;max-width:1100px;background:#000;border-radius:12px;overflow:hidden}
.sp-modal-close{position:absolute;top:.75rem;right:.75rem;z-index:100;width:40px;height:40px;background:rgba(255,255,255,.15);border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff}
.sp-modal-close:hover{background:rgba(255,255,255,.3)}
#spVideoModal .plyr{--plyr-color-main:#D92B3A}
#spVideoModal video{width:100%;max-height:85vh;display:block}
</style>
</head>
<body>

<?php require_once __DIR__ . '/partials/nav-frontend.php'; ?>

<main>
  <?php if ($heroHeading !== '' || $taglineItems || $heroImage !== ''): ?>
  <section class="sp-hero">
    <?php if ($heroHeading !== ''): ?>
    <h1 class="sp-heading"><?= htmlspecialchars($heroHeading) ?></h1>
    <?php endif; ?>
    <?php if ($taglineItems): ?>
    <p class="sp-tagline"><?= implode('<span class="dot" aria-hidden="true">·</span>', array_map('htmlspecialchars', $taglineItems)) ?></p>
    <?php endif; ?>
  </section>
  <?php if ($heroImage !== ''): ?>
  <div class="sp-hero-img">
    <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($heroHeading ?: 'Scripts') ?>">
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($cards)): ?>
  <section class="sp-cards" aria-label="Script services">
    <?php foreach ($cards as $c): ?>
    <article class="sp-card al-<?= $c['align'] ?>">
      <?php if ($c['title'] !== ''): ?>
      <h2 class="sp-card-title"><?= htmlspecialchars($c['title']) ?></h2>
      <?php endif; ?>

      <div class="sp-card-desc">
        <?php foreach ($c['blocks'] as $b): ?>
          <?php if ($b['type'] === 'ul'): ?>
          <ul><?php foreach ($b['items'] as $it): ?><li><?= htmlspecialchars($it) ?></li><?php endforeach; ?></ul>
          <?php else: ?>
          <p><?= implode('<br>', array_map('htmlspecialchars', $b['lines'])) ?></p>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($c['price'] !== ''): ?>
      <p class="sp-card-price"><?= htmlspecialchars($c['price']) ?></p>
      <?php endif; ?>

      <?php if ($c['ctaType'] === 'pdf'): ?>
      <a href="<?= htmlspecialchars($c['ctaUrl']) ?>" target="_blank" rel="noopener" class="sp-cta">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <?= htmlspecialchars($c['ctaText']) ?>
      </a>
      <?php elseif ($c['ctaType'] === 'video'): ?>
      <button type="button" class="sp-cta" onclick="spOpenVideo(<?= htmlspecialchars(json_encode($c['ctaUrl']), ENT_QUOTES) ?>)">
        <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        <?= htmlspecialchars($c['ctaText']) ?>
      </button>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</main>

<!-- Self-hosted video popup -->
<div id="spVideoModal" role="dialog" aria-modal="true" aria-label="Video player">
  <div class="sp-modal-box">
    <button type="button" class="sp-modal-close" onclick="spCloseVideo()" aria-label="Close">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <video id="spVideo" playsinline controls></video>
  </div>
</div>

<!-- YouTube popup -->
<div id="spYtModal" role="dialog" aria-modal="true" aria-label="YouTube video">
  <div class="sp-modal-box" style="max-width:960px">
    <button type="button" class="sp-modal-close" onclick="spCloseVideo()" aria-label="Close">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div style="position:relative;padding-bottom:56.25%;height:0">
      <iframe id="spYtFrame" src="" title="YouTube video" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>

<script>
var spPlayer = null;
function spYtEmbed(u){
    var m;
    m=u.match(/youtu\.be\/([^?&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    m=u.match(/[?&]v=([^&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    m=u.match(/\/shorts\/([^?&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    m=u.match(/\/embed\/([^?&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    return '';
}
function spOpenVideo(url){
    if(!url) return;
    document.body.style.overflow='hidden';
    if(/youtu(\.be|be\.com)/i.test(url)){
        var e = spYtEmbed(url);
        if(!e) return;
        document.getElementById('spYtFrame').src = e + '?autoplay=1&rel=0&modestbranding=1';
        document.getElementById('spYtModal').style.display='flex';
        return;
    }
    var v = document.getElementById('spVideo');
    v.src = url;
    document.getElementById('spVideoModal').style.display='flex';
    if(window.Plyr){
        if(spPlayer) spPlayer.destroy();
        spPlayer = new Plyr(v, { controls:['play-large','play','progress','current-time','mute','volume','settings','fullscreen'] });
        spPlayer.play().catch(function(){});
    } else {
        v.play().catch(function(){});
    }
}
function spCloseVideo(){
    if(spPlayer){ spPlayer.stop(); spPlayer.destroy(); spPlayer=null; }
    var v = document.getElementById('spVideo');
    v.pause(); v.removeAttribute('src'); v.load();
    document.getElementById('spYtFrame').src='';
    document.getElementById('spVideoModal').style.display='none';
    document.getElementById('spYtModal').style.display='none';
    document.body.style.overflow='';
}
['spVideoModal','spYtModal'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) spCloseVideo(); });
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') spCloseVideo(); });
</script>

<?php require_once __DIR__ . '/partials/footer-frontend.php'; ?>
<?php include __DIR__ . '/partials/language-switcher.php'; ?>
</body>
</html>

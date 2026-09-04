<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/helpers/settings_helper.php';

$logoUrl = setting('site_logo_url', '');
$pageTitle = 'Terms & Conditions — Faceless Pictures 3';

// Get legal page content from settings
$legalHeading = setting('legal_heading', 'SUBMISSION TERMS & RIGHTS');
$legalContent = setting('legal_content', 'Legal terms and conditions will appear here.');

// Get cache version for asset cache-busting
$cacheVersion = '1';
$versionFile = __DIR__ . '/../cache/.version';
if (file_exists($versionFile)) {
    $cacheVersion = trim(file_get_contents($versionFile)) ?: '1';
}

// Get the header height from nav-frontend.php calculation
$headerHeight = (int)setting('site_logo_height', '44') + 16;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<!-- Cache busting -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;overflow-x:hidden}
body{font-family:'DM Sans','Noto Sans Devanagari',sans-serif;background:#f9fafb;color:#111;-webkit-font-smoothing:antialiased;line-height:1.7}
.legal-container{max-width:1200px;margin:0 auto;padding:0 2rem 5rem}
.legal-heading{font-family:'Bebas Neue',sans-serif;font-size:clamp(32px,5vw,48px);letter-spacing:.06em;color:#111;margin-bottom:2rem;text-align:center}
.legal-content{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:4rem;box-shadow:0 1px 4px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.06);font-size:1rem;color:#374151;line-height:1.7}
.legal-content br{display:block;margin-top:0.5rem}
.legal-content br+br{margin-top:1.5rem}
@media(max-width:1024px){
  .legal-container{max-width:900px}
  .legal-content{padding:3rem}
}
@media(max-width:768px){
  .legal-container{padding:0 1.25rem 4rem;max-width:100%}
  .legal-content{padding:2rem 1.5rem}
  .legal-heading{font-size:clamp(28px,6vw,36px)}
  .legal-content h1{font-size:2rem}
  .legal-content h2{font-size:1.5rem}
  .legal-content ul,.legal-content ol{margin-left:1rem;padding-left:0.5rem}
}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .4s ease forwards}
</style>
</head>
<body>

<?php require_once __DIR__ . '/partials/nav-frontend.php'; ?>

<!-- HERO -->
<section style="padding:<?= $headerHeight + 48 ?>px 2rem 3rem;text-align:center" class="fade-up">
  <h1 class="legal-heading"><?= htmlspecialchars($legalHeading) ?></h1>
</section>

<!-- LEGAL CONTENT -->
<div class="legal-container fade-up">
  <div class="legal-content">
    <?= nl2br(htmlspecialchars($legalContent)) ?>
  </div>
</div>

<!-- FOOTER -->
<?php require_once __DIR__ . '/partials/footer-frontend.php'; ?>

<?php include __DIR__ . '/partials/language-switcher.php'; ?>
</body>
</html>

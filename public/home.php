<?php
// Page cache - serve cached HTML if available (skips all PHP/DB processing)
require_once __DIR__ . '/../app/helpers/page_cache.php';

$cacheKey = 'home_' . ($_GET['lang'] ?? 'en');
$cachedPage = PageCache::get($cacheKey);

if ($cachedPage !== null && !isset($_SESSION['user_id'])) {
    // Serve cached page for non-logged-in users
    header('X-Cache: HIT');
    echo $cachedPage;
    exit;
}

// Start output buffering to capture page for caching
ob_start();

require_once __DIR__ . '/../app/config/config.php';
$settingsModel = new App\Models\Settings();

// Load ALL settings once to avoid multiple database queries
$allSettings = $settingsModel->getAllCached();

// Helper function to get setting with fallback
function getSetting($key, $default = '') {
    global $allSettings;
    return $allSettings[$key] ?? $default;
}

// Helper function to format text content with list detection
function format_text_content($text) {
    if (empty($text)) return '';
    
    // Detect numbered lists: lines starting with "1. " or "1) " etc.
    $lines = explode("\n", $text);
    $inList = false;
    $result = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if ($inList) {
                $result .= '</ul>';
                $inList = false;
            }
            $result .= '<br>';
            continue;
        }
        
        // Check if line starts with number pattern: "1. " or "1) " or "1 - "
        if (preg_match('/^\d+[\.\)]\s+(.+)$/', $line, $matches)) {
            if (!$inList) {
                $result .= '<ul class="text-list">';
                $inList = true;
            }
            $result .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
        } else {
            if ($inList) {
                $result .= '</ul>';
                $inList = false;
            }
            $result .= htmlspecialchars($line) . '<br>';
        }
    }
    
    if ($inList) {
        $result .= '</ul>';
    }
    
    return $result;
}

$aboutText    = getSetting('landing_about_text', "Faceless Pictures is India's first anonymous film competition where talent speaks without a face.");
$logoUrl      = getSetting('site_logo_url', '');
$logoHeight   = getSetting('site_logo_height', '44'); // Height in pixels
$siteTagline  = getSetting('site_tagline', "India's first film competition. Submit your video. Show your talent.");
$heroHeadline     = getSetting('landing_headline', 'NO FACE. NO CONNECTIONS. JUST TALENT.');
$rolesHeading     = getSetting('landing_roles_heading', 'Become a Star in 3 Clicks');
$rolesSubheading  = getSetting('landing_roles_subheading', 'Pick your role. Shoot your video. Submit. That\'s it.');

// Get customizable header menu items (grouped by left/right position)
try {
    $headerMenuItems = $settingsModel->getHeaderMenuItemsGrouped();
} catch (\Exception $e) {
    // Fallback if method fails or database not updated yet
    $headerMenuItems = [
        'left' => [
            ['text' => 'About', 'url' => '#about', 'order' => 1],
            ['text' => 'Writers', 'url' => '/writer', 'order' => 2],
        ],
        'right' => [
            ['text' => 'Directors', 'url' => '/director', 'order' => 3],
            ['text' => 'Actors', 'url' => '/actor', 'order' => 4],
        ]
    ];
}

// Manifesto video slider (up to 6 YouTube URLs + optional titles)
$manifestoHeading    = $settingsModel->get('manifesto_heading', 'OUR MANIFESTO');
$manifestoSubheading = $settingsModel->get('manifesto_subheading', 'What Faceless Pictures 3 stands for.');
$manifestoVideos = [];
for ($i = 1; $i <= 6; $i++) {
    $url   = $settingsModel->get('manifesto_video' . $i . '_url', '');
    $title = $settingsModel->get('manifesto_video' . $i . '_title', '');
    if ($url) $manifestoVideos[] = ['url' => $url, 'title' => $title];
}

// Role card settings
$roles = [];
foreach (['writer', 'director', 'actor'] as $role) {
    $roles[$role] = [
        'title' => $settingsModel->get('role_' . $role . '_title', strtoupper($role)),
        'icon' => $settingsModel->get('role_' . $role . '_icon', $role === 'writer' ? '✍️' : ($role === 'director' ? '🎬' : '🎭')),
        'description' => $settingsModel->get('role_' . $role . '_description', 
            $role === 'writer' ? "Read your script on camera.\nYour words. Your voice. One video." :
            ($role === 'director' ? "Shoot your scene your way.\nOne phone. One take. Your vision." :
            "Shoot your scene on camera.\nFace hidden. Talent only.")),
        'badge1' => $settingsModel->get('role_' . $role . '_badge1', 
            $role === 'writer' ? 'Script Reading' : 
            ($role === 'director' ? 'Scene Direction' : 'Dialogue')),
        'badge2' => $settingsModel->get('role_' . $role . '_badge2',
            $role === 'director' ? 'Pitch' : ($role === 'actor' ? 'Song' : '')),
        'button_text' => $settingsModel->get('role_' . $role . '_button_text', 'Click Here →'),
        'button_url' => $settingsModel->get('role_' . $role . '_button_url', '/' . $role),
    ];
}

// Marquee items
$marqueeItems = [];
for ($i = 1; $i <= 10; $i++) {
    $item = $settingsModel->get('marquee_item' . $i, '');
    if ($item) $marqueeItems[] = $item;
}
// Fallback if no items
if (empty($marqueeItems)) {
    $marqueeItems = ['ACTORS','DIRECTORS','WRITERS','NO CONNECTIONS','ONE VIDEO','ONE CHANCE','NOW OPEN','NO FACE','JUST TALENT','SUBMIT TODAY'];
}

// About section
$aboutSectionLabel = $settingsModel->get('about_section_label', 'About');
$aboutSectionHeading = $settingsModel->get('about_section_heading', 'WHAT IS FACELESS PICTURES?');

// Up to 10 poster slots
$posterKeys = [
    ['landing_poster_url',  'landing_poster_title',  'landing_trailer_url',  'landing_poster_btn_label'],
    ['landing_poster2_url', 'landing_poster2_title', 'landing_trailer2_url', 'landing_poster2_btn_label'],
    ['landing_poster3_url', 'landing_poster3_title', 'landing_trailer3_url', 'landing_poster3_btn_label'],
    ['landing_poster4_url', 'landing_poster4_title', 'landing_trailer4_url', 'landing_poster4_btn_label'],
    ['landing_poster5_url', 'landing_poster5_title', 'landing_trailer5_url', 'landing_poster5_btn_label'],
    ['landing_poster6_url', 'landing_poster6_title', 'landing_trailer6_url', 'landing_poster6_btn_label'],
    ['landing_poster7_url', 'landing_poster7_title', 'landing_trailer7_url', 'landing_poster7_btn_label'],
    ['landing_poster8_url', 'landing_poster8_title', 'landing_trailer8_url', 'landing_poster8_btn_label'],
    ['landing_poster9_url', 'landing_poster9_title', 'landing_trailer9_url', 'landing_poster9_btn_label'],
    ['landing_poster10_url', 'landing_poster10_title', 'landing_trailer10_url', 'landing_poster10_btn_label'],
];
$posters = [];
foreach ($posterKeys as $i => $keys) {
    $url      = $settingsModel->get($keys[0], '');
    $title    = $settingsModel->get($keys[1], '');
    $trailer  = $settingsModel->get($keys[2], '');
    $btnLabel = $settingsModel->get($keys[3], '');
    // Only include if poster image URL is set
    if ($url) {
        $posters[] = ['url' => $url, 'title' => $title, 'trailer' => $trailer, 'btn_label' => $btnLabel, 'idx' => $i];
    }
}
$posterCount = count($posters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faceless Pictures 3 — No Face. Just Talent.</title>
<meta name="description" content="India's first anonymous film competition. Actor, Director & Writer auditions open now.">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/favicon.ico">

<!-- Performance Optimizations -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.plyr.io">
<link rel="preconnect" href="https://cdn.tailwindcss.com">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdn.plyr.io">

<!-- Preload critical images ONLY (first 3 posters) - more creates congestion -->
<?php 
$criticalPosters = array_slice($posters, 0, 3);
foreach ($criticalPosters as $idx => $p): 
    if (!empty($p['url'])): ?>
<link rel="preload" as="image" href="<?= htmlspecialchars($p['url']) ?>" fetchpriority="high" imagesrcset="<?= htmlspecialchars($p['url']) ?> 1x">
<?php 
    endif;
endforeach; 
?>

<!-- Preload logo if set -->
<?php if ($logoUrl): ?>
<link rel="preload" as="image" href="<?= htmlspecialchars($logoUrl) ?>" fetchpriority="high">
<?php endif; ?>

<!-- Critical CSS inline (fonts loaded async below) -->
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>

<!-- Plyr Video Player (deferred) -->
<link rel="preload" href="https://cdn.plyr.io/3.7.8/plyr.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css"></noscript>
<script defer src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>

<!-- Tailwind and Alpine -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'DM Sans','Noto Sans Devanagari','Noto Sans Bengali','Noto Sans Tamil','Noto Sans Telugu','Noto Sans Kannada','Noto Sans Malayalam','Noto Sans Gujarati','Noto Sans Gurmukhi',sans-serif;background:#fff;color:#111;-webkit-font-smoothing:antialiased;overflow-x:hidden}
[x-cloak]{display:none!important}

/* TEXT LISTS - Auto-format numbered lists with spacing */
.text-list{list-style:none;padding:0;margin:0.5rem 0}
.text-list li{padding:0.5rem 0;position:relative;line-height:1.6}
.text-list li:not(:last-child){margin-bottom:0.375rem}

/* NAV */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50}
.fp-nav > div{position:relative}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s;white-space:nowrap}
.nav-link:hover{color:#111}
.mobile-nav-link{display:block;transition:all .2s;border-radius:8px;margin:0 12px}

/* POSTER CARD */
.poster-wrap{display:flex;flex-direction:column;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.10);transition:transform .3s,box-shadow .3s}
.poster-wrap:hover{transform:translateY(-5px);box-shadow:0 12px 40px rgba(0,0,0,.16)}
.poster-card{position:relative;background:#f3f4f6;aspect-ratio:2/3;display:block;flex-shrink:0}
.poster-card img{width:100%;height:100%;object-fit:cover;display:block}
.poster-empty{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:linear-gradient(145deg,#e5e7eb,#f9fafb)}
.play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3);opacity:0;transition:opacity .25s;cursor:pointer}
.poster-wrap:hover .play-overlay{opacity:1}
.play-circle{width:56px;height:56px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.25);transition:transform .2s}
.play-overlay:hover .play-circle{transform:scale(1.1)}
.poster-title-bar{position:absolute;bottom:0;left:0;right:0;padding:.875rem .75rem .6rem;background:linear-gradient(to top,rgba(0,0,0,.7),transparent);color:#fff;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
/* CTA bar below poster image */
.poster-cta{background:#111;color:#fff;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.55rem .75rem;font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;white-space:nowrap;flex-shrink:0}
.poster-cta svg{width:9px;height:9px;fill:#fff;flex-shrink:0}
.poster-cta.no-trailer{background:#1a1a1a;color:rgba(255,255,255,.55)}

/* ROLE CARD */
.role-card{border:2px solid #e5e7eb;border-radius:12px;padding:1.5rem 1.25rem;text-align:center;text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;background:#fff;transition:border-color .2s,transform .2s,box-shadow .2s;height:100%}
.role-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.07);border-color:#111}
.role-card .btn-black{margin-top:auto}
.role-icon{width:56px;height:56px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:.875rem}
.role-name{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:.04em;color:#111;margin-bottom:.25rem}
.role-desc{font-size:.8rem;color:#6b7280;margin-bottom:1rem;line-height:1.5}
.role-badges{display:flex;flex-wrap:wrap;justify-content:center;gap:.35rem;margin-bottom:1rem}
.badge{font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 7px;border-radius:4px;border:1px solid #d1d5db;color:#374151;background:#f9fafb}

/* BUTTONS */
.btn-black{background:#111;color:#fff;font-weight:700;border:none;border-radius:7px;padding:.65rem 1.4rem;font-size:.85rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:background .2s;width:100%;justify-content:center}
.btn-black:hover{background:#333}

/* SECTION LABEL */
.section-label{font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:1.25rem}

/* MARQUEE */
@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.marquee-track{animation:marquee 40s linear infinite;display:flex;white-space:nowrap}
.marquee-wrap:hover .marquee-track{animation-play-state:paused}

/* MODAL BACKDROP */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);z-index:200;display:flex;align-items:center;justify-content:center;padding:1rem}

/* VIDEO PLAYER WRAPPER */
.vp-wrap{position:relative;width:100%;max-width:960px;background:#000;border-radius:14px;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.8);user-select:none}
.vp-wrap video{display:block;width:100%;max-height:80vh;background:#000}

/* CLOSE BUTTON */
.vp-close{position:absolute;top:.75rem;right:.75rem;width:36px;height:36px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;z-index:10;transition:background .2s;backdrop-filter:blur(4px)}
.vp-close:hover{background:rgba(255,255,255,.22)}
.vp-close svg{width:16px;height:16px;flex-shrink:0}

/* CONTROLS BAR */
.vp-controls{position:absolute;bottom:0;left:0;right:0;padding:.75rem 1rem 1rem;background:linear-gradient(to top,rgba(0,0,0,.85) 0%,transparent 100%);display:flex;flex-direction:column;gap:.5rem;opacity:0;transition:opacity .25s}
.vp-wrap:hover .vp-controls,.vp-wrap.paused .vp-controls{opacity:1}

/* PROGRESS SCRUBBER */
.vp-progress{position:relative;height:4px;background:rgba(255,255,255,.2);border-radius:2px;cursor:pointer;flex:1}
.vp-progress:hover{height:6px;margin-top:-1px}
.vp-buf{position:absolute;top:0;left:0;height:100%;background:rgba(255,255,255,.25);border-radius:2px;pointer-events:none}
.vp-played{position:absolute;top:0;left:0;height:100%;background:#fff;border-radius:2px;pointer-events:none}
.vp-thumb{position:absolute;top:50%;right:-6px;transform:translateY(-50%);width:12px;height:12px;background:#fff;border-radius:50%;box-shadow:0 0 4px rgba(0,0,0,.4);pointer-events:none;opacity:0;transition:opacity .15s}
.vp-progress:hover .vp-thumb{opacity:1}
.vp-tooltip{position:absolute;bottom:calc(100% + 8px);background:rgba(0,0,0,.8);color:#fff;font-size:.68rem;font-weight:600;padding:2px 7px;border-radius:4px;white-space:nowrap;pointer-events:none;transform:translateX(-50%);display:none}
.vp-progress:hover .vp-tooltip{display:block}

/* BOTTOM ROW */
.vp-row{display:flex;align-items:center;gap:.625rem}
.vp-btn{background:none;border:none;cursor:pointer;color:#fff;opacity:.85;padding:4px;display:flex;align-items:center;justify-content:center;transition:opacity .15s;flex-shrink:0}
.vp-btn:hover{opacity:1}
.vp-btn svg{width:20px;height:20px}

/* VOLUME */
.vp-vol-wrap{display:flex;align-items:center;gap:.375rem}
.vp-vol-slider{-webkit-appearance:none;appearance:none;height:3px;width:64px;background:rgba(255,255,255,.25);border-radius:2px;cursor:pointer;outline:none}
.vp-vol-slider::-webkit-slider-thumb{-webkit-appearance:none;width:12px;height:12px;border-radius:50%;background:#fff;cursor:pointer}
.vp-vol-slider::-moz-range-thumb{width:12px;height:12px;border:none;border-radius:50%;background:#fff;cursor:pointer}

/* TIME */
.vp-time{font-size:.72rem;color:rgba(255,255,255,.7);font-variant-numeric:tabular-nums;white-space:nowrap;margin-left:.25rem}

/* TITLE */
.vp-title{position:absolute;top:0;left:0;right:0;padding:.875rem 1rem;background:linear-gradient(to bottom,rgba(0,0,0,.7),transparent);color:#fff;font-size:.8rem;font-weight:600;letter-spacing:.04em;opacity:0;transition:opacity .25s;pointer-events:none}
.vp-wrap:hover .vp-title{opacity:1}

/* BIG PLAY (centre) */
.vp-bigplay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.vp-bigplay-btn{width:72px;height:72px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.4);border-radius:50%;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:transform .2s,background .2s;transform:scale(0);opacity:0}
.vp-wrap.paused .vp-bigplay-btn{transform:scale(1);opacity:1}
.vp-bigplay-btn svg{width:28px;height:28px;color:#fff;margin-left:4px}

/* FULLSCREEN icon swap */
.icon-fs-enter{display:block}
.icon-fs-exit{display:none}
.vp-wrap.fullscreen .icon-fs-enter{display:none}
.vp-wrap.fullscreen .icon-fs-exit{display:block}

/* POSTER ROW SLIDER */
.poster-slider{position:relative}
.poster-track{display:flex;gap:1rem;transition:transform .4s cubic-bezier(.25,.46,.45,.94)}
.slider-btn{position:absolute;top:50%;transform:translateY(-60%);width:36px;height:36px;background:#fff;border:1px solid #e5e7eb;border-radius:50%;box-shadow:0 2px 12px rgba(0,0,0,.12);cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:5;transition:box-shadow .2s,background .2s}
.slider-btn:hover{background:#f9fafb;box-shadow:0 4px 16px rgba(0,0,0,.16)}
.slider-btn svg{width:16px;height:16px;color:#374151}
.slider-btn.prev{left:0}
.slider-btn.next{right:0}
.slider-dots{display:flex;justify-content:center;gap:.4rem;margin-top:.75rem}
.slider-dot{width:6px;height:6px;border-radius:50%;background:#d1d5db;transition:background .2s,width .2s;cursor:pointer}
.slider-dot.active{background:#111;width:16px;border-radius:3px}

/* NO-TRAILER TOAST */
#fp-no-trailer-toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(20px);background:#1a1a1a;color:#fff;font-size:.8rem;font-weight:600;padding:.6rem 1.25rem;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.25);opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;z-index:500;white-space:nowrap;display:flex;align-items:center;gap:.5rem}
#fp-no-trailer-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

/* Poster play hint — always visible on touch/mobile */
@media(hover:none){
  .play-overlay{opacity:1!important;background:rgba(0,0,0,.18)!important}
  .play-circle{opacity:.85}
}
/* Mobile/touch: only suppress the big dark overlay, badge always visible */
@media(hover:none){
  .play-overlay{opacity:0!important;pointer-events:none}
  .poster-wrap:active .play-overlay{opacity:1!important;pointer-events:auto}
}

/* FOOTER */
.fp-footer{background:#f3f4f6;color:#111;padding:2.5rem 1rem;border-top:1px solid #e5e7eb}

/* MANIFESTO SLIDER */
.manifesto-section{padding:3rem 0;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;margin-bottom:3rem}
.manifesto-slider{position:relative;overflow:visible;padding-bottom:.5rem}
.manifesto-track{display:flex;gap:1.25rem;transition:transform .5s cubic-bezier(.25,.46,.45,.94)}
.manifesto-slide{flex-shrink:0;border-radius:12px;overflow:hidden;background:#fff;position:relative;
  box-shadow:0 2px 16px rgba(0,0,0,.10);
  width:calc((100% - 2 * 1.25rem) / 3)} /* desktop: 3 per row */
@media(max-width:1023px){
  .manifesto-slide{width:100%} /* mobile/tablet: 1 per row */
  .manifesto-track{transition:transform .38s cubic-bezier(.22,.68,0,1.2)} /* springy on mobile */
}
/* 16:9 YouTube embed inside each slide */
.manifesto-embed{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background:#000;cursor:pointer}
.manifesto-embed iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;pointer-events:none}
.manifesto-embed img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.manifesto-title{padding:.55rem .75rem;background:#fff;font-size:.78rem;font-weight:600;color:#111;line-height:1.4;border-top:1px solid #f0f0f0}
.manifesto-play-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.28);transition:background .2s;z-index:2}
.manifesto-slide:hover .manifesto-play-overlay{background:rgba(0,0,0,.45)}
.manifesto-play-circle{width:52px;height:52px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.5);border-radius:50%;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:transform .2s,background .2s}
.manifesto-slide:hover .manifesto-play-circle{transform:scale(1.1);background:rgba(255,255,255,.25)}
.manifesto-play-circle svg{width:22px;height:22px;fill:#fff;margin-left:3px}
.manifesto-dots{display:flex;justify-content:center;gap:.4rem;margin-top:.875rem}
.manifesto-dot{width:6px;height:6px;border-radius:50%;background:#d1d5db;cursor:pointer;transition:background .2s,width .2s}
.manifesto-dot.active{background:#111;width:16px;border-radius:3px}
@media(max-width:639px){
  .poster-grid{grid-template-columns:1fr 1fr!important}
  .role-cards-grid{grid-template-columns:1fr!important}
  .poster-slider{padding:0 16px!important}
  .poster-slide-card{width:calc(100% - 1rem)!important}
}

/* Poster: 1-col grid on mobile/tablet, grid/slider on desktop */
.poster-mobile-grid{display:none;grid-template-columns:1fr;gap:1rem}
.poster-desktop-grid{display:none;gap:1rem 1.25rem}
@media(max-width:1023px){
  .poster-mobile-grid{display:grid}
  .poster-desktop-grid,.poster-desktop-only{display:none!important}
}
@media(min-width:1024px){
  .poster-mobile-grid{display:none!important}
  .poster-desktop-grid{display:grid}
}

/* Hero Trailer - ensure same width as poster row */
.hero-trailer-wrap{width:100%;max-width:100%;margin:0 auto}
@media(min-width:1024px){
  /* Match the three-poster width calculation */
  .hero-trailer-wrap{width:100%}
}
</style>
</head>

<body x-data="homePage()" @keydown.escape.window="closePlayer()" x-init="init()">

<!-- ── NAV ── -->
<?php
// Calculate header height based on logo size (logo height + 16px padding)
$navHeight = (int)$logoHeight + 16;

// Merge all menu items for mobile
$allMenuItems = array_merge($headerMenuItems['left'], $headerMenuItems['right']);
usort($allMenuItems, fn($a, $b) => $a['order'] <=> $b['order']);
?>
<nav class="fp-nav" style="height:<?= $navHeight ?>px" x-data="{ mobileMenuOpen: false }">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-center gap-8 sm:gap-12 md:gap-16">
    
    <!-- Mobile: Hamburger Button (Left) -->
    <button @click="mobileMenuOpen = true" class="lg:hidden absolute left-4 text-dark p-2 hover:bg-dark/5 rounded-lg transition">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    
    <!-- LEFT MENU ITEMS (Desktop only) -->
    <div class="hidden lg:flex items-center gap-4 sm:gap-5">
      <?php foreach ($headerMenuItems['left'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    
    <!-- CENTERED LOGO (Always visible) -->
    <a href="/" class="nav-logo flex-shrink-0">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= (int)$logoHeight ?>px;width:auto" loading="eager">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    
    <!-- RIGHT MENU ITEMS (Desktop only) -->
    <div class="hidden lg:flex items-center gap-4 sm:gap-5">
      <?php foreach ($headerMenuItems['right'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  
  <!-- Mobile Sidebar Overlay -->
  <div x-show="mobileMenuOpen" 
       x-cloak
       @click="mobileMenuOpen = false" 
       class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 lg:hidden"
       x-transition:enter="transition-opacity ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">
  </div>
  
  <!-- Mobile Sidebar Menu -->
  <aside x-show="mobileMenuOpen"
         x-cloak
         class="fixed top-0 left-0 bottom-0 w-[280px] bg-white shadow-2xl z-50 lg:hidden"
         @click.away="mobileMenuOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full">
    <div class="flex flex-col h-full">
      <!-- Sidebar Header -->
      <div class="flex items-center justify-between p-4 border-b border-dark/10">
        <a href="/" class="nav-logo">
          <?php if ($logoUrl): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= max(32, (int)$logoHeight * 0.75) ?>px;width:auto">
          <?php else: ?>
            <span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
            <span class="nav-badge">3</span>
          <?php endif; ?>
        </a>
        <button @click="mobileMenuOpen = false" class="p-2 hover:bg-dark/5 rounded-lg transition">
          <svg class="w-5 h-5 text-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      
      <!-- Menu Items -->
      <nav class="flex-1 overflow-y-auto py-6">
        <?php 
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        foreach ($allMenuItems as $item): 
          $itemPath = parse_url($item['url'], PHP_URL_PATH);
          $isActive = ($currentPath === $itemPath) || 
                      ($itemPath !== '/' && strpos($currentPath, $itemPath) === 0);
        ?>
          <a href="<?= htmlspecialchars($item['url']) ?>" 
             class="mobile-nav-link block px-6 py-4 <?= $isActive ? 'bg-black text-white font-semibold' : 'text-gray-700 hover:bg-gray-100' ?> transition text-base">
            <?= htmlspecialchars($item['text']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
</nav>

<!-- ── MAIN CONTENT ── -->
<main style="padding-top:<?= $navHeight ?>px">
  <div class="max-w-6xl mx-auto px-4 sm:px-6">

    <!-- ══ HERO HEADLINE — centered ══ -->
    <div style="text-align:center;padding:2rem 0 1.25rem;border-bottom:1px solid #e5e7eb">
      <h1 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(24px,5vw,56px);letter-spacing:.02em;line-height:.95;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
        <?= htmlspecialchars($heroHeadline) ?>
      </h1>
    </div>

    <div class="py-10 sm:py-12">

    <!-- ══ HORIZONTAL AUTO-PLAY TRAILER ══ -->
    <?php
    $heroTrailerUrl = $settingsModel->get('landing_hero_trailer_url', '');
    if ($heroTrailerUrl):
        // Check if it's a YouTube URL
        $isYoutube = (strpos($heroTrailerUrl, 'youtube.com') !== false || strpos($heroTrailerUrl, 'youtu.be') !== false);
        $ytId = '';
        if ($isYoutube) {
            // Extract YouTube ID
            $clean = preg_replace('/[?&]si=[^&]+/', '', $heroTrailerUrl);
            if (preg_match('/youtu\.be\/([A-Za-z0-9_\-]{11})/', $clean, $mm))      $ytId = $mm[1];
            elseif (preg_match('/[?&]v=([A-Za-z0-9_\-]{11})/', $clean, $mm))       $ytId = $mm[1];
            elseif (preg_match('/\/shorts\/([A-Za-z0-9_\-]{11})/', $clean, $mm))   $ytId = $mm[1];
            elseif (preg_match('/\/embed\/([A-Za-z0-9_\-]{11})/', $clean, $mm))    $ytId = $mm[1];
        }
    ?>
    <div class="hero-trailer-wrap" style="margin-bottom:3rem;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);background:#000">
        <?php if ($isYoutube && $ytId): ?>
            <!-- YouTube Embed with Autoplay -->
            <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden">
                <iframe 
                    src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>?autoplay=1&mute=1&loop=1&playlist=<?= htmlspecialchars($ytId) ?>&controls=1&modestbranding=1&rel=0"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    loading="lazy">
                </iframe>
            </div>
        <?php else: ?>
            <!-- Modern Video Player with Plyr -->
            <div style="position:relative;width:100%;height:auto">
                <!-- Loading skeleton -->
                <div id="hero-trailer-skeleton" style="position:absolute;inset:0;background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:skeleton-shimmer 1.5s infinite;border-radius:12px;display:flex;align-items:center;justify-content:center;min-height:400px">
                    <div style="text-align:center">
                        <!-- Modern spinner -->
                        <div style="width:48px;height:48px;border:4px solid #e5e7eb;border-top-color:#d4a574;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px"></div>
                        <p style="color:#6b7280;font-size:14px;font-weight:500">Loading trailer...</p>
                    </div>
                </div>
                
                <video 
                    id="hero-trailer-player"
                    class="plyr-video"
                    controls 
                    autoplay 
                    muted 
                    loop 
                    playsinline
                    style="width:100%;height:auto;display:block;opacity:0;transition:opacity 0.3s ease">
                    <source src="<?= htmlspecialchars($heroTrailerUrl) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
            
            <style>
                @keyframes skeleton-shimmer {
                    0% { background-position: -200% 0; }
                    100% { background-position: 200% 0; }
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ ROW 1: FILM POSTER BOXES ══ -->

    <?php
    // Build the poster card HTML as a reusable string
    function posterCard(array $p, int $index): string {
        $hasTrailer = !empty($p['trailer']);
        $btnLabel   = !empty($p['btn_label']) ? htmlspecialchars($p['btn_label']) : ($hasTrailer ? 'Watch Trailer Now' : 'Trailer / Teaser Coming Soon');
        
        // First 10 posters: eager loading (all above the fold)
        // Remaining posters: lazy loading
        $loading = $index < 10 ? 'eager' : 'lazy';
        $fetchpriority = $index < 3 ? 'fetchpriority="high"' : '';
        
        $img = $p['url']
            ? '<img src="'.htmlspecialchars($p['url']).'" alt="'.htmlspecialchars($p['title'] ?: 'Film Poster').'" loading="'.$loading.'" decoding="async" '.$fetchpriority.' style="width:100%;height:100%;object-fit:cover">'
            : '<div class="poster-empty"><svg width="36" height="36" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><span style="font-size:.65rem;color:#9ca3af;letter-spacing:.08em;text-transform:uppercase">Set poster in Admin</span></div>';
        $overlay = $hasTrailer
            ? '<div class="play-overlay"><div class="play-circle"><svg width="22" height="22" fill="#111" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>'
            : '';
        $title = $p['title'] ? '<div class="poster-title-bar">'.htmlspecialchars($p['title']).'</div>' : '';
        $ctaClass = $hasTrailer ? 'poster-cta' : 'poster-cta no-trailer';
        $ctaIcon  = $hasTrailer ? '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>' : '';
        $cta = '<div class="'.$ctaClass.'">'.$ctaIcon.'<span>'.$btnLabel.'</span></div>';
        $wrapAttr = $hasTrailer
            ? 'style="cursor:pointer" @click="openPlayer(\''.addslashes(htmlspecialchars($p['trailer'])).'\',\''.addslashes(htmlspecialchars($p['title'])).'\' )"'
            : 'style="cursor:default"';
        return '<div class="poster-wrap" '.$wrapAttr.'><div class="poster-card">'.$img.$overlay.$title.'</div>'.$cta.'</div>';
    }
    ?>

    <?php if (count($posters) > 0): ?>
    <!-- MOBILE: 1-col grid -->
    <div class="poster-mobile-grid" style="display:grid;grid-template-columns:1fr;gap:1.5rem;margin-bottom:3rem">
      <?php foreach ($posters as $idx => $p): ?>
      <div><?= posterCard($p, $idx) ?></div>
      <?php endforeach; ?>
    </div>

    <!-- TABLET: 2-col grid -->
    <div class="poster-tablet-grid" style="display:none;grid-template-columns:repeat(2,1fr);gap:1.5rem;margin-bottom:3rem">
      <?php foreach ($posters as $idx => $p): ?>
      <div><?= posterCard($p, $idx) ?></div>
      <?php endforeach; ?>
    </div>

    <!-- DESKTOP: 3-col grid -->
    <div class="poster-desktop-grid" style="display:none;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:3rem">
      <?php foreach ($posters as $idx => $p): ?>
      <div><?= posterCard($p, $idx) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <style>
    @media (min-width: 640px) {
      .poster-mobile-grid { display: none !important; }
      .poster-tablet-grid { display: grid !important; }
    }
    @media (min-width: 1024px) {
      .poster-tablet-grid { display: none !important; }
      .poster-desktop-grid { display: grid !important; }
    }
    </style>

    <!-- ══ MANIFESTO VIDEO SLIDER ══ -->
    <?php if (!empty($manifestoVideos)): ?>
    <div class="manifesto-section" x-data="manifestoSlider(<?= count($manifestoVideos) ?>)">
      <div style="text-align:center;margin-bottom:1.75rem">
        <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(28px,4vw,48px);letter-spacing:.02em;line-height:.95;color:#111">
          <?= htmlspecialchars($manifestoHeading) ?>
        </h2>
      </div>
      <div class="manifesto-slider">
        <div style="overflow:hidden;margin:-12px;padding:12px">
        <div class="manifesto-track" :style="'transform:translateX(-'+offset+'px)'">
          <?php foreach ($manifestoVideos as $idx => $mv):
            $mvUrl   = $mv['url'];
            $mvTitle = $mv['title'];
            // Robust YouTube ID extraction — strip tracking params first
            $mvId = '';
            $clean = preg_replace('/[?&]si=[^&]+/', '', $mvUrl); // remove ?si= tracking
            if (preg_match('/youtu\.be\/([A-Za-z0-9_\-]{11})/', $clean, $mm))      $mvId = $mm[1];
            elseif (preg_match('/[?&]v=([A-Za-z0-9_\-]{11})/', $clean, $mm))       $mvId = $mm[1];
            elseif (preg_match('/\/shorts\/([A-Za-z0-9_\-]{11})/', $clean, $mm))   $mvId = $mm[1];
            elseif (preg_match('/\/embed\/([A-Za-z0-9_\-]{11})/', $clean, $mm))    $mvId = $mm[1];
            $mvThumb = $mvId ? 'https://img.youtube.com/vi/' . $mvId . '/hqdefault.jpg' : '';
            // First 3 videos: eager loading with high priority
            $loading = $idx < 3 ? 'eager' : 'lazy';
            $fetchpriority = $idx < 3 ? 'fetchpriority="high"' : '';
          ?>
          <div class="manifesto-slide">
            <div class="manifesto-embed"
              @click="openManifestoPlayer(<?= htmlspecialchars(json_encode($mvUrl), ENT_QUOTES) ?>)">
              <?php if ($mvThumb): ?>
                <img src="<?= htmlspecialchars($mvThumb) ?>" alt="<?= htmlspecialchars($mvTitle ?: 'Video ' . ($idx+1)) ?>" loading="<?= $loading ?>" decoding="async" <?= $fetchpriority ?> style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <div style="position:absolute;inset:0;background:#1a1a1a;display:flex;align-items:center;justify-content:center">
                  <svg width="32" height="32" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </div>
              <?php endif; ?>
              <div class="manifesto-play-overlay">
                <div class="manifesto-play-circle">
                  <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
              </div>
            </div>
            <?php if ($mvTitle): ?>
            <div class="manifesto-title"><?= htmlspecialchars($mvTitle) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div><!-- /manifesto-track -->
        </div><!-- /clip wrapper -->
      </div><!-- /manifesto-slider -->
      <div class="manifesto-dots">
        <template x-for="i in totalPages" :key="i">
          <div class="manifesto-dot" :class="i-1===currentPage?'active':''" @click="goTo(i-1)"></div>
        </template>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ ROW 2: ROLE BOXES ══ -->
    <div style="text-align:center;margin-bottom:2rem;padding-top:0">
      <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(32px,5vw,56px);letter-spacing:.02em;line-height:.95;color:#111">
        <?= htmlspecialchars($rolesHeading) ?>
      </h2>
    </div>
    <div class="role-cards-grid grid grid-cols-3 gap-4 sm:gap-6" style="align-items:stretch">

      <!-- WRITER -->
      <div class="role-card" style="height:100%">
        <?php if (!empty($roles['writer']['icon'])): ?>
        <div class="role-icon"><?= htmlspecialchars($roles['writer']['icon']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['writer']['title'])): ?>
        <p class="role-name"><?= htmlspecialchars($roles['writer']['title']) ?></p>
        <?php endif; ?>
        <?php if (!empty($roles['writer']['description'])): ?>
        <div class="role-desc"><?= format_text_content($roles['writer']['description']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['writer']['badge1']) || !empty($roles['writer']['badge2'])): ?>
        <div class="role-badges">
          <?php if (!empty($roles['writer']['badge1']) && trim($roles['writer']['badge1']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['writer']['badge1']) ?></span>
          <?php endif; ?>
          <?php if (!empty($roles['writer']['badge2']) && trim($roles['writer']['badge2']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['writer']['badge2']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($roles['writer']['button_text']) && !empty($roles['writer']['button_url'])): ?>
        <a href="<?= htmlspecialchars($roles['writer']['button_url']) ?>" class="btn-black"><?= htmlspecialchars($roles['writer']['button_text']) ?></a>
        <?php endif; ?>
      </div>

      <!-- DIRECTOR -->
      <div class="role-card" style="height:100%">
        <?php if (!empty($roles['director']['icon'])): ?>
        <div class="role-icon"><?= htmlspecialchars($roles['director']['icon']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['director']['title'])): ?>
        <p class="role-name"><?= htmlspecialchars($roles['director']['title']) ?></p>
        <?php endif; ?>
        <?php if (!empty($roles['director']['description'])): ?>
        <div class="role-desc"><?= format_text_content($roles['director']['description']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['director']['badge1']) || !empty($roles['director']['badge2'])): ?>
        <div class="role-badges">
          <?php if (!empty($roles['director']['badge1']) && trim($roles['director']['badge1']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['director']['badge1']) ?></span>
          <?php endif; ?>
          <?php if (!empty($roles['director']['badge2']) && trim($roles['director']['badge2']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['director']['badge2']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($roles['director']['button_text']) && !empty($roles['director']['button_url'])): ?>
        <a href="<?= htmlspecialchars($roles['director']['button_url']) ?>" class="btn-black"><?= htmlspecialchars($roles['director']['button_text']) ?></a>
        <?php endif; ?>
      </div>

      <!-- ACTOR -->
      <div class="role-card" style="height:100%">
        <?php if (!empty($roles['actor']['icon'])): ?>
        <div class="role-icon"><?= htmlspecialchars($roles['actor']['icon']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['actor']['title'])): ?>
        <p class="role-name"><?= htmlspecialchars($roles['actor']['title']) ?></p>
        <?php endif; ?>
        <?php if (!empty($roles['actor']['description'])): ?>
        <div class="role-desc"><?= format_text_content($roles['actor']['description']) ?></div>
        <?php endif; ?>
        <?php if (!empty($roles['actor']['badge1']) || !empty($roles['actor']['badge2'])): ?>
        <div class="role-badges">
          <?php if (!empty($roles['actor']['badge1']) && trim($roles['actor']['badge1']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['actor']['badge1']) ?></span>
          <?php endif; ?>
          <?php if (!empty($roles['actor']['badge2']) && trim($roles['actor']['badge2']) !== ''): ?>
          <span class="badge"><?= htmlspecialchars($roles['actor']['badge2']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($roles['actor']['button_text']) && !empty($roles['actor']['button_url'])): ?>
        <a href="<?= htmlspecialchars($roles['actor']['button_url']) ?>" class="btn-black"><?= htmlspecialchars($roles['actor']['button_text']) ?></a>
        <?php endif; ?>
      </div>

    </div>

    </div><!-- /poster+role section -->
  </div><!-- /max-w -->
</main>

<!-- ── MARQUEE ── -->
<?php if (!empty($marqueeItems)): ?>
<div class="marquee-wrap overflow-hidden border-y border-gray-100 py-3 bg-gray-50">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
    <div class="flex items-center gap-6 px-4">
      <?php foreach ($marqueeItems as $item): ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:12px;letter-spacing:.18em;color:#9ca3af"><?= htmlspecialchars($item) ?></span>
        <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;flex-shrink:0"></span>
      <?php endforeach; ?>
    </div>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── ABOUT ── -->
<section id="about" class="py-16 px-4 bg-white border-t border-gray-100">
  <div class="max-w-3xl mx-auto text-center">
    <?php if (!empty($aboutSectionLabel)): ?>
    <p style="font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#9ca3af;margin-bottom:.75rem"><?= htmlspecialchars($aboutSectionLabel) ?></p>
    <?php endif; ?>
    <?php if (!empty($aboutSectionHeading)): ?>
    <h2 style="font-family:'Bebas Neue',sans-serif;font-size:clamp(36px,5vw,52px);letter-spacing:.02em;color:#111;margin-bottom:1rem"><?= htmlspecialchars($aboutSectionHeading) ?></h2>
    <?php endif; ?>
    <?php if (!empty($aboutText)): ?>
    <div style="color:#6b7280;font-size:.95rem;line-height:1.75;max-width:600px;margin:0 auto"><?= format_text_content($aboutText) ?></div>
    <?php endif; ?>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer class="fp-footer">
  <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-6">
    <a href="/" style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= (int)$logoHeight ?>px;width:auto" loading="eager" fetchpriority="high">
      <?php else: ?>
        FACELESS PICTURES
        <span style="background:#111;color:#fff;font-size:10px;font-weight:700;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center">3</span>
      <?php endif; ?>
    </a>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;justify-content:center">
      <?php 
      try {
        $footerMenuItems = $settingsModel->getFooterMenuItems();
        foreach ($footerMenuItems as $item) {
          if (!empty($item['text']) && !empty($item['url'])) {
            echo '<a href="' . htmlspecialchars($item['url']) . '" style="color:#6b7280;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color=\'#111\'" onmouseout="this.style.color=\'#6b7280\'">' . htmlspecialchars($item['text']) . '</a>';
          }
        }
      } catch (Exception $e) {
        // Fallback menu if settings fail
        echo '<a href="/actor" style="color:#6b7280;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color=\'#111\'" onmouseout="this.style.color=\'#6b7280\'">Actors</a>';
        echo '<a href="/director" style="color:#6b7280;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color=\'#111\'" onmouseout="this.style.color=\'#6b7280\'">Directors</a>';
        echo '<a href="/writer" style="color:#6b7280;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color=\'#111\'" onmouseout="this.style.color=\'#6b7280\'">Writers</a>';
        echo '<a href="#about" style="color:#6b7280;font-size:.8rem;text-decoration:none;transition:color .2s" onmouseover="this.style.color=\'#111\'" onmouseout="this.style.color=\'#6b7280\'">About</a>';
      }
      ?>
    </div>
    <p style="color:#9ca3af;font-size:.75rem">© <?= date('Y') ?> Faceless Pictures. All rights reserved.</p>
  </div>
</footer>

<!-- ══ CUSTOM VIDEO PLAYER MODAL ══ -->
<div x-show="playerOpen" x-cloak class="modal-bg"
  @click.self="closePlayer()"
  x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
  x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

  <div class="vp-wrap" :class="{paused: !playing, fullscreen: isFullscreen}" x-ref="vpWrap"
    @mousemove="showControls()" @mouseleave="scheduleHide()">

    <!-- Title bar -->
    <div class="vp-title" x-text="playerTitle"></div>

    <!-- Close button -->
    <button class="vp-close" @click="closePlayer()" aria-label="Close">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <!-- Video element -->
    <video x-ref="video" @timeupdate="onTimeUpdate()" @loadedmetadata="onMeta()"
      @ended="playing=false" @waiting="buffering=true" @playing="buffering=false"
      @progress="onProgress()" @click="togglePlay()"
      preload="metadata" playsinline
      style="display:block;width:100%;max-height:80vh;background:#000;cursor:pointer">
    </video>

    <!-- Big centre play/pause indicator -->
    <div class="vp-bigplay">
      <div class="vp-bigplay-btn">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      </div>
    </div>

    <!-- Buffering spinner -->
    <div x-show="buffering" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none">
      <svg class="animate-spin" width="40" height="40" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="3"></circle>
        <path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
      </svg>
    </div>

    <!-- Controls -->
    <div class="vp-controls" x-ref="controls">

      <!-- Scrubber -->
      <div class="vp-progress" x-ref="progress"
        @click="seek($event)"
        @mousemove="hoverScrub($event)"
        @mouseleave="hoverTime=''">
        <div class="vp-buf" :style="'width:'+buffered+'%'"></div>
        <div class="vp-played" :style="'width:'+pct+'%'">
          <div class="vp-thumb"></div>
        </div>
        <div class="vp-tooltip" :style="'left:'+hoverPct+'%'" x-text="hoverTime"></div>
      </div>

      <!-- Bottom row -->
      <div class="vp-row">

        <!-- Play/Pause -->
        <button class="vp-btn" @click="togglePlay()" :aria-label="playing?'Pause':'Play'">
          <template x-if="!playing">
            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </template>
          <template x-if="playing">
            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
          </template>
        </button>

        <!-- Rewind 10s -->
        <button class="vp-btn" @click="skip(-10)" aria-label="Rewind 10s">
          <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m0-8A9 9 0 1 0 6.168 6.168"/>
            <text x="8.5" y="15" font-size="5" fill="currentColor" stroke="none" font-family="sans-serif" font-weight="700">10</text>
          </svg>
        </button>

        <!-- Skip 10s -->
        <button class="vp-btn" @click="skip(10)" aria-label="Skip 10s">
          <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l-4 2m0-8A9 9 0 1 1 17.832 6.168"/>
            <text x="8.5" y="15" font-size="5" fill="currentColor" stroke="none" font-family="sans-serif" font-weight="700">10</text>
          </svg>
        </button>

        <!-- Time -->
        <span class="vp-time" x-text="currentTime+' / '+duration"></span>

        <!-- Spacer -->
        <div style="flex:1"></div>

        <!-- Volume -->
        <div class="vp-vol-wrap">
          <button class="vp-btn" @click="toggleMute()" :aria-label="muted?'Unmute':'Mute'">
            <template x-if="muted || volume===0">
              <svg fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
            </template>
            <template x-if="!muted && volume > 0">
              <svg fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/></svg>
            </template>
          </button>
          <input type="range" class="vp-vol-slider" min="0" max="1" step="0.05"
            :value="muted ? 0 : volume"
            @input="setVolume($event.target.value)"
            aria-label="Volume">
        </div>

        <!-- Playback speed -->
        <div style="position:relative" x-data="{speedOpen:false}">
          <button class="vp-btn" @click="speedOpen=!speedOpen" style="font-size:.72rem;font-weight:700;width:auto;padding:4px 6px;letter-spacing:.02em" x-text="speed+'x'"></button>
          <div x-show="speedOpen" x-cloak @click.away="speedOpen=false"
            style="position:absolute;bottom:calc(100% + 8px);right:0;background:#1a1a1a;border:1px solid rgba(255,255,255,.12);border-radius:8px;overflow:hidden;min-width:72px">
            <?php foreach ([0.5, 0.75, 1, 1.25, 1.5, 2] as $s): ?>
            <button @click="setSpeed(<?= $s ?>);speedOpen=false"
              :class="speed===<?= $s ?>?'bg-white/20':'hover:bg-white/10'"
              style="display:block;width:100%;text-align:center;padding:.375rem .75rem;color:#fff;font-size:.75rem;font-weight:600;border:none;cursor:pointer;transition:background .15s">
              <?= $s ?>x
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Fullscreen -->
        <button class="vp-btn" @click="toggleFullscreen()" aria-label="Fullscreen">
          <svg class="icon-fs-enter" fill="currentColor" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
          <svg class="icon-fs-exit" fill="currentColor" viewBox="0 0 24 24"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>
        </button>

      </div><!-- /vp-row -->
    </div><!-- /vp-controls -->
  </div><!-- /vp-wrap -->
</div><!-- /modal-bg -->

<script>
function homePage() {
    return {
        // Player state
        playerOpen:  false,
        playerSrc:   '',
        playerTitle: '',
        playing:     false,
        buffering:   false,
        pct:         0,
        buffered:    0,
        currentTime: '0:00',
        duration:    '0:00',
        volume:      1,
        muted:       false,
        speed:       1,
        isFullscreen:false,
        hoverPct:    0,
        hoverTime:   '',
        _hideTimer:  null,

        init() {
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
        },

        openPlayer(src, title) {
            if (!src || src.trim() === '') {
                // No trailer set — show a gentle toast instead of silently doing nothing
                const toast = document.getElementById('fp-no-trailer-toast');
                if (toast) {
                    toast.classList.add('show');
                    clearTimeout(toast._t);
                    toast._t = setTimeout(() => toast.classList.remove('show'), 3000);
                }
                return;
            }
            // YouTube URL → use the manifesto YouTube modal instead of local player
            if (/youtu(\.be|be\.com)/i.test(src)) {
                document.dispatchEvent(new CustomEvent('fp-open-yt', { detail: { url: src } }));
                return;
            }
            this.playerSrc   = src;
            this.playerTitle = title || '';
            this.playerOpen  = true;
            this.$nextTick(() => {
                const v = this.$refs.video;
                v.src     = src;
                v.volume  = this.volume;
                v.muted   = this.muted;
                v.playbackRate = this.speed;
                v.play().catch(() => {});
                this.playing = true;
            });
        },

        closePlayer() {
            const v = this.$refs.video;
            if (v) { v.pause(); v.src = ''; }
            this.playerOpen  = false;
            this.playing     = false;
            this.pct         = 0;
            this.buffered    = 0;
            this.currentTime = '0:00';
            this.duration    = '0:00';
            if (this.isFullscreen) document.exitFullscreen?.();
        },

        togglePlay() {
            const v = this.$refs.video;
            if (!v) return;
            if (v.paused) { v.play(); this.playing = true; }
            else          { v.pause(); this.playing = false; }
        },

        onTimeUpdate() {
            const v = this.$refs.video;
            if (!v || !v.duration) return;
            this.pct         = (v.currentTime / v.duration) * 100;
            this.currentTime = this.fmt(v.currentTime);
        },

        onMeta() {
            const v = this.$refs.video;
            this.duration = this.fmt(v.duration);
        },

        onProgress() {
            const v = this.$refs.video;
            if (!v.buffered.length || !v.duration) return;
            this.buffered = (v.buffered.end(v.buffered.length - 1) / v.duration) * 100;
        },

        seek(e) {
            const v = this.$refs.video;
            const r = this.$refs.progress.getBoundingClientRect();
            const p = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
            v.currentTime = p * v.duration;
        },

        hoverScrub(e) {
            const v = this.$refs.video;
            if (!v?.duration) return;
            const r = this.$refs.progress.getBoundingClientRect();
            const p = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
            this.hoverPct  = p * 100;
            this.hoverTime = this.fmt(p * v.duration);
        },

        skip(s) {
            const v = this.$refs.video;
            if (!v) return;
            v.currentTime = Math.max(0, Math.min(v.duration, v.currentTime + s));
        },

        toggleMute() {
            const v = this.$refs.video;
            this.muted = v.muted = !v.muted;
        },

        setVolume(val) {
            const v = this.$refs.video;
            this.volume = v.volume = parseFloat(val);
            this.muted  = v.muted  = this.volume === 0;
        },

        setSpeed(s) {
            this.speed = s;
            if (this.$refs.video) this.$refs.video.playbackRate = s;
        },

        toggleFullscreen() {
            const el = this.$refs.vpWrap;
            if (!document.fullscreenElement) {
                el.requestFullscreen?.() || el.webkitRequestFullscreen?.();
            } else {
                document.exitFullscreen?.();
            }
        },

        showControls() {
            clearTimeout(this._hideTimer);
            if (this.$refs.controls) this.$refs.controls.style.opacity = '1';
        },
        scheduleHide() {
            if (this.playing) {
                this._hideTimer = setTimeout(() => {
                    if (this.$refs.controls) this.$refs.controls.style.opacity = '0';
                }, 2000);
            }
        },

        fmt(s) {
            if (!s || isNaN(s)) return '0:00';
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60).toString().padStart(2, '0');
            return m + ':' + sec;
        }
    };
}

function posterSlider(total) {
    const perPage = window.innerWidth < 640 ? 1 : 3;
    return {
        total,
        perPage: window.innerWidth < 640 ? 1 : 3,
        currentPage: 0,
        offset: 0,
        get pages()   { return Math.ceil(this.total / this.perPage); },
        get canPrev() { return this.currentPage > 0; },
        get canNext() { return this.currentPage < this.pages - 1; },
        goTo(i) {
            this.currentPage = i;
            this._update();
        },
        prev() { if (this.canPrev) { this.currentPage--; this._update(); } },
        next() { if (this.canNext) { this.currentPage++; this._update(); } },
        _update() {
            const track = document.querySelector('.poster-track');
            if (!track) return;
            const card = track.children[0];
            if (!card) return;
            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.gap) || 16;
            const cardW = card.getBoundingClientRect().width + gap;
            this.offset = this.currentPage * this.perPage * cardW;
        }
    };
}

function manifestoSlider(total) {
    const perPage = () => window.innerWidth >= 1024 ? 3 : 1;
    return {
        currentPage: 0,
        autoTimer: null,
        get totalPages() { return Math.ceil(total / perPage()); },
        get offset() {
            // Calculate pixel offset: each "page" moves by perPage slides
            const wrap = document.querySelector('.manifesto-slider');
            if (!wrap) return 0;
            const pp = perPage();
            const gap = 20; // 1.25rem gap
            const slideW = (wrap.offsetWidth - (pp - 1) * gap) / pp;
            return this.currentPage * pp * (slideW + gap);
        },
        init() {
            this.startAuto();
            window.addEventListener('resize', () => { this.goTo(0); });
        },
        goTo(page) {
            const tp = this.totalPages;
            this.currentPage = ((page % tp) + tp) % tp;
        },
        next() { this.goTo(this.currentPage + 1); },
        startAuto() {
            const interval = window.innerWidth >= 1024 ? 4500 : 2500;
            this.autoTimer = setInterval(() => this.next(), interval);
        },
        openManifestoPlayer(url) {
            document.dispatchEvent(new CustomEvent('fp-open-yt', { detail: { url } }));
        }
    };
}
</script>

<!-- Initialize Plyr for Hero Trailer -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const heroPlayer = document.getElementById('hero-trailer-player');
    const skeleton = document.getElementById('hero-trailer-skeleton');
    
    if (heroPlayer) {
        const player = new Plyr(heroPlayer, {
            controls: ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'settings', 'fullscreen'],
            autoplay: true,
            muted: true,
            loop: { active: true },
            hideControls: true,
            resetOnEnd: false,
            settings: ['quality', 'speed'],
            quality: {
                default: 720,
                options: [1080, 720, 576, 480, 360]
            }
        });
        
        // Hide skeleton and show video when ready
        player.on('ready', () => {
            if (skeleton) {
                skeleton.style.display = 'none';
            }
            heroPlayer.style.opacity = '1';
            
            player.play().catch(() => {
                console.log('Autoplay blocked by browser');
            });
        });
        
        // Also handle canplay event as fallback
        heroPlayer.addEventListener('canplay', () => {
            if (skeleton) {
                skeleton.style.display = 'none';
            }
            heroPlayer.style.opacity = '1';
        });
    }
});
</script>

<!-- ══ MANIFESTO YOUTUBE MODAL ══ -->
<div id="fp-manifesto-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);backdrop-filter:blur(12px);z-index:250;align-items:center;justify-content:center;padding:1rem">
  <div style="position:relative;width:100%;max-width:960px;background:#000;border-radius:14px;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.8)">
    <button onclick="fpCloseManifesto()" aria-label="Close"
      style="position:absolute;top:.75rem;right:.75rem;width:36px;height:36px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;z-index:10;backdrop-filter:blur(4px)">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div style="position:relative;padding-bottom:56.25%;height:0">
      <iframe id="fp-manifesto-iframe" src="" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen
        style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
    </div>
  </div>
</div>
<script>
function fpYtEmbed(u){
    if(!u)return'';
    var m;
    m=u.match(/youtu\.be\/([^?&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    m=u.match(/[?&]v=([^&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    m=u.match(/\/shorts\/([^?&#]+)/);if(m)return'https://www.youtube.com/embed/'+m[1];
    return u;
}
document.addEventListener('fp-open-yt', function(e){
    var modal = document.getElementById('fp-manifesto-modal');
    var iframe = document.getElementById('fp-manifesto-iframe');
    iframe.src = fpYtEmbed(e.detail.url) + '?autoplay=1&rel=0';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
});
function fpCloseManifesto(){
    document.getElementById('fp-manifesto-iframe').src = '';
    document.getElementById('fp-manifesto-modal').style.display = 'none';
    document.body.style.overflow = '';
}
document.getElementById('fp-manifesto-modal').addEventListener('click', function(e){
    if(e.target === this) fpCloseManifesto();
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') fpCloseManifesto(); });
</script>
<!-- NO-TRAILER TOAST -->
<div id="fp-no-trailer-toast">
  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
  Trailer not available yet
</div>

<!-- ══ LANGUAGE SWITCHER ══ -->
<?php include __DIR__ . '/partials/language-switcher.php'; ?>

<!-- ══ LAZY LOADING FIX ══ -->
<script>
// Progressive image loading - load in batches to avoid overwhelming slow connections
document.addEventListener('DOMContentLoaded', function() {
    const lazyImages = Array.from(document.querySelectorAll('img[loading="lazy"]'));
    const eagerImages = Array.from(document.querySelectorAll('img[loading="eager"]'));
    
    // Load lazy images in batches of 3
    const batchSize = 3;
    let currentBatch = 0;
    
    function loadNextBatch() {
        const start = currentBatch * batchSize;
        const end = start + batchSize;
        const batch = lazyImages.slice(start, end);
        
        if (batch.length === 0) return;
        
        batch.forEach(img => {
            if (img.src) {
                const src = img.src;
                img.src = '';
                img.src = src;
            }
        });
        
        currentBatch++;
        setTimeout(loadNextBatch, 1000);
    }
    
    // IntersectionObserver for viewport-based loading
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.src && img.loading === 'lazy') {
                        const src = img.src;
                        img.src = '';
                        img.src = src;
                    }
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '100px'
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        setTimeout(loadNextBatch, 2000);
    }
});
</script>

</body>
</html>
>
<?php
// Save rendered page to cache for next request (non-logged-in users only)
if (!isset($_SESSION['user_id'])) {
    $pageContent = ob_get_contents();
    PageCache::set($cacheKey, $pageContent);
    header('X-Cache: MISS');
}
ob_end_flush();
?>
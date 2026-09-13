<?php
// Frontend Navigation - Desktop menu + Mobile hamburger (v2 - clean rewrite)
$settingsModel = $settingsModel ?? new App\Models\Settings();
$logoUrl = $logoUrl ?? $settingsModel->get('site_logo_url', '');
$logoHeight = $logoHeight ?? $settingsModel->get('site_logo_height', '44');
$headerHeight = (int)$logoHeight + 16;

try {
    $headerMenuItems = $settingsModel->getHeaderMenuItemsGrouped();
} catch (\Exception $e) {
    $headerMenuItems = [
        'left' => [
            ['text' => 'About', 'url' => '/#about', 'order' => 1],
            ['text' => 'Writers', 'url' => '/writer', 'order' => 2],
        ],
        'right' => [
            ['text' => 'Directors', 'url' => '/director', 'order' => 3],
            ['text' => 'Actors', 'url' => '/actor', 'order' => 4],
        ]
    ];
}

$allMenuItems = array_merge($headerMenuItems['left'], $headerMenuItems['right']);
usort($allMenuItems, fn($a, $b) => $a['order'] <=> $b['order']);

$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
?>

<!-- ═══════════════════════════════════════════════════════════
     DESKTOP NAVIGATION BAR (unchanged from before)
     ═══════════════════════════════════════════════════════════ -->
<nav class="fp-nav" style="height:<?= $headerHeight ?>px">
  <div class="fp-nav-container">
    <div class="desktop-menu desktop-menu-left">
      <?php foreach ($headerMenuItems['left'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link"><?= htmlspecialchars($item['text']) ?></a>
      <?php endforeach; ?>
    </div>
    
    <a href="/" class="nav-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= (int)$logoHeight ?>px;width:auto">
      <?php else: ?>
        <span class="logo-text">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    
    <div class="desktop-menu desktop-menu-right">
      <?php foreach ($headerMenuItems['right'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link"><?= htmlspecialchars($item['text']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     MOBILE MENU v2 - Brand new, super simple, uses native <details>
     ═══════════════════════════════════════════════════════════ -->
<?php $mmv2ButtonTop = max(8, intval(($headerHeight - 44) / 2)); ?>
<div id="mmv2-wrap">
  <button id="mmv2-open" type="button" aria-label="Open menu" style="top:<?= $mmv2ButtonTop ?>px" onclick="document.getElementById('mmv2-drawer').classList.add('open');document.getElementById('mmv2-back').classList.add('open');">
    <span></span><span></span><span></span>
  </button>
  
  <div id="mmv2-back" onclick="this.classList.remove('open');document.getElementById('mmv2-drawer').classList.remove('open');"></div>
  
  <div id="mmv2-drawer">
    <div id="mmv2-head">
      <a href="/" style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px">
        <?php if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="height:32px;width:auto">
        <?php else: ?>
          FACELESS PICTURES<span class="nav-badge">3</span>
        <?php endif; ?>
      </a>
      <button type="button" aria-label="Close menu" onclick="document.getElementById('mmv2-drawer').classList.remove('open');document.getElementById('mmv2-back').classList.remove('open');" style="background:transparent;border:none;font-size:28px;color:#111;cursor:pointer;padding:4px 8px;line-height:1">×</button>
    </div>
    
    <div id="mmv2-links">
      <?php foreach ($allMenuItems as $item): 
        $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '/';
        $isActive = ($itemPath === $currentPath) || ($currentPath === '/home.php' && $itemPath === '/');
      ?>
        <a href="<?= htmlspecialchars($item['url']) ?>"<?= $isActive ? ' class="active"' : '' ?>>
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════
   DESKTOP NAV STYLES (unchanged)
   ═══════════════════════════════════════════════════════════ */
.fp-nav {
  background: rgba(255, 255, 255, 0.97);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid #e5e7eb;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 50;
}
.fp-nav-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 1rem;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
  position: relative;
}
.nav-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 19px;
  letter-spacing: 0.06em;
  color: #111;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.logo-text {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 20px;
  letter-spacing: 0.06em;
  color: #111;
}
.nav-badge {
  background: #111;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  width: 19px;
  height: 19px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.nav-link {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #6b7280;
  text-decoration: none;
  transition: color 0.2s;
  white-space: nowrap;
}
.nav-link:hover { color: #111; }
.desktop-menu {
  display: none;
  align-items: center;
  gap: 1.25rem;
}
@media (min-width: 1024px) {
  .desktop-menu { display: flex; }
}

/* ═══════════════════════════════════════════════════════════
   MOBILE MENU v2 - Isolated, unique IDs, simple JS onclick
   ═══════════════════════════════════════════════════════════ */

/* Hide everything mobile on desktop */
#mmv2-wrap { display: none; }

/* Only show on mobile */
@media (max-width: 1023px) {
  #mmv2-wrap { display: block; }
}

/* Open button (hamburger) - floating fixed position, above everything */
#mmv2-open {
  position: fixed;
  top: 10px;
  left: 12px;
  z-index: 99996;
  width: 44px;
  height: 44px;
  background: rgba(255,255,255,0.95);
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  padding: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  transition: opacity 0.2s;
}

#mmv2-open span {
  display: block;
  width: 20px;
  height: 2px;
  background: #111;
  border-radius: 2px;
  transition: transform 0.2s;
  pointer-events: none;
}

#mmv2-open:active {
  background: #f3f4f6;
}

/* Back overlay */
#mmv2-back {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 99997;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s;
}

#mmv2-back.open {
  opacity: 1;
  pointer-events: auto;
}

/* Drawer */
#mmv2-drawer {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 280px;
  max-width: 85vw;
  background: #fff;
  z-index: 99998;
  transform: translateX(-100%);
  transition: transform 0.25s;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 20px rgba(0,0,0,0.15);
  pointer-events: none;
}

#mmv2-drawer.open {
  transform: translateX(0);
  pointer-events: auto;
}

#mmv2-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  min-height: 68px;
}

#mmv2-head a img {
  height: 44px !important;
  width: auto;
}

#mmv2-head a {
  font-size: 22px !important;
}

#mmv2-links {
  flex: 1;
  overflow-y: auto;
  padding: 12px 0;
}

#mmv2-links a {
  display: block;
  padding: 14px 20px;
  color: #374151;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: background 0.15s, color 0.15s;
}

#mmv2-links a:hover {
  background: rgba(0,0,0,0.05);
  color: #111;
}

#mmv2-links a.active {
  background: #111;
  color: #fff;
  font-weight: 600;
  border-left-color: #d92b3a;
}

/* Hide on desktop */
@media (min-width: 1024px) {
  #mmv2-open, #mmv2-back, #mmv2-drawer { display: none !important; }
}
</style>

<script>
// Only script needed: close menu on link click + BFCache reset
(function(){
  var drawer = document.getElementById('mmv2-drawer');
  var back = document.getElementById('mmv2-back');
  if (!drawer || !back) return;
  
  // Close on any link click inside drawer
  var links = drawer.querySelectorAll('a');
  for (var i = 0; i < links.length; i++) {
    links[i].addEventListener('click', function(){
      drawer.classList.remove('open');
      back.classList.remove('open');
    });
  }
  
  // Reset on page show
  window.addEventListener('pageshow', function(){
    drawer.classList.remove('open');
    back.classList.remove('open');
  });
})();
</script>

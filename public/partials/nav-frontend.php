<?php
// Frontend Navigation - Used across home, actor, director, writer pages
$settingsModel = $settingsModel ?? new App\Models\Settings();
$logoUrl = $logoUrl ?? $settingsModel->get('site_logo_url', '');
$logoHeight = $logoHeight ?? $settingsModel->get('site_logo_height', '44');

// Calculate header height with padding (logo height + 16px padding top/bottom)
$headerHeight = (int)$logoHeight + 16;

// Get customizable header menu items (grouped by left/right position)
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

// Merge all menu items for mobile
$allMenuItems = array_merge($headerMenuItems['left'], $headerMenuItems['right']);
usort($allMenuItems, fn($a, $b) => $a['order'] <=> $b['order']);

$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
?>

<!-- ═══════════════════════════════════════════════════════════
     DESKTOP NAVIGATION (unchanged)
     ═══════════════════════════════════════════════════════════ -->
<nav class="fp-nav" style="height:<?= $headerHeight ?>px">
  <div class="fp-nav-container">
    
    <!-- LEFT MENU ITEMS (Desktop) -->
    <div class="desktop-menu desktop-menu-left">
      <?php foreach ($headerMenuItems['left'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    
    <!-- CENTERED LOGO -->
    <a href="/" class="nav-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= (int)$logoHeight ?>px;width:auto">
      <?php else: ?>
        <span class="logo-text">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    
    <!-- RIGHT MENU ITEMS (Desktop) -->
    <div class="desktop-menu desktop-menu-right">
      <?php foreach ($headerMenuItems['right'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════
     MOBILE MENU (CSS-only, uses checkbox trick - ALWAYS WORKS)
     ═══════════════════════════════════════════════════════════ -->
<input type="checkbox" id="fp-menu-toggle" class="fp-menu-toggle-input">

<!-- Hamburger button (label) -->
<label for="fp-menu-toggle" class="fp-hamburger" aria-label="Open menu">
  <span class="fp-hamburger-line"></span>
  <span class="fp-hamburger-line"></span>
  <span class="fp-hamburger-line"></span>
</label>

<!-- Overlay (label that closes menu) -->
<label for="fp-menu-toggle" class="fp-mobile-overlay" aria-label="Close menu"></label>

<!-- Mobile menu drawer -->
<aside class="fp-mobile-menu">
  <div class="fp-mobile-header">
    <a href="/" class="fp-mobile-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3">
      <?php else: ?>
        <span>FACELESS PICTURES</span><span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    <label for="fp-menu-toggle" class="fp-mobile-close" aria-label="Close menu">×</label>
  </div>
  
  <nav class="fp-mobile-nav">
    <?php foreach ($allMenuItems as $item): 
      $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '/';
      $isActive = ($itemPath === $currentPath) || ($currentPath === '/home.php' && $itemPath === '/');
    ?>
      <a href="<?= htmlspecialchars($item['url']) ?>" class="fp-mobile-link<?= $isActive ? ' active' : '' ?>">
        <?= htmlspecialchars($item['text']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>

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

.nav-link:hover {
  color: #111;
}

.desktop-menu {
  display: none;
  align-items: center;
  gap: 1.25rem;
}

@media (min-width: 1024px) {
  .desktop-menu {
    display: flex;
  }
}

/* ═══════════════════════════════════════════════════════════
   MOBILE MENU (CSS-only, checkbox toggle)
   ═══════════════════════════════════════════════════════════ */

/* Hide checkbox */
.fp-menu-toggle-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
  width: 0;
  height: 0;
}

/* Hamburger button - fixed top-left */
.fp-hamburger {
  display: none;
  position: fixed;
  top: 12px;
  left: 12px;
  z-index: 60;
  width: 44px;
  height: 44px;
  cursor: pointer;
  background: transparent;
  border-radius: 8px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
}

.fp-hamburger-line {
  display: block;
  width: 22px;
  height: 2px;
  background: #111;
  border-radius: 2px;
  transition: transform 0.25s ease, opacity 0.2s ease;
}

.fp-hamburger:hover {
  background: rgba(0, 0, 0, 0.05);
}

/* Mobile overlay - hidden by default */
.fp-mobile-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 998;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.3s ease;
}

/* Mobile menu drawer - hidden by default */
.fp-mobile-menu {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 280px;
  max-width: 85vw;
  background: #fff;
  z-index: 999;
  box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15);
  transform: translateX(-100%);
  transition: transform 0.3s ease;
  flex-direction: column;
}

.fp-mobile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.fp-mobile-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 18px;
  letter-spacing: 0.06em;
  color: #111;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 6px;
}

.fp-mobile-logo img {
  height: 36px;
  width: auto;
}

.fp-mobile-close {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: #111;
  cursor: pointer;
  border-radius: 8px;
  line-height: 1;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
}

.fp-mobile-close:hover {
  background: rgba(0, 0, 0, 0.05);
}

.fp-mobile-nav {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0;
}

.fp-mobile-link {
  display: block;
  padding: 0.9rem 1.5rem;
  color: #374151;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: all 0.2s;
}

.fp-mobile-link:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #111;
  border-left-color: #d92b3a;
}

.fp-mobile-link.active {
  background: #111;
  color: #fff;
  font-weight: 600;
  border-left-color: #d92b3a;
}

/* Show mobile menu only on mobile (< 1024px) */
@media (max-width: 1023px) {
  .fp-hamburger {
    display: flex;
  }
  
  .fp-mobile-overlay {
    display: block;
    visibility: hidden;
  }
  
  .fp-mobile-menu {
    display: flex;
  }
  
  /* When checkbox is CHECKED, show menu and overlay */
  .fp-menu-toggle-input:checked ~ .fp-mobile-overlay {
    visibility: visible;
    opacity: 1;
  }
  
  .fp-menu-toggle-input:checked ~ .fp-mobile-menu {
    transform: translateX(0);
  }
  
  /* Animate hamburger to X when checked */
  .fp-menu-toggle-input:checked ~ .fp-hamburger .fp-hamburger-line:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
  }
  
  .fp-menu-toggle-input:checked ~ .fp-hamburger .fp-hamburger-line:nth-child(2) {
    opacity: 0;
  }
  
  .fp-menu-toggle-input:checked ~ .fp-hamburger .fp-hamburger-line:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
  }
  
  /* Prevent body scroll when menu open */
  .fp-menu-toggle-input:checked ~ * {
    /* This is a hack - body scroll lock via JS instead */
  }
}

/* On desktop, hide everything mobile */
@media (min-width: 1024px) {
  .fp-hamburger,
  .fp-mobile-overlay,
  .fp-mobile-menu {
    display: none !important;
  }
}
</style>

<script>
// Minimal JS: only for closing menu on link click and body scroll lock
(function() {
  var toggle = document.getElementById('fp-menu-toggle');
  if (!toggle) return;
  
  // Body scroll lock when menu is open
  toggle.addEventListener('change', function() {
    if (this.checked) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  });
  
  // Close menu when clicking any menu link
  var links = document.querySelectorAll('.fp-mobile-link');
  for (var i = 0; i < links.length; i++) {
    links[i].addEventListener('click', function() {
      toggle.checked = false;
      document.body.style.overflow = '';
    });
  }
  
  // Reset on page show (BFCache safety)
  window.addEventListener('pageshow', function() {
    toggle.checked = false;
    document.body.style.overflow = '';
  });
})();
</script>

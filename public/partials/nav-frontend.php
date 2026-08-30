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
    // Fallback if method fails or database not updated yet
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
?>

<!-- Frontend Navigation Bar -->
<nav class="fp-nav" style="height:<?= $headerHeight ?>px">
  <div class="fp-nav-container">
    
    <!-- Mobile: Hamburger Button -->
    <button id="hamburger-btn" class="hamburger-btn" aria-label="Open menu">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    
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

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="sidebar-overlay"></div>

<!-- Mobile Sidebar -->
<div id="mobile-sidebar" class="mobile-sidebar">
  <div class="sidebar-header">
    <a href="/" class="nav-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= max(32, (int)$logoHeight * 0.75) ?>px;width:auto">
      <?php else: ?>
        <span class="logo-text" style="font-size:18px">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    <button id="close-btn" class="close-btn" aria-label="Close menu">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
  
  <div class="sidebar-menu">
    <?php foreach ($allMenuItems as $item): ?>
      <a href="<?= htmlspecialchars($item['url']) ?>" class="sidebar-link">
        <?= htmlspecialchars($item['text']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<style>
/* Navigation */
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

/* Desktop Menu */
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

/* Hamburger Button */
.hamburger-btn {
  position: absolute;
  left: 1rem;
  background: transparent;
  border: none;
  color: #111;
  padding: 0.5rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  transition: background 0.2s;
}

.hamburger-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

@media (min-width: 1024px) {
  .hamburger-btn {
    display: none;
  }
}

/* Sidebar Overlay */
.sidebar-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s, visibility 0.3s;
  z-index: 998;
}

.sidebar-overlay.active {
  opacity: 1;
  visibility: visible;
}

/* Mobile Sidebar */
.mobile-sidebar {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 280px;
  max-width: 85vw;
  background: #ffffff;
  box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15);
  transform: translateX(-100%);
  transition: transform 0.3s;
  z-index: 999;
  display: flex;
  flex-direction: column;
}

.mobile-sidebar.active {
  transform: translateX(0);
}

@media (min-width: 1024px) {
  .mobile-sidebar,
  .sidebar-overlay {
    display: none !important;
  }
}

/* Sidebar Header */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.close-btn {
  background: transparent;
  border: none;
  color: #111;
  padding: 0.5rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  transition: background 0.2s;
}

.close-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

/* Sidebar Menu */
.sidebar-menu {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0;
}

.sidebar-link {
  display: block;
  padding: 0.75rem 1.5rem;
  color: rgba(17, 17, 17, 0.7);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: all 0.2s;
}

.sidebar-link:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #111;
  border-left-color: #d92b3a;
}

.sidebar-link.active {
  background: #111;
  color: #fff;
  font-weight: 600;
  border-left-color: #d92b3a;
  margin: 0.5rem 0;
}

.sidebar-link.active:hover {
  background: #000;
}

/* Prevent body scroll when menu open */
body.menu-open {
  overflow: hidden;
}
</style>

<script>
(function() {
  // Get elements
  const hamburgerBtn = document.getElementById('hamburger-btn');
  const closeBtn = document.getElementById('close-btn');
  const sidebar = document.getElementById('mobile-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const sidebarLinks = document.querySelectorAll('.sidebar-link');
  
  // Set active link based on current page
  const currentPath = window.location.pathname;
  const isHomePage = currentPath === '/' || currentPath === '/home.php' || currentPath === '/index.php';
  
  sidebarLinks.forEach(link => {
    const linkHref = link.getAttribute('href');
    const linkUrl = new URL(link.href, window.location.origin);
    const linkPath = linkUrl.pathname;
    
    // Skip About link on non-home pages (don't show it as active)
    if (!isHomePage && linkHref.includes('#about')) {
      return; // Skip setting active, but still let the link work
    }
    
    // Exact match for pages
    if (currentPath === linkPath) {
      link.classList.add('active');
    }
  });
  
  // Open menu
  function openMenu() {
    sidebar.classList.add('active');
    overlay.classList.add('active');
    document.body.classList.add('menu-open');
  }
  
  // Close menu
  function closeMenu() {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    document.body.classList.remove('menu-open');
  }
  
  // Event listeners
  if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', openMenu);
  }
  
  if (closeBtn) {
    closeBtn.addEventListener('click', closeMenu);
  }
  
  if (overlay) {
    overlay.addEventListener('click', closeMenu);
  }
  
  // Close when clicking sidebar links
  sidebarLinks.forEach(link => {
    link.addEventListener('click', closeMenu);
  });
  
  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeMenu();
    }
  });
})();
</script>

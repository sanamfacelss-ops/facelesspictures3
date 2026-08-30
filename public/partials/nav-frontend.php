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
  <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-center gap-8 sm:gap-12 md:gap-16">
    
    <!-- Mobile: Hamburger Button (Left) -->
    <button onclick="openMobileMenu()" class="lg:hidden absolute left-4 text-dark p-2 hover:bg-dark/5 rounded-lg transition" style="z-index: 10;">
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
</nav>

<!-- Mobile Sidebar Overlay -->
<div id="mobileOverlay" class="mobile-overlay" onclick="closeMobileMenu()"></div>

<!-- Mobile Sidebar Menu -->
<aside id="mobileSidebar" class="mobile-sidebar">
  <div class="mobile-sidebar-inner">
    <!-- Sidebar Header -->
    <div class="mobile-sidebar-header">
      <a href="/" class="nav-logo">
        <?php if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= max(32, (int)$logoHeight * 0.75) ?>px;width:auto">
        <?php else: ?>
          <span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
          <span class="nav-badge">3</span>
        <?php endif; ?>
      </a>
      <button onclick="closeMobileMenu()" class="mobile-close-btn">
        <svg style="width: 20px; height: 20px; color: #111;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    
    <!-- Menu Items -->
    <nav class="mobile-sidebar-nav">
      <?php foreach ($allMenuItems as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</aside>

<style>
/* Frontend Navigation Styles */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50}
.fp-nav > div{position:relative}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s;white-space:nowrap}
.nav-link:hover{color:#111}

/* Mobile Sidebar Overlay */
.mobile-overlay{
  display:none;
  position:fixed;
  top:0;
  left:0;
  right:0;
  bottom:0;
  background:rgba(0,0,0,0.6);
  z-index:9998;
  opacity:0;
  transition:opacity 0.3s ease;
}
.mobile-overlay.active{
  display:block;
  opacity:1;
}

/* Mobile Sidebar */
.mobile-sidebar{
  display:none;
  position:fixed;
  top:0;
  left:0;
  bottom:0;
  width:280px;
  max-width:85vw;
  background:#ffffff;
  box-shadow:2px 0 20px rgba(0,0,0,0.3);
  z-index:9999;
  transform:translateX(-100%);
  transition:transform 0.3s ease;
}
.mobile-sidebar.active{
  display:block;
  transform:translateX(0);
}
.mobile-sidebar-inner{
  display:flex;
  flex-direction:column;
  height:100%;
  background:#ffffff;
}
.mobile-sidebar-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:1rem;
  border-bottom:1px solid rgba(0,0,0,0.1);
  background:#ffffff;
  flex-shrink:0;
}
.mobile-close-btn{
  padding:0.5rem;
  border-radius:0.5rem;
  background:transparent;
  border:none;
  cursor:pointer;
  transition:background 0.2s;
  display:flex;
  align-items:center;
  justify-content:center;
}
.mobile-close-btn:hover{
  background:rgba(0,0,0,0.05);
}
.mobile-sidebar-nav{
  flex:1;
  overflow-y:auto;
  overflow-x:hidden;
  padding:1rem 0;
  background:#ffffff;
}
.mobile-nav-link{
  display:block;
  padding:0.75rem 1.5rem;
  color:rgba(17,17,17,0.7);
  text-decoration:none;
  font-size:0.875rem;
  font-weight:500;
  border-left:3px solid transparent;
  transition:all 0.2s;
  background:#ffffff;
}
.mobile-nav-link:hover{
  background:rgba(0,0,0,0.05);
  color:#111;
  border-left-color:#D92B3A;
}

/* Prevent body scroll when sidebar is open */
body.menu-open{
  overflow:hidden;
}

/* Hide mobile menu on desktop */
@media (min-width: 1024px) {
  .mobile-overlay,
  .mobile-sidebar{
    display:none !important;
  }
}
</style>

<script>
function openMobileMenu() {
  document.getElementById('mobileOverlay').classList.add('active');
  document.getElementById('mobileSidebar').classList.add('active');
  document.body.classList.add('menu-open');
}

function closeMobileMenu() {
  document.getElementById('mobileOverlay').classList.remove('active');
  document.getElementById('mobileSidebar').classList.remove('active');
  document.body.classList.remove('menu-open');
}

// Close menu when clicking a link
document.addEventListener('DOMContentLoaded', function() {
  const menuLinks = document.querySelectorAll('.mobile-nav-link');
  menuLinks.forEach(link => {
    link.addEventListener('click', closeMobileMenu);
  });
});
</script>

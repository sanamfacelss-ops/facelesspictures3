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
<nav class="fp-nav" style="height:<?= $headerHeight ?>px" x-data="{ mobileMenuOpen: false }">
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
       style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 40;"
       class="lg:hidden"
       x-transition:enter="transition-opacity ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition-opacity ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">
  </div>
  
  <!-- Mobile Sidebar Menu -->
  <aside x-show="mobileMenuOpen"
         x-cloak
         @click.outside="mobileMenuOpen = false"
         style="position: fixed; top: 0; left: 0; bottom: 0; width: 280px; background: #ffffff; box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15); z-index: 50; transform: translateX(-100%);"
         class="lg:hidden sidebar-menu"
         :class="{ 'sidebar-open': mobileMenuOpen }"
         x-transition:enter="transition-transform ease-out duration-300"
         x-transition:enter-start="transform -translate-x-full"
         x-transition:enter-end="transform translate-x-0"
         x-transition:leave="transition-transform ease-in duration-200"
         x-transition:leave-start="transform translate-x-0"
         x-transition:leave-end="transform -translate-x-full">
    <div style="display: flex; flex-direction: column; height: 100%;">
      <!-- Sidebar Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid rgba(0, 0, 0, 0.1);">
        <a href="/" class="nav-logo">
          <?php if ($logoUrl): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= max(32, (int)$logoHeight * 0.75) ?>px;width:auto">
          <?php else: ?>
            <span style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
            <span class="nav-badge">3</span>
          <?php endif; ?>
        </a>
        <button @click="mobileMenuOpen = false" style="padding: 0.5rem; border-radius: 0.5rem; transition: background 0.2s; background: transparent; border: none; cursor: pointer;">
          <svg style="width: 20px; height: 20px; color: #111;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      
      <!-- Menu Items -->
      <nav style="flex: 1; overflow-y: auto; padding: 1rem 0;">
        <?php foreach ($allMenuItems as $item): ?>
          <a href="<?= htmlspecialchars($item['url']) ?>" 
             class="mobile-nav-link"
             style="display: block; padding: 0.75rem 1.5rem; color: rgba(17, 17, 17, 0.7); text-decoration: none; font-size: 0.875rem; font-weight: 500; border-left: 3px solid transparent; transition: all 0.2s;">
            <?= htmlspecialchars($item['text']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
</nav>

<style>
/* Frontend Navigation Styles */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50}
.fp-nav > div{position:relative}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s;white-space:nowrap}
.nav-link:hover{color:#111}
.mobile-nav-link:hover{background:rgba(0,0,0,0.05);color:#111;border-left-color:#D92B3A}
[x-cloak]{display:none!important}

/* Sidebar Menu Animation */
.sidebar-menu{transition:transform 0.3s ease-out}
.sidebar-menu.sidebar-open{transform:translateX(0) !important}
</style>

<!-- Alpine.js for mobile menu (inline to avoid conflicts) -->
<script>
if (typeof Alpine === 'undefined') {
  document.write('<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"><\/script>');
}
</script>

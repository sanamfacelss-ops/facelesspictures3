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
?>
<!-- Frontend Navigation Bar -->
<nav class="fp-nav" style="height:<?= $headerHeight ?>px">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center justify-center gap-8 sm:gap-12 md:gap-16">
    <!-- LEFT MENU ITEMS (orders 1-2) -->
    <div class="flex items-center gap-4 sm:gap-5">
      <?php foreach ($headerMenuItems['left'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link <?= $item['order'] <= 1 ? 'hidden sm:block' : '' ?>">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    
    <!-- CENTERED LOGO -->
    <a href="/" class="nav-logo flex-shrink-0">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:<?= (int)$logoHeight ?>px;width:auto" loading="eager">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    
    <!-- RIGHT MENU ITEMS (orders 3-4) -->
    <div class="flex items-center gap-4 sm:gap-5">
      <?php foreach ($headerMenuItems['right'] as $item): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link">
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>

<style>
/* Frontend Navigation Styles */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50}
.fp-nav > div{position:relative}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s;white-space:nowrap}
.nav-link:hover{color:#111}
</style>

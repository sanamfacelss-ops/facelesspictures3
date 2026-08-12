<?php
// Frontend Navigation - Used across home, actor, director, writer pages
$settingsModel = $settingsModel ?? new App\Models\Settings();
$logoUrl = $logoUrl ?? $settingsModel->get('site_logo_url', '');
?>
<!-- Frontend Navigation Bar -->
<nav class="fp-nav">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 h-full flex items-center justify-between">
    <a href="/" class="nav-logo">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:52px;width:auto">
      <?php else: ?>
        <span style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:.06em;color:#111">FACELESS PICTURES</span>
        <span class="nav-badge">3</span>
      <?php endif; ?>
    </a>
    <div class="flex items-center gap-4 sm:gap-6">
      <a href="/#about"   class="nav-link hidden sm:block">About</a>
      <a href="/writer"   class="nav-link">Writers</a>
      <a href="/director" class="nav-link">Directors</a>
      <a href="/actor"    class="nav-link">Actors</a>
    </div>
  </div>
</nav>

<style>
/* Frontend Navigation Styles */
.fp-nav{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid #e5e7eb;position:fixed;top:0;left:0;right:0;z-index:50;height:60px}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:19px;letter-spacing:.06em;color:#111;text-decoration:none;display:flex;align-items:center;gap:6px}
.nav-badge{background:#111;color:#fff;font-size:10px;font-weight:700;width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.nav-link{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;text-decoration:none;transition:color .2s}
.nav-link:hover{color:#111}
</style>

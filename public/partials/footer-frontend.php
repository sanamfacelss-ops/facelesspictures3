<?php
// Frontend Footer - Used across all pages
$settingsModel = $settingsModel ?? new App\Models\Settings();
$logoUrl = $logoUrl ?? $settingsModel->get('site_logo_url', '');
$footerTagline = $settingsModel->get('footer_tagline', 'No face. Just talent.');

// Social media links
$socialYoutube = $settingsModel->get('social_youtube', '');
$socialFacebook = $settingsModel->get('social_facebook', '');
$socialInstagram = $settingsModel->get('social_instagram', '');
$socialX = $settingsModel->get('social_x', '');
$socialLinkedin = $settingsModel->get('social_linkedin', '');

// Filter out empty social links
$socialLinks = array_filter([
    'youtube' => $socialYoutube,
    'facebook' => $socialFacebook,
    'instagram' => $socialInstagram,
    'x' => $socialX,
    'linkedin' => $socialLinkedin
]);
?>

<footer class="fp-footer">
  <div class="footer-container">
    
    <!-- Logo Section -->
    <div class="footer-logo-section">
      <a href="/" class="footer-logo">
        <?php if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Faceless Pictures 3" style="height:44px;width:auto">
        <?php else: ?>
          <span class="footer-logo-text">FACELESS PICTURES</span>
          <span class="footer-badge">3</span>
        <?php endif; ?>
      </a>
      <?php if ($footerTagline): ?>
      <p class="footer-tagline"><?= htmlspecialchars($footerTagline) ?></p>
      <?php endif; ?>
    </div>
    
    <!-- Navigation Links -->
    <nav class="footer-nav">
      <a href="/#about" class="footer-link">About Us</a>
      <a href="/legal" class="footer-link">Terms & Conditions</a>
    </nav>
    
    <!-- Social Links -->
    <?php if (!empty($socialLinks)): ?>
    <div class="footer-social">
      <?php if (!empty($socialLinks['youtube'])): ?>
      <a href="<?= htmlspecialchars($socialLinks['youtube']) ?>" target="_blank" rel="noopener" class="social-link" aria-label="YouTube">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
      </a>
      <?php endif; ?>
      
      <?php if (!empty($socialLinks['facebook'])): ?>
      <a href="<?= htmlspecialchars($socialLinks['facebook']) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Facebook">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      </a>
      <?php endif; ?>
      
      <?php if (!empty($socialLinks['instagram'])): ?>
      <a href="<?= htmlspecialchars($socialLinks['instagram']) ?>" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
      </a>
      <?php endif; ?>
      
      <?php if (!empty($socialLinks['x'])): ?>
      <a href="<?= htmlspecialchars($socialLinks['x']) ?>" target="_blank" rel="noopener" class="social-link" aria-label="X (Twitter)">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
      </a>
      <?php endif; ?>
      
      <?php if (!empty($socialLinks['linkedin'])): ?>
      <a href="<?= htmlspecialchars($socialLinks['linkedin']) ?>" target="_blank" rel="noopener" class="social-link" aria-label="LinkedIn">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Copyright -->
    <p class="footer-copyright">© <?= date('Y') ?> Faceless Pictures. All rights reserved.</p>
    
  </div>
</footer>

<style>
.fp-footer {
  background: #F5F5F5;
  border-top: 1px solid #E0E0E0;
  color: #4A5568;
  padding: 3rem 1.5rem 2rem;
  margin-top: auto;
}

.footer-container {
  max-width: 900px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 1.5rem;
}

/* Logo Section */
.footer-logo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.footer-logo-text {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 20px;
  letter-spacing: 0.06em;
  color: #1A202C;
}

.footer-badge {
  background: #1A202C;
  color: #FFFFFF;
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

.footer-tagline {
  color: #718096;
  font-size: 0.875rem;
  line-height: 1.5;
}

/* Navigation */
.footer-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
}

.footer-link {
  color: #4A5568;
  text-decoration: none;
  font-size: 0.875rem;
  transition: color 0.2s;
  line-height: 1.6;
  white-space: nowrap;
}

.footer-link:hover {
  color: #1A202C;
}

/* Social Links */
.footer-social {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: #FFFFFF;
  border: 1px solid #E0E0E0;
  border-radius: 50%;
  color: #4A5568;
  text-decoration: none;
  transition: all 0.2s;
}

.social-link:hover {
  background: #E0E0E0;
  border-color: #CBD5E0;
  color: #1A202C;
  transform: translateY(-2px);
}

.social-link svg {
  width: 18px;
  height: 18px;
}

/* Copyright */
.footer-copyright {
  font-size: 0.875rem;
  color: #718096;
  margin-top: 0.5rem;
}
</style>

# Production Deployment Checklist

## 🚀 Deploy Code Changes

### 1. Pull Latest Changes
```bash
cd /var/www/html
git pull origin feature/public-redesign-auditions
```

### 2. Verify Files Updated
```bash
git log --oneline -5
```

Expected commits:
- Add comprehensive image loading optimization documentation
- Optimize image loading: eager loading for above-fold images, preload hints, bulk optimization script
- Fix lazy loading images not loading when in viewport - add Intersection Observer

---

## 🖼️ Optimize Existing Images (CRITICAL)

### Run Bulk Compression Script
```bash
cd /var/www/html
php cron/optimize-images-bulk.php
```

**Expected Output:**
```
══════════════════════════════════════════════════════
  BULK IMAGE OPTIMIZATION SCRIPT
══════════════════════════════════════════════════════

Found X images to optimize

[1/X] Processing: poster1.jpg
  Original size: 3.2 MB
  ✓ Optimized: 450 KB
  → Saved: 2.75 MB (85.9%)
  → Processing time: 1.2s

...

══════════════════════════════════════════════════════
  OPTIMIZATION COMPLETE
══════════════════════════════════════════════════════

Total images processed: X
Successfully optimized: X
Skipped (already optimal): X
Errors: 0
Total space saved: X MB
Average reduction: 75%
```

**This step is CRITICAL** - Without it, images will still be huge and slow!

---

## ✅ Verify Optimizations

### 1. Check Image Sizes
```bash
cd /var/www/html/public/uploads
ls -lh *.{jpg,jpeg,png,webp} 2>/dev/null | head -20
```

**Expected:** Most images should be under 500KB (ideally 200-400KB)

### 2. Test Frontend Loading

**Open in Browser:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Open: https://your-domain.com
3. Open DevTools (F12) > Network tab
4. Reload page

**Check:**
- ✅ First 3 posters load immediately (no delay)
- ✅ Logo loads instantly (preloaded)
- ✅ All images under 1MB
- ✅ Page loads in < 5 seconds
- ✅ Smooth scrolling, no blank image boxes

### 3. Test Slow Connection

**Chrome DevTools:**
1. Network tab > Throttling dropdown
2. Select "Slow 3G"
3. Reload page

**Check:**
- ✅ Above-fold images load within 3-5 seconds
- ✅ Images load progressively as you scroll
- ✅ No stuck "loading" placeholders
- ✅ Smooth experience even on slow connection

### 4. Run Lighthouse Test

**Chrome DevTools:**
1. Click Lighthouse tab
2. Select "Performance" + "Desktop"
3. Click "Analyze page load"

**Target Scores:**
- Performance: 80+ (ideally 90+)
- LCP (Largest Contentful Paint): < 2.5s
- FCP (First Contentful Paint): < 1.8s
- CLS (Cumulative Layout Shift): < 0.1

---

## 🔍 Troubleshooting

### Images Still Loading Slowly

**Check 1: Compression ran successfully**
```bash
cd /var/www/html/public/uploads
find . -type f -name "*.webp" | wc -l
```
Should show WebP files (compressed images)

**Check 2: File sizes**
```bash
cd /var/www/html/public/uploads
du -sh .
```
Should be significantly smaller than before (60-80% reduction)

**Check 3: GZIP enabled**
```bash
curl -I -H "Accept-Encoding: gzip" https://your-domain.com | grep -i "content-encoding"
```
Should show: `content-encoding: gzip`

### Images Not Loading At All

**Check 1: File permissions**
```bash
cd /var/www/html/public
ls -la uploads/
```
Should be readable by web server (755 or 644)

**Fix permissions if needed:**
```bash
chmod 755 /var/www/html/public/uploads
chmod 644 /var/www/html/public/uploads/*
```

**Check 2: Browser console errors**
Open DevTools > Console tab, look for 404 or permission errors

### Page Still Slow

**Check 1: Database queries**
- Settings should be cached (1 query instead of 50+)
- Check logs: `tail -f /var/www/html/logs/*.log`

**Check 2: Server resources**
```bash
top -bn1 | head -20
```
Check CPU/RAM usage

---

## 📊 Performance Benchmarks

### Before Optimization
- Page load: 15-30 seconds (slow 3G)
- Image sizes: 2-5 MB per image
- Database queries: 50+ per page
- User experience: Slow, blank boxes, stuck loading

### After Optimization
- Page load: 3-5 seconds (slow 3G)
- Image sizes: 200-500 KB per image
- Database queries: 1-2 per page
- User experience: Fast, smooth, instant visibility

**Expected improvement: 5-10x faster**

---

## 🎯 What Was Fixed

### 1. Image Loading Strategy
- ✅ First 6 posters: `loading="eager"` + `fetchpriority="high"`
- ✅ Remaining posters: `loading="lazy"` with IntersectionObserver
- ✅ Logo: Preloaded in `<head>` for instant display

### 2. Lazy Loading Bug
- ✅ Added IntersectionObserver to force load images when visible
- ✅ 50px preload margin for smooth experience
- ✅ Fallback for older browsers

### 3. Image Compression
- ✅ Bulk optimization script: `cron/optimize-images-bulk.php`
- ✅ WebP conversion (85% quality)
- ✅ Auto-resize (max 1920x1920)
- ✅ 60-80% file size reduction

### 4. Resource Hints
- ✅ Preload critical images in `<head>`
- ✅ Preconnect to CDN domains
- ✅ DNS prefetch for faster lookups

### 5. Database Optimization
- ✅ Settings cached (1 query instead of 50+)
- ✅ Static cache for repeated calls
- ✅ Global helper functions

### 6. Performance Optimizations
- ✅ HTTP Range Requests for video streaming
- ✅ Browser caching (1 year for static assets)
- ✅ GZIP compression
- ✅ Defer non-critical CSS/JS

---

## 📝 Post-Deployment Checklist

- [ ] Code deployed (git pull)
- [ ] Bulk compression ran successfully
- [ ] Image file sizes verified (< 500KB each)
- [ ] Frontend loads fast (< 5 seconds)
- [ ] Lighthouse score 80+ (performance)
- [ ] No console errors
- [ ] Slow 3G test passed
- [ ] All posters visible and loading correctly
- [ ] Logo displays instantly
- [ ] Smooth scrolling experience

---

## 🆘 Emergency Rollback

If something breaks:

```bash
cd /var/www/html
git log --oneline -5  # Find previous commit hash
git checkout <previous-commit-hash>
```

Or revert specific commits:
```bash
git revert HEAD~1  # Revert last commit
git revert HEAD~2  # Revert last 2 commits
```

---

## 📞 Support

If issues persist after following this checklist:
1. Check server logs: `/var/www/html/logs/*.log`
2. Check Apache/Nginx error logs
3. Check browser console for JavaScript errors
4. Verify PHP version (7.4+ required for image compression)
5. Check GD/Imagick extension installed: `php -m | grep -E "(gd|imagick)"`

---

## 🎉 Success Criteria

**Deployment is successful when:**
1. ✅ Page loads in < 5 seconds on slow connection
2. ✅ All images load correctly (no blank boxes)
3. ✅ Lighthouse Performance score 80+
4. ✅ Image file sizes under 500KB
5. ✅ Smooth scrolling with progressive loading
6. ✅ No console errors or 404s
7. ✅ Zero user-reported loading issues

**Client should notice:**
- Page loads way faster
- Images appear instantly when scrolling
- No more "loading forever" issues
- Professional, polished experience

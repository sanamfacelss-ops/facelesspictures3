#!/bin/bash
#
# Production Security Cleanup Script
# Removes debug/test files and documentation from production server
#
# Usage: bash cleanup-production.sh
#

BASE_DIR="/var/www/html"

echo "═══════════════════════════════════════════════════════"
echo "  Production Security Cleanup"
echo "═══════════════════════════════════════════════════════"
echo ""

# Remove test/debug files
echo "→ Removing test and debug files..."
rm -f "$BASE_DIR/public/test-loading.html"
rm -f "$BASE_DIR/public/test-*.html"
rm -f "$BASE_DIR/public/test-*.php"
rm -f "$BASE_DIR/cron/check-image-setup.php"
rm -f "$BASE_DIR/cron/check-*.php"
rm -f "$BASE_DIR/cron/test-*.php"

# Remove documentation files (keep README.md)
echo "→ Removing documentation files..."
cd "$BASE_DIR"
# Exclude deploy.sh and cleanup.sh from deletion
find . -maxdepth 1 -type f -name "*_PLAN.md" -delete
find . -maxdepth 1 -type f -name "*_GUIDE.md" -delete
find . -maxdepth 1 -type f -name "*_COMPLETE.md" -delete
find . -maxdepth 1 -type f -name "*_FIX.md" -delete
find . -maxdepth 1 -type f -name "*_SUMMARY.md" -delete
find . -maxdepth 1 -type f -name "*_CHECKLIST.md" -delete
find . -maxdepth 1 -type f -name "FEATURE_*.md" -delete
find . -maxdepth 1 -type f -name "ADMIN_*.md" -delete
find . -maxdepth 1 -type f -name "HORIZONTAL_*.md" -delete
find . -maxdepth 1 -type f -name "IMAGE_*.md" -delete
find . -maxdepth 1 -type f -name "LAZY_*.md" -delete
find . -maxdepth 1 -type f -name "PLAYLIST_*.md" -delete
find . -maxdepth 1 -type f -name "PRODUCTION_*.md" -delete
find . -maxdepth 1 -type f -name "ROLE_*.md" -delete
find . -maxdepth 1 -type f -name "SCRIPTS_*.md" -delete
find . -maxdepth 1 -type f -name "SETTINGS_*.md" -delete
find . -maxdepth 1 -type f -name "SIMPLE_*.md" -delete
find . -maxdepth 1 -type f -name "YOUTUBE_*.md" -delete

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Cleanup Complete"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "✓ Removed debug/test files"
echo "✓ Removed documentation files"
echo "✓ Production environment secured"
echo ""

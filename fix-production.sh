#!/bin/bash
#
# Production Quick Fix Script
# Fixes common issues: permissions, cache, restarts services
#
# Usage: sudo bash fix-production.sh
#

BASE_DIR="/var/www/html"

echo "═══════════════════════════════════════════════════════"
echo "  Production Quick Fix"
echo "═══════════════════════════════════════════════════════"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root: sudo bash fix-production.sh"
    exit 1
fi

# Fix file permissions
echo "→ Fixing file permissions..."
chown -R www-data:www-data "$BASE_DIR"
chmod -R 755 "$BASE_DIR"
chmod -R 777 "$BASE_DIR/uploads"
chmod -R 777 "$BASE_DIR/logs"
chmod -R 777 "$BASE_DIR/cache"
echo "  ✓ Permissions fixed"
echo ""

# Clear cache
echo "→ Clearing cache..."
rm -rf "$BASE_DIR/cache/pages/"*
mkdir -p "$BASE_DIR/cache/pages"
chmod 777 "$BASE_DIR/cache/pages"
echo "  ✓ Cache cleared"
echo ""

# Create required directories
echo "→ Creating required directories..."
mkdir -p "$BASE_DIR/uploads"
mkdir -p "$BASE_DIR/logs"
mkdir -p "$BASE_DIR/cache/pages"
mkdir -p "$BASE_DIR/uploads/settings"
chmod 777 "$BASE_DIR/uploads"
chmod 777 "$BASE_DIR/logs"
chmod 777 "$BASE_DIR/cache/pages"
chmod 777 "$BASE_DIR/uploads/settings"
echo "  ✓ Directories created"
echo ""

# Restart web server
echo "→ Restarting web server..."
if systemctl is-active --quiet apache2; then
    systemctl restart apache2
    echo "  ✓ Apache restarted"
elif systemctl is-active --quiet nginx; then
    systemctl restart nginx
    systemctl restart php*-fpm 2>/dev/null
    echo "  ✓ Nginx and PHP-FPM restarted"
else
    echo "  ⚠️  No web server found to restart"
fi
echo ""

# Check .env exists
echo "→ Checking environment configuration..."
if [ ! -f "$BASE_DIR/.env" ]; then
    if [ -f "$BASE_DIR/.env.example" ]; then
        echo "  ⚠️  .env missing, copying from .env.example"
        cp "$BASE_DIR/.env.example" "$BASE_DIR/.env"
        echo "  ⚠️  IMPORTANT: Edit .env with correct database credentials!"
    else
        echo "  ✗ ERROR: No .env or .env.example found!"
    fi
else
    echo "  ✓ .env file exists"
fi
echo ""

# Test database connection
echo "→ Testing database connection..."
php -r "
require_once '$BASE_DIR/app/config/config.php';
try {
    \$db = \App\Config\Database::getConnection();
    echo '  ✓ Database connection successful\n';
} catch (Exception \$e) {
    echo '  ✗ Database connection FAILED: ' . \$e->getMessage() . '\n';
    echo '  → Check database credentials in .env\n';
}
"
echo ""

echo "═══════════════════════════════════════════════════════"
echo "  Fix Complete"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "✓ Permissions fixed"
echo "✓ Cache cleared"
echo "✓ Web server restarted"
echo ""
echo "If site still not working:"
echo "  1. Run: bash diagnose-server.sh"
echo "  2. Check: tail -f /var/log/apache2/error.log"
echo "  3. Verify database credentials in .env"
echo ""

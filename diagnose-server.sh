#!/bin/bash
#
# Server Diagnostic Script
# Run this on your production server to diagnose issues
#
# Usage: bash diagnose-server.sh
#

BASE_DIR="/var/www/html"

echo "═══════════════════════════════════════════════════════"
echo "  Faceless Pictures - Server Diagnostics"
echo "═══════════════════════════════════════════════════════"
echo ""

# Check PHP version
echo "→ PHP Version:"
php -v | head -n 1
echo ""

# Check if Apache/Nginx is running
echo "→ Web Server Status:"
if systemctl is-active --quiet apache2; then
    echo "  ✓ Apache is running"
    systemctl status apache2 --no-pager | grep "Active:"
elif systemctl is-active --quiet nginx; then
    echo "  ✓ Nginx is running"
    systemctl status nginx --no-pager | grep "Active:"
else
    echo "  ✗ WARNING: No web server detected running!"
fi
echo ""

# Check database connection
echo "→ Database Connection Test:"
php -r "
require_once '$BASE_DIR/app/config/config.php';
try {
    \$db = \App\Config\Database::getConnection();
    echo '  ✓ Database connection successful\n';
} catch (Exception \$e) {
    echo '  ✗ Database connection FAILED: ' . \$e->getMessage() . '\n';
}
"
echo ""

# Check file permissions
echo "→ Directory Permissions:"
ls -la "$BASE_DIR" | grep -E "(uploads|logs|cache)"
echo ""

# Check .env file
echo "→ Environment Configuration:"
if [ -f "$BASE_DIR/.env" ]; then
    echo "  ✓ .env file exists"
    echo "  → APP_ENV: $(grep 'APP_ENV=' $BASE_DIR/.env | cut -d'=' -f2)"
    echo "  → FP3_DEBUG: $(grep 'FP3_DEBUG=' $BASE_DIR/.env | cut -d'=' -f2)"
    echo "  → DB_HOST: $(grep 'DB_HOST=' $BASE_DIR/.env | cut -d'=' -f2)"
    echo "  → DB_DATABASE: $(grep 'DB_DATABASE=' $BASE_DIR/.env | cut -d'=' -f2)"
else
    echo "  ✗ WARNING: .env file not found!"
fi
echo ""

# Check PHP error logs
echo "→ Recent PHP Errors (last 20 lines):"
if [ -f /var/log/apache2/error.log ]; then
    tail -20 /var/log/apache2/error.log | grep -i "faceless\|fatal\|error" || echo "  (no recent errors)"
elif [ -f /var/log/nginx/error.log ]; then
    tail -20 /var/log/nginx/error.log | grep -i "faceless\|fatal\|error" || echo "  (no recent errors)"
else
    echo "  (no error log found)"
fi
echo ""

# Check application logs
echo "→ Application Logs (last 20 lines):"
if [ -f "$BASE_DIR/logs/2026-07-12.log" ]; then
    tail -20 "$BASE_DIR/logs/2026-07-12.log"
else
    echo "  (no application logs found)"
fi
echo ""

# Test a simple PHP file
echo "→ Testing PHP execution:"
echo "<?php echo 'PHP works!'; ?>" > /tmp/test-php.php
php /tmp/test-php.php
echo ""
rm /tmp/test-php.php

# Check critical files exist
echo "→ Critical Files Check:"
FILES=(
    "app/config/config.php"
    "app/config/database.php"
    "public/index.php"
    "public/.htaccess"
)

for file in "${FILES[@]}"; do
    if [ -f "$BASE_DIR/$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ MISSING: $file"
    fi
done
echo ""

# Check PHP modules
echo "→ Required PHP Extensions:"
REQUIRED=(
    "pdo_mysql"
    "mbstring"
    "json"
    "curl"
)

for ext in "${REQUIRED[@]}"; do
    if php -m | grep -qi "^$ext$"; then
        echo "  ✓ $ext"
    else
        echo "  ✗ MISSING: $ext"
    fi
done
echo ""

echo "═══════════════════════════════════════════════════════"
echo "  Diagnostic Complete"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "If issues found, check:"
echo "  1. Web server error logs: /var/log/apache2/error.log or /var/log/nginx/error.log"
echo "  2. PHP-FPM logs (if using Nginx): /var/log/php*-fpm.log"
echo "  3. Database credentials in .env"
echo "  4. File permissions: sudo chown -R www-data:www-data /var/www/html"
echo ""

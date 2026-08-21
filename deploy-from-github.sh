#!/bin/bash
#
# Auto-deploy from GitHub without Git
# Downloads latest files from GitHub repository
#
# Usage: bash deploy-from-github.sh
#

REPO_URL="https://raw.githubusercontent.com/sanamfacelss-ops/facelesspictures3/feature/public-redesign-auditions"
BASE_DIR="/var/www/html"

echo "═══════════════════════════════════════════════════════"
echo "  GitHub Auto-Deploy Script"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Downloading latest files from GitHub..."
echo ""

# Create backup directory
BACKUP_DIR="${BASE_DIR}/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Function to download file
download_file() {
    local file_path=$1
    local full_path="${BASE_DIR}/${file_path}"
    local dir_path=$(dirname "$full_path")
    
    echo "→ Downloading: $file_path"
    
    # Create directory if needed
    mkdir -p "$dir_path"
    
    # Backup existing file
    if [ -f "$full_path" ]; then
        cp "$full_path" "${BACKUP_DIR}/${file_path}" 2>/dev/null
    fi
    
    # Download file
    curl -s -o "$full_path" "${REPO_URL}/${file_path}"
    
    if [ $? -eq 0 ]; then
        echo "  ✓ Downloaded successfully"
    else
        echo "  ✗ Download failed"
    fi
}

# List of critical files to update
FILES=(
    "cron/optimize-images-bulk.php"
    "app/services/ImageCompressionService.php"
    "public/home.php"
    "public/admin.php"
    "public/.htaccess"
)

# Download each file
for file in "${FILES[@]}"; do
    download_file "$file"
done

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  Deployment Complete"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "✓ Files updated from GitHub"
echo "✓ Backups saved to: $BACKUP_DIR"
echo ""
echo "Next steps:"
echo "  1. php cron/check-image-setup.php"
echo "  2. php cron/optimize-images-bulk.php"
echo ""

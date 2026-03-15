#!/bin/bash
#
# AutoTranslator v1.2.0 for Pterodactyl Panel
# Standalone Uninstaller
#
# Author: ElDeiividMtz
# License: MIT
#
# Usage: bash uninstall.sh
#

set -e

PANEL_DIR="/var/www/pterodactyl"
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${RED}╔═══════════════════════════════════════════════╗${NC}"
echo -e "${RED}║     AutoTranslator v1.2.0 — Uninstaller       ║${NC}"
echo -e "${RED}╚═══════════════════════════════════════════════╝${NC}"
echo ""

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

if [ ! -d "$PANEL_DIR" ]; then
    read -p "Enter panel path: " PANEL_DIR
    if [ ! -d "$PANEL_DIR" ]; then
        echo -e "${RED}Directory not found. Aborting.${NC}"
        exit 1
    fi
fi

echo -e "${YELLOW}WARNING: This will remove AutoTranslator from $PANEL_DIR${NC}"
read -p "Delete translation data too? (y/N): " delete_data
read -p "Are you sure you want to uninstall? (y/N): " confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "Aborted."
    exit 0
fi

# ── Step 1: Remove PHP backend ──
echo -e "  ${RED}[1/7]${NC} Removing PHP backend files..."
rm -f "$PANEL_DIR/app/Console/Commands/ScanTranslationsCommand.php"
rm -f "$PANEL_DIR/app/Console/Commands/TranslationSetupCommand.php"
rm -f "$PANEL_DIR/app/Http/Controllers/Admin/TranslationScanController.php"
rm -f "$PANEL_DIR/app/Http/Controllers/Base/TranslationJsonController.php"
rm -f "$PANEL_DIR/app/Services/Helpers/GoogleTranslateService.php"
rm -f "$PANEL_DIR/app/Http/Requests/Api/Client/Account/UpdateLanguageRequest.php"
rm -f "$PANEL_DIR/config/autotranslator.php"

# ── Step 2: Remove views ──
echo -e "  ${RED}[2/7]${NC} Removing views..."
rm -rf "$PANEL_DIR/resources/views/admin/translations"

# NOTE: admin.blade.php and wrapper.blade.php are NOT removed because they are
# core panel files that were replaced. The user should restore from backup or
# git checkout if they want the original versions.
echo "    (admin.blade.php and wrapper.blade.php were modified — restore from backup if needed)"

# ── Step 3: Remove frontend plugin ──
echo -e "  ${RED}[3/7]${NC} Removing frontend plugin..."
rm -rf "$PANEL_DIR/resources/scripts/plugins/AutoTranslator"
rm -f "$PANEL_DIR/resources/scripts/components/dashboard/forms/UpdateLanguageForm.tsx"
rm -f "$PANEL_DIR/resources/scripts/components/elements/Translate.tsx"

# ── Step 4: Clean index.tsx ──
echo -e "  ${RED}[4/7]${NC} Cleaning React entry point..."
INDEX_TSX="$PANEL_DIR/resources/scripts/index.tsx"
if [ -f "$INDEX_TSX" ]; then
    sed -i '/Auto-translation addon/d' "$INDEX_TSX"
    sed -i '/AutoTranslator/d' "$INDEX_TSX"
    sed -i '/initAutoTranslator/d' "$INDEX_TSX"
    # Clean up empty lines left behind
    sed -i '/^$/N;/^\n$/d' "$INDEX_TSX"
fi

# ── Step 5: Clean route files ──
echo -e "  ${RED}[5/7]${NC} Cleaning route files..."

# Clean admin.php — remove AutoTranslator route block (uses BEGIN/END markers)
ADMIN_ROUTES="$PANEL_DIR/routes/admin.php"
if [ -f "$ADMIN_ROUTES" ]; then
    sed -i '/^\/\/ BEGIN AutoTranslator$/,/^\/\/ END AutoTranslator$/d' "$ADMIN_ROUTES"
    # Fallback: clean old-style blocks without markers
    sed -i '/AutoTranslator Routes/,/^});$/d' "$ADMIN_ROUTES"
    sed -i '/Translation Scanner Routes/,/^});$/d' "$ADMIN_ROUTES"
fi

# Clean base.php — remove translation routes (uses BEGIN/END markers)
BASE_ROUTES="$PANEL_DIR/routes/base.php"
if [ -f "$BASE_ROUTES" ]; then
    sed -i '/^\/\/ BEGIN AutoTranslator$/,/^\/\/ END AutoTranslator$/d' "$BASE_ROUTES"
    # Fallback for old installs
    sed -i '/TranslationJsonController/d' "$BASE_ROUTES"
    sed -i '/translations\.json/d' "$BASE_ROUTES"
    sed -i '/translations\.live/d' "$BASE_ROUTES"
fi

# Clean api-client.php — remove language route
API_ROUTES="$PANEL_DIR/routes/api-client.php"
if [ -f "$API_ROUTES" ]; then
    sed -i '/updateLanguage/d' "$API_ROUTES"
fi

# Clean admin sidebar
ADMIN_LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
if [ -f "$ADMIN_LAYOUT" ]; then
    sed -i '/admin\.translations/d' "$ADMIN_LAYOUT"
    sed -i '/fa-language/d' "$ADMIN_LAYOUT"
fi

# ── Step 6: Translation data ──
echo -e "  ${RED}[6/7]${NC} Handling translation data..."
if [ "$delete_data" = "y" ] || [ "$delete_data" = "Y" ]; then
    rm -rf "$PANEL_DIR/storage/app/translations"
    rm -f "$PANEL_DIR/storage/app/translation-scan-results.json"
    echo "    Translation data deleted"
else
    echo "    Translation data preserved in storage/app/translations/"
fi

# ── Step 7: Fix permissions & clear caches ──
echo -e "  ${RED}[7/7]${NC} Fixing permissions & clearing caches..."
cd "$PANEL_DIR"
php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

chown -R www-data:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo ""
echo -e "${GREEN}Uninstall complete.${NC}"
echo -e "${YELLOW}NOTE: You need to rebuild frontend assets:${NC}"
echo -e "  cd $PANEL_DIR && npx webpack --mode production"
echo ""
# Restore AssetComposer from backup if available
if [ -f "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php.bak.autotranslator" ]; then
    mv "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php.bak.autotranslator" \
       "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php"
    echo -e "${GREEN}Restored original AssetComposer.php from backup${NC}"
else
    echo -e "${YELLOW}If you need to restore original AssetComposer.php:${NC}"
    echo -e "  git checkout app/Http/ViewComposers/AssetComposer.php"
fi

echo -e "${YELLOW}You may also want to restore:${NC}"
echo -e "  git checkout resources/views/layouts/admin.blade.php"
echo -e "  git checkout resources/views/templates/wrapper.blade.php"
echo ""

#!/bin/bash
set -euo pipefail
#
# AutoTranslator v1.0.0 — Blueprint Remove Script
# Cleans up files installed outside Blueprint's managed paths.
# Blueprint automatically handles: admin views, wrappers, components, routers, CSS.

PANEL_DIR="${BLUEPRINT__ROOTFOLDER:-/var/www/pterodactyl}"

echo "[AutoTranslator] Removing addon files..."

# ── Remove PHP backend files ──
rm -f "$PANEL_DIR/app/Http/Controllers/Admin/TranslationScanController.php"
rm -f "$PANEL_DIR/app/Http/Controllers/Base/TranslationJsonController.php"
rm -f "$PANEL_DIR/app/Http/Controllers/Base/TranslationLanguageController.php"
rm -f "$PANEL_DIR/app/Services/Helpers/GoogleTranslateService.php"
rm -f "$PANEL_DIR/app/Console/Commands/ScanTranslationsCommand.php"
rm -f "$PANEL_DIR/app/Console/Commands/TranslationSetupCommand.php"
rm -f "$PANEL_DIR/config/autotranslator.php"

# ── Remove views ──
rm -rf "$PANEL_DIR/resources/views/admin/translations"

# ── Remove frontend plugin ──
rm -rf "$PANEL_DIR/resources/scripts/plugins/AutoTranslator"

# ── Clean index.tsx ──
INDEX_TSX="$PANEL_DIR/resources/scripts/index.tsx"
if [ -f "$INDEX_TSX" ]; then
    sed -i '/\/\/ AutoTranslator/d' "$INDEX_TSX"
    sed -i '/AutoTranslator/d' "$INDEX_TSX"
    sed -i '/initAutoTranslator/d' "$INDEX_TSX"
fi

# ── Clean public JSON route from base.php (using markers) ──
BASE_ROUTES="$PANEL_DIR/routes/base.php"
if [ -f "$BASE_ROUTES" ]; then
    sed -i '/^\/\/ BEGIN AutoTranslator$/,/^\/\/ END AutoTranslator$/d' "$BASE_ROUTES"
    # Fallback for old installs without markers
    sed -i '/TranslationJsonController/d' "$BASE_ROUTES"
    sed -i '/translations\.json/d' "$BASE_ROUTES"
fi

# ── Clean EncryptCookies ──
ENCRYPT="$PANEL_DIR/app/Http/Middleware/EncryptCookies.php"
if [ -f "$ENCRYPT" ]; then
    sed -i "/autotranslator_lang/d" "$ENCRYPT"
fi

# ── Clear caches & fix permissions ──
cd "$PANEL_DIR"
php artisan view:clear   2>/dev/null || true
php artisan route:clear  2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear  2>/dev/null || true

chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

echo "[AutoTranslator] Removal complete!"
echo "NOTE: Translation data in storage/app/translations/ was preserved."
echo "      Delete manually if no longer needed: rm -rf $PANEL_DIR/storage/app/translations/"

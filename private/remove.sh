#!/bin/bash
#
# AutoTranslator v1.0.0 — Blueprint Remove Script
# Blueprint runs this automatically from: .blueprint/extensions/{id}/private/remove.sh
# Cleans up files installed outside Blueprint's managed paths.

PANEL_DIR="${PTERODACTYL_DIRECTORY:-/var/www/pterodactyl}"

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

# ── Clean public JSON route from base.php ──
BASE_ROUTES="$PANEL_DIR/routes/base.php"
if [ -f "$BASE_ROUTES" ]; then
    sed -i '/^\/\/ BEGIN AutoTranslator$/,/^\/\/ END AutoTranslator$/d' "$BASE_ROUTES"
    sed -i '/TranslationJsonController/d' "$BASE_ROUTES"
fi

# ── Clean EncryptCookies ──
ENCRYPT="$PANEL_DIR/app/Http/Middleware/EncryptCookies.php"
if [ -f "$ENCRYPT" ]; then
    sed -i "/autotranslator_lang/d" "$ENCRYPT"
fi

# ── Clear caches ──
cd "$PANEL_DIR"
php artisan view:clear   2>/dev/null || true
php artisan route:clear  2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear  2>/dev/null || true

echo "[AutoTranslator] Removal complete!"
echo "NOTE: Translation data in storage/app/translations/ was preserved."
echo "      Delete manually if no longer needed: rm -rf $PANEL_DIR/storage/app/translations/"

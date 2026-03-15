#!/bin/bash
#
# AutoTranslator v1.0.0 — Blueprint Install Script
# Blueprint runs this automatically from: .blueprint/extensions/{id}/private/install.sh
#
# Available env vars from Blueprint:
#   PTERODACTYL_DIRECTORY, BLUEPRINT_VERSION, EXTENSION_IDENTIFIER,
#   EXTENSION_VERSION, EXTENSION_TARGET, ENGINE
#
# What Blueprint handles natively:
#   - Admin extension page, routes, wrappers, components, CSS
#
# What this script handles:
#   - PHP backend files (controllers, services, commands, config)
#   - Translation dashboard view
#   - Frontend plugin (TypeScript, patching index.tsx)
#   - Public JSON route (needs no-auth access)
#   - EncryptCookies middleware patch
#   - Storage directories and permissions

PANEL_DIR="${PTERODACTYL_DIRECTORY:-/var/www/pterodactyl}"
EXT_DIR="$PANEL_DIR/.blueprint/extensions/autotranslator"
PRIV="$EXT_DIR/private"

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║  AutoTranslator v1.0.0 — Installing...  ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# ── Preflight ──
if [ ! -d "$PRIV/app" ]; then
    echo "[!] Extension private files missing. Aborting."
    exit 1
fi
echo "[✓] Extension files found"

# ══════════════════════════════════════════════
# 1. COPY PHP BACKEND FILES
# ══════════════════════════════════════════════
echo "[1/6] Copying PHP backend..."

cp -f "$PRIV/app/Http/Controllers/Admin/TranslationScanController.php" "$PANEL_DIR/app/Http/Controllers/Admin/"

mkdir -p "$PANEL_DIR/app/Http/Controllers/Base"
cp -f "$PRIV/app/Http/Controllers/Base/TranslationJsonController.php" "$PANEL_DIR/app/Http/Controllers/Base/"
cp -f "$PRIV/app/Http/Controllers/Base/TranslationLanguageController.php" "$PANEL_DIR/app/Http/Controllers/Base/"

mkdir -p "$PANEL_DIR/app/Services/Helpers"
cp -f "$PRIV/app/Services/Helpers/GoogleTranslateService.php" "$PANEL_DIR/app/Services/Helpers/"

cp -f "$PRIV/app/Console/Commands/ScanTranslationsCommand.php" "$PANEL_DIR/app/Console/Commands/"
cp -f "$PRIV/app/Console/Commands/TranslationSetupCommand.php" "$PANEL_DIR/app/Console/Commands/"

cp -f "$PRIV/config/autotranslator.php" "$PANEL_DIR/config/"

echo "  Done"

# ══════════════════════════════════════════════
# 2. COPY VIEWS & FRONTEND
# ══════════════════════════════════════════════
echo "[2/6] Copying views and frontend..."

mkdir -p "$PANEL_DIR/resources/views/admin/translations"
cp -f "$PRIV/resources/views/admin/translations/index.blade.php" "$PANEL_DIR/resources/views/admin/translations/"

mkdir -p "$PANEL_DIR/resources/scripts/plugins/AutoTranslator"
cp -f "$PRIV/resources/scripts/plugins/AutoTranslator/index.ts" "$PANEL_DIR/resources/scripts/plugins/AutoTranslator/"

echo "  Done"

# ══════════════════════════════════════════════
# 3. PATCH INDEX.TSX (import AutoTranslator)
# ══════════════════════════════════════════════
echo "[3/6] Patching frontend entry..."

INDEX_TSX="$PANEL_DIR/resources/scripts/index.tsx"
if [ -f "$INDEX_TSX" ] && ! grep -q "AutoTranslator" "$INDEX_TSX"; then
    LAST_IMPORT=$(grep -n "^import " "$INDEX_TSX" | tail -1 | cut -d: -f1)
    if [ -n "$LAST_IMPORT" ]; then
        sed -i "${LAST_IMPORT}a\\
\\
// AutoTranslator — Blueprint extension\\
import { initAutoTranslator } from './plugins/AutoTranslator';\\
initAutoTranslator();" "$INDEX_TSX"
        echo "  index.tsx patched"
    fi
fi

# ══════════════════════════════════════════════
# 4. ADD PUBLIC TRANSLATION JSON ROUTE
# ══════════════════════════════════════════════
echo "[4/6] Adding public translation route..."

BASE_ROUTES="$PANEL_DIR/routes/base.php"
if [ -f "$BASE_ROUTES" ] && ! grep -q "TranslationJsonController" "$BASE_ROUTES"; then
    sed -i "/Route::get('\/{react}'/i\\
\\
// BEGIN AutoTranslator\\
Route::get('/translations/{lang}.json', [\\\\Pterodactyl\\\\Http\\\\Controllers\\\\Base\\\\TranslationJsonController::class, 'show'])\\
    ->withoutMiddleware(['auth', \\\\Pterodactyl\\\\Http\\\\Middleware\\\\RequireTwoFactorAuthentication::class])\\
    ->where('lang', '[a-z]{2}');\\
// END AutoTranslator" "$BASE_ROUTES"
    echo "  Public JSON route added to base.php"
fi

# ══════════════════════════════════════════════
# 5. PATCH ENCRYPT COOKIES
# ══════════════════════════════════════════════
echo "[5/6] Patching EncryptCookies..."

ENCRYPT="$PANEL_DIR/app/Http/Middleware/EncryptCookies.php"
if [ -f "$ENCRYPT" ] && ! grep -q "autotranslator_lang" "$ENCRYPT"; then
    sed -i "/protected \$except/,/\];/{
        /\];/i\\        'autotranslator_lang',
    }" "$ENCRYPT"
    echo "  EncryptCookies patched"
fi

# ══════════════════════════════════════════════
# 6. FINALIZE
# ══════════════════════════════════════════════
echo "[6/6] Finalizing..."

mkdir -p "$PANEL_DIR/storage/app/translations"

cd "$PANEL_DIR"
php artisan translate:setup 2>/dev/null || true

# Fix permissions now (may partially fail since Blueprint hasn't finished)
chown -R www-data:www-data \
    "$PANEL_DIR/storage" \
    "$PANEL_DIR/bootstrap/cache" \
    "$PANEL_DIR/config/autotranslator.php" \
    "$PANEL_DIR/app/Http/Controllers/Admin/TranslationScanController.php" \
    "$PANEL_DIR/app/Http/Controllers/Base/TranslationJsonController.php" \
    "$PANEL_DIR/app/Http/Controllers/Base/TranslationLanguageController.php" \
    "$PANEL_DIR/app/Services/Helpers/GoogleTranslateService.php" \
    "$PANEL_DIR/app/Console/Commands/ScanTranslationsCommand.php" \
    "$PANEL_DIR/app/Console/Commands/TranslationSetupCommand.php" \
    "$PANEL_DIR/resources/views/admin/translations" \
    "$PANEL_DIR/resources/scripts/plugins/AutoTranslator" \
    2>/dev/null || true

chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod 644 "$PANEL_DIR/config/autotranslator.php" 2>/dev/null || true

php artisan view:clear   2>/dev/null || true
php artisan route:clear  2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear  2>/dev/null || true

# Schedule a delayed permission fix AFTER Blueprint finishes its own process
# Blueprint does chown + webpack after our script, which can break permissions
(
    sleep 60
    cd "$PANEL_DIR" || exit 0
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    php artisan cache:clear 2>/dev/null
    php artisan config:clear 2>/dev/null
) &>/dev/null &
disown 2>/dev/null

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║  AutoTranslator installed successfully!  ║"
echo "╚══════════════════════════════════════════╝"
echo ""
echo "Next steps:"
echo "  1. Visit /admin/extensions/autotranslator"
echo "  2. Run 'Scan' to detect translatable strings"
echo "  3. Click 'Auto Translate' for each language"
echo ""
echo "If you get 500 errors, fix permissions with:"
echo "  chown -R www-data:www-data $PANEL_DIR/storage $PANEL_DIR/bootstrap/cache"
echo "  chmod -R 775 $PANEL_DIR/storage $PANEL_DIR/bootstrap/cache"
echo "  cd $PANEL_DIR && php artisan cache:clear"
echo ""

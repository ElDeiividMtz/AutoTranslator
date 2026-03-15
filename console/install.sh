#!/bin/bash
#
# AutoTranslator v1.0.0 — Blueprint Install Script
# Runs automatically after Blueprint installs the extension.
#
# What Blueprint handles natively (no patching needed):
#   - Admin extension page (conf.yml admin.controller)
#   - Admin routes (routers/web.php)
#   - Client API routes (routers/client.php)
#   - Dashboard wrapper (dashboard/wrapper.blade.php)
#   - Admin wrapper (admin/wrapper.blade.php)
#   - LanguageSelector component (Components.yml)
#   - CSS injection (admin.css, dashboard.css)
#
# What this script handles (no native Blueprint alternative):
#   - PHP backend files (controllers, services, commands)
#   - Config file
#   - Translation dashboard view
#   - Frontend plugin (TypeScript, patching index.tsx)
#   - Public JSON route (needs no-auth access)
#   - EncryptCookies middleware patch
#   - Storage directories and permissions

PANEL_DIR="${BLUEPRINT__ROOTFOLDER:-/var/www/pterodactyl}"
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

# Controllers
cp -f "$PRIV/app/Http/Controllers/Admin/TranslationScanController.php" "$PANEL_DIR/app/Http/Controllers/Admin/"

mkdir -p "$PANEL_DIR/app/Http/Controllers/Base"
cp -f "$PRIV/app/Http/Controllers/Base/TranslationJsonController.php" "$PANEL_DIR/app/Http/Controllers/Base/"
cp -f "$PRIV/app/Http/Controllers/Base/TranslationLanguageController.php" "$PANEL_DIR/app/Http/Controllers/Base/"

# Services
mkdir -p "$PANEL_DIR/app/Services/Helpers"
cp -f "$PRIV/app/Services/Helpers/GoogleTranslateService.php" "$PANEL_DIR/app/Services/Helpers/"

# Console Commands
cp -f "$PRIV/app/Console/Commands/ScanTranslationsCommand.php" "$PANEL_DIR/app/Console/Commands/"
cp -f "$PRIV/app/Console/Commands/TranslationSetupCommand.php" "$PANEL_DIR/app/Console/Commands/"

# Config
cp -f "$PRIV/config/autotranslator.php" "$PANEL_DIR/config/"

echo "  Done"

# ══════════════════════════════════════════════
# 2. COPY VIEWS & FRONTEND
# ══════════════════════════════════════════════
echo "[2/6] Copying views and frontend..."

# Admin translation dashboard view
mkdir -p "$PANEL_DIR/resources/views/admin/translations"
cp -f "$PRIV/resources/views/admin/translations/index.blade.php" "$PANEL_DIR/resources/views/admin/translations/"

# Frontend translation plugin
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
    # Insert before the catch-all React route
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

# Create storage directories
mkdir -p "$PANEL_DIR/storage/app/translations"

# Run setup command
cd "$PANEL_DIR"
php artisan translate:setup 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

# Clear caches
php artisan view:clear   2>/dev/null || true
php artisan route:clear  2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear  2>/dev/null || true

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

#!/bin/bash
#
# AutoTranslator v1.2.0 for Pterodactyl Panel
# Standalone Installer (no Blueprint required)
#
# Author: ElDeiividMtz
# License: MIT
#
# Usage: bash install.sh
#

set -e

PANEL_DIR="/var/www/pterodactyl"
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}╔═══════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     AutoTranslator v1.2.0 — Installer         ║${NC}"
echo -e "${CYAN}║  Multi-language translation for Pterodactyl    ║${NC}"
echo -e "${CYAN}║  ElDeiividMtz · goldstarstudio.net                ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════╝${NC}"
echo ""

ERRORS=0
WARNINGS=0

fail() { echo -e "  ${RED}✗ FAIL:${NC} $1"; ERRORS=$((ERRORS + 1)); }
warn() { echo -e "  ${YELLOW}⚠ WARN:${NC} $1"; WARNINGS=$((WARNINGS + 1)); }
pass() { echo -e "  ${GREEN}✓${NC} $1"; }

# ══════════════════════════════════════════════
# PRE-FLIGHT CHECKS
# ══════════════════════════════════════════════
echo -e "${CYAN}Running pre-flight checks...${NC}"
echo ""

# ── 1. Root check ──
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root${NC}"
    exit 1
fi
pass "Running as root"

# ── 2. Panel directory ──
if [ ! -d "$PANEL_DIR" ]; then
    echo -e "${YELLOW}Pterodactyl panel not found at $PANEL_DIR${NC}"
    read -p "Enter panel path: " PANEL_DIR
    if [ ! -d "$PANEL_DIR" ]; then
        echo -e "${RED}Directory not found. Aborting.${NC}"
        exit 1
    fi
fi
pass "Panel directory: $PANEL_DIR"

# ── 3. Artisan exists ──
if [ ! -f "$PANEL_DIR/artisan" ]; then
    fail "$PANEL_DIR does not appear to be a Pterodactyl installation (artisan not found)"
    echo -e "\n${RED}Cannot continue. Aborting.${NC}"
    exit 1
fi
pass "Laravel artisan found"

ADDON_DIR="$(cd "$(dirname "$0")" && pwd)"
if [ ! -d "$ADDON_DIR/files" ]; then
    fail "files/ directory not found. Ensure install.sh is in the addon root."
    echo -e "\n${RED}Cannot continue. Aborting.${NC}"
    exit 1
fi

# ── 4. PHP version check ──
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo "0.0")
PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
if [ "$PHP_MAJOR" -ge 8 ] && [ "$PHP_MINOR" -ge 1 ]; then
    pass "PHP $PHP_VERSION"
else
    fail "PHP 8.1+ required, found $PHP_VERSION"
fi

# ── 5. Required PHP extensions ──
MISSING_EXT=""
for ext in json mbstring pdo pdo_mysql; do
    if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
        MISSING_EXT="$MISSING_EXT $ext"
    fi
done
if [ -z "$MISSING_EXT" ]; then
    pass "PHP extensions (json, mbstring, pdo, pdo_mysql)"
else
    fail "Missing PHP extensions:$MISSING_EXT"
fi

# ── 6. Panel version ──
PANEL_VER=$(grep -oP "'version'\s*=>\s*'\K[^']+" "$PANEL_DIR/config/app.php" 2>/dev/null || echo "unknown")
if echo "$PANEL_VER" | grep -qP '^1\.1[12]\.\d+'; then
    pass "Panel version: $PANEL_VER"
else
    warn "Panel version $PANEL_VER — addon tested on 1.11.x/1.12.x"
fi

# ── 7. Required panel files exist ──
MISSING_FILES=0
for file in routes/admin.php routes/base.php routes/api-client.php \
    resources/scripts/index.tsx app/Http/ViewComposers/AssetComposer.php \
    resources/views/layouts/admin.blade.php resources/views/templates/wrapper.blade.php; do
    if [ ! -f "$PANEL_DIR/$file" ]; then
        fail "Missing required file: $file"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done
if [ "$MISSING_FILES" -eq 0 ]; then
    pass "All required panel files present (7/7)"
fi

# ── 8. Check if already installed ──
ALREADY_INSTALLED=0
if grep -q "BEGIN AutoTranslator" "$PANEL_DIR/routes/admin.php" 2>/dev/null; then
    ALREADY_INSTALLED=1
fi
if grep -q "AutoTranslator" "$PANEL_DIR/resources/scripts/index.tsx" 2>/dev/null; then
    ALREADY_INSTALLED=1
fi
if [ -f "$PANEL_DIR/config/autotranslator.php" ]; then
    ALREADY_INSTALLED=1
fi
if [ "$ALREADY_INSTALLED" -eq 1 ]; then
    warn "AutoTranslator appears already installed — will update in place"
fi

# ── 9. Check for conflicting translation addons ──
if grep -rql "i18next\|react-i18next\|pterodactyl-translation\|multi-language" \
    "$PANEL_DIR/resources/scripts/" 2>/dev/null | grep -v AutoTranslator | head -1 > /dev/null 2>&1; then
    warn "Another translation system detected — may conflict"
fi

# ── 10. Storage writable ──
if [ -w "$PANEL_DIR/storage/app" ]; then
    pass "storage/app/ is writable"
else
    fail "storage/app/ is not writable"
fi

# ── 11. Database connectivity ──
if cd "$PANEL_DIR" && php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
    pass "Database connection OK"
else
    warn "Database connection check failed — artisan commands may fail"
fi

# ── 12. Disk space check ──
AVAIL_MB=$(df -m "$PANEL_DIR" 2>/dev/null | awk 'NR==2{print $4}')
if [ -n "$AVAIL_MB" ] && [ "$AVAIL_MB" -gt 100 ]; then
    pass "Disk space: ${AVAIL_MB}MB available"
else
    warn "Low disk space: ${AVAIL_MB:-unknown}MB"
fi

# ── Summary ──
echo ""
if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}Pre-flight failed: $ERRORS error(s), $WARNINGS warning(s)${NC}"
    echo -e "${RED}Fix the errors above before installing.${NC}"
    exit 1
fi

if [ "$WARNINGS" -gt 0 ]; then
    echo -e "${YELLOW}Pre-flight passed with $WARNINGS warning(s)${NC}"
    read -p "Continue anyway? (y/N): " CONTINUE
    if [ "$CONTINUE" != "y" ] && [ "$CONTINUE" != "Y" ]; then
        echo "Aborted."
        exit 0
    fi
else
    echo -e "${GREEN}All pre-flight checks passed!${NC}"
fi

echo ""
echo -e "${YELLOW}Installing AutoTranslator to $PANEL_DIR ...${NC}"
echo ""

# ── Step 1: Configuration ──
echo -e "  ${GREEN}[1/9]${NC} Installing configuration..."
cp -f "$ADDON_DIR/files/config/autotranslator.php" "$PANEL_DIR/config/"

# ── Step 2: PHP Backend ──
echo -e "  ${GREEN}[2/9]${NC} Copying PHP backend files..."
cp -f "$ADDON_DIR/files/app/Console/Commands/ScanTranslationsCommand.php" "$PANEL_DIR/app/Console/Commands/"
cp -f "$ADDON_DIR/files/app/Console/Commands/TranslationSetupCommand.php" "$PANEL_DIR/app/Console/Commands/"
cp -f "$ADDON_DIR/files/app/Http/Controllers/Admin/TranslationScanController.php" "$PANEL_DIR/app/Http/Controllers/Admin/"
cp -f "$ADDON_DIR/files/app/Http/Controllers/Base/TranslationJsonController.php" "$PANEL_DIR/app/Http/Controllers/Base/"
mkdir -p "$PANEL_DIR/app/Services/Helpers"
cp -f "$ADDON_DIR/files/app/Services/Helpers/GoogleTranslateService.php" "$PANEL_DIR/app/Services/Helpers/"
mkdir -p "$PANEL_DIR/app/Http/Requests/Api/Client/Account"
cp -f "$ADDON_DIR/files/app/Http/Requests/Api/Client/Account/UpdateLanguageRequest.php" "$PANEL_DIR/app/Http/Requests/Api/Client/Account/"
# Backup original AssetComposer before overwriting
if [ -f "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php" ] && [ ! -f "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php.bak.autotranslator" ]; then
    cp "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php" "$PANEL_DIR/app/Http/ViewComposers/AssetComposer.php.bak.autotranslator"
fi
cp -f "$ADDON_DIR/files/app/Http/ViewComposers/AssetComposer.php" "$PANEL_DIR/app/Http/ViewComposers/"

# ── Step 3: Views ──
echo -e "  ${GREEN}[3/9]${NC} Copying views..."
mkdir -p "$PANEL_DIR/resources/views/admin/translations"
cp -f "$ADDON_DIR/files/resources/views/admin/translations/index.blade.php" "$PANEL_DIR/resources/views/admin/translations/"
cp -f "$ADDON_DIR/files/resources/views/layouts/admin.blade.php" "$PANEL_DIR/resources/views/layouts/"
cp -f "$ADDON_DIR/files/resources/views/templates/wrapper.blade.php" "$PANEL_DIR/resources/views/templates/"

# ── Step 4: Frontend ──
echo -e "  ${GREEN}[4/9]${NC} Copying frontend translation plugin..."
mkdir -p "$PANEL_DIR/resources/scripts/plugins/AutoTranslator"
cp -f "$ADDON_DIR/files/resources/scripts/plugins/AutoTranslator/index.ts" "$PANEL_DIR/resources/scripts/plugins/AutoTranslator/"
mkdir -p "$PANEL_DIR/resources/scripts/components/dashboard/forms"
cp -f "$ADDON_DIR/files/resources/scripts/components/dashboard/forms/UpdateLanguageForm.tsx" "$PANEL_DIR/resources/scripts/components/dashboard/forms/"
mkdir -p "$PANEL_DIR/resources/scripts/components/elements"
cp -f "$ADDON_DIR/files/resources/scripts/components/elements/Translate.tsx" "$PANEL_DIR/resources/scripts/components/elements/"

# ── Step 5: Patch index.tsx ──
echo -e "  ${GREEN}[5/9]${NC} Patching React entry point..."
INDEX_TSX="$PANEL_DIR/resources/scripts/index.tsx"
if [ -f "$INDEX_TSX" ] && ! grep -q "AutoTranslator" "$INDEX_TSX"; then
    if grep -q "react-hot-loader" "$INDEX_TSX"; then
        sed -i "/import.*react-hot-loader.*/a\\
\\
// Auto-translation addon.\\
import { initAutoTranslator } from './plugins/AutoTranslator';\\
initAutoTranslator();" "$INDEX_TSX"
    else
        LAST_IMPORT=$(grep -n "^import " "$INDEX_TSX" | tail -1 | cut -d: -f1)
        if [ -n "$LAST_IMPORT" ]; then
            sed -i "${LAST_IMPORT}a\\
\\
// Auto-translation addon.\\
import { initAutoTranslator } from './plugins/AutoTranslator';\\
initAutoTranslator();" "$INDEX_TSX"
        fi
    fi
    echo "    index.tsx patched"
else
    echo "    index.tsx already patched or not found"
fi

# ── Step 6: Patch routes ──
echo -e "  ${GREEN}[6/9]${NC} Patching routes..."

ADMIN_ROUTES="$PANEL_DIR/routes/admin.php"
if [ -f "$ADMIN_ROUTES" ] && ! grep -q "admin.translations" "$ADMIN_ROUTES"; then
    cat >> "$ADMIN_ROUTES" << 'ROUTES'

// BEGIN AutoTranslator
Route::group(['prefix' => 'translations'], function () {
    Route::get('/', [Admin\TranslationScanController::class, 'index'])->name('admin.translations');
    Route::post('/scan', [Admin\TranslationScanController::class, 'scan'])->name('admin.translations.scan');
    Route::post('/translate/{lang}', [Admin\TranslationScanController::class, 'translate'])->name('admin.translations.translate');
    Route::get('/progress/{lang}', [Admin\TranslationScanController::class, 'progress'])->name('admin.translations.progress');
    Route::get('/list/{lang}', [Admin\TranslationScanController::class, 'getTranslations'])->name('admin.translations.list');
    Route::put('/update/{lang}', [Admin\TranslationScanController::class, 'updateTranslation'])->name('admin.translations.update');
    Route::delete('/delete/{lang}', [Admin\TranslationScanController::class, 'deleteTranslation'])->name('admin.translations.delete');
    Route::get('/export/{lang}', [Admin\TranslationScanController::class, 'exportTranslations'])->name('admin.translations.export');
    Route::post('/import/{lang}', [Admin\TranslationScanController::class, 'importTranslations'])->name('admin.translations.import');
    Route::post('/clear-cache/{lang}', [Admin\TranslationScanController::class, 'clearCache'])->name('admin.translations.clearCache');
    Route::post('/deep-clean', [Admin\TranslationScanController::class, 'deepClean'])->name('admin.translations.deepClean');
    Route::post('/reset-all', [Admin\TranslationScanController::class, 'resetAll'])->name('admin.translations.resetAll');
});
// END AutoTranslator
ROUTES
    echo "    Admin routes registered"
else
    echo "    Admin routes already present"
fi

BASE_ROUTES="$PANEL_DIR/routes/base.php"
if [ -f "$BASE_ROUTES" ] && ! grep -q "TranslationJsonController" "$BASE_ROUTES"; then
    sed -i "/Route::get('\/{react}'/i\\
// BEGIN AutoTranslator\\
Route::get('/translations/{lang}.json', [Base\\\\TranslationJsonController::class, 'show'])\\
    ->withoutMiddleware(['auth', Pterodactyl\\\\Http\\\\Middleware\\\\RequireTwoFactorAuthentication::class])\\
    ->where('lang', '[a-z]{2}')\\
    ->name('translations.json');\\
\\
Route::post('/translations/{lang}/live', [Base\\\\TranslationJsonController::class, 'liveTranslate'])\\
    ->middleware('throttle:5,1')\\
    ->where('lang', '[a-z]{2}')\\
    ->name('translations.live');\\
// END AutoTranslator\\
" "$BASE_ROUTES"
    echo "    Base routes registered"
else
    echo "    Base routes already present"
fi

API_ROUTES="$PANEL_DIR/routes/api-client.php"
if [ -f "$API_ROUTES" ] && ! grep -q "updateLanguage" "$API_ROUTES"; then
    sed -i "/updateEmail/a\\
        Route::put('/language', [Client\\\\AccountController::class, 'updateLanguage']);" "$API_ROUTES"
    echo "    API client route registered"
else
    echo "    API client route already present"
fi

# ── Step 7: Patch admin sidebar ──
echo -e "  ${GREEN}[7/9]${NC} Adding admin sidebar link..."
ADMIN_LAYOUT="$PANEL_DIR/resources/views/layouts/admin.blade.php"
if [ -f "$ADMIN_LAYOUT" ] && ! grep -q "admin.translations" "$ADMIN_LAYOUT"; then
    sed -i '/<\/ul>/i\
            <li class="{{ starts_with(Route::currentRouteName(), '"'"'admin.translations'"'"') ? '"'"'active'"'"' : '"'"''"'"' }}">\
                <a href="{{ route('"'"'admin.translations'"'"') }}">\
                    <i class="fa fa-language"><\/i> <span>Translations<\/span>\
                <\/a>\
            <\/li>' "$ADMIN_LAYOUT"
    echo "    Sidebar link added"
else
    echo "    Sidebar link already present"
fi

# ── Step 8: Setup storage ──
echo -e "  ${GREEN}[8/9]${NC} Running initial setup..."
cd "$PANEL_DIR"
mkdir -p storage/app/translations
php artisan translate:setup 2>/dev/null || echo "    (setup will complete on first scan)"

# ── Step 9: Fix ALL permissions & clear ALL caches ──
echo -e "  ${GREEN}[9/9]${NC} Fixing permissions & clearing caches..."
chown -R www-data:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR"
chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"

php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     Installation Complete!                     ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "Next steps:"
echo -e "  1. Rebuild frontend: ${YELLOW}cd $PANEL_DIR && npx webpack --mode production${NC}"
echo -e "  2. Go to ${YELLOW}Admin > Translations${NC} in your panel"
echo -e "  3. Click ${YELLOW}Run Scan${NC} then ${YELLOW}Auto Translate${NC} for each language"
echo ""
echo -e "${CYAN}AutoTranslator by ElDeiividMtz${NC}"
echo ""

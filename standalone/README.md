# AutoTranslator — Standalone Installation

For panels **without** Blueprint framework. Works on any Pterodactyl v1.12.x.

## Requirements

- Pterodactyl Panel v1.12.x installed at `/var/www/pterodactyl` (or custom path)
- Root access
- PHP 8.1+
- Node.js (for frontend rebuild)

## Install

```bash
# 1. Upload the addon folder to your server
scp -r addons/AutoTranslator/ root@your-server:/tmp/autotranslator/

# 2. SSH into your server and run the installer
ssh root@your-server
cd /tmp/autotranslator
bash install.sh

# 3. Rebuild frontend assets
cd /var/www/pterodactyl
npx webpack --mode production

# 4. Done! Go to Admin > Translations
```

### What the installer does (9 steps):

1. Copies `config/autotranslator.php`
2. Copies all PHP backend files (Controllers, Commands, Services, Requests)
3. Copies views (admin translations dashboard)
4. Copies frontend plugin (AutoTranslator/index.ts, Translate.tsx, UpdateLanguageForm.tsx)
5. Patches `resources/scripts/index.tsx` (adds AutoTranslator import)
6. Registers routes in `routes/admin.php`, `routes/base.php`, `routes/api-client.php`
7. Adds "Translations" link to admin sidebar
8. Runs `php artisan translate:setup`
9. Fixes all permissions and clears all caches

### Custom panel path

If your panel is not at `/var/www/pterodactyl`, the installer will ask for the path.

## Uninstall

```bash
ssh root@your-server
cd /tmp/autotranslator
bash uninstall.sh

# Rebuild frontend
cd /var/www/pterodactyl
npx webpack --mode production
```

The uninstaller will ask if you want to delete translation data. Core files modified by the installer (`AssetComposer.php`, `admin.blade.php`, `wrapper.blade.php`) should be restored manually:

```bash
cd /var/www/pterodactyl
git checkout app/Http/ViewComposers/AssetComposer.php
git checkout resources/views/layouts/admin.blade.php
git checkout resources/views/templates/wrapper.blade.php
```

## File Inventory (14 files + 2 scripts)

```
install.sh                  # Standalone installer
uninstall.sh                # Standalone uninstaller
files/
├── config/autotranslator.php
├── app/
│   ├── Console/Commands/ScanTranslationsCommand.php
│   ├── Console/Commands/TranslationSetupCommand.php
│   ├── Http/Controllers/Admin/TranslationScanController.php
│   ├── Http/Controllers/Base/TranslationJsonController.php
│   ├── Http/Requests/Api/Client/Account/UpdateLanguageRequest.php
│   ├── Http/ViewComposers/AssetComposer.php
│   └── Services/Helpers/GoogleTranslateService.php
├── resources/
│   ├── scripts/
│   │   ├── plugins/AutoTranslator/index.ts
│   │   ├── components/elements/Translate.tsx
│   │   └── components/dashboard/forms/UpdateLanguageForm.tsx
│   └── views/
│       ├── admin/translations/index.blade.php
│       ├── layouts/admin.blade.php
│       └── templates/wrapper.blade.php
```

# AutoTranslator — Blueprint Extension

For panels running the [Blueprint](https://blueprint.zip) extension framework.

## Requirements

- Pterodactyl Panel v1.12.x
- Blueprint framework installed
- PHP 8.1+

## Install

```bash
# 1. Package the extension
cd addons/AutoTranslator/blueprint
zip -r autotranslator.blueprint *

# 2. Upload to your server
scp autotranslator.blueprint root@your-server:/var/www/pterodactyl/

# 3. Install via Blueprint CLI
ssh root@your-server
cd /var/www/pterodactyl
blueprint -install autotranslator.blueprint
```

Blueprint handles:
- Admin page registration (via `conf.yml`)
- Route registration (via `routers/`)
- Dashboard wrapper injection (via `dashboard/wrapper.blade.php`)
- CSS injection (via `admin.css`, `dashboard.css`)

The `console/install.sh` post-install script handles:
- Copying PHP backend files from `private/` to panel directories
- Patching `index.tsx` with AutoTranslator import
- Registering additional routes in `admin.php` and `base.php`
- Adding sidebar link
- Setting up storage, permissions, and clearing caches

## Uninstall

```bash
ssh root@your-server
cd /var/www/pterodactyl
blueprint -remove autotranslator
```

Blueprint runs `console/remove.sh` which cleans up all files and route patches.

**Note**: `AssetComposer.php`, `admin.blade.php`, and `wrapper.blade.php` were modified during install. Restore originals with:

```bash
git checkout app/Http/ViewComposers/AssetComposer.php
git checkout resources/views/layouts/admin.blade.php
git checkout resources/views/templates/wrapper.blade.php
```

## File Structure

```
blueprint/
├── conf.yml                          # Blueprint extension manifest
├── admin/
│   ├── TranslationScanController.php # Blueprint admin controller
│   ├── admin.css                     # Admin panel styles
│   ├── view.blade.php                # Admin page view
│   └── wrapper.blade.php             # Admin wrapper (injects translations)
├── dashboard/
│   ├── dashboard.css                 # Client dashboard styles
│   └── wrapper.blade.php             # Dashboard wrapper (injects translations)
├── console/
│   ├── install.sh                    # Post-install script
│   └── remove.sh                     # Pre-remove script
├── routers/
│   ├── client.php                    # Client API routes (/api/client/extensions/autotranslator/)
│   └── web.php                       # Web routes (admin + public translation JSON)
└── private/
    ├── AssetComposer.php             # ViewComposer with translation injection
    ├── app/
    │   ├── Console/Commands/ScanTranslationsCommand.php
    │   ├── Console/Commands/TranslationSetupCommand.php
    │   ├── Http/Controllers/Admin/TranslationScanController.php
    │   ├── Http/Controllers/Base/TranslationJsonController.php
    │   └── Services/Helpers/GoogleTranslateService.php
    ├── config/autotranslator.php
    └── resources/
        ├── scripts/
        │   ├── plugins/AutoTranslator/index.ts
        │   └── components/elements/Translate.tsx
        └── views/admin/translations/index.blade.php
```

## Differences from Standalone

| Feature | Standalone | Blueprint |
|---------|-----------|-----------|
| Install method | `bash install.sh` | `blueprint -install` |
| Route registration | Patches route files via sed | Uses `routers/` in conf.yml + sed patches |
| Admin page | Patches sidebar via sed | Registered via conf.yml `admin:` section |
| Dashboard wrapper | Replaces `wrapper.blade.php` | Injected via conf.yml `dashboard: wrapper:` |
| UpdateLanguageForm.tsx | Included | Not needed (Blueprint handles dashboard) |
| UpdateLanguageRequest.php | Included | Not needed (Blueprint handles API) |

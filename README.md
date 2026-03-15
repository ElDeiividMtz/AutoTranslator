# AutoTranslator v1.0.0

Multi-language auto-translation extension for Pterodactyl Panel.
Translates the entire panel UI in real-time using DOM interception — no React fork, no core modifications.

![Blueprint Compatible](https://img.shields.io/badge/Blueprint-Compatible-blue)
![Version](https://img.shields.io/badge/version-1.0.0-green)
![License](https://img.shields.io/badge/license-MIT-yellow)

## Features

- **Zero-flicker translations** — Inline JSON injection in `<head>`, translations load before the page renders
- **Full panel coverage** — Admin panel (Laravel Blade) + Client dashboard (React) both translated
- **5 languages included** — Spanish, Portuguese, French, German, Italian
- **Google Translate integration** — One-click auto-translation of all detected strings
- **Smart string scanner** — Detects translatable strings from Blade templates and React TSX files
- **Brand-name protection** — Pterodactyl, Blueprint, Docker, and panel-specific terms (Egg, Nest, Wings) are never translated
- **Language selector component** — Flag-based dropdown injected into Account page via Blueprint Components
- **MutationObserver** — Catches dynamic DOM changes from React re-renders in real time
- **Admin dashboard** — Full translation management UI with scan, edit, export/import, deep clean
- **Rate-limited API** — Protected endpoints to prevent abuse
- **Cached translations** — Laravel cache with configurable TTL for performance

## Supported Languages

| Flag | Code | Language   |
|------|------|-----------|
| 🇪🇸 | es   | Spanish    |
| 🇧🇷 | pt   | Portuguese |
| 🇫🇷 | fr   | French     |
| 🇩🇪 | de   | German     |
| 🇮🇹 | it   | Italian    |

Adding more languages is as simple as editing one config file.

## Installation

### Option 1: Blueprint (Recommended)

```bash
# Download the .blueprint file from Releases (or use the one in this repo)
cp autotranslator.blueprint /var/www/pterodactyl/

# Install via Blueprint CLI
cd /var/www/pterodactyl
blueprint -install autotranslator
```

That's it. Blueprint handles everything automatically.

> **Permissions fix (if you see 500 errors after install):**
> ```bash
> chown -R www-data:www-data /var/www/pterodactyl/storage /var/www/pterodactyl/bootstrap/cache
> chmod -R 775 /var/www/pterodactyl/storage /var/www/pterodactyl/bootstrap/cache
> cd /var/www/pterodactyl && php artisan cache:clear
> ```

### Option 2: Standalone (Without Blueprint)

See [standalone/README.md](standalone/README.md) for manual installation instructions.

## Post-Installation Setup

1. Visit **Admin Panel > Extensions > AutoTranslator**
2. Click **Run Scan** to detect all translatable strings (~1000+ strings)
3. Click **Auto Translate** for each language you want
4. Users can select their language from **Account > Language Selector**

## How It Works

### Client Dashboard (React)
1. `dashboard/wrapper.blade.php` injects `window.__TRANSLATIONS__` and `SiteConfiguration` in `<head>` before React loads
2. `AutoTranslator/index.ts` patches DOM methods (`createTextNode`, `nodeValue`, `textContent`) to translate on render
3. `MutationObserver` catches any dynamic DOM updates from React re-renders
4. `LanguageSelector.tsx` component injected via Blueprint `Components.yml` into Account page

### Admin Panel (Laravel Blade)
1. `admin/wrapper.blade.php` injects inline translations (same zero-flicker pattern)
2. DOM walker translates all text nodes, placeholders, and ARIA labels
3. Extension cards on `/admin/extensions` are excluded from translation (brand protection)

### Fallback
- On pages without auth (login), async fetch to `/translations/{lang}.json` is used as fallback

## Admin Dashboard Features

| Feature | Description |
|---------|-------------|
| **Run Scan** | Detects translatable strings from all Blade and TSX files |
| **Auto Translate** | Translates all strings via Google Translate API |
| **Edit** | Manually edit any translation inline |
| **Export/Import** | JSON backup and restore |
| **Clear Cache** | Refresh cached translations per language |
| **Deep Clean** | Remove orphaned translations not found in current scan |
| **Reset All** | Start fresh (with confirmation) |
| **Flag Editor** | Customize flag images per language |

## Architecture

```
AutoTranslator/
├── conf.yml                              # Extension manifest
├── admin/
│   ├── controller.php                    # Blueprint admin page controller
│   ├── view.blade.php                    # Admin extension page (redirect)
│   ├── wrapper.blade.php                 # Admin inline translations + DOM walker
│   └── admin.css                         # notranslate styles
├── dashboard/
│   ├── wrapper.blade.php                 # Dashboard inline translations + SiteConfiguration
│   ├── dashboard.css                     # notranslate styles
│   └── components/
│       ├── Components.yml                # Blueprint component injection config
│       └── LanguageSelector.tsx          # Language selector with flags
├── routers/
│   ├── web.php                           # Admin routes (12 endpoints)
│   └── client.php                        # Client API routes (rate-limited)
├── console/
│   ├── install.sh                        # Post-install setup script
│   └── remove.sh                         # Clean uninstall script
└── private/
    ├── app/
    │   ├── Console/Commands/
    │   │   ├── ScanTranslationsCommand.php
    │   │   └── TranslationSetupCommand.php
    │   ├── Http/Controllers/
    │   │   ├── Admin/TranslationScanController.php
    │   │   └── Base/
    │   │       ├── TranslationJsonController.php
    │   │       └── TranslationLanguageController.php
    │   └── Services/Helpers/GoogleTranslateService.php
    ├── config/autotranslator.php
    └── resources/
        ├── scripts/
        │   ├── plugins/AutoTranslator/index.ts
        │   └── components/elements/Translate.tsx
        └── views/admin/translations/index.blade.php
```

## Adding a New Language

1. Edit `config/autotranslator.php` — add the language code and name to the `languages` array
2. Go to Admin > Extensions > AutoTranslator > Auto Translate for the new language
3. Done. The language selector and translations update automatically.

## Security

- All translation API endpoints are rate-limited
- Input sanitization on all user-editable translations (strip_tags, length limits)
- Language validation against configured allowlist
- CSRF protection on all admin endpoints
- No eval, no raw HTML in translations

## Requirements

- Pterodactyl Panel v1.12.x
- Blueprint Framework (beta-2026-01 or later)
- PHP 8.1+

## Troubleshooting

| Problem | Solution |
|---------|----------|
| **500 error after install** | Run the permissions fix above. This is the most common issue. |
| **"Failed to clear cache"** during install | Normal — Blueprint fixes this automatically at the end. If errors persist, run `php artisan cache:clear` as root. |
| **Translations not showing** | Make sure you ran Scan first, then Auto Translate. Check that the user selected a language in Account. |
| **Brand names getting translated** | Add them to Protected Terms in the admin dashboard (Admin > Extensions > AutoTranslator > Protected Terms). |
| **Blank page after uninstall** | Run `cd /var/www/pterodactyl && php artisan view:clear && php artisan cache:clear` and fix permissions. |

## Author

**ElDeiividMtz**
- Website: [goldstarstudio.net](https://goldstarstudio.net)
- BuiltByBit: [eldeiividmtz](https://builtbybit.com/members/eldeiividmtz.550034/)
- GitHub: [ElDeiividMtz](https://github.com/ElDeiividMtz)

## License

MIT

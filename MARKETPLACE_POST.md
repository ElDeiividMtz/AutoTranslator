# AutoTranslator — Marketplace Post

Use this content to publish the extension on the Blueprint marketplace, Discord, or forums.

---

## Short Description (for cards/listings)

Multi-language auto-translation for Pterodactyl Panel. Supports ES, PT, FR, DE, IT with Google Translate integration and zero-flicker DOM translation.

---

## Full Post

### AutoTranslator v1.0.0

Automatically translate your entire Pterodactyl Panel into multiple languages — both the admin area and the client dashboard.

**No React fork. No core file modifications. Just install and translate.**

#### What it does

- Translates the full panel UI (admin + client) into **Spanish, Portuguese, French, German, and Italian**
- Zero-flicker: translations are injected inline before the page renders
- Includes a built-in **translation manager** with scan, auto-translate, edit, export/import
- Adds a **language selector** to the Account page so users can pick their language
- Smart enough to never translate brand names (Pterodactyl, Blueprint, Docker) or panel-specific terms (Egg, Nest, Wings)

#### How it works

1. Install the extension via Blueprint
2. Go to Admin > Extensions > AutoTranslator
3. Click "Run Scan" to detect all translatable strings (~1000+)
4. Click "Auto Translate" for each language
5. Done — your panel is now multilingual

#### Key Features

- **Zero-flicker translations** — loads before the page renders, no "flash of English"
- **1000+ translatable strings** detected automatically
- **Google Translate** one-click auto-translation
- **Inline editor** — fix any translation directly from the admin dashboard
- **Export/Import** — backup your translations as JSON
- **Deep Clean** — remove orphaned translations after panel updates
- **Rate-limited API** — protected against abuse
- **MutationObserver** — catches dynamic React DOM changes in real-time
- **Cached** — translations are cached server-side for performance

#### Adding more languages

Edit one config file (`config/autotranslator.php`), add the language code and name, then auto-translate from the dashboard. That's it.

#### Requirements

- Pterodactyl Panel v1.12.x
- Blueprint Framework

#### Installation

```bash
cd /var/www/pterodactyl
blueprint -install autotranslator
```

#### Author

ElDeiividMtz
- https://goldstarstudio.net
- https://builtbybit.com/members/eldeiividmtz.550034/
- https://github.com/ElDeiividMtz

---

## Tags/Keywords

translation, multi-language, i18n, localization, spanish, portuguese, french, german, italian, auto-translate, google translate

---

## Screenshots Checklist

Take these screenshots for the marketplace listing:

1. [ ] **Language Selector** — Account page showing the flag dropdown
2. [ ] **Admin Dashboard** — Translation manager with scan results and language tabs
3. [ ] **Translated Dashboard** — Client dashboard in Spanish (or another language)
4. [ ] **Translated Admin** — Admin panel sidebar + page in Spanish
5. [ ] **Auto Translate** — Progress bar during auto-translation
6. [ ] **Edit Translation** — Inline editing a specific translation

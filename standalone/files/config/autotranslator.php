<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 *
 * Central configuration file — all translation settings in one place.
 */

return [
    // Supported languages (besides English which is always the source)
    'languages' => [
        'es' => 'Español',
        'pt' => 'Português',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
    ],

    // Country code for flag images per language (customizable from admin dashboard)
    // Uses flagcdn.com for rendering: https://flagcdn.com/24x18/{code}.png
    // Stored in storage/app/translations/flags.json at runtime when customized
    'default_flags' => [
        'en' => 'us',
        'es' => 'mx',
        'pt' => 'br',
        'fr' => 'fr',
        'de' => 'de',
        'it' => 'it',
    ],

    // Cache TTL in seconds for inline translations (injected via window.__TRANSLATIONS__)
    'cache_ttl' => (int) env('AUTOTRANSLATOR_CACHE_TTL', 300),

    // Google Translate API settings
    'google' => [
        'base_url' => 'https://translate.googleapis.com/translate_a/single',
        'chunk_size' => 4000,        // Max characters per translation request
        'delay_ms' => 200,           // Delay between API chunks (ms)
        'timeout' => 15,             // HTTP timeout per request (seconds)
        'max_per_request' => 200,    // Max strings per live-translate call
    ],

    // Storage paths (relative to storage_path('app/'))
    'storage' => [
        'translations_dir' => 'translations',
        'scan_results' => 'translation-scan-results.json',
    ],

    // Auto-fix file permissions after writing translation files
    // Set to false if your web server user owns the storage directory
    'fix_permissions' => env('AUTOTRANSLATOR_FIX_PERMISSIONS', true),
    'web_user' => env('AUTOTRANSLATOR_WEB_USER', 'www-data'),
    'web_group' => env('AUTOTRANSLATOR_WEB_GROUP', 'www-data'),

    // Brand names and technical terms protected from translation.
    // Replaced with placeholders before sending to Google Translate, then restored after.
    'protected_terms' => [
        'Blueprint',
        'Pterodactyl',
        'Docker',
        'Wings',
        'Egg',
        'Eggs',
        'Nest',
        'Nests',
        'Node',
        'Nodes',
        'SFTP',
        'SSH',
        'API',
        'GitHub',
        'Discord',
        'MySQL',
        'MariaDB',
        'Redis',
        'Nginx',
        'Cloudflare',
        'cPanel',
        'phpMyAdmin',
        'Laravel',
        'AutoTranslator',
        'Translation Manager',
    ],
];

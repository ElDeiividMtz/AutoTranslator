<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'translate:scan {--update : Append new strings to dictionary file} {--lang= : Target language (es,pt,fr,de,it)}';
    protected $description = 'Scan all panel views and components for untranslated strings';

    private function supportedLanguages(): array
    {
        return array_keys(config('autotranslator.languages', ['es' => 'Español']));
    }

    private array $tsxPatterns = [
        // Text content between JSX tags: >Some visible text<
        '/>\s*([A-Z][^<>{}\n]{2,}?)\s*</m',
        // Lowercase multi-word text between tags (5+ chars to avoid code)
        '/>\s*([a-z][^<>{}\n]{4,}?)\s*</m',
        // Visible text in JSX props: title={'Text'}, label={'Text'}, description={'Text'}
        // These use ={'...'} syntax (JSX expression with string literal)
        '/(?:label|title|placeholder|description|message|content|confirm|heading)\s*=\s*\{\s*[\'"]([A-Za-z][^\'"\n]{2,})[\'"]\s*\}/m',
        // Visible text in JSX props with plain quotes: title="Text", placeholder="Text"
        '/(?:label|title|placeholder|description|message|content|confirm|heading)\s*=\s*[\'"]([A-Za-z][^\'"\n]{2,})[\'"]/m',
        // Multi-word text inside closing tags
        '/>\s*([A-Z][a-zA-Z\s\-\'\.,:;!?]{2,}?)\s*<\//m',
        // Object property strings for display: { title: 'Text', message: 'Text' }
        '/(?:title|message|label|text|heading|description|placeholder)\s*:\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
        // Yup/validation error messages (any case)
        '/\.(?:required|min|max|test|matches)\s*\(\s*[\'"]([A-Za-z][^\'"\n]{5,})[\'"]/m',
        // Ternary expressions with visible text: condition ? 'Text' : 'Other'
        '/\?\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]\s*:/m',
        '/:\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]\s*[\)}\]]/m',
        // JSX expression strings: {'Some text'} — multi-word only
        '/\{\s*[\'"]([A-Z][a-zA-Z\s\-\'\.,:;!?]{3,})[\'"]\s*\}/m',
        // Flash/toast messages: addFlash({...title: 'Error', message: 'text'})
        '/(?:addFlash|addError|clearFlash)\s*\([^)]*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
        // Dialog confirm prop: confirm={'Delete Key'}
        '/confirm\s*=\s*\{\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]\s*\}/m',
        // ServerError/Alert messages
        '/(?:ServerError|Alert)\s[^>]*message\s*=\s*\{\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
        // Inline conditional text: loading ? 'Loading...' : 'No items'
        '/\?\s*[\'"]([A-Za-z][^\'"\n]{3,}\.{0,3})[\'"]\s*:\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
    ];

    private array $bladePatterns = [
        // Text between any HTML tags (including lowercase start)
        '/>\s*([A-Za-z][^<>{}\n@$]{2,}?)\s*</m',
        // Label, button, th, td, h1-h6, small, strong, span, p, a, li content
        '/<(?:label|button|th|td|h[1-6]|small|strong|em|span|p|a|li|legend|caption|dt|dd)[^>]*>\s*([^<@{$\n]{2,}?)\s*<\//m',
        // Attributes: title, placeholder, alt, value, data-original-title
        '/(?:title|placeholder|alt|data-original-title)\s*=\s*"([^"@{$]{3,})"/m',
        // Text after </i> (icon + label pattern, common in admin)
        '/<\/i>\s*([A-Za-z][^<{}\n]{2,}?)\s*</m',
        // Text inside <span> after icon
        '/<\/i>\s*<span>([^<]{2,}?)<\/span>/m',
        // Standalone text lines between tags (multi-word English sentences)
        '/>\s*([A-Z][a-z]+(?:\s+[a-zA-Z,\.\-\']+){2,})\s*[.<]/m',
        // Select option text
        '/<option[^>]*>\s*([^<@{$]{2,}?)\s*<\/option>/m',
    ];

    /**
     * Known UI strings that must NEVER be filtered out, even if they match
     * ignore patterns (single lowercase word, short string, etc.).
     * Compiled from Arix Translation Pack + Pterodactyl community projects.
     */
    private array $uiWhitelist = [
        // Common buttons & actions
        'Cancel', 'Close', 'Continue', 'Create', 'Delete', 'Disable', 'Edit',
        'Enable', 'Login', 'Logout', 'Register', 'Rename', 'Restore', 'Save',
        'Search', 'Upload', 'Download', 'Update', 'save',
        // Status labels
        'Active', 'Inactive', 'Online', 'Offline', 'Starting', 'Stopping',
        'Suspended', 'Installing', 'Processing', 'Status', 'Failed',
        'Transferring', 'Unavailable',
        // Navigation / sections
        'Account', 'Activity', 'Backups', 'Configuration', 'Console',
        'Dashboard', 'Databases', 'Files', 'Management', 'Network',
        'Schedules', 'Servers', 'Settings', 'Startup', 'Users',
        // Form labels
        'Description', 'Email', 'Endpoint', 'Hostname', 'Name', 'Notes',
        'Password', 'Payload', 'Permissions', 'Port', 'Primary', 'Username',
        'Variables',
        // Resource labels
        'CPU', 'Disk', 'Memory', 'Node', 'IP', 'Uptime',
        'CPU Usage', 'Disk Usage', 'Memory Usage',
        'Inbound / Outbound', 'Unlimited',
        // Server actions
        'Start', 'Stop', 'Restart', 'Kill',
        // Misc UI
        'Action', 'Appearance', 'Dark', 'Light', 'Locked',
        'Minute', 'Hour', 'Month', 'Lock', 'Unlock',
        'Copy', 'Move', 'Archive', 'Unarchive',
        'Loading...', 'Read Only',
        // Arix-specific but common
        'On', 'Off', 'Done',
        // Additional single words visible in UI
        'Name', 'Error', 'File',
        // Lowercase visible in power controls / UI context
        'start', 'stop', 'kill', 'error', 'password', 'file',
        'database', 'databases', 'backups', 'schedules', 'settings', 'users',
        'account', 'admin', 'installing',
        // Auth/login page strings
        'Login to Continue', 'Username or Email', 'Forgot Password?',
        'Remember Me', 'Sign In', 'Sign Up', 'Sign Out',
        'Reset Password', 'Send Password Reset Link', 'Confirm Password',
        'Two Factor Authentication', 'Recovery Code', 'Submit',
        'I already have an account', 'Create Account',
        'Login', 'Username', 'Password', 'Email',
        // Common page titles and navigation
        'Overview', 'Home', 'Welcome', 'Loading', 'Confirm',
        'Are you sure?', 'Yes', 'No', 'OK', 'Apply', 'Reset',
        'Previous', 'Next', 'Back', 'Forward',
        'Select', 'Choose', 'Browse', 'Refresh', 'Retry',
        'Success', 'Warning', 'Info', 'Danger',
        // Server/resource management (common across themes)
        'Resources', 'Allocation', 'Allocations', 'Location', 'Locations',
        'Connections', 'Manage', 'Details', 'General', 'Advanced',
        'Mounts', 'Nests', 'Eggs', 'Nodes',
    ];

    private array $ignorePatterns = [
        '/^[A-Z_]{2,}$/',          // ALL_CAPS constants
        '/^[a-z]+:/',               // namespaced keys
        '/^https?:/',               // URLs
        '/^[\/\\\\]/',              // paths
        '/^\d/',                    // starts with number
        '/^[{<@$]/',               // template syntax
        // HTML tags and attributes
        '/^(div|span|img|svg|br|hr|input|button|form|table|tr|td|th|select|option|class|style|type|id|name|method|action|href|src|rel|role|aria|data)$/i',
        // JS/PHP keywords and TypeScript type syntax
        '/^(true|false|null|undefined|function|return|const|let|var|import|export|require|module|extends|yield|section|foreach|endforeach|endif|if|else|elseif|csrf|endsection|include|php|endphp|async|await|this|new|typeof|instanceof|switch|case|default|break|continue|throw|try|catch|finally)$/i',
        // TypeScript code patterns: "const user: Type", "extends ClassName"
        '/\b(const|let|var)\s+\w+\s*[:=]/i',
        '/\bextends\s+[A-Z]/',
        '/^[a-z_]+\.[a-z_]+/',     // dot-notation keys
        '/^[a-f0-9-]{8,}$/i',      // UUIDs/hashes
        '/\.php$/',                 // file names
        '/^[A-Z][a-z]+[A-Z]/',     // camelCase
        '/^fa\s/',                  // font-awesome classes
        '/^btn\s/',                 // button classes
        '/^col-/',                  // bootstrap columns
        '/^\w+\(/',                 // function calls
        // ── Tailwind CSS classes ──
        '/^(flex|grid|block|inline|hidden|relative|absolute|fixed|sticky|overflow|rounded|border|shadow|opacity|transition|duration|ease|transform|scale|rotate|translate|cursor|pointer|select|resize|appearance|outline|ring|gap|space|divide|place|justify|items|self|order|float|clear|object|z|inset|top|right|bottom|left|w|h|min|max|p|m|text|font|leading|tracking|whitespace|break|truncate|align|underline|line|decoration|list|table|caption|bg|from|via|to|gradient|fill|stroke|sr)$/i',
        // Tailwind utility patterns: w-full, flex-1, text-sm, px-4, mt-2, etc.
        '/^[a-z]+-[\da-z]/',
        '/^-?[a-z]+[\d]$/',
        // React component props (not visible text)
        '/^(className|onClick|onChange|onSubmit|onBlur|onFocus|onKeyDown|onKeyUp|onMouseDown|onMouseUp|onMouseEnter|onMouseLeave|disabled|checked|selected|readOnly|required|autoFocus|autoComplete|tabIndex|htmlFor|dangerouslySetInnerHTML|ref|key|children|loading|isLoading|isOpen|isVisible|isDisabled|isActive|variant|size|color|icon|iconPosition)$/i',
        // Common React/code single words that are NOT user-visible text
        '/^(container|wrapper|modal|tooltip|dropdown|popover|sidebar|header|footer|navbar|breadcrumb|pagination|spinner|skeleton|badge|chip|avatar|divider|spacer|overlay|backdrop|portal|fragment|slot|layout|main|content|body|panel|card|section|row|column|item|group|list|stack|box|root|app|mount|render|component|element|hook|context|provider|consumer|store|reducer|action|dispatch|state|props|params|query|mutation|handler|callback|listener|observer|emitter|factory|builder|adapter|proxy|decorator|middleware|interceptor|resolver|loader|fetcher|parser|serializer|validator|formatter|converter|mapper|filter|sorter|comparator|iterator|generator|scheduler|timer|counter|logger|debugger|profiler|monitor|tracker|recorder|reporter|analyzer|scanner|crawler|scraper|indexer|cache|buffer|pool|queue|stack|heap|tree|graph|node|edge|vertex|leaf|branch)$/i',
    ];

    public function handle(): int
    {
        $this->info('Scanning panel for translatable strings...');
        $this->newLine();

        $collected = [];

        // ── Core panel ──
        $tsxStrings = $this->scanDirectory(
            resource_path('scripts'),
            ['tsx', 'ts'],
            $this->tsxPatterns
        );
        $collected = array_merge($collected, $tsxStrings);
        $this->info(sprintf('  Core scripts (TSX/TS): %d strings', count($tsxStrings)));

        $bladeStrings = $this->scanDirectory(
            resource_path('views'),
            ['blade.php'],
            $this->bladePatterns
        );
        $collected = array_merge($collected, $bladeStrings);
        $this->info(sprintf('  Core views (Blade): %d strings', count($bladeStrings)));

        // ── Blueprint extensions ──
        // Blueprint installs views to resources/views/blueprint/ and
        // extension app logic to app/BlueprintFramework/Extensions/
        $scanSources = [
            ['path' => resource_path('views/blueprint'), 'exts' => ['blade.php'], 'patterns' => $this->bladePatterns, 'label' => 'Blueprint views'],
            ['path' => base_path('.blueprint/extensions'), 'exts' => ['blade.php', 'tsx', 'ts'], 'patterns' => null, 'label' => 'Blueprint extensions data'],
            ['path' => resource_path('scripts/blueprint'), 'exts' => ['tsx', 'ts'], 'patterns' => $this->tsxPatterns, 'label' => 'Blueprint components'],
            // Blueprint framework core (controllers with error messages, etc.)
            ['path' => app_path('BlueprintFramework'), 'exts' => ['php'], 'patterns' => $this->phpPatterns(), 'label' => 'Blueprint framework PHP'],
            // Themes — may contain custom Blade templates with translatable text
            ['path' => resource_path('views/templates'), 'exts' => ['blade.php'], 'patterns' => $this->bladePatterns, 'label' => 'Template overrides'],
            // Public theme assets (JS with UI strings)
            ['path' => public_path('themes'), 'exts' => ['js'], 'patterns' => $this->jsPatterns(), 'label' => 'Theme assets'],
            // Standalone addon controllers (may have flash/error messages)
            ['path' => app_path('Extensions'), 'exts' => ['php'], 'patterns' => $this->phpPatterns(), 'label' => 'Standalone extensions PHP'],
            // Jexactyl / Pelican / custom panel extension paths
            ['path' => app_path('Addons'), 'exts' => ['php'], 'patterns' => $this->phpPatterns(), 'label' => 'Addon PHP controllers'],
            ['path' => resource_path('views/admin/jexactyl'), 'exts' => ['blade.php'], 'patterns' => $this->bladePatterns, 'label' => 'Jexactyl admin views'],
        ];

        foreach ($scanSources as $source) {
            if (!is_dir($source['path'])) continue;

            $sourceStrings = [];
            foreach ((array) $source['exts'] as $ext) {
                // Choose patterns based on file type
                if ($source['patterns']) {
                    $patterns = $source['patterns'];
                } else {
                    $patterns = in_array($ext, ['tsx', 'ts']) ? $this->tsxPatterns : $this->bladePatterns;
                }
                $sourceStrings = array_merge($sourceStrings, $this->scanDirectory($source['path'], [$ext], $patterns));
            }

            if (!empty($sourceStrings)) {
                $collected = array_merge($collected, $sourceStrings);
                $this->info(sprintf('  %s: %d strings', $source['label'], count($sourceStrings)));
            }
        }

        $allStrings = array_unique($collected);
        sort($allStrings);

        $this->info(sprintf('Total unique strings found: %d', count($allStrings)));
        $this->newLine();

        $languageResults = [];

        foreach ($this->supportedLanguages() as $lang) {
            $dictPath = resource_path("scripts/plugins/AutoTranslator/dictionaries/{$lang}.ts");
            $translations = $this->parseDictionary($dictPath);

            // Also load runtime JSON translations
            $jsonPath = storage_path("app/translations/{$lang}.json");
            if (File::exists($jsonPath)) {
                $jsonTranslations = json_decode(File::get($jsonPath), true) ?: [];
                $translations = array_merge($translations, $jsonTranslations);
            }

            $untranslated = array_filter($allStrings, function (string $str) use ($translations) {
                return !isset($translations[$str]) || $translations[$str] === '';
            });
            $untranslated = array_values($untranslated);

            $translated = count($allStrings) - count($untranslated);

            $languageResults[$lang] = [
                'translated' => $translated,
                'untranslated_count' => count($untranslated),
                'untranslated' => $untranslated,
                'percent' => count($allStrings) > 0 ? round(($translated / count($allStrings)) * 100) : 0,
            ];

            $this->info(sprintf('[%s] Translated: %d / %d (%d%%)', strtoupper($lang), $translated, count($allStrings), $languageResults[$lang]['percent']));
        }

        $resultsPath = storage_path('app/translation-scan-results.json');
        File::put($resultsPath, json_encode([
            'scanned_at' => now()->toIso8601String(),
            'total_strings' => count($allStrings),
            'all_strings' => $allStrings,
            'languages' => $languageResults,
            // Legacy compat
            'translated' => $languageResults['es']['translated'] ?? 0,
            'untranslated_count' => $languageResults['es']['untranslated_count'] ?? 0,
            'untranslated' => $languageResults['es']['untranslated'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("Results saved to: $resultsPath");

        if ($this->option('update')) {
            $targetLang = $this->option('lang') ?: 'es';
            $dictPath = resource_path("scripts/plugins/AutoTranslator/dictionaries/{$targetLang}.ts");
            if (File::exists($dictPath) && !empty($languageResults[$targetLang]['untranslated'])) {
                $this->appendToDictionary($dictPath, $languageResults[$targetLang]['untranslated']);
                $this->info("New entries appended to dictionaries/{$targetLang}.ts with empty values.");
            }
        }

        return 0;
    }

    /**
     * Patterns for PHP files — catches flash messages, error strings, response messages,
     * and visible text that controllers/services return to the UI.
     */
    private function phpPatterns(): array
    {
        return [
            // Flash/session messages: ->with('error', 'Something failed')
            '/->with\s*\(\s*[\'"](?:error|success|warning|info|message|status)[\'"]\s*,\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]\s*\)/m',
            // JSON response messages: ['message' => 'Operation completed']
            '/[\'"](?:message|error|status|title|description)[\'"]\s*=>\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
            // Abort/exception messages: abort(403, 'Not authorized')
            '/abort\s*\(\s*\d+\s*,\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
            // Validation messages: 'field.required' => 'This field is required'
            '/=>\s*[\'"]([A-Z][^\'"\n]{5,}[.!?]?)[\'"]\s*[,\]]/m',
            // Alert/notification text in controllers
            '/(?:Alert|Notification|Flash)\s*::\s*\w+\s*\(\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
        ];
    }

    /**
     * Patterns for JS files — catches UI strings in theme JavaScript (modals, tooltips, labels).
     */
    private function jsPatterns(): array
    {
        return [
            // String assignments for UI: title: 'Text', message: 'Text'
            '/(?:title|message|label|text|heading|description|placeholder|tooltip|confirm)\s*:\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
            // Alert/confirm/prompt: alert('Something happened')
            '/(?:alert|confirm|prompt)\s*\(\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
            // innerHTML/textContent assignments with visible text
            '/(?:innerHTML|textContent|innerText)\s*=\s*[\'"]([A-Za-z][^\'"\n]{3,})[\'"]/m',
            // Template literals with visible text: `Some text ${var} more text`
            '/`([A-Z][^`\n]{5,})`/m',
            // document.querySelector + text: .textContent = 'Text'
            '/\.(?:text|textContent|innerHTML)\s*=\s*[\'"]([A-Za-z][^\'"\n]{2,})[\'"]/m',
        ];
    }

    private function scanDirectory(string $path, array $extensions, array $patterns): array
    {
        $strings = [];
        $isBlade = in_array('blade.php', $extensions);

        foreach ($extensions as $ext) {
            $files = $this->recursiveGlob($path, $ext);

            foreach ($files as $file) {
                // Skip AutoTranslator's own files — they aren't user-facing panel text
                if (str_contains($file, 'plugins/AutoTranslator/') || str_contains($file, 'dictionaries/')) {
                    continue;
                }

                // Skip UpdateLanguageForm — contains hardcoded translations for the language selector itself
                if (str_contains($file, 'UpdateLanguageForm')) {
                    continue;
                }

                $content = File::get($file);

                // For Blade: strip strings already inside @lang(), trans(), __() — already translated by Laravel
                if ($isBlade) {
                    $content = $this->stripAlreadyTranslated($content);
                }

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        // Capture all groups (some patterns have 2+ capture groups)
                        $groupCount = count($matches);
                        for ($g = 1; $g < $groupCount; $g++) {
                            foreach ($matches[$g] as $match) {
                                if ($match === '') continue;
                                $cleaned = $this->cleanString($match);
                                if ($cleaned && $this->isTranslatable($cleaned)) {
                                    $strings[] = $cleaned;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_unique($strings);
    }

    /**
     * Remove content already handled by Laravel's translation system
     * so the scanner doesn't pick up strings inside @lang(), trans(), __(), etc.
     */
    private function stripAlreadyTranslated(string $content): string
    {
        // Remove @lang('...') and @lang("...")
        $content = preg_replace('/@lang\s*\(\s*[\'"][^\'"]*[\'"]\s*(?:,\s*\[[^\]]*\])?\s*\)/', '', $content);
        // Remove trans('...') and __('...')
        $content = preg_replace('/(?:trans|__)\s*\(\s*[\'"][^\'"]*[\'"]\s*(?:,\s*\[[^\]]*\])?\s*\)/', '', $content);
        // Remove {{ trans(...) }} and {{ __(...) }}
        $content = preg_replace('/\{\{\s*(?:trans|__)\s*\([^)]*\)\s*\}\}/', '', $content);

        return $content;
    }

    private function cleanString(string $str): string
    {
        $str = trim($str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = rtrim($str, ' ');

        return $str;
    }

    private function isTranslatable(string $str): bool
    {
        if (strlen($str) < 2 || strlen($str) > 500) {
            return false;
        }

        // Known UI strings bypass ALL filters
        if (in_array($str, $this->uiWhitelist, true)) {
            return true;
        }

        foreach ($this->ignorePatterns as $pattern) {
            if (preg_match($pattern, $str)) {
                return false;
            }
        }

        // Must contain at least one letter
        if (!preg_match('/[a-zA-Z]/', $str)) {
            return false;
        }

        // Reject code-like strings
        if (preg_match('/[{}()\[\]=>]/', $str)) {
            return false;
        }

        // Reject already-translated strings (non-English chars — Spanish, French, German, Portuguese, Italian, etc.)
        if (preg_match('/[áéíóúñ¿¡àâãçêîôûäöüèùëïÿœæ]/u', $str)) {
            return false;
        }

        // ── Technical content protection ──

        // UUIDs
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str)) {
            return false;
        }

        // IP addresses with optional port
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$/', $str)) {
            return false;
        }

        // Pure numbers with optional units
        if (preg_match('/^[\d,.]+\s*(%|MB|GB|KB|TB|ms|s|B|GiB|MiB|KiB)?$/i', $str)) {
            return false;
        }

        // Email addresses
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $str)) {
            return false;
        }

        // Connection strings (sftp://, jdbc://, etc.)
        if (preg_match('/^(sftp|ssh|jdbc|mysql|https?|ftp):\/\//i', $str)) {
            return false;
        }

        // Version numbers: 1.12.1, v1.0.0
        if (preg_match('/^v?\d+\.\d+(\.\d+)*(-[\w.]+)?$/', $str)) {
            return false;
        }

        // Environment variable names (ALL_CAPS with underscores)
        if (preg_match('/^[A-Z][A-Z0-9_]{2,}$/', $str)) {
            return false;
        }

        // Docker images: ghcr.io/image:tag
        if (preg_match('/^[\w.-]+\.(io|com|org)\/[\w.\/-]+(:\w+)?$/', $str)) {
            return false;
        }

        // Cron expressions
        if (preg_match('/^[\d*\/,-]+(\s+[\d*\/,-]+){3,5}$/', $str)) {
            return false;
        }

        // Database names with prefix: s1_mydb
        if (preg_match('/^s\d+_\w+$/', $str)) {
            return false;
        }

        // Long tokens/API keys
        if (preg_match('/^[A-Za-z0-9_-]{32,}$/', $str)) {
            return false;
        }

        // Reject CSS/class-like single words with hyphens or underscores
        if (preg_match('/^[\w-]+$/', $str) && !preg_match('/\s/', $str) && strlen($str) < 30) {
            // Allow real English words like "Server", "Dashboard", "MANAGEMENT"
            if (!preg_match('/^[A-Z][a-z]{2,}$/', $str) && !preg_match('/^[A-Z]{2,}$/', $str)) {
                if (preg_match('/[-_]/', $str)) {
                    return false;
                }
            }
        }

        // ── Additional filters to prevent junk translations ──

        // HTML entities
        if (preg_match('/^&[a-z]+;$/i', $str)) {
            return false;
        }

        // Time values like 0.028s, 1.5ms
        if (preg_match('/^\d+[\d.]*\s*(ms|s|m|h)$/i', $str)) {
            return false;
        }

        // Regex-like patterns
        if (preg_match('/^[a-z]-[a-z]\s+[A-Z]-[A-Z]\s+\d/', $str)) {
            return false;
        }

        // File extensions or mime types
        if (preg_match('/^[\w\/]+\.\w{2,4}$/', $str)) {
            return false;
        }

        // MIME types: multipart/form-data, application/json
        if (preg_match('/^[\w-]+\/[\w-]+$/', $str)) {
            return false;
        }

        // Shell/CLI commands: df -h, ls -la, etc.
        if (preg_match('/^[a-z]{2,}\s+-[a-zA-Z]/', $str)) {
            return false;
        }

        // TypeScript type declarations and code fragments
        if (preg_match('/\b(const|let|var|extends|implements|typeof|instanceof)\s+\w/i', $str)) {
            return false;
        }
        if (preg_match('/\bundefined\s*\||\|\s*(string|number|boolean|null)\b/i', $str)) {
            return false;
        }

        // Code patterns: isX ? children :, fn(), Promise<
        if (preg_match('/\?\s*\w+\s*:/', $str) || preg_match('/\w+\(/', $str) || preg_match('/<\w+>/', $str)) {
            return false;
        }

        // C/system error messages (pthread, malloc, etc.)
        if (preg_match('/^(pthread|malloc|fork|exec|mmap|brk)/', $str)) {
            return false;
        }

        // React/dev-only error messages
        if (preg_match('/\b(component|prop|render|hook|ref|context|provider)\b/i', $str) &&
            preg_match('/\b(without|missing|invalid|expected|unexpected)\b/i', $str)) {
            return false;
        }

        // Strings that are just 1-3 lowercase letters (likely code identifiers)
        if (preg_match('/^[a-z]{1,3}$/', $str)) {
            return false;
        }

        // ── Tailwind / CSS class patterns ──

        // Tailwind compound classes: "flex items-center", "text-white bg-gray-900"
        if (preg_match('/^[a-z][\w-]*(\s+[a-z][\w-]*){0,}$/', $str) && preg_match('/[-]/', $str)) {
            return false;
        }

        // CSS property values (case-sensitive — only match lowercase CSS keywords)
        if (preg_match('/^(auto|none|inherit|initial|unset|transparent|block|inline|flex|grid|hidden|visible|absolute|relative|fixed|sticky|normal|bold|italic|center|left|right|top|bottom|middle|nowrap|wrap|uppercase|lowercase|capitalize|underline|overline)$/', $str)) {
            return false;
        }

        // Color hex codes or color names used as values
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $str)) {
            return false;
        }

        // ── React / TypeScript code patterns ──

        // Single lowercase word under 4 chars without spaces — very likely code identifier
        // (longer lowercase words like "save", "once deleted." are caught by JSX patterns and should pass)
        if (preg_match('/^[a-z]+$/', $str) && strlen($str) < 4) {
            return false;
        }

        // Single lowercase words that commonly appear as activity log keys, code identifiers,
        // or PHP/JS reserved words — NOT visible UI text (whitelisted words bypass this)
        if (preg_match('/^(alias|bounce|code|http|index|invisible|must|only|open|private|virtual|will|public|protected|static|final|abstract|interface|enum|class|trait|match|clone|goto|recoveryCode)$/', $str)) {
            return false;
        }

        // HTTP methods
        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)$/i', $str)) {
            return false;
        }

        // Data types and common code terms
        if (preg_match('/^(string|number|boolean|object|array|integer|float|double|void|any|never|unknown|null|undefined|bigint|symbol)$/i', $str)) {
            return false;
        }

        // JSON/config keys (single word, all lowercase or snake_case)
        if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)+$/', $str)) {
            return false;
        }

        // Already translated by Laravel's @lang() — skip strings from lang files
        if (isset($this->getLaravelNativeStrings()[$str])) {
            return false;
        }

        return true;
    }

    private ?array $laravelNativeCache = null;

    /**
     * Load all English strings from Laravel's native lang files (memoized).
     */
    private function getLaravelNativeStrings(): array
    {
        if ($this->laravelNativeCache !== null) {
            return $this->laravelNativeCache;
        }

        $this->laravelNativeCache = [];
        $langDir = resource_path('lang/en');

        if (!is_dir($langDir)) {
            return $this->laravelNativeCache;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($langDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $arr = @include $file->getPathname();
            if (is_array($arr)) {
                array_walk_recursive($arr, function ($v) {
                    if (is_string($v)) {
                        $this->laravelNativeCache[trim($v)] = true;
                    }
                });
            }
        }

        return $this->laravelNativeCache;
    }

    private function recursiveGlob(string $path, string $ext): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.' . $ext)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function parseDictionary(string $path): array
    {
        $translations = [];

        if (!File::exists($path)) {
            return $translations;
        }

        $content = File::get($path);

        if (preg_match_all("/'\s*((?:[^'\\\\]|\\\\.)+?)'\s*:\s*'((?:[^'\\\\]|\\\\.)*)'/m", $content, $matches)) {
            foreach ($matches[1] as $i => $key) {
                $clean_key = stripslashes(trim($key));
                $translations[$clean_key] = stripslashes($matches[2][$i]);
            }
        }

        return $translations;
    }

    private function appendToDictionary(string $path, array $strings): void
    {
        $content = File::get($path);

        $insertPos = strrpos($content, '};');
        if ($insertPos === false) {
            return;
        }

        $newEntries = "\n    // ── New strings (scan " . date('Y-m-d H:i') . ") ──\n";
        foreach ($strings as $str) {
            $escaped = str_replace("'", "\\'", $str);
            $newEntries .= "    '{$escaped}': '',\n";
        }

        $content = substr($content, 0, $insertPos) . $newEntries . substr($content, $insertPos);
        File::put($path, $content);
    }
}

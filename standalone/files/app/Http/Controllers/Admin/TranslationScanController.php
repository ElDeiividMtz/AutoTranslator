<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\GoogleTranslateService;

class TranslationScanController extends Controller
{
    private GoogleTranslateService $translateService;

    public function __construct(GoogleTranslateService $translateService)
    {
        $this->translateService = $translateService;
    }

    private function supportedLanguages(): array
    {
        return array_keys(config('autotranslator.languages', []));
    }

    private function languageNames(): array
    {
        return config('autotranslator.languages', []);
    }

    public function index()
    {
        $resultsFile = config('autotranslator.storage.scan_results', 'translation-scan-results.json');
        $resultsPath = storage_path("app/{$resultsFile}");
        $results = null;

        if (File::exists($resultsPath)) {
            $results = json_decode(File::get($resultsPath), true);
        }

        return view('admin.translations.index', [
            'results' => $results,
            'supportedLanguages' => $this->supportedLanguages(),
            'languageNames' => $this->languageNames(),
        ]);
    }

    public function scan(Request $request)
    {
        Artisan::call('translate:scan');
        $this->translateService->fixAllPermissions();

        return redirect('/admin/translations')->with('scan_complete', true);
    }

    public function translate(Request $request, string $lang): JsonResponse
    {
        set_time_limit(300);

        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $resultsFile = config('autotranslator.storage.scan_results', 'translation-scan-results.json');
        $resultsPath = storage_path("app/{$resultsFile}");

        if (!File::exists($resultsPath)) {
            return new JsonResponse(['error' => 'Run a scan first'], 400);
        }

        $results = json_decode(File::get($resultsPath), true);
        $untranslated = $results['languages'][$lang]['untranslated'] ?? [];

        if (empty($untranslated)) {
            return new JsonResponse(['status' => 'complete', 'translated' => 0]);
        }

        $existing = $this->translateService->loadExistingTranslations($lang);

        $toTranslate = array_filter($untranslated, function ($str) use ($existing) {
            return !isset($existing[$str]) || $existing[$str] === '';
        });
        $toTranslate = array_values($toTranslate);

        if (empty($toTranslate)) {
            return new JsonResponse(['status' => 'complete', 'translated' => 0]);
        }

        $progressFile = storage_path("app/translations/progress-{$lang}.json");
        @file_put_contents($progressFile, json_encode([
            'done' => 0,
            'total' => count($toTranslate),
            'percent' => 0,
            'status' => 'translating',
        ]));

        $newTranslations = $this->translateService->translateBatch($toTranslate, $lang, $progressFile);

        // Sanitize all translations received from Google
        $sanitizedNew = [];
        foreach ($newTranslations as $key => $value) {
            $sanitizedNew[$key] = $this->sanitizeTranslationValue($value);
        }

        $merged = array_merge($existing, $sanitizedNew);
        $this->translateService->saveTranslations($lang, $merged);

        $this->updateScanResults($resultsPath, $lang, $merged);

        @file_put_contents($progressFile, json_encode([
            'done' => count($toTranslate),
            'total' => count($toTranslate),
            'percent' => 100,
            'status' => 'complete',
        ]));

        // Auto-fix all permissions after translation
        $this->translateService->fixAllPermissions();

        return new JsonResponse([
            'status' => 'complete',
            'translated' => count($newTranslations),
        ]);
    }

    private function sanitizeTranslationValue(string $value): string
    {
        // Strip all HTML tags — translations should be plain text
        $value = strip_tags($value);
        // Limit length
        return mb_substr(trim($value), 0, 5000);
    }

    public function progress(string $lang): JsonResponse
    {
        if (!preg_match('/^[a-z]{2}$/', $lang) || !in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['status' => 'idle', 'percent' => 0]);
        }

        $progressFile = storage_path("app/translations/progress-{$lang}.json");

        if (!File::exists($progressFile)) {
            return new JsonResponse(['status' => 'idle', 'percent' => 0]);
        }

        $data = json_decode(File::get($progressFile), true) ?: [];

        return new JsonResponse($data);
    }

    /**
     * Return all translations for a language (for the edit UI).
     */
    public function getTranslations(string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $translations = $this->translateService->loadExistingTranslations($lang);

        return new JsonResponse([
            'language' => $lang,
            'count' => count($translations),
            'translations' => $translations,
        ]);
    }

    /**
     * Update a single translation entry.
     */
    public function updateTranslation(Request $request, string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $key = $request->input('key');
        $value = $request->input('value');

        if (!$key || !is_string($key)) {
            return new JsonResponse(['error' => 'Key is required'], 400);
        }

        $translations = $this->translateService->loadExistingTranslations($lang);
        $translations[$key] = $this->sanitizeTranslationValue($value ?? '');
        $this->translateService->saveTranslations($lang, $translations);

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Delete a translation entry.
     */
    public function deleteTranslation(Request $request, string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $key = $request->input('key');

        if (!$key || !is_string($key)) {
            return new JsonResponse(['error' => 'Key is required'], 400);
        }

        $translations = $this->translateService->loadExistingTranslations($lang);
        unset($translations[$key]);
        $this->translateService->saveTranslations($lang, $translations);

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Export translations as downloadable JSON.
     */
    public function exportTranslations(string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $translations = $this->translateService->loadExistingTranslations($lang);

        return new JsonResponse($translations, 200, [
            'Content-Disposition' => "attachment; filename=\"{$lang}.json\"",
        ]);
    }

    /**
     * Import translations from JSON payload.
     */
    public function importTranslations(Request $request, string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $data = $request->input('translations');

        if (!is_array($data) || empty($data)) {
            return new JsonResponse(['error' => 'Invalid translations data'], 400);
        }

        // Validate and sanitize: only string keys/values, strip dangerous HTML
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value) && mb_strlen($key) <= 1000 && mb_strlen($value) <= 2000) {
                $sanitized[$key] = $this->sanitizeTranslationValue($value);
            }
        }

        if (empty($sanitized)) {
            return new JsonResponse(['error' => 'No valid translations in payload'], 400);
        }

        $existing = $this->translateService->loadExistingTranslations($lang);
        $merged = array_merge($existing, $sanitized);
        $this->translateService->saveTranslations($lang, $merged);

        return new JsonResponse([
            'status' => 'ok',
            'imported' => count($sanitized),
            'total' => count($merged),
        ]);
    }

    /**
     * Clear translation cache for a language.
     */
    public function clearCache(string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        Cache::forget("autotranslator.inline.{$lang}");

        return new JsonResponse(['status' => 'ok', 'message' => "Cache cleared for {$lang}"]);
    }

    /**
     * Deep clean: re-scan panel, then remove orphaned translations
     * that no longer match any string in the current panel.
     * Useful after removing addons/plugins/themes.
     */
    public function deepClean(Request $request): JsonResponse
    {
        // 1. Run a fresh scan to get current strings
        Artisan::call('translate:scan');

        $resultsFile = config('autotranslator.storage.scan_results', 'translation-scan-results.json');
        $resultsPath = storage_path("app/{$resultsFile}");

        if (!File::exists($resultsPath)) {
            return new JsonResponse(['error' => 'Scan failed'], 500);
        }

        $results = json_decode(File::get($resultsPath), true);
        $currentStrings = array_flip($results['all_strings'] ?? []);

        $totalRemoved = 0;
        $totalFixed = 0;
        $langStats = [];

        // Pterodactyl terms to fix per language
        $termFixes = [
            'es' => ['huevo' => 'Egg', 'huevos' => 'Eggs', 'Huevo' => 'Egg', 'Huevos' => 'Eggs', 'nido' => 'Nest', 'nidos' => 'Nests', 'Nido' => 'Nest', 'Nidos' => 'Nests'],
            'pt' => ['ovo' => 'Egg', 'ovos' => 'Eggs', 'Ovo' => 'Egg', 'Ovos' => 'Eggs', 'ninho' => 'Nest', 'ninhos' => 'Nests', 'Ninho' => 'Nest', 'Ninhos' => 'Nests'],
            'fr' => ['oeuf' => 'Egg', 'oeufs' => 'Eggs', 'Oeuf' => 'Egg', 'Oeufs' => 'Eggs', 'nid' => 'Nest', 'nids' => 'Nests', 'Nid' => 'Nest', 'Nids' => 'Nests'],
            'de' => ['Ei' => 'Egg', 'Eier' => 'Eggs'],
            'it' => ['uovo' => 'Egg', 'uova' => 'Eggs', 'Uovo' => 'Egg', 'Uova' => 'Eggs', 'nido' => 'Nest', 'nidi' => 'Nests', 'Nido' => 'Nest', 'Nidi' => 'Nests'],
        ];

        // Laravel native values (to avoid duplicating)
        $laravelValues = [];
        $langDir = resource_path('lang/en');
        if (is_dir($langDir)) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($langDir));
            foreach ($iter as $f) {
                if ($f->getExtension() !== 'php') continue;
                $arr = @include $f->getPathname();
                if (is_array($arr)) {
                    array_walk_recursive($arr, function ($v) use (&$laravelValues) {
                        if (is_string($v)) $laravelValues[trim($v)] = true;
                    });
                }
            }
        }

        foreach ($this->supportedLanguages() as $lang) {
            $translations = $this->translateService->loadExistingTranslations($lang);
            $before = count($translations);
            $removed = 0;
            $fixed = 0;

            foreach ($translations as $key => $val) {
                $k = trim($key);

                // Remove technical patterns
                if (preg_match('/^[\d.,]+\s*(%|MB|GB|KB|TB|ms|s|B|GiB|MiB)?$/i', $k) ||
                    preg_match('/^[0-9a-f]{8}-/i', $k) ||
                    preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $k) ||
                    preg_match('/^[A-Z][A-Z0-9_]{2,}$/', $k) ||
                    preg_match('/^[\w.-]+\.(io|com|org)\//', $k) ||
                    preg_match('/^&[a-z]+;$/i', $k) ||
                    preg_match('/^[^a-zA-Z]*$/', $k) ||
                    preg_match('/^[a-z]{1,3}$/', $k) ||
                    mb_strlen($k) <= 2 ||
                    $k === trim($val)) {
                    unset($translations[$key]);
                    $removed++;
                    continue;
                }

                // Remove Laravel native duplicates
                if (isset($laravelValues[$k])) {
                    unset($translations[$key]);
                    $removed++;
                    continue;
                }
            }

            // Fix Egg/Nest terminology
            $fixes = $termFixes[$lang] ?? [];
            foreach ($translations as $key => &$val) {
                $origVal = $val;
                foreach ($fixes as $wrong => $correct) {
                    $val = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/u', $correct, $val);
                }
                if ($val !== $origVal) $fixed++;
            }
            unset($val);

            // Deduplicate with/without trailing period
            foreach (array_keys($translations) as $k) {
                $t = trim($k);
                if (substr($t, -1) === '.' && isset($translations[substr($t, 0, -1)])) {
                    unset($translations[substr($t, 0, -1)]);
                    $removed++;
                }
            }

            ksort($translations);
            $this->translateService->saveTranslations($lang, $translations);
            Cache::forget("autotranslator.inline.{$lang}");

            $totalRemoved += $removed;
            $totalFixed += $fixed;
            $langStats[$lang] = [
                'before' => $before,
                'after' => count($translations),
                'removed' => $removed,
                'fixed' => $fixed,
            ];
        }

        $this->translateService->fixAllPermissions();

        return new JsonResponse([
            'status' => 'ok',
            'total_removed' => $totalRemoved,
            'total_fixed' => $totalFixed,
            'languages' => $langStats,
        ]);
    }

    /**
     * Reset: clear all runtime translation JSONs for every language.
     */
    public function resetAll(Request $request): JsonResponse
    {
        $langStats = [];

        foreach ($this->supportedLanguages() as $lang) {
            $translations = $this->translateService->loadExistingTranslations($lang);
            $before = count($translations);

            $this->translateService->saveTranslations($lang, []);
            Cache::forget("autotranslator.inline.{$lang}");

            $langStats[$lang] = [
                'before' => $before,
                'removed' => $before,
            ];
        }

        // Also reset scan results using File::delete (respects permissions better)
        $resultsFile = config('autotranslator.storage.scan_results', 'translation-scan-results.json');
        $resultsPath = storage_path("app/{$resultsFile}");
        if (File::exists($resultsPath)) {
            File::delete($resultsPath);
        }

        // Clean progress files and TS dictionaries
        foreach ($this->supportedLanguages() as $lang) {
            $progressFile = storage_path("app/translations/progress-{$lang}.json");
            if (File::exists($progressFile)) {
                File::delete($progressFile);
            }

            // Reset compiled TS dictionary to empty
            $dictPath = resource_path("scripts/plugins/AutoTranslator/dictionaries/{$lang}.ts");
            if (File::exists($dictPath)) {
                $emptyDict = "/**\n * Dictionary EN → " . strtoupper($lang) . " for Pterodactyl Panel.\n * Auto-generated by AutoTranslator.\n */\nconst dictionary: Record<string, string> = {};\n\nexport default dictionary;\n";
                File::put($dictPath, $emptyDict);
            }
        }

        $this->translateService->fixAllPermissions();

        return new JsonResponse([
            'status' => 'ok',
            'languages' => $langStats,
        ]);
    }

    /**
     * Get current flag configuration.
     */
    public function getFlags(): JsonResponse
    {
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $flagsPath = storage_path("app/{$dir}/flags.json");
        $defaults = config('autotranslator.default_flags', []);

        $custom = [];
        if (File::exists($flagsPath)) {
            $custom = json_decode(File::get($flagsPath), true) ?: [];
        }

        return new JsonResponse([
            'flags' => array_merge($defaults, $custom),
            'defaults' => $defaults,
        ]);
    }

    /**
     * Update flag emoji for languages.
     */
    public function updateFlags(Request $request): JsonResponse
    {
        $flags = $request->input('flags');
        if (!is_array($flags)) {
            return new JsonResponse(['error' => 'Invalid flags data'], 400);
        }

        // Validate: only allow known language codes, emoji-safe values (max 10 chars)
        $supported = array_merge(['en'], $this->supportedLanguages());
        $clean = [];
        foreach ($flags as $code => $emoji) {
            if (in_array($code, $supported) && is_string($emoji) && mb_strlen($emoji) <= 10) {
                $clean[$code] = strip_tags($emoji);
            }
        }

        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $flagsPath = storage_path("app/{$dir}/flags.json");
        File::put($flagsPath, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->translateService->fixPermissions($flagsPath);

        // Clear only AutoTranslator cache keys
        $supported = array_keys(config('autotranslator.languages', []));
        foreach ($supported as $lang) {
            Cache::forget("autotranslator.inline.{$lang}");
        }

        return new JsonResponse(['status' => 'ok', 'flags' => $clean]);
    }

    /**
     * Get protected terms (base config + custom).
     */
    public function getProtectedTerms(): JsonResponse
    {
        $base = config('autotranslator.protected_terms', []);
        $custom = $this->loadCustomProtectedTerms();

        return new JsonResponse([
            'base' => $base,
            'custom' => $custom,
        ]);
    }

    /**
     * Update custom protected terms.
     */
    public function updateProtectedTerms(Request $request): JsonResponse
    {
        $terms = $request->input('terms');
        if (!is_array($terms)) {
            return new JsonResponse(['error' => 'Invalid terms data'], 400);
        }

        // Sanitize: only non-empty strings, max 100 chars each, max 500 terms
        $clean = [];
        foreach (array_slice($terms, 0, 500) as $term) {
            if (is_string($term) && mb_strlen(trim($term)) > 0 && mb_strlen(trim($term)) <= 100) {
                $clean[] = strip_tags(trim($term));
            }
        }
        $clean = array_values(array_unique($clean));

        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $path = storage_path("app/{$dir}/protected_terms.json");
        File::put($path, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->translateService->fixPermissions($path);

        // Remove protected terms from all existing translations
        $allProtected = array_merge(config('autotranslator.protected_terms', []), $clean);
        $removedCount = 0;
        foreach ($this->supportedLanguages() as $lang) {
            $translations = $this->translateService->loadExistingTranslations($lang);
            $before = count($translations);
            foreach ($allProtected as $term) {
                unset($translations[$term]);
            }
            if (count($translations) < $before) {
                $this->translateService->saveTranslations($lang, $translations);
                $removedCount += $before - count($translations);
            }
            Cache::forget("autotranslator.inline.{$lang}");
        }

        return new JsonResponse(['status' => 'ok', 'terms' => $clean, 'removed' => $removedCount]);
    }

    private function loadCustomProtectedTerms(): array
    {
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $path = storage_path("app/{$dir}/protected_terms.json");

        if (!File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?: [];
    }

    private function updateScanResults(string $resultsPath, string $lang, array $translations): void
    {
        if (!File::exists($resultsPath)) {
            return;
        }

        $results = json_decode(File::get($resultsPath), true);
        $allStrings = $results['all_strings'] ?? [];
        $total = count($allStrings);

        // Merge compiled dictionary translations
        $dictPath = resource_path("scripts/plugins/AutoTranslator/dictionaries/{$lang}.ts");
        if (File::exists($dictPath)) {
            $content = File::get($dictPath);
            if (preg_match_all("/'\s*((?:[^'\\\\]|\\\\.)+?)'\s*:\s*'((?:[^'\\\\]|\\\\.)*)'/m", $content, $matches)) {
                foreach ($matches[1] as $i => $key) {
                    $clean_key = stripslashes(trim($key));
                    if (!isset($translations[$clean_key]) || $translations[$clean_key] === '') {
                        $val = stripslashes($matches[2][$i]);
                        if ($val !== '') {
                            $translations[$clean_key] = $val;
                        }
                    }
                }
            }
        }

        $untranslated = array_filter($allStrings, function ($str) use ($translations) {
            return !isset($translations[$str]) || $translations[$str] === '';
        });
        $untranslated = array_values($untranslated);
        $translated = $total - count($untranslated);

        $results['languages'][$lang] = [
            'translated' => $translated,
            'untranslated_count' => count($untranslated),
            'untranslated' => $untranslated,
            'percent' => $total > 0 ? round(($translated / $total) * 100) : 0,
        ];

        if ($lang === 'es') {
            $results['translated'] = $translated;
            $results['untranslated_count'] = count($untranslated);
            $results['untranslated'] = $untranslated;
        }

        File::put($resultsPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->translateService->fixPermissions($resultsPath);
    }
}

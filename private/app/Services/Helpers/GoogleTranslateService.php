<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Services\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GoogleTranslateService
{
    public function translateBatch(array $strings, string $targetLang, ?string $progressFile = null): array
    {
        $translations = [];
        $total = count($strings);
        $done = 0;
        $chunkSize = config('autotranslator.google.chunk_size', 4000);
        $delayMs = config('autotranslator.google.delay_ms', 200);

        $chunks = $this->chunkBySize($strings, $chunkSize);

        foreach ($chunks as $chunk) {
            [$joined, $brandMap] = $this->protectBrands(implode("\n", $chunk));
            $translated = $this->translateText($joined, $targetLang);

            if ($translated !== null) {
                $translated = $this->restoreBrands($translated, $brandMap);
                $parts = explode("\n", $translated);
                foreach ($chunk as $i => $original) {
                    $translations[$original] = trim($parts[$i] ?? '');
                }
            }

            $done += count($chunk);

            if ($progressFile) {
                $this->writeProgress($progressFile, $done, $total);
            }

            usleep($delayMs * 1000);
        }

        return $translations;
    }

    private function writeProgress(string $path, int $done, int $total): void
    {
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
        @file_put_contents($path, json_encode([
            'done' => $done,
            'total' => $total,
            'percent' => $percent,
            'status' => $done >= $total ? 'complete' : 'translating',
        ]));
        $this->fixPermissions($path);
    }

    private function chunkBySize(array $strings, int $maxChars): array
    {
        $chunks = [];
        $current = [];
        $size = 0;

        foreach ($strings as $str) {
            $len = strlen($str) + 1;
            if ($size + $len > $maxChars && !empty($current)) {
                $chunks[] = $current;
                $current = [];
                $size = 0;
            }
            $current[] = $str;
            $size += $len;
        }

        if (!empty($current)) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function translateText(string $text, string $targetLang): ?string
    {
        try {
            $baseUrl = config('autotranslator.google.base_url', 'https://translate.googleapis.com/translate_a/single');
            $timeout = config('autotranslator.google.timeout', 15);

            $url = $baseUrl . '?' . http_build_query([
                'client' => 'gtx',
                'sl' => 'en',
                'tl' => $targetLang,
                'dt' => 't',
                'q' => $text,
            ]);

            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                Log::warning("GoogleTranslate: Request failed for {$targetLang}");
                return null;
            }

            $data = json_decode($response, true);

            if (!is_array($data) || empty($data[0])) {
                return null;
            }

            $result = '';
            foreach ($data[0] as $segment) {
                if (isset($segment[0])) {
                    $result .= $segment[0];
                }
            }

            return $result ?: null;
        } catch (\Exception $e) {
            Log::warning("GoogleTranslate error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Replace protected brand/technical terms with placeholders before translation.
     */
    private function protectBrands(string $text): array
    {
        $terms = config('autotranslator.protected_terms', []);

        // Merge custom terms from storage
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $customPath = storage_path("app/{$dir}/protected_terms.json");
        if (File::exists($customPath)) {
            $custom = json_decode(File::get($customPath), true) ?: [];
            $terms = array_values(array_unique(array_merge($terms, $custom)));
        }

        usort($terms, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        $map = [];
        $counter = 0;

        foreach ($terms as $term) {
            $pattern = '/\b' . preg_quote($term, '/') . '\b/u';
            $text = preg_replace_callback($pattern, function ($match) use (&$map, &$counter) {
                $placeholder = '{{' . $counter . '}}';
                $map[$counter] = $match[0];
                $counter++;
                return $placeholder;
            }, $text);
        }

        return [$text, $map];
    }

    /**
     * Restore brand name placeholders after translation.
     */
    private function restoreBrands(string $text, array $map): string
    {
        foreach ($map as $index => $original) {
            $pattern = '/\{\{\s*' . $index . '\s*\}\}/u';
            $text = preg_replace($pattern, $original, $text);
        }
        return $text;
    }

    public function getStoragePath(string $lang): string
    {
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        return storage_path("app/{$dir}/{$lang}.json");
    }

    public function loadExistingTranslations(string $lang): array
    {
        $path = $this->getStoragePath($lang);

        if (File::exists($path)) {
            return json_decode(File::get($path), true) ?: [];
        }

        return [];
    }

    public function saveTranslations(string $lang, array $translations): void
    {
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $fullDir = storage_path("app/{$dir}");

        if (!File::isDirectory($fullDir)) {
            File::makeDirectory($fullDir, 0775, true);
        }

        $path = $this->getStoragePath($lang);
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Atomic write with file locking to prevent race conditions
        $lock = Cache::lock("autotranslator.write.{$lang}", 30);
        try {
            $lock->block(10);
            File::put($path, $json);
        } catch (\Exception $e) {
            // Fallback: write without lock
            File::put($path, $json);
        } finally {
            optional($lock)->release();
        }

        // Auto-fix permissions and invalidate cache
        $this->fixPermissions($path);
        $this->fixPermissions($fullDir);
        Cache::forget("autotranslator.inline.{$lang}");
    }

    /**
     * Fix file ownership so the web server can read/write.
     */
    public function fixPermissions(?string $path = null): void
    {
        if (!config('autotranslator.fix_permissions', true)) {
            return;
        }

        $target = $path ?? storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations'));
        $user = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_user', 'www-data'));
        $group = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_group', 'www-data'));

        @exec("chown " . escapeshellarg("{$user}:{$group}") . " " . escapeshellarg($target) . " 2>/dev/null");
        @exec("chmod 775 " . escapeshellarg($target) . " 2>/dev/null");
    }

    /**
     * Fix permissions on the entire translations directory.
     */
    public function fixAllPermissions(): void
    {
        if (!config('autotranslator.fix_permissions', true)) {
            return;
        }

        $dir = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations'));
        $user = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_user', 'www-data'));
        $group = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_group', 'www-data'));
        $userGroup = escapeshellarg("{$user}:{$group}");

        @exec("chown -R {$userGroup} " . escapeshellarg($dir) . " 2>/dev/null");
        @exec("chmod -R 775 " . escapeshellarg($dir) . " 2>/dev/null");

        // Also fix storage cache
        $cachePath = storage_path('framework/cache/data');
        if (is_dir($cachePath)) {
            @exec("chown -R {$userGroup} " . escapeshellarg($cachePath) . " 2>/dev/null");
        }
    }
}

<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;
use Pterodactyl\Services\Helpers\AssetHashService;

class AssetComposer
{
    /**
     * AssetComposer constructor.
     */
    public function __construct(private AssetHashService $assetHashService)
    {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $view->with('asset', $this->assetHashService);

        $supported = array_keys(config('autotranslator.languages', []));
        $userLang = $this->detectLanguage($supported);

        // AutoTranslator: load custom flags (admin-editable) or fall back to defaults
        $flagsPath = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations') . '/flags.json');
        $flags = config('autotranslator.default_flags', []);
        if (File::exists($flagsPath)) {
            $customFlags = json_decode(File::get($flagsPath), true);
            if (is_array($customFlags)) {
                $flags = array_merge($flags, $customFlags);
            }
        }

        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'locale' => config('app.locale') ?? 'en',
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
            // AutoTranslator: expose languages, flags, and user lang to frontend
            'translatorLanguages' => config('autotranslator.languages', []),
            'translatorFlags' => $flags,
            'translatorLang' => $userLang,
        ]);

        // AutoTranslator: inject runtime translations for the user's language
        // Sanitized with JSON_HEX_TAG to prevent XSS via </script> in translation values
        $translations = '{}';

        if ($userLang !== 'en' && in_array($userLang, $supported)) {
            $cacheTtl = config('autotranslator.cache_ttl', 300);
            $translations = Cache::remember("autotranslator.inline.{$userLang}", $cacheTtl, function () use ($userLang) {
                $dir = config('autotranslator.storage.translations_dir', 'translations');
                $path = storage_path("app/{$dir}/{$userLang}.json");
                if (!File::exists($path)) return '{}';
                $data = json_decode(File::get($path), true);
                return is_array($data)
                    ? json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
                    : '{}';
            });
        }
        $view->with('inlineTranslations', $translations);
    }

    /**
     * Detect user language with fallback chain:
     * - Authenticated: ALWAYS use user's saved preference (even 'en')
     * - Unauthenticated (login page): cookie → Accept-Language → 'en'
     */
    private function detectLanguage(array $supported): string
    {
        $user = Auth::user();

        // Authenticated user — their saved preference is the final word
        if ($user) {
            return $user->language ?? 'en';
        }

        // ── Unauthenticated (login, forgot password, etc.) ──

        // Cookie set by JS when user picks a language (persists across sessions)
        $cookieLang = Request::cookie('autotranslator_lang');
        if ($cookieLang && is_string($cookieLang) && in_array($cookieLang, $supported)) {
            return $cookieLang;
        }

        // Browser Accept-Language header (auto-detect on first visit)
        $acceptLang = Request::header('Accept-Language', '');
        if ($acceptLang) {
            $parts = explode(',', $acceptLang);
            foreach ($parts as $part) {
                $code = substr(strtolower(trim(explode(';', $part)[0])), 0, 2);
                if (in_array($code, $supported)) {
                    return $code;
                }
            }
        }

        return 'en';
    }
}

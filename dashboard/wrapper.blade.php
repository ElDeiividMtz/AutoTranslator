{{-- AutoTranslator: Dashboard wrapper — injects translation config + data --}}
{{-- Blueprint injects this at @yield('blueprint.wrappers') before main.js loads --}}
@php
    $supported = array_keys(config('autotranslator.languages', []));

    // Language detection chain: authenticated user → cookie → Accept-Language → 'en'
    $userLang = 'en';
    if (auth()->check() && auth()->user()->language && auth()->user()->language !== 'en') {
        $userLang = auth()->user()->language;
    } elseif (($cookieLang = request()->cookie('autotranslator_lang')) && in_array($cookieLang, $supported)) {
        $userLang = $cookieLang;
    } else {
        $accept = request()->header('Accept-Language', '');
        foreach (explode(',', $accept) as $part) {
            $code = substr(strtolower(trim(explode(';', $part)[0])), 0, 2);
            if (in_array($code, $supported)) {
                $userLang = $code;
                break;
            }
        }
    }

    // Load flag configuration
    $flagsPath = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations') . '/flags.json');
    $flags = config('autotranslator.default_flags', []);
    if (file_exists($flagsPath)) {
        $customFlags = json_decode(file_get_contents($flagsPath), true);
        if (is_array($customFlags)) {
            $flags = array_merge($flags, $customFlags);
        }
    }

    // Load cached translations for current language
    $inlineTranslations = '{}';
    if ($userLang !== 'en' && in_array($userLang, $supported)) {
        $dir = config('autotranslator.storage.translations_dir', 'translations');
        $cacheTtl = config('autotranslator.cache_ttl', 300);
        $inlineTranslations = \Illuminate\Support\Facades\Cache::remember(
            "autotranslator.inline.{$userLang}",
            $cacheTtl,
            function () use ($userLang, $dir) {
                $path = storage_path("app/{$dir}/{$userLang}.json");
                if (!file_exists($path)) return '{}';
                $data = json_decode(file_get_contents($path), true);
                return is_array($data)
                    ? json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
                    : '{}';
            }
        );
    }
@endphp
{{-- Extend SiteConfiguration with translator data (runs before React reads it) --}}
<script>
(function() {
    var sc = window.SiteConfiguration || {};
    sc.translatorLanguages = {!! json_encode(config('autotranslator.languages', []), JSON_HEX_TAG) !!};
    sc.translatorFlags = {!! json_encode($flags, JSON_HEX_TAG) !!};
    sc.translatorLang = '{{ $userLang }}';
    window.SiteConfiguration = sc;
})();
</script>
@if($userLang !== 'en')
<script>
window.__TRANSLATIONS__ = {!! $inlineTranslations !!};
(function(t) {
    if (!t) return;
    @php
        $baseTerms = config('autotranslator.protected_terms', []);
        $customTermsPath = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations') . '/protected_terms.json');
        $customTerms = file_exists($customTermsPath) ? (json_decode(file_get_contents($customTermsPath), true) ?: []) : [];
        $allTerms = array_values(array_unique(array_merge($baseTerms, $customTerms)));
    @endphp
    var p = {!! json_encode($allTerms, JSON_HEX_TAG) !!};
    for (var i = 0; i < p.length; i++) delete t[p[i]];
})(window.__TRANSLATIONS__);
</script>
@endif

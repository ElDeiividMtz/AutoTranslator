{{-- AutoTranslator: Admin panel translation via inline JSON (zero-flicker) --}}
{{-- This wrapper is injected into the admin layout by Blueprint --}}
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

    // Load cached translations inline (same as dashboard wrapper — eliminates async fetch flicker)
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
@if($userLang !== 'en')
<script>window.__TRANSLATIONS__ = {!! $inlineTranslations !!};</script>
<script>
(function() {
    var translations = window.__TRANSLATIONS__ || null;
    if (!translations || !Object.keys(translations).length) return;

    // Protected brand/technical terms — never translate these even if they appear in translations JSON
    @php
        $baseTerms = config('autotranslator.protected_terms', []);
        $customTermsPath = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations') . '/protected_terms.json');
        $customTerms = file_exists($customTermsPath) ? (json_decode(file_get_contents($customTermsPath), true) ?: []) : [];
        $allProtectedTerms = array_values(array_unique(array_merge($baseTerms, $customTerms)));
    @endphp
    var protectedTerms = {!! json_encode($allProtectedTerms, JSON_HEX_TAG) !!};

    // Remove any translation entries whose KEY is exactly a protected term
    for (var p = 0; p < protectedTerms.length; p++) {
        delete translations[protectedTerms[p]];
    }

    // On extensions listing page, mark extension cards as notranslate
    if (/\/admin\/extensions\/?$/.test(window.location.pathname)) {
        document.querySelectorAll('.extension-btn').forEach(function(el) {
            el.setAttribute('data-notranslate', '');
        });
    }

    function tr(node) {
        if (node.nodeType === 3) {
            var text = node.textContent.trim();
            if (text && translations[text]) {
                node.textContent = node.textContent.replace(text, translations[text]);
            }
            return;
        }
        if (node.nodeType === 1) {
            var tag = node.tagName;
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA' || tag === 'INPUT' || tag === 'CODE' || tag === 'PRE') return;
            if (node.classList && node.classList.contains('notranslate')) return;
            if (node.hasAttribute && node.hasAttribute('data-notranslate')) return;

            ['placeholder', 'title', 'aria-label', 'data-original-title', 'data-tooltip'].forEach(function(attr) {
                var v = node.getAttribute ? node.getAttribute(attr) : null;
                if (v && translations[v.trim()]) node.setAttribute(attr, translations[v.trim()]);
            });

            for (var i = 0; i < node.childNodes.length; i++) {
                tr(node.childNodes[i]);
            }
        }
    }

    function startObserver() {
        var pending = [];
        var raf = false;
        new MutationObserver(function(mutations) {
            for (var m = 0; m < mutations.length; m++) {
                for (var n = 0; n < mutations[m].addedNodes.length; n++) {
                    var node = mutations[m].addedNodes[n];
                    if (node.nodeType === 1) pending.push(node);
                }
            }
            if (pending.length > 0 && !raf) {
                raf = true;
                requestAnimationFrame(function() {
                    raf = false;
                    var nodes = pending.splice(0);
                    for (var i = 0; i < nodes.length; i++) {
                        if (nodes[i].isConnected) tr(nodes[i]);
                    }
                });
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    tr(document.body);
    startObserver();
})();
</script>
@endif

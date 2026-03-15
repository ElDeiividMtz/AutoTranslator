<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'Pterodactyl') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
            <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
            <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
            <link rel="manifest" href="/favicons/manifest.json">
            <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
            <link rel="shortcut icon" href="/favicons/favicon.ico">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#0e4688">
        @show

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
            {{-- AutoTranslator: inject translations synchronously so they're ready before React renders --}}
            @if(isset($inlineTranslations) && $inlineTranslations !== '{}')
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
        @show

        @yield('assets')

        @include('layouts.scripts')
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            {!! $asset->js('main.js') !!}
        @show
    </body>
</html>

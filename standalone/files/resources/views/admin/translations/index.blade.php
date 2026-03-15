{{-- AutoTranslator for Pterodactyl Panel | ElDeiividMtz | MIT License --}}
@extends('layouts.admin')

@section('title')
    Translation Manager
@endsection

@section('content-header')
    <h1>Translation Manager<small>AutoTranslator — ElDeiividMtz</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Translation Manager</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            @if(session('scan_complete'))
                <div class="alert alert-success" style="border-left: 4px solid #00a65a; background: rgba(0,166,90,0.15); color: #fff;">
                    <i class="fa fa-check-circle"></i> Scan completed successfully.
                </div>
            @endif

            <div id="translate-alert" style="display:none; color: #fff;" class="alert"></div>

            {{-- Scan Panel --}}
            <div class="box" style="background: #1a2332; border: 1px solid #2a3a4e;">
                <div class="box-header with-border" style="border-color: #2a3a4e;">
                    <h3 class="box-title"><i class="fa fa-search" style="color: #3c8dbc;"></i> Scan Panel</h3>
                </div>
                <div class="box-body">
                    <p style="color: #8899aa; margin-bottom: 15px;">Scan all Blade views and React components for untranslated strings. Run this after installing addons, themes, or making UI changes.</p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <form action="/admin/translations/scan" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;">
                                <i class="fa fa-search"></i> Run Scan
                            </button>
                        </form>
                        <button onclick="deepClean()" id="btn-deep-clean" class="btn" style="padding: 10px 24px; font-size: 14px; background: linear-gradient(135deg, #c62828, #e53935); border: none; color: #fff;">
                            <i class="fa fa-trash"></i> Deep Clean
                        </button>
                        <button onclick="resetAll()" id="btn-reset-all" class="btn" style="padding: 10px 24px; font-size: 14px; background: linear-gradient(135deg, #37474f, #546e7a); border: none; color: #fff;">
                            <i class="fa fa-eraser"></i> Reset All
                        </button>
                    </div>
                    <p style="color: #5a6a7a; font-size: 12px; margin-top: 10px;">
                        <i class="fa fa-info-circle"></i> <strong>Deep Clean</strong>: Re-scans the panel and removes orphaned translations (from deleted addons/plugins/themes), junk entries, and duplicates. Use after uninstalling addons.
                        <br><i class="fa fa-info-circle"></i> <strong>Reset All</strong>: Deletes ALL runtime translations for every language. You'll need to run Scan + Auto Translate again from scratch.
                    </p>
                </div>
            </div>

            {{-- Flag Customization --}}
            <div class="box" style="background: #1a2332; border: 1px solid #2a3a4e;">
                <div class="box-header with-border" style="border-color: #2a3a4e; cursor: pointer;" onclick="document.getElementById('flags-body').style.display = document.getElementById('flags-body').style.display === 'none' ? 'block' : 'none';">
                    <h3 class="box-title"><i class="fa fa-flag" style="color: #f39c12;"></i> Language Flags</h3>
                    <span class="pull-right" style="color: #5a6a7a; font-size: 12px;"><i class="fa fa-chevron-down"></i> Click to expand</span>
                </div>
                <div class="box-body" id="flags-body" style="display: none;">
                    <p style="color: #8899aa; margin-bottom: 15px;">Customize the flag emoji shown next to each language in the panel. Changes are saved per-flag and take effect immediately.</p>
                    <div class="row" id="flags-grid">
                        @php
                            $defaultFlags = config('autotranslator.default_flags', []);
                            $customFlagsPath = storage_path('app/' . config('autotranslator.storage.translations_dir', 'translations') . '/flags.json');
                            $customFlags = file_exists($customFlagsPath) ? (json_decode(file_get_contents($customFlagsPath), true) ?: []) : [];
                            $allFlags = array_merge($defaultFlags, $customFlags);
                            $allLangs = array_merge(['en' => 'English'], config('autotranslator.languages', []));
                        @endphp
                        @foreach($allLangs as $code => $name)
                        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom: 12px;">
                            <div style="background: #0d1520; border: 1px solid #2a3a4e; border-radius: 8px; padding: 12px; text-align: center;">
                                <img id="flag-preview-{{ $code }}" src="https://flagcdn.com/48x36/{{ $allFlags[$code] ?? $code }}.png" style="width: 48px; height: 36px; border-radius: 3px; margin-bottom: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.4);">
                                <div style="color: #e0e6ed; font-size: 13px; font-weight: 600; margin-bottom: 8px;">{{ $name }}</div>
                                <input
                                    type="text"
                                    id="flag-input-{{ $code }}"
                                    value="{{ $allFlags[$code] ?? $code }}"
                                    maxlength="2"
                                    placeholder="us"
                                    style="width: 60px; text-align: center; background: #1a2332; border: 1px solid #2a3a4e; color: #e0e6ed; padding: 4px 8px; border-radius: 4px; font-size: 14px; text-transform: lowercase;"
                                    oninput="var v=this.value.toLowerCase().replace(/[^a-z]/g,'');this.value=v;if(v.length===2)document.getElementById('flag-preview-{{ $code }}').src='https://flagcdn.com/48x36/'+v+'.png';"
                                >
                                <div style="color: #5a6a7a; font-size: 10px; margin-top: 4px;">Country code (2 letters)</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button onclick="saveFlags()" id="btn-save-flags" class="btn" style="padding: 10px 24px; font-size: 14px; background: linear-gradient(135deg, #1565c0, #1e88e5); border: none; color: #fff; border-radius: 6px;">
                        <i class="fa fa-save"></i> Save Flags
                    </button>
                </div>
            </div>

            {{-- Protected Terms --}}
            <div class="box" style="background: #1a2332; border: 1px solid #2a3a4e;">
                <div class="box-header with-border" style="border-color: #2a3a4e; cursor: pointer;" onclick="document.getElementById('terms-body').style.display = document.getElementById('terms-body').style.display === 'none' ? 'block' : 'none';">
                    <h3 class="box-title"><i class="fa fa-shield" style="color: #e53935;"></i> Protected Terms</h3>
                    <span class="pull-right" style="color: #5a6a7a; font-size: 12px;"><i class="fa fa-chevron-down"></i> Click to expand</span>
                </div>
                <div class="box-body" id="terms-body" style="display: none;">
                    <p style="color: #8899aa; margin-bottom: 15px;">Words and brand names that should <strong>never</strong> be translated. Adding a term here will also remove it from all existing translations.</p>

                    <div style="display: flex; gap: 8px; margin-bottom: 15px;">
                        <input type="text" id="new-term-input" placeholder="Enter a term to protect..." maxlength="100"
                            style="flex: 1; background: #0d1520; border: 1px solid #2a3a4e; color: #e0e6ed; padding: 8px 12px; border-radius: 6px; font-size: 13px;"
                            onkeydown="if(event.key==='Enter')addProtectedTerm();">
                        <button onclick="addProtectedTerm()" class="btn" style="background: linear-gradient(135deg, #1565c0, #1e88e5); border: none; color: #fff; padding: 8px 16px; border-radius: 6px; font-size: 13px;">
                            <i class="fa fa-plus"></i> Add
                        </button>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <span style="color: #5a6a7a; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Built-in terms (from config)</span>
                        <div id="base-terms-list" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                            @foreach(config('autotranslator.protected_terms', []) as $term)
                                <span style="background: #2a3a4e; color: #8899aa; padding: 4px 10px; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center;">
                                    <i class="fa fa-lock" style="margin-right: 4px; font-size: 10px;"></i> {{ $term }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <span style="color: #5a6a7a; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Custom terms</span>
                        <div id="custom-terms-list" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                            {{-- Populated by JS --}}
                        </div>
                        <p id="no-custom-terms" style="color: #5a6a7a; font-size: 12px; margin-top: 8px; display: none;">
                            <i class="fa fa-info-circle"></i> No custom terms added yet.
                        </p>
                    </div>
                </div>
            </div>

            @if($results)
                @php
                    $total = $results['total_strings'] ?? 0;
                    $languages = $results['languages'] ?? [];
                    $langNames = $languageNames ?? [];
                    $langFlags = $allFlags ?? config('autotranslator.default_flags', []);
                    $langColors = [
                        'es' => ['#c62828', '#e53935'],
                        'pt' => ['#2e7d32', '#43a047'],
                        'fr' => ['#1565c0', '#1e88e5'],
                        'de' => ['#f57f17', '#fbc02d'],
                        'it' => ['#6a1b9a', '#8e24aa'],
                    ];
                @endphp

                {{-- Results Overview --}}
                <div class="box" style="background: #1a2332; border: 1px solid #2a3a4e;">
                    <div class="box-header with-border" style="border-color: #2a3a4e;">
                        <h3 class="box-title"><i class="fa fa-bar-chart" style="color: #f39c12;"></i> Scan Results</h3>
                        <span class="label pull-right" style="background: #2a3a4e; color: #8899aa; padding: 5px 12px; font-size: 12px;">
                            <i class="fa fa-clock-o"></i> {{ $results['scanned_at'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="box-body">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="background: linear-gradient(135deg, #1565c0, #1e88e5); border-radius: 10px; padding: 20px; display: inline-block; min-width: 200px; box-shadow: 0 4px 15px rgba(21,101,192,0.3);">
                                <i class="fa fa-globe" style="font-size: 28px; color: rgba(255,255,255,0.8); margin-bottom: 6px;"></i>
                                <div style="color: rgba(255,255,255,0.7); text-transform: uppercase; font-size: 11px; letter-spacing: 1px; margin-bottom: 4px;">Total Strings</div>
                                <div style="color: #fff; font-size: 32px; font-weight: 700;">{{ number_format($total) }}</div>
                            </div>
                        </div>

                        <div class="row">
                            @foreach($supportedLanguages as $lang)
                                @php
                                    $langData = $languages[$lang] ?? null;
                                    $translated = $langData['translated'] ?? 0;
                                    $untranslatedCount = $langData['untranslated_count'] ?? $total;
                                    $percent = $langData['percent'] ?? 0;
                                    $colors = $langColors[$lang] ?? ['#455a64', '#607d8b'];
                                    $flag = $langFlags[$lang] ?? '';
                                    $name = $langNames[$lang] ?? strtoupper($lang);
                                @endphp
                                <div class="col-md-4 col-sm-6" style="margin-bottom: 20px;">
                                    <div id="card-{{ $lang }}" style="background: #0d1520; border: 1px solid #2a3a4e; border-radius: 12px; padding: 20px; height: 100%;">
                                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                            <img src="https://flagcdn.com/32x24/{{ $flag }}.png" style="width: 32px; height: 24px; border-radius: 3px; margin-right: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.3);" alt="{{ $lang }}">
                                            <div>
                                                <div style="color: #e0e6ed; font-size: 16px; font-weight: 700;">{{ $name }}</div>
                                                <div style="color: #5a6a7a; font-size: 12px;">{{ strtoupper($lang) }}</div>
                                            </div>
                                            <span id="percent-{{ $lang }}" style="margin-left: auto; color: {{ $percent >= 80 ? '#00e676' : ($percent >= 50 ? '#f39c12' : '#ff5252') }}; font-size: 22px; font-weight: 700;">{{ $percent }}%</span>
                                        </div>

                                        <div style="background: #1a2332; border-radius: 6px; height: 8px; overflow: hidden; margin-bottom: 12px;">
                                            <div id="bar-{{ $lang }}" style="background: linear-gradient(90deg, {{ $colors[0] }}, {{ $colors[1] }}); height: 100%; width: {{ $percent }}%; border-radius: 6px; transition: width 0.6s ease;"></div>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                            <div style="text-align: center;">
                                                <div id="translated-{{ $lang }}" style="color: #00e676; font-size: 18px; font-weight: 600;">{{ number_format($translated) }}</div>
                                                <div style="color: #5a6a7a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Translated</div>
                                            </div>
                                            <div style="text-align: center;">
                                                <div id="missing-{{ $lang }}" style="color: #ff5252; font-size: 18px; font-weight: 600;">{{ number_format($untranslatedCount) }}</div>
                                                <div style="color: #5a6a7a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Missing</div>
                                            </div>
                                        </div>

                                        {{-- Progress bar --}}
                                        <div id="progress-wrap-{{ $lang }}" style="display: none; margin-bottom: 10px;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                <span style="color: #8899aa; font-size: 11px;" id="progress-label-{{ $lang }}">Translating...</span>
                                                <span style="color: #00e676; font-size: 11px; font-weight: 600;" id="progress-pct-{{ $lang }}">0%</span>
                                            </div>
                                            <div style="background: #1a2332; border-radius: 4px; height: 6px; overflow: hidden;">
                                                <div id="progress-bar-{{ $lang }}" style="background: linear-gradient(90deg, #00c853, #00e676); height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s ease;"></div>
                                            </div>
                                        </div>

                                        {{-- Action buttons --}}
                                        <div id="btn-wrap-{{ $lang }}" style="display: flex; gap: 6px; flex-wrap: wrap;">
                                            @if($untranslatedCount > 0)
                                                <button onclick="startTranslation('{{ $lang }}')" id="btn-{{ $lang }}" class="btn btn-sm" style="flex: 1; background: linear-gradient(135deg, {{ $colors[0] }}, {{ $colors[1] }}); border: none; color: #fff; padding: 8px; font-size: 12px; border-radius: 6px;">
                                                    <i class="fa fa-magic"></i> Auto Translate ({{ number_format($untranslatedCount) }})
                                                </button>
                                            @else
                                                <div style="flex: 1; text-align: center; color: #00e676; font-size: 13px; padding: 8px;">
                                                    <i class="fa fa-check-circle"></i> Fully translated
                                                </div>
                                            @endif
                                            <button onclick="openEditor('{{ $lang }}', '{{ $name }}')" class="btn btn-sm" style="background: #2a3a4e; border: none; color: #8899aa; padding: 8px 12px; font-size: 12px; border-radius: 6px;" title="Edit translations">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <a href="/admin/translations/export/{{ $lang }}" class="btn btn-sm" style="background: #2a3a4e; border: none; color: #8899aa; padding: 8px 12px; font-size: 12px; border-radius: 6px;" title="Export JSON">
                                                <i class="fa fa-download"></i>
                                            </a>
                                            <button onclick="clearLangCache('{{ $lang }}')" class="btn btn-sm" style="background: #2a3a4e; border: none; color: #8899aa; padding: 8px 12px; font-size: 12px; border-radius: 6px;" title="Clear cache">
                                                <i class="fa fa-refresh"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Translation Editor Modal --}}
    <div id="editor-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; overflow:auto;">
        <div style="max-width: 960px; margin: 30px auto; background: #1a2332; border-radius: 12px; border: 1px solid #2a3a4e;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding: 16px 20px; border-bottom: 1px solid #2a3a4e;">
                <h3 style="margin:0; color: #e0e6ed; font-size: 16px;"><i class="fa fa-pencil"></i> <span id="editor-title">Edit Translations</span></h3>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="editor-search" placeholder="Search..." onkeyup="filterEditor()" style="background:#0d1520; border:1px solid #2a3a4e; color:#e0e6ed; padding:6px 12px; border-radius:6px; font-size:13px; width:200px;">
                    <label style="cursor:pointer;">
                        <input type="file" id="import-file" accept=".json" onchange="importTranslations()" style="display:none;">
                        <span class="btn btn-sm" style="background:#2e7d32; border:none; color:#fff; padding:6px 12px; border-radius:6px; font-size: 12px;">
                            <i class="fa fa-upload"></i> Import
                        </span>
                    </label>
                    <button onclick="closeEditor()" class="btn btn-sm" style="background:#c62828; border:none; color:#fff; padding:6px 12px; border-radius:6px;">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="editor-body" style="padding: 0; max-height: 70vh; overflow-y: auto;">
                <div style="padding: 40px; text-align: center; color: #5a6a7a;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p style="margin-top: 10px;">Loading translations...</p>
                </div>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid #2a3a4e; display:flex; justify-content:space-between; align-items:center;">
                <span id="editor-count" style="color: #5a6a7a; font-size: 12px;"></span>
                <button onclick="closeEditor()" class="btn btn-sm" style="background:#2a3a4e; border:none; color:#8899aa; padding:8px 16px; border-radius:6px;">Close</button>
            </div>
        </div>
    </div>

    <script>
    var baseUrl = '/admin/translations';
    var currentEditorLang = '';
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    // ── Auto Translate ──
    function startTranslation(lang) {
        var btn = document.getElementById('btn-' + lang);
        var progressWrap = document.getElementById('progress-wrap-' + lang);
        var progressBar = document.getElementById('progress-bar-' + lang);
        var progressPct = document.getElementById('progress-pct-' + lang);
        var progressLabel = document.getElementById('progress-label-' + lang);

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Starting...';
        progressWrap.style.display = 'block';

        var pollInterval = setInterval(function() {
            fetch(baseUrl + '/progress/' + lang, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); }).then(function(p) {
                if (p.percent !== undefined) {
                    progressBar.style.width = p.percent + '%';
                    progressPct.textContent = p.percent + '%';
                    progressLabel.textContent = 'Translating... ' + (p.done || 0) + ' / ' + (p.total || 0);
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + p.percent + '%';
                }
                if (p.status === 'complete') clearInterval(pollInterval);
            }).catch(function() {});
        }, 1000);

        fetch(baseUrl + '/translate/' + lang, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        }).then(function(r) { return r.json(); }).then(function(data) {
            clearInterval(pollInterval);
            progressBar.style.width = '100%';
            progressPct.textContent = '100%';
            progressLabel.textContent = 'Complete!';
            progressLabel.style.color = '#00e676';
            btn.innerHTML = '<i class="fa fa-check"></i> Done! (' + (data.translated || 0) + ' strings)';
            btn.style.background = '#2e7d32';
            setTimeout(function() { window.location.reload(); }, 2000);
        }).catch(function(err) {
            clearInterval(pollInterval);
            btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            btn.style.background = '#c62828';
            progressLabel.textContent = 'Error: ' + err.message;
            progressLabel.style.color = '#ff5252';
        });
    }

    // ── Cache ──
    function clearLangCache(lang) {
        fetch(baseUrl + '/clear-cache/' + lang, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(function() { showAlert('Cache cleared for ' + lang.toUpperCase()); });
    }

    function showAlert(msg) {
        var el = document.getElementById('translate-alert');
        el.innerHTML = '<i class="fa fa-check-circle"></i> ' + msg;
        el.style.display = 'block';
        el.style.borderLeft = '4px solid #00e676';
        el.style.background = 'rgba(0,230,118,0.15)';
        el.style.color = '#fff';
        setTimeout(function() { el.style.display = 'none'; }, 3000);
    }

    // ── Editor ──
    function openEditor(lang, name) {
        currentEditorLang = lang;
        document.getElementById('editor-title').textContent = 'Edit — ' + name + ' (' + lang.toUpperCase() + ')';
        document.getElementById('editor-modal').style.display = 'block';
        document.getElementById('editor-body').innerHTML = '<div style="padding:40px;text-align:center;color:#5a6a7a;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top:10px;">Loading...</p></div>';

        fetch(baseUrl + '/list/' + lang, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) { renderEditor(data.translations || {}); });
    }

    function renderEditor(translations) {
        var keys = Object.keys(translations).sort();
        document.getElementById('editor-count').textContent = keys.length + ' translations';

        if (keys.length === 0) {
            document.getElementById('editor-body').innerHTML = '<div style="padding:40px;text-align:center;color:#5a6a7a;">No translations found.</div>';
            return;
        }

        var html = '<table style="width:100%;border-collapse:collapse;">';
        html += '<thead><tr style="background:#0d1520;position:sticky;top:0;z-index:1;"><th style="padding:10px 12px;color:#8899aa;font-size:11px;text-transform:uppercase;text-align:left;border-bottom:1px solid #2a3a4e;">English</th><th style="padding:10px 12px;color:#8899aa;font-size:11px;text-transform:uppercase;text-align:left;border-bottom:1px solid #2a3a4e;">Translation</th><th style="width:40px;border-bottom:1px solid #2a3a4e;"></th></tr></thead><tbody>';

        keys.forEach(function(key) {
            var val = translations[key] || '';
            var ek = escapeAttr(key);
            var ev = escapeAttr(val);
            html += '<tr class="editor-row" data-key="' + ek + '" style="border-bottom:1px solid #151f2e;">';
            html += '<td style="padding:6px 12px;color:#8899aa;font-size:12px;max-width:350px;word-break:break-word;">' + escapeHtml(key) + '</td>';
            html += '<td style="padding:6px 8px;"><input type="text" value="' + ev + '" onchange="saveEntry(this)" style="width:100%;background:#0d1520;border:1px solid #2a3a4e;color:#e0e6ed;padding:5px 8px;border-radius:4px;font-size:12px;"></td>';
            html += '<td style="padding:6px 4px;text-align:center;"><button onclick="deleteEntry(this)" style="background:none;border:none;color:#ff5252;cursor:pointer;font-size:13px;" title="Delete"><i class="fa fa-trash"></i></button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        document.getElementById('editor-body').innerHTML = html;
    }

    function escapeHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escapeAttr(s) { return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

    function filterEditor() {
        var q = document.getElementById('editor-search').value.toLowerCase();
        var rows = document.querySelectorAll('.editor-row');
        var n = 0;
        rows.forEach(function(r) {
            var k = (r.getAttribute('data-key') || '').toLowerCase();
            var v = (r.querySelector('input')?.value || '').toLowerCase();
            var show = k.indexOf(q) >= 0 || v.indexOf(q) >= 0;
            r.style.display = show ? '' : 'none';
            if (show) n++;
        });
        document.getElementById('editor-count').textContent = n + ' shown';
    }

    function saveEntry(input) {
        var key = input.closest('tr').getAttribute('data-key');
        fetch(baseUrl + '/update/' + currentEditorLang, {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ key: key, value: input.value }),
        }).then(function(r) { return r.json(); }).then(function() {
            input.style.borderColor = '#00e676';
            setTimeout(function() { input.style.borderColor = '#2a3a4e'; }, 1000);
        }).catch(function() { input.style.borderColor = '#ff5252'; });
    }

    function deleteEntry(btn) {
        var row = btn.closest('tr');
        var key = row.getAttribute('data-key');
        if (!confirm('Delete: "' + key + '"?')) return;
        fetch(baseUrl + '/delete/' + currentEditorLang, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ key: key }),
        }).then(function() { row.remove(); });
    }

    function importTranslations() {
        var fi = document.getElementById('import-file');
        if (!fi.files.length) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var data = JSON.parse(e.target.result);
                fetch(baseUrl + '/import/' + currentEditorLang, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ translations: data }),
                }).then(function(r) { return r.json(); }).then(function(resp) {
                    showAlert('Imported ' + (resp.imported || 0) + ' translations');
                    openEditor(currentEditorLang, currentEditorLang.toUpperCase());
                });
            } catch(err) { alert('Invalid JSON file'); }
        };
        reader.readAsText(fi.files[0]);
        fi.value = '';
    }

    function closeEditor() {
        document.getElementById('editor-modal').style.display = 'none';
    }

    // ── Deep Clean ──
    function deepClean() {
        var btn = document.getElementById('btn-deep-clean');
        if (!confirm('This will re-scan the panel and remove all orphaned/junk translations from every language. Continue?')) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Cleaning...';

        fetch(baseUrl + '/deep-clean', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.error) {
                btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
                btn.style.background = '#c62828';
                showAlert('Deep Clean failed: ' + data.error);
                btn.disabled = false;
                return;
            }

            var msg = 'Deep Clean complete! Removed ' + (data.total_removed || 0) + ' junk entries, fixed ' + (data.total_fixed || 0) + ' terms.';
            if (data.languages) {
                Object.keys(data.languages).forEach(function(lang) {
                    var s = data.languages[lang];
                    msg += ' | ' + lang.toUpperCase() + ': ' + s.before + ' \u2192 ' + s.after;
                });
            }

            btn.innerHTML = '<i class="fa fa-check"></i> Done!';
            btn.style.background = '#2e7d32';
            showAlert(msg);
            setTimeout(function() { window.location.reload(); }, 3000);
        }).catch(function(err) {
            btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            btn.style.background = '#c62828';
            showAlert('Deep Clean error: ' + err.message);
            btn.disabled = false;
        });
    }

    // ── Reset All ──
    function resetAll() {
        var btn = document.getElementById('btn-reset-all');
        if (!confirm('WARNING: This will DELETE all runtime translations for EVERY language. You will need to re-scan and re-translate from scratch.\n\nAre you sure?')) return;
        if (!confirm('This action cannot be undone. Confirm again to proceed.')) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Resetting...';

        fetch(baseUrl + '/reset-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.error) {
                btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
                btn.disabled = false;
                showAlert('Reset failed: ' + data.error);
                return;
            }

            var msg = 'All translations reset!';
            if (data.languages) {
                Object.keys(data.languages).forEach(function(lang) {
                    msg += ' | ' + lang.toUpperCase() + ': ' + data.languages[lang].removed + ' removed';
                });
            }

            btn.innerHTML = '<i class="fa fa-check"></i> Done!';
            btn.style.background = '#2e7d32';
            showAlert(msg);
            setTimeout(function() { window.location.reload(); }, 2000);
        }).catch(function(err) {
            btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            btn.disabled = false;
            showAlert('Reset error: ' + err.message);
        });
    }

    // ── Flag Customization ──
    function saveFlags() {
        var btn = document.getElementById('btn-save-flags');
        var langs = {!! json_encode(array_merge(['en' => 'English'], config('autotranslator.languages', []))) !!};
        var flags = {};
        Object.keys(langs).forEach(function(code) {
            var input = document.getElementById('flag-input-' + code);
            if (input && input.value.trim()) {
                flags[code] = input.value.trim();
            }
        });

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

        fetch(baseUrl + '/flags', {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ flags: flags }),
        }).then(function(r) { return r.json(); }).then(function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check"></i> Saved!';
            btn.style.background = 'linear-gradient(135deg, #2e7d32, #43a047)';
            showAlert('Flags saved! Changes will appear on next page load.');
            setTimeout(function() {
                btn.innerHTML = '<i class="fa fa-save"></i> Save Flags';
                btn.style.background = 'linear-gradient(135deg, #1565c0, #1e88e5)';
            }, 2000);
        }).catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            showAlert('Error saving flags: ' + err.message);
        });
    }

    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeEditor(); });

    // ── Protected Terms ──
    var customTerms = [];

    function loadProtectedTerms() {
        fetch(baseUrl + '/protected-terms', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            customTerms = data.custom || [];
            renderCustomTerms();
        });
    }

    function renderCustomTerms() {
        var container = document.getElementById('custom-terms-list');
        var noTerms = document.getElementById('no-custom-terms');

        if (customTerms.length === 0) {
            container.innerHTML = '';
            noTerms.style.display = 'block';
            return;
        }

        noTerms.style.display = 'none';
        var html = '';
        customTerms.forEach(function(term) {
            html += '<span style="background: #0d1520; border: 1px solid #2a3a4e; color: #e0e6ed; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">';
            html += escapeHtml(term);
            html += ' <button onclick="removeProtectedTerm(\'' + escapeAttr(term) + '\')" style="background: none; border: none; color: #ff5252; cursor: pointer; padding: 0; font-size: 11px;" title="Remove"><i class="fa fa-times"></i></button>';
            html += '</span>';
        });
        container.innerHTML = html;
    }

    function addProtectedTerm() {
        var input = document.getElementById('new-term-input');
        var term = input.value.trim();
        if (!term) return;

        // Check if already exists
        var baseTerms = {!! json_encode(config('autotranslator.protected_terms', [])) !!};
        if (baseTerms.indexOf(term) >= 0) {
            showAlert('Term "' + term + '" is already in the built-in list.');
            input.value = '';
            return;
        }
        if (customTerms.indexOf(term) >= 0) {
            showAlert('Term "' + term + '" is already protected.');
            input.value = '';
            return;
        }

        customTerms.push(term);
        input.value = '';
        saveProtectedTerms();
    }

    function removeProtectedTerm(term) {
        customTerms = customTerms.filter(function(t) { return t !== term; });
        saveProtectedTerms();
    }

    function saveProtectedTerms() {
        fetch(baseUrl + '/protected-terms', {
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ terms: customTerms }),
        }).then(function(r) { return r.json(); }).then(function(data) {
            customTerms = data.terms || [];
            renderCustomTerms();
            var msg = 'Protected terms saved!';
            if (data.removed > 0) msg += ' Removed ' + data.removed + ' translations matching protected terms.';
            showAlert(msg);
        }).catch(function(err) {
            showAlert('Error saving terms: ' + err.message);
        });
    }

    // Load custom terms on page load
    loadProtectedTerms();
    </script>
@endsection

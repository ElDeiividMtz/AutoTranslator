<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\GoogleTranslateService;

class TranslationJsonController extends Controller
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

    /**
     * Serve translations JSON for the frontend.
     */
    public function show(string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse([], 404);
        }

        $path = $this->translateService->getStoragePath($lang);

        if (!File::exists($path)) {
            return new JsonResponse([], 200);
        }

        $data = json_decode(File::get($path), true) ?: [];

        return new JsonResponse($data, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    /**
     * Live-translate: receive untranslated strings, translate via Google,
     * merge into storage, and return the new translations.
     */
    public function liveTranslate(Request $request, string $lang): JsonResponse
    {
        if (!in_array($lang, $this->supportedLanguages())) {
            return new JsonResponse(['error' => 'Unsupported language'], 400);
        }

        $strings = $request->input('strings', []);
        if (!is_array($strings) || empty($strings)) {
            return new JsonResponse([], 200);
        }

        $maxPerRequest = config('autotranslator.google.max_per_request', 200);

        $strings = array_slice(array_unique(array_filter($strings, function ($s) {
            return is_string($s) && mb_strlen(trim($s)) >= 2 && mb_strlen(trim($s)) <= 500;
        })), 0, $maxPerRequest);

        if (empty($strings)) {
            return new JsonResponse([], 200);
        }

        $existing = $this->translateService->loadExistingTranslations($lang);

        $toTranslate = [];
        $alreadyDone = [];
        foreach ($strings as $str) {
            $str = trim($str);
            if (isset($existing[$str]) && $existing[$str] !== '') {
                $alreadyDone[$str] = $existing[$str];
            } else {
                $toTranslate[] = $str;
            }
        }

        $newTranslations = [];
        if (!empty($toTranslate)) {
            set_time_limit(120);
            $newTranslations = $this->translateService->translateBatch($toTranslate, $lang);

            $merged = array_merge($existing, $newTranslations);
            $this->translateService->saveTranslations($lang, $merged);
        }

        return new JsonResponse(array_merge($alreadyDone, $newTranslations), 200);
    }
}

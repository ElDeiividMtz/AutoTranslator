<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Http\Controllers\Controller;

class TranslationLanguageController extends Controller
{
    /**
     * Update the authenticated user's language preference.
     * Used by the AutoTranslator LanguageSelector component.
     */
    public function update(Request $request): JsonResponse
    {
        $supported = array_keys(config('autotranslator.languages', []));
        $request->validate([
            'language' => ['required', 'string', Rule::in(array_merge(['en'], $supported))],
        ]);

        $request->user()->update([
            'language' => $request->input('language'),
        ]);

        return new JsonResponse(['success' => true]);
    }
}

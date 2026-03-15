<?php
/*
 * AutoTranslator — Client API Routes (Blueprint)
 * Blueprint auto-prefixes: /api/client/extensions/autotranslator/
 */

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base\TranslationJsonController;
use Pterodactyl\Http\Controllers\Base\TranslationLanguageController;

// Live translate endpoint for on-demand translations (10 req/min per user)
Route::post('/translate/{lang}', [TranslationJsonController::class, 'liveTranslate'])
    ->where('lang', '[a-z]{2}')
    ->middleware('throttle:10,1');

// Language preference update (used by the LanguageSelector component)
Route::put('/language', [TranslationLanguageController::class, 'update'])
    ->middleware('throttle:30,1');

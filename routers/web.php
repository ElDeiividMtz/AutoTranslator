<?php
/*
 * AutoTranslator — Web Routes (Blueprint)
 * Blueprint auto-prefixes: /extensions/autotranslator/
 * Blueprint auto-applies: web + admin middleware
 */

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\TranslationScanController;

// Translation management dashboard
// Full URL: /extensions/autotranslator/translations/...
Route::prefix('translations')->group(function () {
    Route::get('/', [TranslationScanController::class, 'index']);
    Route::post('/scan', [TranslationScanController::class, 'scan']);
    Route::post('/translate/{lang}', [TranslationScanController::class, 'translate'])->where('lang', '[a-z]{2}');
    Route::get('/progress/{lang}', [TranslationScanController::class, 'progress'])->where('lang', '[a-z]{2}');
    Route::get('/list/{lang}', [TranslationScanController::class, 'getTranslations'])->where('lang', '[a-z]{2}');
    Route::put('/update/{lang}', [TranslationScanController::class, 'updateTranslation'])->where('lang', '[a-z]{2}');
    Route::delete('/delete/{lang}', [TranslationScanController::class, 'deleteTranslation'])->where('lang', '[a-z]{2}');
    Route::get('/export/{lang}', [TranslationScanController::class, 'exportTranslations'])->where('lang', '[a-z]{2}');
    Route::post('/import/{lang}', [TranslationScanController::class, 'importTranslations'])->where('lang', '[a-z]{2}');
    Route::post('/clear-cache/{lang}', [TranslationScanController::class, 'clearCache'])->where('lang', '[a-z]{2}');
    Route::post('/deep-clean', [TranslationScanController::class, 'deepClean'])->middleware('throttle:3,1');
    Route::post('/reset-all', [TranslationScanController::class, 'resetAll'])->middleware('throttle:3,1');
    Route::get('/flags', [TranslationScanController::class, 'getFlags']);
    Route::put('/flags', [TranslationScanController::class, 'updateFlags']);
    Route::get('/protected-terms', [TranslationScanController::class, 'getProtectedTerms']);
    Route::put('/protected-terms', [TranslationScanController::class, 'updateProtectedTerms']);
});

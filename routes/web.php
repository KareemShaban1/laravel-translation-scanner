<?php

use Illuminate\Support\Facades\Route;
use Kareem\TranslationScanner\Http\Controllers\TranslationController;

Route::middleware(config('translations-scanner.middleware'))
    ->prefix('translation-scanner')
    ->group(function () {
        Route::get('/', [TranslationController::class, 'index'])->name('translations.index');
        Route::post('/update', [TranslationController::class, 'update'])->name('translations.update');
        Route::post('/scan', [TranslationController::class, 'scan'])->name('translations.scan');
        Route::post('/scan-translate', [TranslationController::class, 'scanTranslate'])->name('translations.scan-translate');
        Route::post('/delete', [TranslationController::class, 'delete'])->name('translations.delete');
    });

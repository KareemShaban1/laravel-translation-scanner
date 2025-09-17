<?php

namespace Kareem\LaravelTranslationScanner\Src\Exceptions;

use Exception;

class TranslationScannerException extends Exception
{
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status'  => 'error',
                'message' => $this->getMessage(),
            ], 400);
        }

        return response()->view('translation-scanner::error', [
            'message' => $this->getMessage(),
        ], 400);
    }
}

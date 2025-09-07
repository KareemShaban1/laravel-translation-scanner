<?php

namespace Kareem\TranslationScanner;

use Illuminate\Support\ServiceProvider;
use Kareem\TranslationScanner\Console\Commands\ScanTranslations;

class TranslationScannerServiceProvider extends ServiceProvider
{
    public function register()
    {
        //

        $this->mergeConfigFrom(__DIR__.'/../config/translations-scanner.php', 'translations-scanner');

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanTranslations::class,
            ]);
        }


        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'translation-scanner');


        // Config publish
        $this->publishes([
            __DIR__.'/../config/translations-scanner.php' => config_path('translations-scanner.php'),
        ], 'translations-scanner-config');        

        // Views publish
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/translation-scanner'),
        ], 'translation-scanner-views');


        \Illuminate\Pagination\Paginator::useBootstrapFive();

    }
}

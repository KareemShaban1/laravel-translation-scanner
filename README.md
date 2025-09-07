Laravel Translation Scanner

A Laravel package to scan Blade & PHP files for translation keys and manage them in a UI.
Supports .php and .json language files, live search, update, scan, and Google Translate integration.

🚀 Features

🔎 Scan Blade & PHP files for __(), @lang(), trans() calls.

📂 Auto-generate missing translation files.

🌍 Manage multiple locales (default: en, ar).

🖥️ Web UI with live search, update & sync.

⚡ Command to scan/update translations from CLI.

🔄 Optional Google Translate integration (--translate).

🎨 Uses Bootstrap 5 + Tailwind for styling.

📦 Installation

Require the package via Composer:

composer require kareem/laravel-translation-scanner

⚙️ Configuration

Publish the config & views:

php artisan vendor:publish --provider="Kareem\TranslationScanner\TranslationScannerServiceProvider" --tag="translations-scanner-config"
php artisan vendor:publish --provider="Kareem\TranslationScanner\TranslationScannerServiceProvider" --tag="translation-scanner-views"


This will create:

config/translations-scanner.php

resources/views/vendor/translation-scanner/*

Edit config/translations-scanner.php:

return [
    'layout' => 'layouts.app', // Blade layout to extend
    'locales' => ['en', 'ar'], // Default locales
    'middleware' => ['web', 'auth'], // Protect routes
];

🖥️ Web UI

Visit:

http://your-app.test/translation-scanner


You’ll see a translation manager with tabs for each locale.
From here you can:

✅ Update translations inline

🔄 Scan for new keys

🌍 Auto-translate missing values (Google API)

🛠️ Artisan Commands
Scan translations
php artisan translations:scan


Options:

Option	Description
--path	Paths to scan (comma-separated). Default: resources/views,app/Http/Controllers
--locales	Locales to update (comma-separated). Default: en
--ignore	Ignore paths (comma-separated).
--overwrite	Overwrite existing translations.
--translate	Auto-translate using Google Translate (experimental).

Example:

php artisan translations:scan --path=resources/views --locales=en,ar --translate

📝 Example Usage in Blade
{{ __('messages.welcome') }}
@lang('auth.failed')
{{ trans('dashboard.title') }}


The scanner will detect these keys and generate/update:

lang/en/messages.php

lang/en/auth.php

lang/en/dashboard.php

lang/ar/... (with translations if enabled)

🔐 Middleware

Protect routes by adding middleware in config/translations-scanner.php:

'middleware' => ['web', 'auth', 'can:manage-translations'],

📊 Roadmap

 Export translations to Excel/CSV

 Import back into Laravel

 Deep Google Translate API support

🤝 Contributing

Fork the repo

Create a new branch (feature/my-feature)

Commit your changes

Push & open a PR

📄 License

MIT License. Free to use and modify.
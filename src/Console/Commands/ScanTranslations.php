<?php

namespace Kareem\TranslationScanner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScanTranslations extends Command
{
    protected $signature = 'translations:scan
    {--path= : Path(s) to scan, comma separated (default: resources/views,app/Http/Controllers)}
    {--locales= : Comma-separated locales, e.g. en,ar}
    {--translate : Enable auto translation via Google API (experimental)}
    {--ignore= : Comma-separated paths to ignore (e.g. views,controllers)}
    {--ignoreFile= : Comma-separated file names to ignore (e.g. welcome.blade.php,home.blade.php)}
    {--ignoreDir= : Comma-separated directories to ignore (e.g. vendor,storage,cache)}
    {--overwrite : Overwrite existing translations with new ones}';



    protected $description = 'Scan Blade & PHP files recursively and update translation files';

    public function handle()
    {
        try {
            $paths = $this->option('path')
                ? explode(',', $this->option('path'))
                : ['resources/views', 'app/Http/Controllers'];

            $ignore = $this->option('ignore')
                ? explode(',', $this->option('ignore'))
                : [];

            $ignoreFiles = $this->option('ignoreFile')
                ? array_map('trim', explode(',', $this->option('ignoreFile')))
                : [];

            $ignoreDirs = $this->option('ignoreFir')
                ? array_map('trim', explode(',', $this->option('ignoreDir')))
                : [];

            $locales = $this->option('locales')
                ? explode(',', $this->option('locales'))
                : ['en'];

            foreach ($paths as $path) {
                $path = trim($path);

                foreach ($ignore as $skip) {
                    if (str_contains($path, trim($skip))) {
                        $this->warn("⏭️ Skipping {$path} (ignored)");
                        continue 2;
                    }
                }

                if (!File::exists(base_path($path))) {
                    $this->error("❌ Path not found: {$path}");
                    continue;
                }

                $this->info("🔎 Scanning files in: " . base_path($path));

                $keys = $this->extractKeys(base_path($path), $ignoreFiles, $ignoreDirs);
                $this->info("📂 Found " . count($keys) . " translation keys...");

                foreach ($locales as $locale) {
                    $this->updateTranslations($keys, $locale);
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("💥 An error occurred while scanning:");
            $this->line("   " . $e->getMessage());
            if ($this->option('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }


    private function extractKeys(string $path, array $ignoreFiles = [], array $ignoreDirs = []): array
    {
        try {
            if (!File::exists($path)) {
                $this->warn("⚠ Directory not found: {$path}");
                return [];
            }

            $keys = [];
            $files = File::allFiles($path);

            // Filter files based on ignore lists with wildcard support
            $files = array_filter($files, function ($file) use ($ignoreFiles, $ignoreDirs) {
                $filename = $file->getFilename();
                $relativePath = $file->getRelativePath(); // relative dir inside scanned path
                $dirname  = basename($relativePath);

                // Check ignored files (exact match or wildcard)
                foreach ($ignoreFiles as $pattern) {
                    if ($filename === $pattern || fnmatch($pattern, $filename)) {
                        return false;
                    }
                }

                // Check ignored directories (exact match or wildcard)
                foreach ($ignoreDirs as $pattern) {
                    if ($dirname === $pattern || fnmatch($pattern, $dirname) || fnmatch($pattern, $relativePath)) {
                        return false;
                    }
                }

                return true;
            });

            $this->info("📂 Found " . count($files) . " files to scan...");
            $bar = $this->output->createProgressBar(count($files));
            $bar->setFormat("  Scanning [<fg=cyan>%bar%</>] %current%/%max% files");

            foreach ($files as $file) {
                $content = $file->getContents();

                preg_match_all("/__\\(['\"](.+?)['\"]\\)/", $content, $matches1);
                preg_match_all("/@lang\\(['\"](.+?)['\"]\\)/", $content, $matches2);
                preg_match_all("/trans\\(['\"](.+?)['\"]\\)/", $content, $matches3);

                $matches = array_merge($matches1[1], $matches2[1], $matches3[1]);

                foreach ($matches as $match) {
                    $keys[] = $match;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            return array_unique($keys);
        } catch (\Throwable $e) {
            $this->error("❌ Failed to scan {$path}: " . $e->getMessage());
            return [];
        }
    }


    private function updateTranslations(array $keys, string $locale): void
    {
        $phpGrouped = [];
        $jsonKeys   = [];

        foreach ($keys as $fullKey) {
            if (str_contains($fullKey, '.')) {
                [$file, $key] = explode('.', $fullKey, 2);

                if (trim($key) === '') {
                    $jsonKeys[] = $fullKey;
                    continue;
                }

                // sanitize filename (avoid spaces, ?, special chars)
                $safeFile = preg_replace('/[^A-Za-z0-9_\-]/', '_', $file);

                // if file becomes empty, fallback to JSON
                if (empty($safeFile)) {
                    $jsonKeys[] = $fullKey;
                    continue;
                }

                $phpGrouped[$safeFile][] = $key;
            } else {
                $jsonKeys[] = $fullKey;
            }
        }

        // ---- Handle PHP files (file.key style) ----
        foreach ($phpGrouped as $file => $fileKeys) {
            $langPath = lang_path("{$locale}/{$file}.php");

            if (!File::exists(dirname($langPath))) {
                File::makeDirectory(dirname($langPath), 0755, true);
            }

            $translations = File::exists($langPath) ? include $langPath : [];
            if (!is_array($translations)) {
                $translations = [];
            }

            $new = 0;
            $bar = $this->output->createProgressBar(count($fileKeys));
            $bar->setFormat("  Translating {$file}.php [<fg=green>%bar%</>] %current%/%max% keys");

            foreach ($fileKeys as $key) {
                $safeKey = $this->makeSafeKey($key);

                $shouldTranslate = !isset($translations[$safeKey])
                    || $this->option('overwrite')
                    || $this->needsTranslation($translations[$safeKey], $locale, $key);


                if ($shouldTranslate) {
                    $translations[$safeKey] = $this->option('translate')
                        ? $this->translate($key, $locale)
                        : $this->cleanKey($key);
                    $new++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            ksort($translations);

            try {
                File::put(
                    $langPath,
                    "<?php\n\nreturn " . var_export($translations, true) . ";\n"
                );
                $this->info("✅ Updated {$langPath} with {$new} new/updated keys.");
            } catch (\Throwable $e) {
                $this->error("❌ Failed to write file: {$langPath}");
                $this->line("   Reason: " . $e->getMessage());
            }
            
        }

        // ---- Handle JSON ----
        if (!empty($jsonKeys)) {
            $jsonPath = lang_path("{$locale}.json");

            if (!File::exists(lang_path())) {
                File::makeDirectory(lang_path(), 0755, true);
            }

            if (!File::exists($jsonPath)) {
                File::put($jsonPath, json_encode(new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $translations = File::exists($jsonPath)
                ? json_decode(File::get($jsonPath), true)
                : [];

            if (!is_array($translations)) {
                $translations = [];
            }

            $new = 0;
            $bar = $this->output->createProgressBar(count($jsonKeys));
            $bar->setFormat("  Translating {$locale}.json [<fg=yellow>%bar%</>] %current%/%max% keys");

            foreach ($jsonKeys as $key) {
                $safeKey = $this->makeSafeKey($key);
                $shouldTranslate = !isset($translations[$safeKey])
                    || $this->option('overwrite')
                    || $this->needsTranslation($translations[$safeKey], $locale, $key);


                if ($shouldTranslate) {
                    $translations[$safeKey] = $this->option('translate')
                        ? $this->translate($key, $locale)
                        : $key;
                    $new++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            ksort($translations);

            File::put(
                $jsonPath,
                json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->info("✅ Updated {$jsonPath} with {$new} new/updated keys.");
        }
    }





    private function makeSafeKey(string $key): string
    {
        // If it's a long sentence → slugify as safe key
        if (preg_match('/\s/', $key)) {
            return \Illuminate\Support\Str::slug($key, '_');
        }

        return $key;
    }




    private function cleanKey(string $key): string
    {
        $last = $key;

        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $last = end($parts);
        }

        if (str_contains($last, '/')) {
            $parts = explode('/', $last);
            $last = end($parts);
        }

        $last = str_replace('_', ' ', $last);

        return trim(ucwords($last));
    }

    private function translate(string $key, string $locale): string
    {
        $text = $this->cleanKey($key);

        if ($locale === 'en') {
            return $text;
        }

        try {
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={$locale}&dt=t&q=" . urlencode($text);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $this->warn("⚠ Translation API error: " . curl_error($ch));
                curl_close($ch);
                return $text;
            }

            curl_close($ch);

            $result = json_decode($response, true);
            return $result[0][0][0] ?? $text;
        } catch (\Throwable $e) {
            $this->warn("⚠ Failed to translate '{$text}' to {$locale}: " . $e->getMessage());
            return $text;
        }
    }


    private function needsTranslation($value, string $locale, $key = null): bool
    {
        // Empty or null
        if ($value === null || $value === '') {
            return true;
        }

        // If same as key → not translated
        if ($key !== null && ($value === $key || $value === $this->cleanKey($key))) {
            return true;
        }

        // Not a string
        if (!is_string($value)) {
            return true;
        }

        // Wrong language detection
        if ($locale === 'ar' && preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
            return true;
        }

        if ($locale === 'en' && preg_match('/[\p{Arabic}]/u', $value)) {
            return true;
        }

        return false;
    }
}

<?php

namespace Kareem\TranslationScanner\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class TranslationController extends Controller
{
    public function index(Request $request)
    {
        // Gather locales from /lang
        $locales = array_map('basename', File::directories(lang_path()));
        $jsonLocales = collect(File::files(lang_path()))
            ->filter(fn($f) => $f->getExtension() === 'json')
            ->map(fn($f) => str_replace('.json', '', $f->getFilename()))
            ->toArray();

        $locales = array_values(array_unique(array_merge($locales, $jsonLocales)));

        $perPage = (int) $request->input('per_page', 25);
        $search  = strtolower($request->input('search', ''));

        $translations = [];

        foreach ($locales as $locale) {
            $all = $this->loadTranslations($locale);

            // 🔍 Apply search filter
            $all = collect($all)->filter(function ($item) use ($search) {
                if (!$search) return true;

                return str_contains(strtolower($item['key'] ?? ''), $search)
                    || str_contains(strtolower($item['value'] ?? ''), $search)
                    || str_contains(strtolower($item['file'] ?? ''), $search);
            });

            // Order by key
            $all = $all->sortBy('key')->values();

            // Handle pagination per locale
            $pageName    = "page_{$locale}";
            $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);

            $items = $all->forPage($currentPage, $perPage)->values();

            $translations[$locale] = new LengthAwarePaginator(
                $items,
                $all->count(),
                $perPage,
                $currentPage,
                [
                    'path'      => $request->url(),
                    'query'     => $request->query(),
                    'pageName'  => $pageName,
                    'fragment'  => $locale,
                ]
            );
        }

        if ($request->ajax()) {
            $activeLocale = $request->input('locale', $locales[0]);

            return response()->json([
                'success' => "Translations updated successfully!",
                'html'    => view('translation-scanner::partials.tabs-content', [
                    'translations' => $translations,
                    'locales'      => $locales,
                    'perPage'      => $perPage,
                    'search'       => $search,
                ])->render(),
                'tab'     => $activeLocale,
            ]);
        }

        return view('translation-scanner::index', compact('translations', 'locales', 'perPage', 'search'));
    }



    public function update(Request $request)
    {
        $locale = (string) $request->input('locale');
        $key    = (string) $request->input('key');
        $value  = $request->input('value', '');

        $jsonPath = lang_path("{$locale}.json");

        if (!File::exists($jsonPath)) {
            File::put($jsonPath, json_encode(new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $translations = json_decode(File::get($jsonPath), true) ?: [];

        // now safe, key is always string
        $translations[$key] = $value;

        File::put($jsonPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($request->ajax()) {
            return response()->json(['success' => "Updated translation for {$key}"]);
        }
        return back()
            ->with('success', "Updated translation for {$key}")
            ->withFragment($locale);
    }

    public function delete(Request $request)
    {
        $locale = (string) $request->input('locale');
        $key    = (string) $request->input('key');
        $file   = (string) $request->input('file');
        $locales = $request->input('locales', ['en', 'ar']); // keep same locales context
        $tab     = $request->input('tab', $locale); // keep active tab
    
        if (str_ends_with($file, '.json')) {
            // JSON translations
            $jsonPath = lang_path($file);
    
            if (!File::exists($jsonPath)) {
                return response()->json(['error' => "File {$file} not found"], 404);
            }
    
            $translations = json_decode(File::get($jsonPath), true) ?: [];
    
            if (array_key_exists($key, $translations)) {
                unset($translations[$key]);
                File::put($jsonPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            // PHP translations
            $phpPath = lang_path($file);
    
            if (!File::exists($phpPath)) {
                return response()->json(['error' => "File {$file} not found"], 404);
            }
    
            $translations = File::getRequire($phpPath);
            Arr::forget($translations, $key);
    
            File::put($phpPath, '<?php return ' . var_export($translations, true) . ';');
        }
    
        // Reload all translations (same as scan)
        $translations = [];
        $perPage = (int) $request->input('per_page', 25);
        $search  = strtolower($request->input('search', ''));
    
        foreach ($locales as $loc) {
            $all = $this->loadTranslations($loc);
    
            $all = collect($all)->filter(function ($item) use ($search) {
                if (!$search) return true;
    
                return str_contains(strtolower($item['key'] ?? ''), $search)
                    || str_contains(strtolower($item['value'] ?? ''), $search)
                    || str_contains(strtolower($item['file'] ?? ''), $search);
            });
    
            $all = $all->sortBy('key')->values();
    
            $pageName    = "page_{$loc}";
            $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
            $items       = $all->forPage($currentPage, $perPage)->values();
    
            $translations[$loc] = new LengthAwarePaginator(
                $items,
                $all->count(),
                $perPage,
                $currentPage,
                [
                    'path'      => $request->url(),
                    'query'     => $request->query(),
                    'pageName'  => $pageName,
                    'fragment'  => $loc,
                ]
            );
        }
    
        $activeLocale = $tab ?? $locales[0];
    
        if ($request->ajax()) {
            return response()->json([
                'success' => "Deleted translation {$key}",
                'html'    => view('translation-scanner::partials.tabs-content', [
                    'translations' => $translations,
                    'locales'      => $locales,
                    'perPage'      => $perPage,
                    'search'       => $search,
                ])->render(),
                'tab'     => $activeLocale,
            ]);
        }
    
        return back()->with('success', "Deleted translation {$key}")
            ->withFragment($activeLocale);
    }
    


    public function scan(Request $request)
    {
        $locales = $request->input('locales', ['en', 'ar']);
        $tab = $request->input('tab');

        Artisan::call('translations:scan', [
            '--locales' => implode(',', $locales),
        ]);

        if ($request->ajax()) {
            $translations = [];
            $perPage = (int) $request->input('per_page', 25);
            $search  = strtolower($request->input('search', ''));

            foreach ($locales as $locale) {
                $all = $this->loadTranslations($locale);

                $all = collect($all)->filter(function ($item) use ($search) {
                    if (!$search) return true;

                    return str_contains(strtolower($item['key'] ?? ''), $search)
                        || str_contains(strtolower($item['value'] ?? ''), $search)
                        || str_contains(strtolower($item['file'] ?? ''), $search);
                });

                $all = $all->sortBy('key')->values();

                $pageName    = "page_{$locale}";
                $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
                $items       = $all->forPage($currentPage, $perPage)->values();

                $translations[$locale] = new LengthAwarePaginator(
                    $items,
                    $all->count(),
                    $perPage,
                    $currentPage,
                    [
                        'path'      => $request->url(),
                        'query'     => $request->query(),
                        'pageName'  => $pageName,
                        'fragment'  => $locale,
                    ]
                );
            }

            $activeLocale = $tab ?? $locales[0];

            return response()->json([
                'success' => "Translations updated successfully!",
                'html'    => view('translation-scanner::partials.tabs-content', [
                    'translations' => $translations,
                    'locales'      => $locales,
                    'perPage'      => $perPage,
                    'search'       => $search,
                ])->render(),
                'tab'     => $activeLocale,
            ]);
        }

        return back()->with('success', "Translations synced successfully!")
            ->withFragment($tab ?: null);
    }


    public function scanTranslate(Request $request)
    {
        $locales = $request->input('locales', ['en', 'ar']);
        $tab     = $request->input('tab');

        Artisan::call("translations:scan --translate --locales=" . implode(',', $locales));


        if ($request->ajax()) {
            $translations = [];
            $perPage = (int) $request->input('per_page', 25);
            $search  = strtolower($request->input('search', ''));

            foreach ($locales as $locale) {
                $all = $this->loadTranslations($locale);

                // 🔍 Apply search filter
                $all = collect($all)->filter(function ($item) use ($search) {
                    if (!$search) return true;

                    return str_contains(strtolower($item['key'] ?? ''), $search)
                        || str_contains(strtolower($item['value'] ?? ''), $search)
                        || str_contains(strtolower($item['file'] ?? ''), $search);
                });

                // Sort by key
                $all = $all->sortBy('key')->values();

                // Handle pagination per locale
                $pageName    = "page_{$locale}";
                $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
                $items       = $all->forPage($currentPage, $perPage)->values();

                $translations[$locale] = new LengthAwarePaginator(
                    $items,
                    $all->count(),
                    $perPage,
                    $currentPage,
                    [
                        'path'      => $request->url(),
                        'query'     => $request->query(),
                        'pageName'  => $pageName,
                        'fragment'  => $locale,
                    ]
                );
            }

            $activeLocale = $tab ?? $locales[0];

            return response()->json([
                'success' => "Translations updated successfully!",
                'html'    => view('translation-scanner::partials.tabs-content', [
                    'translations' => $translations,
                    'locales'      => $locales,
                    'perPage'      => $perPage,
                    'search'       => $search,
                ])->render(),
                'tab'     => $activeLocale,
            ]);
        }

        return back()->with('success', "Translations scanned & translated successfully!")
            ->withFragment($tab ?: null);
    }





    protected function loadTranslations(string $locale): array
    {
        $translations = [];

        // Load JSON file translations (lang/en.json)
        $jsonPath = lang_path("{$locale}.json");
        if (File::exists($jsonPath)) {
            $json = json_decode(File::get($jsonPath), true) ?? [];
            foreach ($json as $key => $value) {
                $translations["json:{$key}"] = [
                    'file' => "{$locale}.json",
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        // Load PHP file translations (lang/en/messages.php, etc.)
        $phpPath = lang_path($locale);
        if (File::exists($phpPath)) {
            foreach (File::allFiles($phpPath) as $file) {
                $group = $file->getFilenameWithoutExtension();
                $arr = File::getRequire($file->getRealPath());

                foreach (Arr::dot($arr) as $key => $value) {
                    $translations[$key] = [
                        'file' => "{$locale}/{$group}.php",
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }
        }

        return $translations;
    }
}

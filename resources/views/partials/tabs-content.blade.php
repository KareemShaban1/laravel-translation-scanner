<div class="tab-content mt-3" id="localeTabsContent">
    @foreach($locales as $i => $locale)
        <div class="tab-pane fade {{ $i===0 ? 'show active' : '' }}"
            id="{{ $locale }}"
            role="tabpanel"
            aria-labelledby="tab-{{ $locale }}">

            <div id="translations-table-{{ $locale }}">
                @include('translation-scanner::partials.translations-table', [
                    'translations' => $translations[$locale],
                    'locale'       => $locale,
                    'perPage'      => $perPage,
                    'search'       => $search,
                ])
            </div>
        </div>
    @endforeach
</div>

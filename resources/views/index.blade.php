@extends('translation-scanner::layouts.app')

@section('title', 'Translations Scanner')

@section('content')
<div class="container py-3">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            🌍 Translation Scanner
        </h1>
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        {{-- 🔍 Search --}}
        <form id="searchForm" class="flex w-full sm:w-auto">
            <input type="text" name="search"
                placeholder="🔍 Search translations..."
                value="{{ $search ?? '' }}"
                class="w-full sm:w-72 rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm px-3 py-2">
        </form>

        {{-- 📌 Scan & Translate --}}
        <div class="flex gap-2">
            <form id="scanForm" action="{{ route('translations.scan') }}" method="post">
                @csrf
                <input type="hidden" name="tab" class="active-tab-input" value="">
                @foreach($locales as $locale)
                <input type="hidden" name="locales[]" value="{{ $locale }}">
                @endforeach
                <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg shadow hover:bg-indigo-700 transition flex items-center gap-2">
                    <span>Scan from Code</span>
                    <i class="fa-solid fa-spinner hidden animate-spin"></i>
                </button>
            </form>

            <form id="scanAndTranslateForm" action="{{ route('translations.scan-translate') }}" method="post">
                @csrf
                <input type="hidden" name="tab" class="active-tab-input" value="">
                @foreach($locales as $locale)
                <input type="hidden" name="locales[]" value="{{ $locale }}">
                @endforeach
                <button type="submit"
                    class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg shadow hover:bg-green-700 transition flex items-center gap-2">
                    <span>Scan & Translate</span>
                    <i class="fa-solid fa-spinner hidden animate-spin"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="border-b border-gray-200 mt-6">

        <nav class="-mb-px flex gap-6">
            <ul class="nav nav-tabs" id="localeTabs" role="tablist">
                @foreach($locales as $i => $locale)
                <li class="nav-item">
                    <a class="nav-link {{ $i===0 ? 'active' : '' }}" id="tab-{{ $locale }}" data-bs-toggle="tab" href="#{{ $locale }}" role="tab">
                        {{ strtoupper($locale) }}
                    </a>
                </li>
                @endforeach
            </ul>
        </nav>
    </div>


    <div class="tab-content mt-3" id="localeTabsContent">
        @foreach($locales as $i => $locale)
        <div class="tab-pane fade {{ $i===0 ? 'show active' : '' }}"
            id="{{ $locale }}"
            role="tabpanel"
            aria-labelledby="tab-{{ $locale }}">

            <div id="translations-table-{{ $locale }}">
                @include('translation-scanner::partials.translations-table', [
                'translations' => $translations[$locale],
                'locale' => $locale,
                'perPage' => $perPage,
                'search' => $search
                ])

            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('#localeTabsContent');

        // 🟢 Toast setup
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        // === Re-bindable functions ===
        function bindUpdateEvents() {
            // update forms
            document.querySelectorAll('.update-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    let formData = new FormData(form);
                    fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Toast.fire({
                                    icon: 'success',
                                    title: data.success
                                });
                            } else if (data.error) {
                                Toast.fire({
                                    icon: 'error',
                                    title: data.error
                                });
                            }
                        })
                        .catch(() => Toast.fire({
                            icon: 'error',
                            title: 'Update failed ❌'
                        }));
                });
            });
        }

        function bindTabEvents() {
            // Restore from hash on load
            const hash = window.location.hash;
            if (hash) {
                const targetLink = document.querySelector('#localeTabs a[href="' + hash + '"]');
                if (targetLink) {
                    new bootstrap.Tab(targetLink).show();
                }
            }

            // Save hash when tab changes
            document.querySelectorAll('#localeTabs a[data-bs-toggle="tab"]').forEach(link => {
                link.addEventListener('shown.bs.tab', function(e) {
                    const target = e.target.getAttribute('href'); // "#en"
                    history.replaceState(null, '', target);
                });
            });
        }



        // Initial bind
        bindUpdateEvents();
        bindTabEvents();

        // === Reload container after AJAX ===
        function reloadContainer(url, form = null, toast = true) {
            const activeTab = document.querySelector('.nav-link.active')?.getAttribute('href').replace('#', '');

            const params = new URLSearchParams(new FormData(form || document.querySelector('#searchForm')));
            if (activeTab) {
                params.set('locale', activeTab); // <-- send active tab as query param
            }

            fetch(url + (url.includes('?') ? '&' : '?') + params.toString(), {
                    method: form ? 'POST' : 'GET',
                    body: form ? new FormData(form) : null,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (toast) {
                            Toast.fire({
                                icon: 'success',
                                title: data.success
                            });
                        }
                    }
                    if (data.html) {
                        document.querySelector('#localeTabsContent').outerHTML = data.html;

                        bindUpdateEvents();
                        bindTabEvents();

                        // restore active tab
                        const activeTab = data.tab || document.querySelector('.nav-link.active')?.getAttribute('href').replace('#', '');
                        if (activeTab) {
                            const targetLink = document.querySelector('#localeTabs a[href="#' + activeTab + '"]');
                            if (targetLink) new bootstrap.Tab(targetLink).show();
                        }
                    }

                });
        }



        // 🟡 Live Search
        document.querySelector('#searchForm input[name="search"]').addEventListener('keyup', function() {
            let form = document.querySelector('#searchForm');
            reloadContainer("{{ route('translations.index') }}?" + new URLSearchParams(new FormData(form)), null, false);
        });

        // 🟢 scan
        document.querySelector('#scanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            reloadContainer(this.action, this);
        });

        // 🟢 scanAndTranslate
        document.querySelector('#scanAndTranslateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            reloadContainer(this.action, this);
        });

        document.querySelector('#deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            reloadContainer(this.action, this);
        });
    });

    document.querySelectorAll('#localeTabs a[data-bs-toggle="tab"]').forEach(link => {
        link.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('href').replace('#', '');
            history.replaceState(null, '', '#' + target);

            // update all forms
            document.querySelectorAll('.active-tab-input').forEach(input => {
                input.value = target;
            });
        });
    });
</script>

@endpush
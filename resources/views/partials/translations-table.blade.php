<div class="overflow-x-auto shadow rounded-lg border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-600 w-[10%]">File</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600 w-[35%]">Key</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Value</th>
                <th class="px-3 py-2 text-center font-semibold text-gray-600 w-[140px]">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @forelse($translations as $row)
                <tr class="hover:bg-gray-50">
                    <!-- File -->
                    <td class="px-3 py-2 text-gray-700 break-words">
                        {{ $row['file'] ?? '-' }}
                    </td>

                    <!-- Key -->
                    <td class="px-3 py-2 text-gray-700 break-words">
                        {{ $row['key'] ?? '-' }}
                    </td>

                    <!-- Update Form -->
                    <td class="px-3 py-2">
                        <form action="{{ route('translations.update') }}" method="post"
                              class="update-form flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <input type="hidden" name="key" value="{{ $row['key'] ?? '' }}">
                            <input type="hidden" name="file" value="{{ $row['file'] ?? '' }}">

                            <input type="text" name="value"
                                   value="{{ $row['value'] ?? '' }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-2 py-1">

                            <button type="submit"
                                    class="px-3 py-1.5 bg-green-500 text-white text-xs font-medium rounded-md shadow hover:bg-green-600 transition">
                                Update
                            </button>
                        </form>
                    </td>

                    <!-- Delete Form -->
                    <td class="px-3 py-2 text-center">
                        <form class="inline" id="deleteForm" action="{{ route('translations.delete') }}" method="POST">
                            @csrf
                            <!-- <input type="hidden" name="locale" value="{{ $locale }}"> -->
                            <input type="hidden" name="tab" class="active-tab-input" value="">
                            @foreach($locales as $locale)
                            <input type="hidden" name="locales[]" value="{{ $locale }}">
                            @endforeach
                            <input type="hidden" name="key" value="{{ $row['key'] }}">
                            <input type="hidden" name="file" value="{{ $row['file'] }}">
                            <button type="submit"
                                    class="px-3 py-1.5 bg-red-500 text-white text-xs font-medium rounded-md shadow hover:bg-red-600 transition">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-3 py-4 text-center text-gray-500 text-sm">
                        No translations found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="mt-4">
    {{ $translations->withQueryString()->links('pagination::tailwind') }}
</div>

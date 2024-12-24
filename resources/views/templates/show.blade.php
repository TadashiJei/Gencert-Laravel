<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $template->name }}
            </h2>
            @if($template->user_id === Auth::id())
                <div class="flex space-x-4">
                    <a href="{{ route('templates.edit', $template) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Edit Template
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Template Info -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Details</h3>
                            <dl class="space-y-2">
                                <div>
                                    <dt class="font-medium">Created by</dt>
                                    <dd>{{ $template->user->name }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Category</dt>
                                    <dd>{{ $template->category ?? 'Uncategorized' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Visibility</dt>
                                    <dd>{{ $template->is_public ? 'Public' : 'Private' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Description</dt>
                                    <dd>{{ $template->description ?? 'No description provided.' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Variables</h3>
                            @if($template->latestVersion() && !empty($template->latestVersion()->variables))
                                <div class="space-y-2">
                                    @foreach($template->latestVersion()->variables as $variable)
                                        <div class="flex items-center space-x-2">
                                            <span class="font-medium">{{ $variable['key'] }}:</span>
                                            <span>{{ $variable['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500">No variables defined</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Preview</h3>
                        <div class="flex items-center space-x-4">
                            <select id="version-select" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($template->versions as $version)
                                    <option value="{{ $version->id }}" {{ $loop->first ? 'selected' : '' }}>
                                        Version {{ $version->version }} - {{ $version->created_at->format('Y-m-d H:i') }}
                                    </option>
                                @endforeach
                            </select>
                            <button id="preview-btn" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Generate Preview
                            </button>
                        </div>
                    </div>

                    <div id="preview-container" class="border rounded-lg p-4 min-h-[400px]">
                        <div id="preview-content"></div>
                    </div>
                </div>
            </div>

            <!-- Version History -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Version History</h3>
                    <div class="space-y-4">
                        @foreach($template->versions as $version)
                            <div class="border-b pb-4 last:border-b-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium">Version {{ $version->version }}</h4>
                                        <p class="text-sm text-gray-500">
                                            Created by {{ $version->creator->name }} on {{ $version->created_at->format('Y-m-d H:i') }}
                                        </p>
                                    </div>
                                </div>
                                @if($version->change_notes)
                                    <p class="mt-2 text-gray-600">{{ $version->change_notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('preview-btn');
            const versionSelect = document.getElementById('version-select');
            const previewContent = document.getElementById('preview-content');
            const versions = @json($template->versions);

            function updatePreview() {
                const versionId = versionSelect.value;
                const version = versions.find(v => v.id == versionId);
                if (version) {
                    // Get variables from the version
                    const variables = version.variables || [];
                    let content = version.content;

                    // Replace variables in content
                    variables.forEach(variable => {
                        const regex = new RegExp(`\\{\\{\\s*${variable.key}\\s*\\}\\}`, 'g');
                        content = content.replace(regex, variable.value);
                    });

                    previewContent.innerHTML = content;
                }
            }

            previewBtn.addEventListener('click', updatePreview);
            versionSelect.addEventListener('change', updatePreview);

            // Show initial preview
            updatePreview();
        });
    </script>
    @endpush
</x-app-layout>

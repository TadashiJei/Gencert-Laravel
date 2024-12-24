<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Certificates') }}
            </h2>
            <div class="flex space-x-4">
                <a href="{{ route('certificates.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Generate Certificate
                </a>
                <button id="bulk-generate" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Bulk Generate
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($certificates->isEmpty())
                        <p class="text-center text-gray-500">No certificates found.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($certificates as $certificate)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $certificate->recipient_name }}</div>
                                                <div class="text-sm text-gray-500">{{ $certificate->recipient_email }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $certificate->template->name }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $certificate->status_badge }}">
                                                    {{ ucfirst($certificate->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $certificate->generated_at ? $certificate->generated_at->format('Y-m-d H:i') : 'Not generated' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <a href="{{ route('certificates.show', $certificate) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                @if($certificate->file_path)
                                                    <a href="{{ route('certificates.download', $certificate) }}" class="text-green-600 hover:text-green-900">Download</a>
                                                @endif
                                                <form action="{{ route('certificates.destroy', $certificate) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $certificates->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Generate Modal -->
    <div id="bulk-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Bulk Generate Certificates</h3>
                <form id="bulk-form" class="space-y-4">
                    <div>
                        <label for="template" class="block text-sm font-medium text-gray-700">Template</label>
                        <select id="template" name="template_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="format" class="block text-sm font-medium text-gray-700">Format</label>
                        <select id="format" name="format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="pdf">PDF</option>
                            <option value="png">PNG</option>
                            <option value="svg">SVG</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data File (CSV)</label>
                        <input type="file" id="data-file" accept=".csv" class="mt-1 block w-full" required>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="close-modal" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-500 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 rounded-md">
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bulkBtn = document.getElementById('bulk-generate');
            const modal = document.getElementById('bulk-modal');
            const closeBtn = document.getElementById('close-modal');
            const bulkForm = document.getElementById('bulk-form');
            const dataFile = document.getElementById('data-file');

            bulkBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

            closeBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });

            bulkForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('template_id', document.getElementById('template').value);
                formData.append('format', document.getElementById('format').value);
                formData.append('data_file', dataFile.files[0]);

                try {
                    const response = await fetch('{{ route("certificates.bulk") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message || 'Failed to generate certificates');
                    }
                } catch (error) {
                    alert('An error occurred while generating certificates');
                }
            });
        });
    </script>
    @endpush
</x-app-layout>

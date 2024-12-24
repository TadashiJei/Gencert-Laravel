<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Generate Certificate') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('certificates.store') }}" method="POST" class="space-y-6" id="certificate-form">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="template_id" class="block text-sm font-medium text-gray-700">Template</label>
                                <select id="template_id" name="template_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="">Select a template</option>
                                    @foreach($templates as $template)
                                        <option value="{{ $template->id }}" data-variables="{{ json_encode($template->variables) }}">{{ $template->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="format" class="block text-sm font-medium text-gray-700">Format</label>
                                <select id="format" name="format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="pdf">PDF</option>
                                    <option value="png">PNG</option>
                                    <option value="svg">SVG</option>
                                </select>
                            </div>

                            <div>
                                <label for="recipient_name" class="block text-sm font-medium text-gray-700">Recipient Name</label>
                                <input type="text" name="recipient_name" id="recipient_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>

                            <div>
                                <label for="recipient_email" class="block text-sm font-medium text-gray-700">Recipient Email</label>
                                <input type="email" name="recipient_email" id="recipient_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                        </div>

                        <div id="dynamic-fields" class="space-y-4">
                            <!-- Dynamic fields will be added here based on template variables -->
                        </div>

                        <div class="mt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Preview</h3>
                            <div id="preview-container" class="border rounded-lg p-4 min-h-[400px] bg-gray-50">
                                <p class="text-gray-500 text-center">Select a template to see preview</p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('certificates.index') }}" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-500 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Generate Certificate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const templateSelect = document.getElementById('template_id');
            const dynamicFields = document.getElementById('dynamic-fields');
            const previewContainer = document.getElementById('preview-container');
            const certificateForm = document.getElementById('certificate-form');

            function createDynamicFields(variables) {
                dynamicFields.innerHTML = '';
                
                variables.forEach(variable => {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label for="${variable}" class="block text-sm font-medium text-gray-700">${variable}</label>
                        <input type="text" name="data[${variable}]" id="${variable}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                               required>
                    `;
                    dynamicFields.appendChild(div);
                });
            }

            async function updatePreview() {
                const formData = new FormData(certificateForm);
                
                try {
                    const response = await fetch('{{ route("certificates.preview") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        previewContainer.innerHTML = `<iframe src="${result.previewUrl}" class="w-full h-[400px]"></iframe>`;
                    } else {
                        previewContainer.innerHTML = `<p class="text-red-500 text-center">${result.message || 'Failed to generate preview'}</p>`;
                    }
                } catch (error) {
                    previewContainer.innerHTML = '<p class="text-red-500 text-center">Error generating preview</p>';
                }
            }

            templateSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const variables = JSON.parse(selectedOption.dataset.variables);
                    createDynamicFields(variables);
                } else {
                    dynamicFields.innerHTML = '';
                    previewContainer.innerHTML = '<p class="text-gray-500 text-center">Select a template to see preview</p>';
                }
            });

            const debounce = (func, wait) => {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            };

            const debouncedPreview = debounce(updatePreview, 500);

            certificateForm.addEventListener('input', debouncedPreview);
        });
    </script>
    @endpush
</x-app-layout>

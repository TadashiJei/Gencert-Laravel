@props(['template' => null])

<div class="space-y-6">
    <div>
        <x-input-label for="name" :value="__('Template Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $template?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $template?->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    <div>
        <x-input-label for="category" :value="__('Category')" />
        <x-text-input id="category" name="category" type="text" class="mt-1 block w-full" :value="old('category', $template?->category)" />
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div>
        <label class="flex items-center">
            <input type="checkbox" name="is_public" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_public', $template?->is_public) ? 'checked' : '' }}>
            <span class="ml-2 text-sm text-gray-600">{{ __('Make this template public') }}</span>
        </label>
    </div>

    @if($template)
        <div>
            <x-input-label for="change_notes" :value="__('Change Notes')" />
            <textarea id="change_notes" name="change_notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('change_notes') }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('change_notes')" />
        </div>
    @endif

    <div>
        <x-input-label for="content" :value="__('Template Content')" />
        <div id="editor" class="mt-1 border rounded-md overflow-hidden"></div>
        <textarea id="content" name="content" class="hidden">{{ old('content', $template?->latestVersion()?->content) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('content')" />
    </div>

    <div>
        <x-input-label for="variables" :value="__('Template Variables')" />
        <div id="variables-container" class="space-y-2">
            <div class="flex space-x-2">
                <x-text-input type="text" class="variable-key" placeholder="Variable Name" />
                <x-text-input type="text" class="variable-value" placeholder="Default Value" />
                <button type="button" class="remove-variable px-2 py-1 text-red-600 hover:text-red-800">Remove</button>
            </div>
        </div>
        <button type="button" id="add-variable" class="mt-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
            Add Variable
        </button>
        <input type="hidden" name="variables" id="variables-input" :value="old('variables', $template?->latestVersion()?->variables ? json_encode($template?->latestVersion()?->variables) : '[]')" />
    </div>
</div>

@push('scripts')
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            }
        });

        // Set initial content
        var content = document.querySelector('#content').value;
        if (content) {
            quill.root.innerHTML = content;
        }

        // Update hidden input before form submission
        document.querySelector('form').addEventListener('submit', function() {
            document.querySelector('#content').value = quill.root.innerHTML;
            updateVariablesInput();
        });

        // Variables handling
        const variablesContainer = document.querySelector('#variables-container');
        const addVariableBtn = document.querySelector('#add-variable');
        const variablesInput = document.querySelector('#variables-input');

        // Load existing variables
        let variables = JSON.parse(variablesInput.value || '[]');
        variables.forEach(variable => addVariableFields(variable.key, variable.value));

        addVariableBtn.addEventListener('click', () => addVariableFields());

        function addVariableFields(key = '', value = '') {
            const div = document.createElement('div');
            div.className = 'flex space-x-2';
            div.innerHTML = `
                <x-text-input type="text" class="variable-key" placeholder="Variable Name" value="${key}" />
                <x-text-input type="text" class="variable-value" placeholder="Default Value" value="${value}" />
                <button type="button" class="remove-variable px-2 py-1 text-red-600 hover:text-red-800">Remove</button>
            `;
            variablesContainer.appendChild(div);

            div.querySelector('.remove-variable').addEventListener('click', () => div.remove());
        }

        function updateVariablesInput() {
            const variables = [];
            variablesContainer.querySelectorAll('.flex').forEach(row => {
                const key = row.querySelector('.variable-key').value;
                const value = row.querySelector('.variable-value').value;
                if (key && value) {
                    variables.push({ key, value });
                }
            });
            variablesInput.value = JSON.stringify(variables);
        }
    });
</script>
@endpush

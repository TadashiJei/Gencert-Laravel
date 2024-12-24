<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Template') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('templates.update', $template) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('templates.form', ['template' => $template])

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Update Template') }}</x-primary-button>
                            <a href="{{ route('templates.show', $template) }}" class="text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

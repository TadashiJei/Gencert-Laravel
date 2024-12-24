<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Certificate Templates') }}
            </h2>
            <a href="{{ route('templates.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Create Template
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($templates->isEmpty())
                        <p class="text-center text-gray-500">No templates found.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($templates as $template)
                                <div class="border rounded-lg p-4 hover:shadow-lg transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <h3 class="text-lg font-semibold">
                                            <a href="{{ route('templates.show', $template) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $template->name }}
                                            </a>
                                        </h3>
                                        <span class="text-sm {{ $template->is_public ? 'text-green-600' : 'text-gray-600' }}">
                                            {{ $template->is_public ? 'Public' : 'Private' }}
                                        </span>
                                    </div>
                                    <p class="text-gray-600 mt-2">{{ Str::limit($template->description, 100) }}</p>
                                    <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                                        <span>By {{ $template->user->name }}</span>
                                        <span>Version {{ $template->latestVersion()->version ?? '1.0.0' }}</span>
                                    </div>
                                    @if($template->user_id === Auth::id())
                                        <div class="mt-4 flex space-x-2">
                                            <a href="{{ route('templates.edit', $template) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                                            <form action="{{ route('templates.destroy', $template) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            {{ $templates->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

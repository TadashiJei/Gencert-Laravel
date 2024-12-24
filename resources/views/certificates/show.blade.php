<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('View Certificate') }}
            </h2>
            <div class="flex space-x-4">
                @if($certificate->file_path)
                    <a href="{{ route('certificates.download', $certificate) }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Download
                    </a>
                @endif
                <form action="{{ route('certificates.destroy', $certificate) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" onclick="return confirm('Are you sure?')">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Certificate Details</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Template</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $certificate->template->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Recipient Name</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $certificate->recipient_name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Recipient Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $certificate->recipient_email }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="mt-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $certificate->status_badge }}">
                                            {{ ucfirst($certificate->status) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Format</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ strtoupper($certificate->format) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Generated At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $certificate->generated_at ? $certificate->generated_at->format('Y-m-d H:i:s') : 'Not generated' }}</dd>
                                </div>
                                @if($certificate->sent_at)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Sent At</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $certificate->sent_at->format('Y-m-d H:i:s') }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Certificate Data</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <pre class="text-sm text-gray-900 whitespace-pre-wrap">{{ json_encode($certificate->data, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Preview</h3>
                        <div class="border rounded-lg overflow-hidden">
                            @if($certificate->format === 'pdf')
                                <iframe src="{{ route('certificates.preview', $certificate) }}" class="w-full h-[600px]"></iframe>
                            @else
                                <img src="{{ route('certificates.preview', $certificate) }}" alt="Certificate Preview" class="max-w-full h-auto">
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

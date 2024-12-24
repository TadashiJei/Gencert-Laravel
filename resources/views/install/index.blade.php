<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CertificateHub Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-4xl w-full mx-4">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">CertificateHub Installation</h1>
                    <p class="text-gray-600">Complete the following steps to install CertificateHub</p>
                </div>

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                            1
                        </div>
                        <h2 class="ml-3 text-xl font-semibold">System Requirements Check</h2>
                    </div>
                    <div class="ml-11">
                        <ul class="space-y-2">
                            @foreach($requirements as $requirement)
                            <li class="flex items-center">
                                @if($requirement['met'])
                                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                                <span class="ml-2">{{ $requirement['name'] }}: {{ $requirement['current'] }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <form action="{{ route('install.setup') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div class="mb-8">
                        <div class="flex items-center mb-4">
                            <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                                2
                            </div>
                            <h2 class="ml-3 text-xl font-semibold">Database Configuration</h2>
                        </div>
                        <div class="ml-11 grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Host</label>
                                <input type="text" name="db_host" value="{{ old('db_host', 'localhost') }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Name</label>
                                <input type="text" name="db_name" value="{{ old('db_name') }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Username</label>
                                <input type="text" name="db_user" value="{{ old('db_user') }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Database Password</label>
                                <input type="password" name="db_password" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center mb-4">
                            <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                                3
                            </div>
                            <h2 class="ml-3 text-xl font-semibold">Admin Account</h2>
                        </div>
                        <div class="ml-11 grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Admin Email</label>
                                <input type="email" name="admin_email" value="{{ old('admin_email') }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Admin Password</label>
                                <input type="password" name="admin_password" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                                <input type="password" name="admin_password_confirmation" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center mb-4">
                            <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                                4
                            </div>
                            <h2 class="ml-3 text-xl font-semibold">System Settings</h2>
                        </div>
                        <div class="ml-11 grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Application Name</label>
                                <input type="text" name="app_name" value="{{ old('app_name', 'CertificateHub') }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Application URL</label>
                                <input type="url" name="app_url" value="{{ old('app_url', request()->root()) }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" 
                                class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Install CertificateHub
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

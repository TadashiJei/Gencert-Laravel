<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Analytics Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Total Certificates</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $totalCertificates }}</div>
                        <div class="mt-2 text-sm text-gray-600">
                            <span class="{{ $certificateGrowth >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $certificateGrowth }}% from last month
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Certificates This Month</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $certificatesThisMonth }}</div>
                        <div class="mt-2 text-sm text-gray-600">
                            Generated in {{ now()->format('F') }}
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Active Templates</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $activeTemplates }}</div>
                        <div class="mt-2 text-sm text-gray-600">
                            Used in the last 30 days
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Delivery Rate</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $deliveryRate }}%</div>
                        <div class="mt-2 text-sm text-gray-600">
                            Successfully delivered
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Generation Trends -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Generation Trends</h3>
                        <div class="h-64">
                            <canvas id="generationTrends"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Template Usage -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Template Usage</h3>
                        <div class="h-64">
                            <canvas id="templateUsage"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg col-span-1 md:col-span-2">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipients</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentActivity as $activity)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $activity->created_at->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $activity->template->name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $activity->recipient_count }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $activity->status_badge }}">
                                                    {{ ucfirst($activity->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generation Trends Chart
            const trendsCtx = document.getElementById('generationTrends').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: @json($trends->pluck('date')),
                    datasets: [{
                        label: 'Certificates Generated',
                        data: @json($trends->pluck('count')),
                        borderColor: '#3b82f6',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                }
            });

            // Template Usage Chart
            const usageCtx = document.getElementById('templateUsage').getContext('2d');
            new Chart(usageCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($templateUsage->pluck('name')),
                    datasets: [{
                        data: @json($templateUsage->pluck('count')),
                        backgroundColor: [
                            '#3b82f6',
                            '#10b981',
                            '#f59e0b',
                            '#ef4444',
                            '#8b5cf6'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                }
            });
        });
    </script>
    @endpush
</x-app-layout>

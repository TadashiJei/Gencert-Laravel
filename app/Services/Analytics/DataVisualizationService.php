<?php

namespace App\Services\Analytics;

use App\Models\Certificate;
use App\Models\UserActivity;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataVisualizationService
{
    /**
     * Generate certificate trends chart data
     */
    public function getCertificateTrendsChart(Carbon $startDate, Carbon $endDate)
    {
        $data = Certificate::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active'),
            DB::raw('SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired'),
            DB::raw('SUM(CASE WHEN status = "revoked" THEN 1 ELSE 0 END) as revoked')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'type' => 'line',
            'data' => [
                'labels' => $data->pluck('date')->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                }),
                'datasets' => [
                    [
                        'label' => 'Total',
                        'data' => $data->pluck('total'),
                        'borderColor' => '#3498db',
                        'fill' => false
                    ],
                    [
                        'label' => 'Active',
                        'data' => $data->pluck('active'),
                        'borderColor' => '#2ecc71',
                        'fill' => false
                    ],
                    [
                        'label' => 'Expired',
                        'data' => $data->pluck('expired'),
                        'borderColor' => '#f1c40f',
                        'fill' => false
                    ],
                    [
                        'label' => 'Revoked',
                        'data' => $data->pluck('revoked'),
                        'borderColor' => '#e74c3c',
                        'fill' => false
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate user activity heatmap data
     */
    public function getUserActivityHeatmap(Carbon $startDate, Carbon $endDate)
    {
        $activities = UserActivity::select(
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('DAYOFWEEK(created_at) as day'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('hour', 'day')
            ->get();

        $heatmapData = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($activities as $activity) {
            $heatmapData[$activity->day - 1][$activity->hour] = $activity->count;
        }

        return [
            'type' => 'heatmap',
            'data' => [
                'labels' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                'datasets' => array_map(function ($hourData) {
                    return [
                        'data' => $hourData
                    ];
                }, $heatmapData)
            ],
            'options' => [
                'scales' => [
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Hour of Day'
                        ]
                    ],
                    'y' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Day of Week'
                        ]
                    ]
                ],
                'plugins' => [
                    'colorscheme' => [
                        'scheme' => 'blues'
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate certificate distribution pie chart
     */
    public function getCertificateDistributionChart()
    {
        $data = Certificate::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return [
            'type' => 'pie',
            'data' => [
                'labels' => $data->pluck('status'),
                'datasets' => [
                    [
                        'data' => $data->pluck('count'),
                        'backgroundColor' => [
                            '#2ecc71', // Active
                            '#f1c40f', // Expired
                            '#e74c3c', // Revoked
                            '#95a5a6'  // Other
                        ]
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'position' => 'right'
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate user activity bar chart
     */
    public function getUserActivityChart(Carbon $startDate, Carbon $endDate)
    {
        $data = UserActivity::select(
            'activity_type',
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('activity_type')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $data->pluck('activity_type'),
                'datasets' => [
                    [
                        'label' => 'Activity Count',
                        'data' => $data->pluck('count'),
                        'backgroundColor' => '#3498db'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate template usage radar chart
     */
    public function getTemplateUsageChart()
    {
        $data = Certificate::select(
            'template_id',
            DB::raw('COUNT(*) as count')
        )
            ->with('template:id,name')
            ->groupBy('template_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        return [
            'type' => 'radar',
            'data' => [
                'labels' => $data->pluck('template.name'),
                'datasets' => [
                    [
                        'label' => 'Usage Count',
                        'data' => $data->pluck('count'),
                        'backgroundColor' => 'rgba(52, 152, 219, 0.2)',
                        'borderColor' => '#3498db',
                        'pointBackgroundColor' => '#3498db'
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'r' => [
                        'beginAtZero' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate expiry forecast line chart
     */
    public function getExpiryForecastChart()
    {
        $months = collect(range(0, 11))->map(function ($month) {
            $date = now()->addMonths($month);
            $count = Certificate::where('status', 'active')
                ->whereMonth('expires_at', $date->month)
                ->whereYear('expires_at', $date->year)
                ->count();

            return [
                'date' => $date->format('Y-m'),
                'count' => $count
            ];
        });

        return [
            'type' => 'line',
            'data' => [
                'labels' => $months->pluck('date'),
                'datasets' => [
                    [
                        'label' => 'Certificates Expiring',
                        'data' => $months->pluck('count'),
                        'borderColor' => '#e74c3c',
                        'backgroundColor' => 'rgba(231, 76, 60, 0.1)',
                        'fill' => true
                    ]
                ]
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate dashboard summary cards
     */
    public function getDashboardSummaryCards()
    {
        return [
            [
                'title' => 'Total Certificates',
                'value' => Certificate::count(),
                'trend' => $this->calculateTrend('certificates'),
                'icon' => 'certificate',
                'color' => 'primary'
            ],
            [
                'title' => 'Active Certificates',
                'value' => Certificate::where('status', 'active')->count(),
                'trend' => $this->calculateTrend('active_certificates'),
                'icon' => 'check-circle',
                'color' => 'success'
            ],
            [
                'title' => 'Expiring Soon',
                'value' => Certificate::where('status', 'active')
                    ->whereDate('expires_at', '<=', now()->addDays(30))
                    ->count(),
                'trend' => $this->calculateTrend('expiring_certificates'),
                'icon' => 'clock',
                'color' => 'warning'
            ],
            [
                'title' => 'User Activities',
                'value' => UserActivity::whereDate('created_at', today())->count(),
                'trend' => $this->calculateTrend('user_activities'),
                'icon' => 'activity',
                'color' => 'info'
            ]
        ];
    }

    /**
     * Calculate trend percentage
     */
    protected function calculateTrend(string $metric)
    {
        $now = now();
        $previousPeriod = [
            $now->copy()->subDays(14),
            $now->copy()->subDays(7)
        ];
        $currentPeriod = [
            $now->copy()->subDays(7),
            $now
        ];

        switch ($metric) {
            case 'certificates':
                $previous = Certificate::whereBetween('created_at', $previousPeriod)->count();
                $current = Certificate::whereBetween('created_at', $currentPeriod)->count();
                break;
            case 'active_certificates':
                $previous = Certificate::where('status', 'active')
                    ->whereBetween('created_at', $previousPeriod)
                    ->count();
                $current = Certificate::where('status', 'active')
                    ->whereBetween('created_at', $currentPeriod)
                    ->count();
                break;
            case 'expiring_certificates':
                $previous = Certificate::where('status', 'active')
                    ->whereBetween('expires_at', $previousPeriod)
                    ->count();
                $current = Certificate::where('status', 'active')
                    ->whereBetween('expires_at', $currentPeriod)
                    ->count();
                break;
            case 'user_activities':
                $previous = UserActivity::whereBetween('created_at', $previousPeriod)->count();
                $current = UserActivity::whereBetween('created_at', $currentPeriod)->count();
                break;
            default:
                return 0;
        }

        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

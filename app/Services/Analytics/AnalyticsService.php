<?php

namespace App\Services\Analytics;

use App\Models\Certificate;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Get dashboard analytics data
     */
    public function getDashboardData(array $filters = [])
    {
        $cacheKey = 'dashboard_analytics_' . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($filters) {
            $startDate = $filters['start_date'] ?? now()->subMonth();
            $endDate = $filters['end_date'] ?? now();

            return [
                'certificates' => $this->getCertificateMetrics($startDate, $endDate),
                'users' => $this->getUserMetrics($startDate, $endDate),
                'activities' => $this->getActivityMetrics($startDate, $endDate),
                'trends' => $this->getTrendAnalytics($startDate, $endDate)
            ];
        });
    }

    /**
     * Get certificate metrics
     */
    protected function getCertificateMetrics($startDate, $endDate)
    {
        return [
            'total' => Certificate::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active' => Certificate::where('status', 'active')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'expired' => Certificate::where('status', 'expired')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'revoked' => Certificate::where('status', 'revoked')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'by_template' => Certificate::select('template_id', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('template_id')
                ->with('template:id,name')
                ->get(),
            'expiring_soon' => Certificate::where('status', 'active')
                ->whereDate('expires_at', '<=', now()->addDays(30))
                ->count()
        ];
    }

    /**
     * Get user metrics
     */
    protected function getUserMetrics($startDate, $endDate)
    {
        return [
            'total_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_users' => UserActivity::distinct('user_id')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'user_roles' => User::select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->get(),
            'top_users' => Certificate::select('created_by', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('created_by')
                ->with('creator:id,name')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
        ];
    }

    /**
     * Get activity metrics
     */
    protected function getActivityMetrics($startDate, $endDate)
    {
        return [
            'total_activities' => UserActivity::whereBetween('created_at', [$startDate, $endDate])->count(),
            'by_type' => UserActivity::select('activity_type', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('activity_type')
                ->get(),
            'by_user' => UserActivity::select('user_id', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('user_id')
                ->with('user:id,name')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get(),
            'recent' => UserActivity::with('user:id,name')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->latest()
                ->limit(10)
                ->get()
        ];
    }

    /**
     * Get trend analytics
     */
    protected function getTrendAnalytics($startDate, $endDate)
    {
        $days = $startDate->diffInDays($endDate);
        
        return [
            'certificates_trend' => Certificate::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->get(),
            'user_activity_trend' => UserActivity::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->get()
        ];
    }

    /**
     * Generate custom report
     */
    public function generateReport(array $config)
    {
        $report = new Report();
        $report->name = $config['name'];
        $report->type = $config['type'];
        $report->config = $config;
        $report->save();

        $data = [];
        
        foreach ($config['metrics'] as $metric) {
            switch ($metric) {
                case 'certificates':
                    $data['certificates'] = $this->getCertificateMetrics(
                        Carbon::parse($config['start_date']),
                        Carbon::parse($config['end_date'])
                    );
                    break;
                case 'users':
                    $data['users'] = $this->getUserMetrics(
                        Carbon::parse($config['start_date']),
                        Carbon::parse($config['end_date'])
                    );
                    break;
                case 'activities':
                    $data['activities'] = $this->getActivityMetrics(
                        Carbon::parse($config['start_date']),
                        Carbon::parse($config['end_date'])
                    );
                    break;
                case 'trends':
                    $data['trends'] = $this->getTrendAnalytics(
                        Carbon::parse($config['start_date']),
                        Carbon::parse($config['end_date'])
                    );
                    break;
            }
        }

        $report->data = $data;
        $report->save();

        return $report;
    }

    /**
     * Export report in specified format
     */
    public function exportReport(Report $report, string $format)
    {
        $exporter = app(ReportExportService::class);
        
        switch ($format) {
            case 'pdf':
                return $exporter->toPdf($report);
            case 'excel':
                return $exporter->toExcel($report);
            case 'csv':
                return $exporter->toCsv($report);
            case 'json':
                return $exporter->toJson($report);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Schedule report generation
     */
    public function scheduleReport(Report $report, array $schedule)
    {
        $report->schedule = $schedule;
        $report->next_run = $this->calculateNextRun($schedule);
        $report->save();

        return $report;
    }

    /**
     * Calculate next run time based on schedule
     */
    protected function calculateNextRun(array $schedule)
    {
        $frequency = $schedule['frequency'];
        $now = now();

        switch ($frequency) {
            case 'daily':
                return $now->addDay()->setTime(
                    $schedule['hour'] ?? 0,
                    $schedule['minute'] ?? 0
                );
            case 'weekly':
                return $now->next($schedule['day'] ?? 1)->setTime(
                    $schedule['hour'] ?? 0,
                    $schedule['minute'] ?? 0
                );
            case 'monthly':
                return $now->addMonth()->setDay($schedule['day'] ?? 1)->setTime(
                    $schedule['hour'] ?? 0,
                    $schedule['minute'] ?? 0
                );
            default:
                throw new \InvalidArgumentException("Unsupported frequency: {$frequency}");
        }
    }

    /**
     * Track user activity
     */
    public function trackActivity(User $user, string $activityType, array $metadata = [])
    {
        return UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => $activityType,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Get real-time statistics
     */
    public function getRealTimeStats()
    {
        return Cache::remember('realtime_stats', now()->addMinutes(1), function () {
            return [
                'active_users' => UserActivity::where('created_at', '>=', now()->subMinutes(5))
                    ->distinct('user_id')
                    ->count(),
                'certificates_issued' => Certificate::where('created_at', '>=', now()->subHour())
                    ->count(),
                'recent_activities' => UserActivity::with('user:id,name')
                    ->latest()
                    ->limit(5)
                    ->get()
            ];
        });
    }
}

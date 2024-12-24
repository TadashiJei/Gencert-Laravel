<?php

namespace App\Services\Analytics;

use App\Models\Certificate;
use App\Models\UserActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataAggregationService
{
    /**
     * Aggregate certificate statistics
     */
    public function aggregateCertificateStats(array $filters = [])
    {
        $query = Certificate::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        return [
            'total' => $query->count(),
            'by_status' => $query->clone()
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_template' => $query->clone()
                ->select('template_id', DB::raw('count(*) as count'))
                ->groupBy('template_id')
                ->with('template:id,name')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->template->name => $item->count];
                }),
            'expiring_soon' => $query->clone()
                ->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
            'recently_issued' => $query->clone()
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'recently_expired' => $query->clone()
                ->where('status', 'expired')
                ->where('expires_at', '>=', now()->subDays(7))
                ->count()
        ];
    }

    /**
     * Aggregate user activity statistics
     */
    public function aggregateUserActivityStats(array $filters = [])
    {
        $query = UserActivity::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return [
            'total_activities' => $query->count(),
            'by_type' => $query->clone()
                ->select('activity_type', DB::raw('count(*) as count'))
                ->groupBy('activity_type')
                ->pluck('count', 'activity_type'),
            'by_user' => $query->clone()
                ->select('user_id', DB::raw('count(*) as count'))
                ->groupBy('user_id')
                ->with('user:id,name')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->user->name => $item->count];
                }),
            'hourly_distribution' => $query->clone()
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
                ->groupBy('hour')
                ->pluck('count', 'hour'),
            'daily_trend' => $query->clone()
                ->where('created_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->pluck('count', 'date')
        ];
    }

    /**
     * Aggregate user engagement metrics
     */
    public function aggregateUserEngagementMetrics(array $filters = [])
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        return [
            'active_users' => UserActivity::whereBetween('created_at', [$startDate, $endDate])
                ->distinct('user_id')
                ->count(),
            'new_users' => User::whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'engagement_rate' => $this->calculateEngagementRate($startDate, $endDate),
            'user_retention' => $this->calculateUserRetention($startDate, $endDate),
            'activity_frequency' => $this->calculateActivityFrequency($startDate, $endDate),
            'user_segments' => $this->getUserSegments($startDate, $endDate)
        ];
    }

    /**
     * Calculate user engagement rate
     */
    protected function calculateEngagementRate(Carbon $startDate, Carbon $endDate)
    {
        $totalUsers = User::where('created_at', '<=', $endDate)->count();
        if ($totalUsers === 0) {
            return 0;
        }

        $activeUsers = UserActivity::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count();

        return round(($activeUsers / $totalUsers) * 100, 2);
    }

    /**
     * Calculate user retention
     */
    protected function calculateUserRetention(Carbon $startDate, Carbon $endDate)
    {
        $cohorts = collect();
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $cohortStart = $currentDate->copy()->startOfWeek();
            $cohortEnd = $currentDate->copy()->endOfWeek();

            $newUsers = User::whereBetween('created_at', [$cohortStart, $cohortEnd])
                ->pluck('id');

            if ($newUsers->isNotEmpty()) {
                $retentionData = collect();
                $weekNumber = 0;

                $checkDate = $cohortStart->copy();
                while ($checkDate <= $endDate) {
                    $weekUsers = UserActivity::whereBetween('created_at', [
                        $checkDate->copy()->startOfWeek(),
                        $checkDate->copy()->endOfWeek()
                    ])
                        ->whereIn('user_id', $newUsers)
                        ->distinct('user_id')
                        ->count();

                    $retentionData->put(
                        "Week {$weekNumber}",
                        round(($weekUsers / $newUsers->count()) * 100, 2)
                    );

                    $checkDate->addWeek();
                    $weekNumber++;
                }

                $cohorts->put($cohortStart->format('Y-m-d'), $retentionData);
            }

            $currentDate->addWeek();
        }

        return $cohorts;
    }

    /**
     * Calculate activity frequency
     */
    protected function calculateActivityFrequency(Carbon $startDate, Carbon $endDate)
    {
        $users = User::whereHas('activities', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->get();

        $frequency = [
            'daily' => 0,
            'weekly' => 0,
            'monthly' => 0,
            'occasional' => 0
        ];

        foreach ($users as $user) {
            $activityDays = UserActivity::where('user_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(DB::raw('DATE(created_at) as date'))
                ->distinct()
                ->count();

            $totalDays = $startDate->diffInDays($endDate) + 1;
            $activityRate = $activityDays / $totalDays;

            if ($activityRate >= 0.7) {
                $frequency['daily']++;
            } elseif ($activityRate >= 0.3) {
                $frequency['weekly']++;
            } elseif ($activityRate >= 0.1) {
                $frequency['monthly']++;
            } else {
                $frequency['occasional']++;
            }
        }

        return $frequency;
    }

    /**
     * Get user segments
     */
    protected function getUserSegments(Carbon $startDate, Carbon $endDate)
    {
        $users = User::with(['activities' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }])->get();

        $segments = [
            'power_users' => 0,
            'regular_users' => 0,
            'occasional_users' => 0,
            'inactive_users' => 0
        ];

        foreach ($users as $user) {
            $activityCount = $user->activities->count();

            if ($activityCount >= 100) {
                $segments['power_users']++;
            } elseif ($activityCount >= 30) {
                $segments['regular_users']++;
            } elseif ($activityCount > 0) {
                $segments['occasional_users']++;
            } else {
                $segments['inactive_users']++;
            }
        }

        return $segments;
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(array $filters = [])
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        return [
            'average_processing_time' => $this->calculateAverageProcessingTime($startDate, $endDate),
            'error_rate' => $this->calculateErrorRate($startDate, $endDate),
            'peak_usage_times' => $this->getPeakUsageTimes($startDate, $endDate),
            'resource_utilization' => $this->getResourceUtilization($startDate, $endDate)
        ];
    }

    /**
     * Calculate average processing time
     */
    protected function calculateAverageProcessingTime(Carbon $startDate, Carbon $endDate)
    {
        return UserActivity::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('metadata->processing_time')
            ->avg('metadata->processing_time');
    }

    /**
     * Calculate error rate
     */
    protected function calculateErrorRate(Carbon $startDate, Carbon $endDate)
    {
        $totalActivities = UserActivity::whereBetween('created_at', [$startDate, $endDate])
            ->count();

        if ($totalActivities === 0) {
            return 0;
        }

        $errorActivities = UserActivity::whereBetween('created_at', [$startDate, $endDate])
            ->where('metadata->error', true)
            ->count();

        return round(($errorActivities / $totalActivities) * 100, 2);
    }

    /**
     * Get peak usage times
     */
    protected function getPeakUsageTimes(Carbon $startDate, Carbon $endDate)
    {
        return UserActivity::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('DAYOFWEEK(created_at) as day'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour', 'day')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Get resource utilization
     */
    protected function getResourceUtilization(Carbon $startDate, Carbon $endDate)
    {
        return [
            'storage' => [
                'total' => Certificate::sum('metadata->file_size'),
                'by_template' => Certificate::select('template_id', DB::raw('SUM(metadata->file_size) as total_size'))
                    ->groupBy('template_id')
                    ->with('template:id,name')
                    ->get()
            ],
            'api_usage' => UserActivity::where('activity_type', 'api_access')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'metadata->endpoint as endpoint',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('endpoint')
                ->get()
        ];
    }
}

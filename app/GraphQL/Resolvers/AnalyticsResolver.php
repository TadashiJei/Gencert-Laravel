<?php

namespace App\GraphQL\Resolvers;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DataVisualizationService;
use App\Services\Analytics\DataAggregationService;
use App\Models\Report;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AnalyticsResolver
{
    protected $analyticsService;
    protected $visualizationService;
    protected $aggregationService;

    public function __construct(
        AnalyticsService $analyticsService,
        DataVisualizationService $visualizationService,
        DataAggregationService $aggregationService
    ) {
        $this->analyticsService = $analyticsService;
        $this->visualizationService = $visualizationService;
        $this->aggregationService = $aggregationService;
    }

    /**
     * Get dashboard metrics
     */
    public function dashboardMetrics($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $dateRange = $args['dateRange'] ?? [];
        return $this->analyticsService->getDashboardData($dateRange);
    }

    /**
     * Get certificate statistics
     */
    public function certificateStats($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $dateRange = $args['dateRange'] ?? [];
        return $this->aggregationService->aggregateCertificateStats($dateRange);
    }

    /**
     * Get user engagement metrics
     */
    public function userEngagementMetrics($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $dateRange = $args['dateRange'] ?? [];
        return $this->aggregationService->aggregateUserEngagementMetrics($dateRange);
    }

    /**
     * Get activity statistics
     */
    public function activityStats($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $dateRange = $args['dateRange'] ?? [];
        return $this->aggregationService->aggregateUserActivityStats($dateRange);
    }

    /**
     * Get all reports
     */
    public function reports($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return Report::with('creator')->get();
    }

    /**
     * Get single report
     */
    public function report($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return Report::with('creator')->find($args['id']);
    }

    /**
     * Generate new report
     */
    public function generateReport($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->analyticsService->generateReport($args['config']);
    }

    /**
     * Schedule report
     */
    public function scheduleReport($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $report = Report::findOrFail($args['id']);
        return $this->analyticsService->scheduleReport($report, $args['schedule']);
    }

    /**
     * Delete report
     */
    public function deleteReport($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $report = Report::findOrFail($args['id']);
        return $report->delete();
    }

    /**
     * Export report
     */
    public function exportReport($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $report = Report::findOrFail($args['id']);
        return $this->analyticsService->exportReport($report, $args['format']);
    }

    /**
     * Get certificate trends chart
     */
    public function certificateTrendsChart($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getCertificateTrendsChart(
            $args['dateRange']['startDate'],
            $args['dateRange']['endDate']
        );
    }

    /**
     * Get user activity heatmap
     */
    public function userActivityHeatmap($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getUserActivityHeatmap(
            $args['dateRange']['startDate'],
            $args['dateRange']['endDate']
        );
    }

    /**
     * Get certificate distribution chart
     */
    public function certificateDistributionChart($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getCertificateDistributionChart();
    }

    /**
     * Get user activity chart
     */
    public function userActivityChart($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getUserActivityChart(
            $args['dateRange']['startDate'],
            $args['dateRange']['endDate']
        );
    }

    /**
     * Get template usage chart
     */
    public function templateUsageChart($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getTemplateUsageChart();
    }

    /**
     * Get expiry forecast chart
     */
    public function expiryForecastChart($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->visualizationService->getExpiryForecastChart();
    }

    /**
     * Get real-time statistics
     */
    public function realTimeStats($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->analyticsService->getRealTimeStats();
    }

    /**
     * Get performance metrics
     */
    public function performanceMetrics($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $dateRange = $args['dateRange'] ?? [];
        return $this->aggregationService->getPerformanceMetrics($dateRange);
    }
}

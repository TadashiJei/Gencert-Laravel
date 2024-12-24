<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;
use App\Models\User;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\UserActivity;
use App\Models\Report;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DataVisualizationService;
use App\Services\Analytics\DataAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class AnalyticsSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $admin;
    protected $templates;
    protected $certificates;
    protected $analyticsService;
    protected $visualizationService;
    protected $aggregationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users first
        $this->admin = User::factory()->create([
            'role' => 'admin'
        ]);
        
        $this->user = User::factory()->create([
            'role' => 'user'
        ]);

        // Create certificate templates
        $this->templates = CertificateTemplate::factory()
            ->count(3)
            ->create([
                'created_by' => $this->admin->id
            ]);

        // Create certificates with different statuses
        $this->certificates = collect();
        foreach ($this->templates as $template) {
            // Active certificates
            $this->certificates = $this->certificates->merge(
                Certificate::factory()
                    ->count(5)
                    ->state(function (array $attributes) use ($template) {
                        return [
                            'template_id' => $template->id,
                            'user_id' => $this->user->id,
                            'created_by' => $this->admin->id,
                            'status' => 'active'
                        ];
                    })
                    ->create()
            );

            // Expired certificates
            $this->certificates = $this->certificates->merge(
                Certificate::factory()
                    ->expired()
                    ->count(3)
                    ->state(function (array $attributes) use ($template) {
                        return [
                            'template_id' => $template->id,
                            'user_id' => $this->user->id,
                            'created_by' => $this->admin->id
                        ];
                    })
                    ->create()
            );

            // Revoked certificates
            $this->certificates = $this->certificates->merge(
                Certificate::factory()
                    ->revoked()
                    ->count(2)
                    ->state(function (array $attributes) use ($template) {
                        return [
                            'template_id' => $template->id,
                            'user_id' => $this->user->id,
                            'created_by' => $this->admin->id
                        ];
                    })
                    ->create()
            );
        }

        // Create user activities
        foreach ($this->certificates as $certificate) {
            UserActivity::factory()->create([
                'user_id' => $this->user->id,
                'activity_type' => UserActivity::TYPE_CREATE_CERTIFICATE,
                'metadata' => [
                    'certificate_id' => $certificate->id,
                    'certificate_number' => $certificate->certificate_number
                ]
            ]);
        }

        // Add some verification activities
        foreach ($this->certificates->where('status', 'active') as $certificate) {
            UserActivity::factory()->count(3)->create([
                'user_id' => $this->user->id,
                'activity_type' => 'verify_certificate',
                'metadata' => [
                    'certificate_id' => $certificate->id,
                    'verification_method' => 'blockchain'
                ]
            ]);
        }

        // Initialize services
        $this->analyticsService = app(AnalyticsService::class);
        $this->visualizationService = app(DataVisualizationService::class);
        $this->aggregationService = app(DataAggregationService::class);
    }

    /** @test */
    public function test_dashboard_metrics_accuracy()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query {
                dashboardMetrics {
                    certificates {
                        total
                        active
                        expired
                        revoked
                        expiringSoon
                    }
                    users {
                        totalUsers
                        activeUsers
                    }
                    activities {
                        totalActivities
                    }
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'dashboardMetrics' => [
                    'certificates' => [
                        'total' => $this->certificates->count(),
                        'active' => $this->certificates->where('status', 'active')->count(),
                        'expired' => $this->certificates->where('status', 'expired')->count(),
                        'revoked' => $this->certificates->where('status', 'revoked')->count()
                    ],
                    'users' => [
                        'totalUsers' => 2,
                        'activeUsers' => 2
                    ]
                ]
            ]
        ]);

        // Verify the numbers match our setup
        $metrics = $response->json('data.dashboardMetrics');
        $this->assertEquals(30, $metrics['certificates']['total']); // 10 certs per template * 3 templates
        $this->assertEquals(15, $metrics['certificates']['active']); // 5 active per template * 3
        $this->assertEquals(9, $metrics['certificates']['expired']); // 3 expired per template * 3
        $this->assertEquals(6, $metrics['certificates']['revoked']); // 2 revoked per template * 3
    }

    /** @test */
    public function test_certificate_trends_visualization()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query($dateRange: DateRangeInput!) {
                certificateTrendsChart(dateRange: $dateRange) {
                    type
                    data
                    options
                }
            }
        ', [
            'dateRange' => [
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $chartData = $response->json('data.certificateTrendsChart');
        
        $this->assertEquals('line', $chartData['type']);
        $this->assertArrayHasKey('datasets', $chartData['data']);
        $this->assertCount(4, $chartData['data']['datasets']); // Total, Active, Expired, Revoked
    }

    /** @test */
    public function test_user_activity_heatmap()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query($dateRange: DateRangeInput!) {
                userActivityHeatmap(dateRange: $dateRange) {
                    type
                    data
                    options
                }
            }
        ', [
            'dateRange' => [
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $heatmapData = $response->json('data.userActivityHeatmap');
        
        $this->assertEquals('heatmap', $heatmapData['type']);
        $this->assertArrayHasKey('labels', $heatmapData['data']);
        $this->assertCount(7, $heatmapData['data']['labels']); // Days of week
    }

    /** @test */
    public function test_report_generation_and_export()
    {
        // Generate report
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            mutation($config: ReportConfigInput!) {
                generateReport(config: $config) {
                    id
                    name
                    type
                    data
                }
            }
        ', [
            'config' => [
                'name' => 'Monthly Certificate Analysis',
                'type' => 'certificate_metrics',
                'metrics' => ['certificates', 'users', 'activities'],
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $reportId = $response->json('data.generateReport.id');
        $this->assertNotNull($reportId);

        // Export report
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            mutation($id: ID!, $format: String!) {
                exportReport(id: $id, format: $format)
            }
        ', [
            'id' => $reportId,
            'format' => 'pdf'
        ]);

        $exportPath = $response->json('data.exportReport');
        $this->assertNotNull($exportPath);
        $this->assertStringEndsWith('.pdf', $exportPath);
    }

    /** @test */
    public function test_real_time_statistics()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query {
                realTimeStats {
                    activeUsers
                    certificatesIssued
                    recentActivities {
                        id
                        type
                        user {
                            name
                        }
                    }
                }
            }
        ');

        $stats = $response->json('data.realTimeStats');
        
        $this->assertArrayHasKey('activeUsers', $stats);
        $this->assertArrayHasKey('certificatesIssued', $stats);
        $this->assertArrayHasKey('recentActivities', $stats);
        $this->assertGreaterThan(0, count($stats['recentActivities']));
    }

    /** @test */
    public function test_performance_metrics()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query($dateRange: DateRangeInput!) {
                performanceMetrics(dateRange: $dateRange) {
                    averageProcessingTime
                    errorRate
                    peakUsageTimes {
                        hour
                        count
                    }
                    resourceUtilization {
                        storage {
                            total
                            byTemplate {
                                template {
                                    name
                                }
                                totalSize
                            }
                        }
                    }
                }
            }
        ', [
            'dateRange' => [
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $metrics = $response->json('data.performanceMetrics');
        
        $this->assertArrayHasKey('averageProcessingTime', $metrics);
        $this->assertArrayHasKey('errorRate', $metrics);
        $this->assertArrayHasKey('peakUsageTimes', $metrics);
        $this->assertArrayHasKey('resourceUtilization', $metrics);
    }

    /** @test */
    public function test_user_engagement_metrics()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query($userId: ID!, $period: PeriodInput!) {
                userEngagement(userId: $userId, period: $period) {
                    overview {
                        engagementScore
                        activityLevel
                        trendsDirection
                    }
                    activities {
                        daily {
                            date
                            count
                        }
                        peakHours {
                            hour
                            activity
                        }
                    }
                    certificates {
                        issued
                        verified
                        shared
                    }
                }
            }
        ', [
            'userId' => $this->user->id,
            'period' => [
                'start' => now()->subMonth()->toIso8601String(),
                'end' => now()->toIso8601String()
            ]
        ]);

        $engagement = $response->json('data.userEngagement');
        
        $this->assertArrayHasKey('overview', $engagement);
        $this->assertArrayHasKey('activities', $engagement);
        $this->assertArrayHasKey('certificates', $engagement);
        
        // Verify certificate counts
        $this->assertEquals($this->certificates->count(), $engagement['certificates']['issued']);
        $this->assertEquals(
            $this->certificates->where('status', 'active')->count() * 3, // 3 verifications per active cert
            $engagement['certificates']['verified']
        );
    }

    /** @test */
    public function test_predictive_analytics()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query($timeframe: TimeframeInput!) {
                predictiveAnalytics(timeframe: $timeframe) {
                    certificateIssuance {
                        predictedCount
                        confidence
                        factors {
                            name
                            impact
                        }
                    }
                    expiryTrends {
                        month
                        predictedExpiries
                        confidence
                    }
                    userGrowth {
                        period
                        predictedUsers
                        confidence
                    }
                }
            }
        ', [
            'timeframe' => [
                'start' => now()->toIso8601String(),
                'end' => now()->addMonths(3)->toIso8601String()
            ]
        ]);

        $predictions = $response->json('data.predictiveAnalytics');
        
        $this->assertArrayHasKey('certificateIssuance', $predictions);
        $this->assertArrayHasKey('expiryTrends', $predictions);
        $this->assertArrayHasKey('userGrowth', $predictions);
        
        // Verify prediction structure
        $this->assertArrayHasKey('predictedCount', $predictions['certificateIssuance']);
        $this->assertArrayHasKey('confidence', $predictions['certificateIssuance']);
        $this->assertGreaterThan(0, $predictions['certificateIssuance']['confidence']);
        $this->assertLessThanOrEqual(100, $predictions['certificateIssuance']['confidence']);
    }

    /** @test */
    public function test_system_health_monitoring()
    {
        $response = $this->actingAs($this->admin)->graphQL(/** @lang GraphQL */ '
            query {
                systemHealth {
                    services {
                        name
                        status
                        latency
                        lastCheck
                    }
                    resources {
                        cpu
                        memory
                        storage
                        queue
                    }
                    alerts {
                        level
                        message
                        timestamp
                    }
                }
            }
        ');

        $health = $response->json('data.systemHealth');
        
        $this->assertArrayHasKey('services', $health);
        $this->assertArrayHasKey('resources', $health);
        $this->assertArrayHasKey('alerts', $health);
        
        // Verify service health
        foreach ($health['services'] as $service) {
            $this->assertContains($service['status'], ['healthy', 'degraded', 'down']);
            $this->assertIsNumeric($service['latency']);
        }
        
        // Verify resource metrics
        $this->assertIsNumeric($health['resources']['cpu']);
        $this->assertIsNumeric($health['resources']['memory']);
        $this->assertGreaterThanOrEqual(0, $health['resources']['cpu']);
        $this->assertLessThanOrEqual(100, $health['resources']['cpu']);
    }
}

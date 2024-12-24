<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;
use App\Models\User;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\UserActivity;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $template;
    protected $certificates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->template = CertificateTemplate::factory()->create();
        $this->certificates = Certificate::factory()
            ->count(10)
            ->create([
                'template_id' => $this->template->id,
                'created_by' => $this->user->id
            ]);

        // Create user activities
        foreach ($this->certificates as $certificate) {
            UserActivity::factory()->create([
                'user_id' => $this->user->id,
                'activity_type' => UserActivity::TYPE_CREATE_CERTIFICATE,
                'metadata' => ['certificate_number' => $certificate->certificate_number]
            ]);
        }
    }

    /** @test */
    public function it_can_get_dashboard_metrics()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
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
                        'total' => 10,
                        'active' => $this->certificates->where('status', 'active')->count(),
                        'expired' => $this->certificates->where('status', 'expired')->count(),
                        'revoked' => $this->certificates->where('status', 'revoked')->count()
                    ],
                    'users' => [
                        'totalUsers' => 1,
                        'activeUsers' => 1
                    ],
                    'activities' => [
                        'totalActivities' => 10
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_get_certificate_trends_chart()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
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

        $response->assertJson([
            'data' => [
                'certificateTrendsChart' => [
                    'type' => 'line'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_generate_report()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($config: ReportConfigInput!) {
                generateReport(config: $config) {
                    id
                    name
                    type
                    config
                }
            }
        ', [
            'config' => [
                'name' => 'Test Report',
                'type' => 'certificate_metrics',
                'metrics' => ['certificates', 'users', 'activities'],
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $response->assertJson([
            'data' => [
                'generateReport' => [
                    'name' => 'Test Report',
                    'type' => 'certificate_metrics'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_schedule_report()
    {
        $report = Report::factory()->create([
            'created_by' => $this->user->id
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($id: ID!, $schedule: ReportScheduleInput!) {
                scheduleReport(id: $id, schedule: $schedule) {
                    id
                    schedule
                    nextRun
                }
            }
        ', [
            'id' => $report->id,
            'schedule' => [
                'frequency' => 'daily',
                'hour' => 9,
                'minute' => 0
            ]
        ]);

        $response->assertJson([
            'data' => [
                'scheduleReport' => [
                    'id' => (string) $report->id
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_export_report()
    {
        $report = Report::factory()->create([
            'created_by' => $this->user->id,
            'data' => [
                'certificates' => [
                    'total' => 10,
                    'active' => 8,
                    'expired' => 1,
                    'revoked' => 1
                ]
            ]
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($id: ID!, $format: String!) {
                exportReport(id: $id, format: $format)
            }
        ', [
            'id' => $report->id,
            'format' => 'pdf'
        ]);

        $response->assertJson([
            'data' => [
                'exportReport' => true
            ]
        ]);
    }

    /** @test */
    public function it_can_get_user_activity_heatmap()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
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

        $response->assertJson([
            'data' => [
                'userActivityHeatmap' => [
                    'type' => 'heatmap'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_get_real_time_stats()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                realTimeStats
            }
        ');

        $response->assertJson([
            'data' => [
                'realTimeStats' => [
                    'active_users' => 1,
                    'certificates_issued' => 10
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_get_performance_metrics()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query($dateRange: DateRangeInput) {
                performanceMetrics(dateRange: $dateRange)
            }
        ', [
            'dateRange' => [
                'startDate' => now()->subMonth()->toIso8601String(),
                'endDate' => now()->toIso8601String()
            ]
        ]);

        $response->assertSuccessful();
        $this->assertArrayHasKey('performanceMetrics', $response->json('data'));
    }

    /** @test */
    public function it_can_get_template_usage_chart()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                templateUsageChart {
                    type
                    data
                    options
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'templateUsageChart' => [
                    'type' => 'radar'
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_get_expiry_forecast_chart()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                expiryForecastChart {
                    type
                    data
                    options
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'expiryForecastChart' => [
                    'type' => 'line'
                ]
            ]
        ]);
    }
}

<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Certificate;
use App\Services\Integration\GoogleWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;

class GoogleWorkspaceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $service;
    protected $user;
    protected $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new GoogleWorkspaceService();
        
        // Create test user with OAuth token
        $this->user = User::factory()->create();
        $this->user->oauthProviders()->create([
            'provider' => 'google',
            'provider_user_id' => $this->faker->uuid,
            'access_token' => 'test_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addDay()
        ]);

        // Create test certificate
        $this->certificate = Certificate::factory()->create([
            'recipient_email' => $this->faker->email
        ]);
    }

    /** @test */
    public function it_can_upload_certificate_to_drive()
    {
        $pdfContent = 'Test PDF Content';
        
        $mockDriveService = Mockery::mock('Google_Service_Drive');
        $mockDriveService->shouldReceive('files->create')
            ->once()
            ->andReturn((object)['id' => 'test_file_id']);

        $mockDriveService->shouldReceive('permissions->create')
            ->once()
            ->andReturn(true);

        $this->service->driveService = $mockDriveService;

        $fileId = $this->service->uploadCertificate($this->certificate, $pdfContent);
        
        $this->assertEquals('test_file_id', $fileId);
    }

    /** @test */
    public function it_can_sync_users_from_workspace()
    {
        $mockUsers = [
            [
                'primaryEmail' => $this->faker->email,
                'name' => (object)['fullName' => $this->faker->name],
                'organizations' => [(object)[
                    'department' => 'IT',
                    'title' => 'Developer'
                ]]
            ]
        ];

        $mockDirectoryService = Mockery::mock('Google_Service_Directory');
        $mockDirectoryService->shouldReceive('users->listUsers')
            ->once()
            ->andReturn((object)[
                'getUsers' => function() use ($mockUsers) {
                    return array_map(function($user) {
                        return (object)$user;
                    }, $mockUsers);
                },
                'getNextPageToken' => null
            ]);

        $this->service->directoryService = $mockDirectoryService;

        $results = $this->service->syncUsers();

        $this->assertCount(1, $results);
        $this->assertEquals($mockUsers[0]['primaryEmail'], $results[0]['email']);
    }

    /** @test */
    public function it_can_export_certificates_to_sheets()
    {
        $certificates = Certificate::factory()->count(3)->create();
        
        $mockSheetsService = Mockery::mock('Google_Service_Sheets');
        $mockSheetsService->shouldReceive('spreadsheets->create')
            ->once()
            ->andReturn((object)['spreadsheetId' => 'test_sheet_id']);

        $mockSheetsService->shouldReceive('spreadsheets_values->update')
            ->once()
            ->andReturn(true);

        $mockSheetsService->shouldReceive('spreadsheets->batchUpdate')
            ->once()
            ->andReturn(true);

        $this->service->sheetsService = $mockSheetsService;

        $spreadsheetId = $this->service->exportToSheets($certificates->toArray());

        $this->assertEquals('test_sheet_id', $spreadsheetId);
    }

    /** @test */
    public function it_handles_drive_upload_errors()
    {
        $this->expectException(\Exception::class);
        
        $mockDriveService = Mockery::mock('Google_Service_Drive');
        $mockDriveService->shouldReceive('files->create')
            ->once()
            ->andThrow(new \Exception('Upload failed'));

        $this->service->driveService = $mockDriveService;

        $this->service->uploadCertificate($this->certificate, 'test content');
    }

    /** @test */
    public function it_handles_user_sync_errors()
    {
        $this->expectException(\Exception::class);
        
        $mockDirectoryService = Mockery::mock('Google_Service_Directory');
        $mockDirectoryService->shouldReceive('users->listUsers')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        $this->service->directoryService = $mockDirectoryService;

        $this->service->syncUsers();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

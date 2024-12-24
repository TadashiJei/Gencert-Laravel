<?php

namespace App\Services\Integration;

use App\Models\User;
use App\Models\Certificate;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Directory;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceService
{
    protected $client;
    protected $driveService;
    protected $directoryService;
    protected $sheetsService;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->setScopes([
            Google_Service_Drive::DRIVE_FILE,
            Google_Service_Directory::DIRECTORY_READONLY,
            Google_Service_Sheets::SPREADSHEETS
        ]);
    }

    /**
     * Initialize services with user's access token
     */
    public function initializeServices(User $user)
    {
        $token = $user->oauthProviders()
            ->where('provider', 'google')
            ->first()
            ->access_token;

        $this->client->setAccessToken($token);

        $this->driveService = new Google_Service_Drive($this->client);
        $this->directoryService = new Google_Service_Directory($this->client);
        $this->sheetsService = new Google_Service_Sheets($this->client);
    }

    /**
     * Upload certificate to Google Drive
     */
    public function uploadCertificate(Certificate $certificate, string $pdfContent)
    {
        try {
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => "Certificate_{$certificate->certificate_number}.pdf",
                'mimeType' => 'application/pdf'
            ]);

            $file = $this->driveService->files->create($fileMetadata, [
                'data' => $pdfContent,
                'mimeType' => 'application/pdf',
                'uploadType' => 'multipart'
            ]);

            // Set file permissions
            $permission = new \Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => 'reader',
                'emailAddress' => $certificate->recipient_email
            ]);

            $this->driveService->permissions->create(
                $file->getId(),
                $permission,
                ['sendNotificationEmail' => true]
            );

            return $file->getId();
        } catch (\Exception $e) {
            Log::error("Failed to upload certificate to Google Drive: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync users from Google Workspace Directory
     */
    public function syncUsers()
    {
        try {
            $results = [];
            $pageToken = null;

            do {
                $optParams = [
                    'customer' => 'my_customer',
                    'pageToken' => $pageToken,
                    'projection' => 'full'
                ];

                $response = $this->directoryService->users->listUsers($optParams);
                
                foreach ($response->getUsers() as $user) {
                    $results[] = [
                        'email' => $user->getPrimaryEmail(),
                        'name' => $user->getName()->getFullName(),
                        'department' => $user->getOrganizations()[0]->getDepartment() ?? null,
                        'title' => $user->getOrganizations()[0]->getTitle() ?? null
                    ];
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            return $results;
        } catch (\Exception $e) {
            Log::error("Failed to sync users from Google Workspace: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Export certificates to Google Sheets
     */
    public function exportToSheets(array $certificates)
    {
        try {
            // Create new spreadsheet
            $spreadsheet = new \Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => 'Certificates Export ' . now()->format('Y-m-d')
                ]
            ]);

            $spreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);

            // Prepare data
            $values = [
                // Header row
                [
                    'Certificate Number',
                    'Recipient Name',
                    'Recipient Email',
                    'Issue Date',
                    'Expiry Date',
                    'Status'
                ]
            ];

            // Add certificate data
            foreach ($certificates as $cert) {
                $values[] = [
                    $cert->certificate_number,
                    $cert->recipient_name,
                    $cert->recipient_email,
                    $cert->issued_at->format('Y-m-d'),
                    $cert->expires_at ? $cert->expires_at->format('Y-m-d') : 'N/A',
                    $cert->status
                ];
            }

            // Update spreadsheet values
            $body = new \Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);

            $this->sheetsService->spreadsheets_values->update(
                $spreadsheet->spreadsheetId,
                'Sheet1!A1',
                $body,
                ['valueInputOption' => 'RAW']
            );

            // Format header row
            $requests = [
                new \Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColor' => [
                                    'red' => 0.8,
                                    'green' => 0.8,
                                    'blue' => 0.8
                                ],
                                'textFormat' => [
                                    'bold' => true
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
                    ]
                ])
            ];

            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $this->sheetsService->spreadsheets->batchUpdate(
                $spreadsheet->spreadsheetId,
                $batchUpdateRequest
            );

            return $spreadsheet->spreadsheetId;
        } catch (\Exception $e) {
            Log::error("Failed to export certificates to Google Sheets: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Import certificates from Google Sheets
     */
    public function importFromSheets(string $spreadsheetId)
    {
        try {
            $range = 'Sheet1!A2:F';
            $response = $this->sheetsService->spreadsheets_values->get(
                $spreadsheetId,
                $range
            );

            $values = $response->getValues();
            $certificates = [];

            foreach ($values as $row) {
                $certificates[] = [
                    'certificate_number' => $row[0] ?? null,
                    'recipient_name' => $row[1] ?? null,
                    'recipient_email' => $row[2] ?? null,
                    'issue_date' => $row[3] ?? null,
                    'expiry_date' => $row[4] ?? null,
                    'status' => $row[5] ?? null
                ];
            }

            return $certificates;
        } catch (\Exception $e) {
            Log::error("Failed to import certificates from Google Sheets: " . $e->getMessage());
            throw $e;
        }
    }
}

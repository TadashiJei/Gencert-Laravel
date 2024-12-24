<?php

namespace App\Services\Integration;

use App\Models\User;
use App\Models\Certificate;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MicrosoftOfficeService
{
    protected $graph;
    protected $token;

    public function __construct()
    {
        $this->graph = new Graph();
    }

    /**
     * Initialize Microsoft Graph API with user's access token
     */
    public function initializeGraph(User $user)
    {
        $this->token = $user->oauthProviders()
            ->where('provider', 'microsoft')
            ->first()
            ->access_token;

        $this->graph->setAccessToken($this->token);
    }

    /**
     * Upload certificate to OneDrive
     */
    public function uploadCertificate(Certificate $certificate, string $pdfContent)
    {
        try {
            // Create upload session
            $fileName = "Certificate_{$certificate->certificate_number}.pdf";
            $uploadSession = $this->graph->createRequest('POST', '/me/drive/root:/' . $fileName . ':/createUploadSession')
                ->setReturnType(Model\UploadSession::class)
                ->execute();

            // Upload file content
            $client = new Client();
            $response = $client->put($uploadSession->getUploadUrl(), [
                'headers' => [
                    'Content-Length' => strlen($pdfContent),
                    'Content-Range' => 'bytes 0-' . (strlen($pdfContent) - 1) . '/' . strlen($pdfContent)
                ],
                'body' => $pdfContent
            ]);

            // Get the file item
            $driveItem = $this->graph->createRequest('GET', '/me/drive/root:/' . $fileName)
                ->setReturnType(Model\DriveItem::class)
                ->execute();

            // Share file with recipient
            $permission = new Model\Permission([
                'roles' => ['read'],
                'grantedToIdentities' => [[
                    'user' => [
                        'email' => $certificate->recipient_email
                    ]
                ]]
            ]);

            $this->graph->createRequest('POST', '/me/drive/items/' . $driveItem->getId() . '/invite')
                ->attachBody([
                    'requireSignIn' => true,
                    'sendInvitation' => true,
                    'roles' => ['read'],
                    'recipients' => [[
                        'email' => $certificate->recipient_email
                    ]]
                ])
                ->execute();

            return $driveItem->getId();
        } catch (\Exception $e) {
            Log::error("Failed to upload certificate to OneDrive: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync users from Microsoft 365
     */
    public function syncUsers()
    {
        try {
            $users = [];
            $nextLink = '/users';

            do {
                $response = $this->graph->createRequest('GET', $nextLink)
                    ->setReturnType(Model\User::class)
                    ->execute();

                foreach ($response as $user) {
                    $users[] = [
                        'email' => $user->getMail(),
                        'name' => $user->getDisplayName(),
                        'department' => $user->getDepartment(),
                        'title' => $user->getJobTitle()
                    ];
                }

                // Get next page if available
                $nextLink = $this->graph->getNextLink();
            } while ($nextLink);

            return $users;
        } catch (\Exception $e) {
            Log::error("Failed to sync users from Microsoft 365: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Export certificates to Excel Online
     */
    public function exportToExcel(array $certificates)
    {
        try {
            // Create new Excel file
            $excelFile = $this->graph->createRequest('POST', '/me/drive/root/children')
                ->attachBody([
                    'name' => 'Certificates Export ' . now()->format('Y-m-d') . '.xlsx',
                    'file' => []
                ])
                ->setReturnType(Model\DriveItem::class)
                ->execute();

            // Create worksheet
            $worksheet = $this->graph->createRequest('POST', '/me/drive/items/' . $excelFile->getId() . '/workbook/worksheets')
                ->attachBody([
                    'name' => 'Certificates'
                ])
                ->execute();

            // Add header row
            $headerRange = 'A1:F1';
            $headerValues = [
                ['Certificate Number', 'Recipient Name', 'Recipient Email', 'Issue Date', 'Expiry Date', 'Status']
            ];

            $this->graph->createRequest('PATCH', '/me/drive/items/' . $excelFile->getId() . '/workbook/worksheets/Certificates/range(address=\'' . $headerRange . '\')')
                ->attachBody([
                    'values' => $headerValues
                ])
                ->execute();

            // Add certificate data
            $dataRange = 'A2:F' . (count($certificates) + 1);
            $dataValues = array_map(function ($cert) {
                return [
                    $cert->certificate_number,
                    $cert->recipient_name,
                    $cert->recipient_email,
                    $cert->issued_at->format('Y-m-d'),
                    $cert->expires_at ? $cert->expires_at->format('Y-m-d') : 'N/A',
                    $cert->status
                ];
            }, $certificates);

            $this->graph->createRequest('PATCH', '/me/drive/items/' . $excelFile->getId() . '/workbook/worksheets/Certificates/range(address=\'' . $dataRange . '\')')
                ->attachBody([
                    'values' => $dataValues
                ])
                ->execute();

            // Format header row
            $this->graph->createRequest('POST', '/me/drive/items/' . $excelFile->getId() . '/workbook/worksheets/Certificates/range(address=\'' . $headerRange . '\')/format')
                ->attachBody([
                    'fill' => [
                        'color' => '#E0E0E0'
                    ],
                    'font' => [
                        'bold' => true
                    ]
                ])
                ->execute();

            return $excelFile->getId();
        } catch (\Exception $e) {
            Log::error("Failed to export certificates to Excel Online: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Import certificates from Excel Online
     */
    public function importFromExcel(string $fileId)
    {
        try {
            $range = 'Certificates!A2:F';
            $response = $this->graph->createRequest('GET', '/me/drive/items/' . $fileId . '/workbook/worksheets/Certificates/range(address=\'' . $range . '\')')
                ->execute();

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
            Log::error("Failed to import certificates from Excel Online: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send certificate notification via Teams
     */
    public function sendTeamsNotification(Certificate $certificate)
    {
        try {
            $messageCard = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => '0076D7',
                'summary' => 'New Certificate Issued',
                'sections' => [
                    [
                        'activityTitle' => 'New Certificate Issued',
                        'activitySubtitle' => 'Certificate #' . $certificate->certificate_number,
                        'activityImage' => 'https://your-app-url.com/images/certificate-icon.png',
                        'facts' => [
                            [
                                'name' => 'Recipient',
                                'value' => $certificate->recipient_name
                            ],
                            [
                                'name' => 'Issue Date',
                                'value' => $certificate->issued_at->format('Y-m-d')
                            ],
                            [
                                'name' => 'Expiry Date',
                                'value' => $certificate->expires_at ? $certificate->expires_at->format('Y-m-d') : 'N/A'
                            ]
                        ],
                        'markdown' => true
                    ]
                ],
                'potentialAction' => [
                    [
                        '@type' => 'OpenUri',
                        'name' => 'View Certificate',
                        'targets' => [
                            [
                                'os' => 'default',
                                'uri' => 'https://your-app-url.com/certificates/' . $certificate->id
                            ]
                        ]
                    ]
                ]
            ];

            $webhookUrl = config('services.microsoft.teams_webhook_url');
            $client = new Client();
            $response = $client->post($webhookUrl, [
                'json' => $messageCard
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error("Failed to send Teams notification: " . $e->getMessage());
            throw $e;
        }
    }
}

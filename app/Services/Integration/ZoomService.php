<?php

namespace App\Services\Integration;

use App\Models\Certificate;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;

class ZoomService
{
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $accountId;
    protected $baseUrl = 'https://api.zoom.us/v2';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.zoom.api_key');
        $this->apiSecret = config('services.zoom.api_secret');
        $this->accountId = config('services.zoom.account_id');
    }

    /**
     * Generate JWT token for Zoom API
     */
    protected function generateToken(): string
    {
        $payload = [
            'iss' => $this->apiKey,
            'exp' => time() + 3600,
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Schedule a certificate ceremony meeting
     */
    public function scheduleCeremony(Certificate $certificate, array $options = [])
    {
        try {
            $token = $this->generateToken();
            $defaultOptions = [
                'topic' => "Certificate Ceremony - {$certificate->recipient_name}",
                'type' => 2, // Scheduled meeting
                'start_time' => now()->addDays(1)->format('Y-m-d\TH:i:s'),
                'duration' => 30, // 30 minutes
                'timezone' => 'UTC',
                'settings' => [
                    'host_video' => true,
                    'participant_video' => true,
                    'join_before_host' => false,
                    'mute_upon_entry' => true,
                    'watermark' => false,
                    'registration_type' => 2, // Required
                    'auto_recording' => 'cloud'
                ]
            ];

            $meetingOptions = array_merge($defaultOptions, $options);

            $response = $this->client->post("{$this->baseUrl}/users/me/meetings", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $meetingOptions
            ]);

            $meeting = json_decode($response->getBody(), true);

            // Send invitation to recipient
            $this->sendMeetingInvitation($certificate, $meeting);

            return $meeting;
        } catch (\Exception $e) {
            Log::error("Failed to schedule Zoom ceremony: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send meeting invitation
     */
    protected function sendMeetingInvitation(Certificate $certificate, array $meeting)
    {
        try {
            $token = $this->generateToken();
            
            $message = [
                'email' => $certificate->recipient_email,
                'subject' => "Certificate Ceremony Invitation - {$certificate->certificate_number}",
                'body' => [
                    'text' => "Dear {$certificate->recipient_name},\n\n" .
                            "You are invited to attend your certificate ceremony.\n\n" .
                            "Meeting Details:\n" .
                            "Time: {$meeting['start_time']}\n" .
                            "Duration: {$meeting['duration']} minutes\n" .
                            "Join URL: {$meeting['join_url']}\n\n" .
                            "Please ensure you join the meeting on time.\n\n" .
                            "Best regards,\nCertificateHub Team"
                ]
            ];

            $this->client->post("{$this->baseUrl}/users/me/emails", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $message
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send Zoom meeting invitation: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a webinar for bulk certificate distribution
     */
    public function createCertificateWebinar(array $certificates, array $options = [])
    {
        try {
            $token = $this->generateToken();
            $defaultOptions = [
                'topic' => 'Certificate Distribution Webinar',
                'type' => 5, // Webinar
                'start_time' => now()->addDays(7)->format('Y-m-d\TH:i:s'),
                'duration' => 60,
                'timezone' => 'UTC',
                'settings' => [
                    'host_video' => true,
                    'panelists_video' => true,
                    'practice_session' => true,
                    'hd_video' => true,
                    'approval_type' => 0,
                    'registration_type' => 2,
                    'auto_recording' => 'cloud',
                    'allow_multiple_devices' => true
                ]
            ];

            $webinarOptions = array_merge($defaultOptions, $options);

            $response = $this->client->post("{$this->baseUrl}/users/me/webinars", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $webinarOptions
            ]);

            $webinar = json_decode($response->getBody(), true);

            // Register recipients
            foreach ($certificates as $certificate) {
                $this->registerWebinarParticipant($webinar['id'], $certificate);
            }

            return $webinar;
        } catch (\Exception $e) {
            Log::error("Failed to create Zoom webinar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Register participant for webinar
     */
    protected function registerWebinarParticipant(string $webinarId, Certificate $certificate)
    {
        try {
            $token = $this->generateToken();
            
            $participant = [
                'email' => $certificate->recipient_email,
                'first_name' => explode(' ', $certificate->recipient_name)[0],
                'last_name' => explode(' ', $certificate->recipient_name)[1] ?? '',
                'auto_approve' => true
            ];

            $this->client->post("{$this->baseUrl}/webinars/{$webinarId}/registrants", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $participant
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to register webinar participant: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Zoom app chatbot for certificate management
     */
    public function createChatbot()
    {
        try {
            $token = $this->generateToken();
            
            $botSettings = [
                'name' => 'CertificateHub Bot',
                'display_name' => 'CertificateHub Assistant',
                'bot_type' => 'chatbot',
                'commands' => [
                    [
                        'command' => '/cert-lookup',
                        'description' => 'Look up certificate details'
                    ],
                    [
                        'command' => '/cert-stats',
                        'description' => 'View certificate statistics'
                    ],
                    [
                        'command' => '/cert-expiring',
                        'description' => 'View expiring certificates'
                    ]
                ]
            ];

            $response = $this->client->post("{$this->baseUrl}/chatbot", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $botSettings
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to create Zoom chatbot: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle chatbot commands
     */
    public function handleChatbotCommand(array $payload)
    {
        try {
            $command = $payload['command'];
            $args = $payload['args'] ?? [];

            switch ($command) {
                case '/cert-lookup':
                    return $this->handleCertificateLookup($args);
                case '/cert-stats':
                    return $this->handleCertificateStats();
                case '/cert-expiring':
                    return $this->handleExpiringCertificates();
                default:
                    return [
                        'message' => 'Unknown command. Available commands: /cert-lookup, /cert-stats, /cert-expiring'
                    ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to handle Zoom chatbot command: " . $e->getMessage());
            return [
                'message' => 'An error occurred while processing your request'
            ];
        }
    }

    /**
     * Handle certificate lookup command
     */
    protected function handleCertificateLookup(array $args)
    {
        if (empty($args)) {
            return [
                'message' => 'Please provide a certificate number or recipient name'
            ];
        }

        $query = implode(' ', $args);
        $certificate = Certificate::where('certificate_number', $query)
            ->orWhere('recipient_name', 'like', "%{$query}%")
            ->first();

        if (!$certificate) {
            return [
                'message' => 'No certificate found matching your query'
            ];
        }

        return [
            'message' => "Certificate Details:\n" .
                        "Number: {$certificate->certificate_number}\n" .
                        "Recipient: {$certificate->recipient_name}\n" .
                        "Status: {$certificate->status}\n" .
                        "Issue Date: {$certificate->issued_at->format('Y-m-d')}"
        ];
    }

    /**
     * Handle certificate stats command
     */
    protected function handleCertificateStats()
    {
        $stats = [
            'total' => Certificate::count(),
            'active' => Certificate::where('status', 'active')->count(),
            'expired' => Certificate::where('status', 'expired')->count(),
            'expiring_soon' => Certificate::where('status', 'active')
                ->whereDate('expires_at', '<=', now()->addDays(30))
                ->count()
        ];

        return [
            'message' => "Certificate Statistics:\n" .
                        "Total Certificates: {$stats['total']}\n" .
                        "Active Certificates: {$stats['active']}\n" .
                        "Expired Certificates: {$stats['expired']}\n" .
                        "Expiring Soon: {$stats['expiring_soon']}"
        ];
    }

    /**
     * Handle expiring certificates command
     */
    protected function handleExpiringCertificates()
    {
        $certificates = Certificate::where('status', 'active')
            ->whereDate('expires_at', '<=', now()->addDays(30))
            ->orderBy('expires_at')
            ->take(5)
            ->get();

        if ($certificates->isEmpty()) {
            return [
                'message' => 'No certificates are expiring soon'
            ];
        }

        $message = "Certificates Expiring Soon:\n\n";
        foreach ($certificates as $cert) {
            $message .= "Certificate: {$cert->certificate_number}\n" .
                       "Recipient: {$cert->recipient_name}\n" .
                       "Expires: {$cert->expires_at->format('Y-m-d')}\n" .
                       "Days Left: {$cert->expires_at->diffInDays(now())}\n\n";
        }

        return ['message' => $message];
    }
}

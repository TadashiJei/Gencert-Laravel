<?php

namespace App\Services\Integration;

use App\Models\Certificate;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SlackService
{
    protected $client;
    protected $token;
    protected $webhookUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = config('services.slack.bot_token');
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    /**
     * Send certificate notification via Slack
     */
    public function sendCertificateNotification(Certificate $certificate)
    {
        try {
            $blocks = [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🎓 New Certificate Issued',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Certificate Number:*\n{$certificate->certificate_number}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Recipient:*\n{$certificate->recipient_name}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Issue Date:*\n{$certificate->issued_at->format('Y-m-d')}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n{$certificate->status}"
                        ]
                    ]
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'View Certificate',
                                'emoji' => true
                            ],
                            'url' => url("/certificates/{$certificate->id}")
                        ]
                    ]
                ]
            ];

            $response = $this->client->post($this->webhookUrl, [
                'json' => [
                    'blocks' => $blocks
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error("Failed to send Slack notification: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Slack channel for certificate management
     */
    public function createCertificateChannel(string $channelName)
    {
        try {
            $response = $this->client->post('https://slack.com/api/conversations.create', [
                'headers' => [
                    'Authorization' => "Bearer {$this->token}"
                ],
                'json' => [
                    'name' => $channelName,
                    'is_private' => false
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            if (!$result['ok']) {
                throw new \Exception($result['error']);
            }

            return $result['channel']['id'];
        } catch (\Exception $e) {
            Log::error("Failed to create Slack channel: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send certificate expiry reminders
     */
    public function sendExpiryReminders(array $certificates)
    {
        try {
            foreach ($certificates as $certificate) {
                $blocks = [
                    [
                        'type' => 'header',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => '⚠️ Certificate Expiry Reminder',
                            'emoji' => true
                        ]
                    ],
                    [
                        'type' => 'section',
                        'fields' => [
                            [
                                'type' => 'mrkdwn',
                                'text' => "*Certificate Number:*\n{$certificate->certificate_number}"
                            ],
                            [
                                'type' => 'mrkdwn',
                                'text' => "*Recipient:*\n{$certificate->recipient_name}"
                            ],
                            [
                                'type' => 'mrkdwn',
                                'text' => "*Expiry Date:*\n{$certificate->expires_at->format('Y-m-d')}"
                            ],
                            [
                                'type' => 'mrkdwn',
                                'text' => "*Days Until Expiry:*\n{$certificate->expires_at->diffInDays(now())}"
                            ]
                        ]
                    ],
                    [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => [
                                    'type' => 'plain_text',
                                    'text' => 'Renew Certificate',
                                    'emoji' => true
                                ],
                                'url' => url("/certificates/{$certificate->id}/renew")
                            ]
                        ]
                    ]
                ];

                $this->client->post($this->webhookUrl, [
                    'json' => [
                        'blocks' => $blocks
                    ]
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send expiry reminders: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Slack command for certificate lookup
     */
    public function handleSlashCommand(array $payload)
    {
        try {
            $command = $payload['command'];
            $text = $payload['text'];

            switch ($command) {
                case '/cert-lookup':
                    return $this->handleCertificateLookup($text);
                case '/cert-stats':
                    return $this->handleCertificateStats();
                default:
                    return [
                        'response_type' => 'ephemeral',
                        'text' => 'Unknown command'
                    ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to handle Slack command: " . $e->getMessage());
            return [
                'response_type' => 'ephemeral',
                'text' => 'An error occurred while processing your request'
            ];
        }
    }

    /**
     * Handle certificate lookup command
     */
    protected function handleCertificateLookup(string $query)
    {
        $certificate = Certificate::where('certificate_number', $query)
            ->orWhere('recipient_name', 'like', "%{$query}%")
            ->first();

        if (!$certificate) {
            return [
                'response_type' => 'ephemeral',
                'text' => 'No certificate found matching your query'
            ];
        }

        return [
            'response_type' => 'in_channel',
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Certificate Found:*"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Number:*\n{$certificate->certificate_number}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Recipient:*\n{$certificate->recipient_name}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Status:*\n{$certificate->status}"
                        ]
                    ]
                ]
            ]
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
            'response_type' => 'in_channel',
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*Certificate Statistics:*"
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Total Certificates:*\n{$stats['total']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Active Certificates:*\n{$stats['active']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Expired Certificates:*\n{$stats['expired']}"
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Expiring Soon:*\n{$stats['expiring_soon']}"
                        ]
                    ]
                ]
            ]
        ];
    }
}

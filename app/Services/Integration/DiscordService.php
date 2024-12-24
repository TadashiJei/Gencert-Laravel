<?php

namespace App\Services\Integration;

use App\Models\Certificate;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;

class DiscordService
{
    protected $client;
    protected $webhookUrl;
    protected $discord;
    protected $botToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->webhookUrl = config('services.discord.webhook_url');
        $this->botToken = config('services.discord.bot_token');

        // Initialize Discord bot if token is available
        if ($this->botToken) {
            $this->discord = new Discord([
                'token' => $this->botToken
            ]);
        }
    }

    /**
     * Send certificate notification via Discord
     */
    public function sendCertificateNotification(Certificate $certificate)
    {
        try {
            $embed = [
                'title' => '🎓 New Certificate Issued',
                'color' => hexdec('3498db'),
                'fields' => [
                    [
                        'name' => 'Certificate Number',
                        'value' => $certificate->certificate_number,
                        'inline' => true
                    ],
                    [
                        'name' => 'Recipient',
                        'value' => $certificate->recipient_name,
                        'inline' => true
                    ],
                    [
                        'name' => 'Issue Date',
                        'value' => $certificate->issued_at->format('Y-m-d'),
                        'inline' => true
                    ],
                    [
                        'name' => 'Status',
                        'value' => ucfirst($certificate->status),
                        'inline' => true
                    ]
                ],
                'url' => url("/certificates/{$certificate->id}"),
                'timestamp' => now()->toIso8601String()
            ];

            $response = $this->client->post($this->webhookUrl, [
                'json' => [
                    'embeds' => [$embed]
                ]
            ]);

            return $response->getStatusCode() === 204;
        } catch (\Exception $e) {
            Log::error("Failed to send Discord notification: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize Discord bot
     */
    public function initializeBot()
    {
        $this->discord->on('ready', function ($discord) {
            Log::info('Discord bot is ready!');
        });

        $this->discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
            $this->handleBotCommands($message);
        });

        $this->discord->run();
    }

    /**
     * Handle bot commands
     */
    protected function handleBotCommands(Message $message)
    {
        // Ignore messages from bots
        if ($message->author->bot) {
            return;
        }

        $content = $message->content;
        
        // Command handler
        if (strpos($content, '!cert') === 0) {
            $args = array_filter(explode(' ', $content));
            $command = isset($args[1]) ? strtolower($args[1]) : 'help';

            switch ($command) {
                case 'lookup':
                    $this->handleLookupCommand($message, array_slice($args, 2));
                    break;
                case 'stats':
                    $this->handleStatsCommand($message);
                    break;
                case 'expiring':
                    $this->handleExpiringCommand($message);
                    break;
                case 'help':
                default:
                    $this->sendHelpMessage($message);
                    break;
            }
        }
    }

    /**
     * Handle certificate lookup command
     */
    protected function handleLookupCommand(Message $message, array $args)
    {
        if (empty($args)) {
            $message->reply('Please provide a certificate number or recipient name to search.');
            return;
        }

        $query = implode(' ', $args);
        $certificate = Certificate::where('certificate_number', $query)
            ->orWhere('recipient_name', 'like', "%{$query}%")
            ->first();

        if (!$certificate) {
            $message->reply('No certificate found matching your query.');
            return;
        }

        $embed = [
            'title' => 'Certificate Details',
            'color' => hexdec('3498db'),
            'fields' => [
                [
                    'name' => 'Certificate Number',
                    'value' => $certificate->certificate_number,
                    'inline' => true
                ],
                [
                    'name' => 'Recipient',
                    'value' => $certificate->recipient_name,
                    'inline' => true
                ],
                [
                    'name' => 'Status',
                    'value' => ucfirst($certificate->status),
                    'inline' => true
                ],
                [
                    'name' => 'Issue Date',
                    'value' => $certificate->issued_at->format('Y-m-d'),
                    'inline' => true
                ]
            ],
            'url' => url("/certificates/{$certificate->id}")
        ];

        $message->channel->sendMessage('', false, $embed);
    }

    /**
     * Handle certificate stats command
     */
    protected function handleStatsCommand(Message $message)
    {
        $stats = [
            'total' => Certificate::count(),
            'active' => Certificate::where('status', 'active')->count(),
            'expired' => Certificate::where('status', 'expired')->count(),
            'expiring_soon' => Certificate::where('status', 'active')
                ->whereDate('expires_at', '<=', now()->addDays(30))
                ->count()
        ];

        $embed = [
            'title' => 'Certificate Statistics',
            'color' => hexdec('2ecc71'),
            'fields' => [
                [
                    'name' => 'Total Certificates',
                    'value' => $stats['total'],
                    'inline' => true
                ],
                [
                    'name' => 'Active Certificates',
                    'value' => $stats['active'],
                    'inline' => true
                ],
                [
                    'name' => 'Expired Certificates',
                    'value' => $stats['expired'],
                    'inline' => true
                ],
                [
                    'name' => 'Expiring Soon',
                    'value' => $stats['expiring_soon'],
                    'inline' => true
                ]
            ]
        ];

        $message->channel->sendMessage('', false, $embed);
    }

    /**
     * Handle expiring certificates command
     */
    protected function handleExpiringCommand(Message $message)
    {
        $expiringCertificates = Certificate::where('status', 'active')
            ->whereDate('expires_at', '<=', now()->addDays(30))
            ->orderBy('expires_at')
            ->take(10)
            ->get();

        if ($expiringCertificates->isEmpty()) {
            $message->reply('No certificates are expiring soon.');
            return;
        }

        $embed = [
            'title' => 'Certificates Expiring Soon',
            'color' => hexdec('e74c3c'),
            'fields' => []
        ];

        foreach ($expiringCertificates as $cert) {
            $embed['fields'][] = [
                'name' => $cert->certificate_number,
                'value' => sprintf(
                    "Recipient: %s\nExpires: %s\nDays Left: %d",
                    $cert->recipient_name,
                    $cert->expires_at->format('Y-m-d'),
                    $cert->expires_at->diffInDays(now())
                )
            ];
        }

        $message->channel->sendMessage('', false, $embed);
    }

    /**
     * Send help message
     */
    protected function sendHelpMessage(Message $message)
    {
        $embed = [
            'title' => 'Certificate Bot Commands',
            'color' => hexdec('9b59b6'),
            'fields' => [
                [
                    'name' => '!cert lookup <query>',
                    'value' => 'Look up a certificate by number or recipient name'
                ],
                [
                    'name' => '!cert stats',
                    'value' => 'Show certificate statistics'
                ],
                [
                    'name' => '!cert expiring',
                    'value' => 'Show certificates expiring soon'
                ],
                [
                    'name' => '!cert help',
                    'value' => 'Show this help message'
                ]
            ]
        ];

        $message->channel->sendMessage('', false, $embed);
    }

    /**
     * Send expiry reminders via Discord
     */
    public function sendExpiryReminders(array $certificates)
    {
        try {
            $embed = [
                'title' => '⚠️ Certificate Expiry Reminders',
                'color' => hexdec('e74c3c'),
                'fields' => []
            ];

            foreach ($certificates as $certificate) {
                $embed['fields'][] = [
                    'name' => $certificate->certificate_number,
                    'value' => sprintf(
                        "Recipient: %s\nExpires: %s\nDays Until Expiry: %d\n[Renew Now](%s)",
                        $certificate->recipient_name,
                        $certificate->expires_at->format('Y-m-d'),
                        $certificate->expires_at->diffInDays(now()),
                        url("/certificates/{$certificate->id}/renew")
                    )
                ];
            }

            $response = $this->client->post($this->webhookUrl, [
                'json' => [
                    'embeds' => [$embed]
                ]
            ]);

            return $response->getStatusCode() === 204;
        } catch (\Exception $e) {
            Log::error("Failed to send Discord expiry reminders: " . $e->getMessage());
            throw $e;
        }
    }
}

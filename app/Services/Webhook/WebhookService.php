<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebhookService
{
    /**
     * Available webhook event types
     */
    public const EVENTS = [
        'certificate.created',
        'certificate.updated',
        'certificate.deleted',
        'certificate.expired',
        'certificate.revoked',
        'certificate.renewed',
        'template.created',
        'template.updated',
        'template.deleted',
        'bulk_operation.started',
        'bulk_operation.completed',
        'bulk_operation.failed'
    ];

    /**
     * Register a new webhook endpoint
     */
    public function register(array $data): Webhook
    {
        $webhook = Webhook::create([
            'url' => $data['url'],
            'description' => $data['description'] ?? null,
            'events' => $data['events'],
            'secret' => $data['secret'] ?? Str::random(32),
            'is_active' => $data['is_active'] ?? true,
            'timeout' => $data['timeout'] ?? 30,
            'retry_count' => $data['retry_count'] ?? 3,
            'retry_delay' => $data['retry_delay'] ?? 60
        ]);

        // Verify the endpoint if requested
        if (isset($data['verify']) && $data['verify']) {
            $this->verifyEndpoint($webhook);
        }

        return $webhook;
    }

    /**
     * Verify a webhook endpoint
     */
    public function verifyEndpoint(Webhook $webhook): bool
    {
        $payload = [
            'type' => 'verification',
            'webhook_id' => $webhook->id,
            'timestamp' => now()->toIso8601String()
        ];

        try {
            $response = $this->sendWebhook($webhook, $payload);
            $webhook->update([
                'last_verified_at' => now(),
                'verification_status' => $response->successful()
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Webhook verification failed: {$e->getMessage()}", [
                'webhook_id' => $webhook->id,
                'url' => $webhook->url
            ]);
            $webhook->update([
                'verification_status' => false
            ]);
            return false;
        }
    }

    /**
     * Dispatch an event to all registered webhooks
     */
    public function dispatchEvent(string $event, array $payload)
    {
        if (!in_array($event, self::EVENTS)) {
            throw new \InvalidArgumentException("Invalid webhook event: {$event}");
        }

        $webhooks = Webhook::active()
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->queueWebhookDelivery($webhook, $event, $payload);
        }
    }

    /**
     * Queue a webhook delivery for processing
     */
    protected function queueWebhookDelivery(Webhook $webhook, string $event, array $payload)
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending'
        ]);

        // Dispatch job to handle the delivery
        dispatch(new ProcessWebhookDelivery($delivery));
    }

    /**
     * Process a webhook delivery
     */
    public function processDelivery(WebhookDelivery $delivery)
    {
        $webhook = $delivery->webhook;
        $payload = array_merge($delivery->payload, [
            'event' => $delivery->event,
            'delivery_id' => $delivery->id,
            'timestamp' => now()->toIso8601String()
        ]);

        $attempt = 1;
        $success = false;

        while ($attempt <= $webhook->retry_count && !$success) {
            try {
                $response = $this->sendWebhook($webhook, $payload);
                $success = $response->successful();

                $delivery->update([
                    'status' => $success ? 'delivered' : 'failed',
                    'response_status' => $response->status(),
                    'response_headers' => $response->headers(),
                    'response_body' => $response->body(),
                    'attempt' => $attempt,
                    'completed_at' => now()
                ]);

                if (!$success && $attempt < $webhook->retry_count) {
                    sleep($webhook->retry_delay);
                }
            } catch (\Exception $e) {
                Log::error("Webhook delivery failed: {$e->getMessage()}", [
                    'delivery_id' => $delivery->id,
                    'webhook_id' => $webhook->id,
                    'attempt' => $attempt
                ]);

                $delivery->update([
                    'status' => 'failed',
                    'response_body' => $e->getMessage(),
                    'attempt' => $attempt
                ]);

                if ($attempt < $webhook->retry_count) {
                    sleep($webhook->retry_delay);
                }
            }

            $attempt++;
        }

        // Update webhook metrics
        $this->updateWebhookMetrics($webhook, $success);

        return $success;
    }

    /**
     * Send a webhook request
     */
    protected function sendWebhook(Webhook $webhook, array $payload)
    {
        $signature = $this->generateSignature($webhook->secret, $payload);

        return Http::timeout($webhook->timeout)
            ->withHeaders([
                'User-Agent' => 'CertificateHub-Webhook/1.0',
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature
            ])
            ->post($webhook->url, $payload);
    }

    /**
     * Generate signature for webhook payload
     */
    protected function generateSignature(string $secret, array $payload): string
    {
        $payloadJson = json_encode($payload);
        return hash_hmac('sha256', $payloadJson, $secret);
    }

    /**
     * Update webhook delivery metrics
     */
    protected function updateWebhookMetrics(Webhook $webhook, bool $success)
    {
        $metrics = Cache::get("webhook_metrics:{$webhook->id}", [
            'total_deliveries' => 0,
            'successful_deliveries' => 0,
            'failed_deliveries' => 0,
            'last_delivery_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null
        ]);

        $metrics['total_deliveries']++;
        if ($success) {
            $metrics['successful_deliveries']++;
            $metrics['last_success_at'] = now();
        } else {
            $metrics['failed_deliveries']++;
            $metrics['last_failure_at'] = now();
        }
        $metrics['last_delivery_at'] = now();

        Cache::put("webhook_metrics:{$webhook->id}", $metrics, now()->addDays(30));
    }

    /**
     * Get webhook delivery metrics
     */
    public function getMetrics(Webhook $webhook): array
    {
        return Cache::get("webhook_metrics:{$webhook->id}", [
            'total_deliveries' => 0,
            'successful_deliveries' => 0,
            'failed_deliveries' => 0,
            'last_delivery_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null
        ]);
    }

    /**
     * Validate a webhook signature
     */
    public function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}

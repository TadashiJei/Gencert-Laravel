<?php

namespace Tests\Feature\Webhook;

use Tests\TestCase;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhook\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WebhookTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $service;
    protected $webhook;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new WebhookService();
        
        // Create test webhook
        $this->webhook = Webhook::create([
            'url' => 'https://example.com/webhook',
            'description' => 'Test webhook',
            'events' => ['certificate.created', 'certificate.updated'],
            'secret' => 'test_secret',
            'is_active' => true,
            'timeout' => 30,
            'retry_count' => 3,
            'retry_delay' => 60
        ]);
    }

    /** @test */
    public function it_can_register_new_webhook()
    {
        $data = [
            'url' => 'https://test.com/webhook',
            'description' => 'Test webhook',
            'events' => ['certificate.created'],
            'secret' => 'secret123',
            'is_active' => true
        ];

        $webhook = $this->service->register($data);

        $this->assertInstanceOf(Webhook::class, $webhook);
        $this->assertEquals($data['url'], $webhook->url);
        $this->assertEquals($data['events'], $webhook->events);
    }

    /** @test */
    public function it_validates_webhook_signature()
    {
        $payload = json_encode(['event' => 'test']);
        $secret = 'test_secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $isValid = $this->service->validateSignature($payload, $signature, $secret);

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_can_verify_webhook_endpoint()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200)
        ]);

        $result = $this->service->verifyEndpoint($this->webhook);

        $this->assertTrue($result);
        $this->assertNotNull($this->webhook->last_verified_at);
        $this->assertTrue($this->webhook->verification_status);
    }

    /** @test */
    public function it_handles_failed_webhook_delivery()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Failed'], 500)
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $this->webhook->id,
            'event' => 'certificate.created',
            'payload' => ['test' => 'data'],
            'status' => 'pending'
        ]);

        $success = $this->service->processDelivery($delivery);

        $this->assertFalse($success);
        $this->assertEquals('failed', $delivery->fresh()->status);
        $this->assertEquals(3, $delivery->fresh()->attempt);
    }

    /** @test */
    public function it_retries_failed_deliveries()
    {
        Http::fake([
            'https://example.com/webhook' => Http::sequence()
                ->push(['error' => 'Failed'], 500)
                ->push(['error' => 'Failed'], 500)
                ->push(['status' => 'ok'], 200)
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $this->webhook->id,
            'event' => 'certificate.created',
            'payload' => ['test' => 'data'],
            'status' => 'pending'
        ]);

        $success = $this->service->processDelivery($delivery);

        $this->assertTrue($success);
        $this->assertEquals('delivered', $delivery->fresh()->status);
        $this->assertEquals(3, $delivery->fresh()->attempt);
    }

    /** @test */
    public function it_updates_webhook_metrics()
    {
        // Simulate successful delivery
        $this->service->updateWebhookMetrics($this->webhook, true);
        
        $metrics = Cache::get("webhook_metrics:{$this->webhook->id}");
        
        $this->assertEquals(1, $metrics['total_deliveries']);
        $this->assertEquals(1, $metrics['successful_deliveries']);
        $this->assertEquals(0, $metrics['failed_deliveries']);
        $this->assertNotNull($metrics['last_delivery_at']);
        $this->assertNotNull($metrics['last_success_at']);
    }

    /** @test */
    public function it_dispatches_events_to_multiple_webhooks()
    {
        // Create additional webhook
        $webhook2 = Webhook::create([
            'url' => 'https://example2.com/webhook',
            'events' => ['certificate.created'],
            'secret' => 'test_secret2',
            'is_active' => true
        ]);

        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
            'https://example2.com/webhook' => Http::response(['status' => 'ok'], 200)
        ]);

        $payload = ['certificate_id' => 1];
        $this->service->dispatchEvent('certificate.created', $payload);

        Http::assertSent(function ($request) {
            return $request->url() == 'https://example.com/webhook' ||
                   $request->url() == 'https://example2.com/webhook';
        });
    }

    /** @test */
    public function it_validates_event_types()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->dispatchEvent('invalid.event', []);
    }

    /** @test */
    public function it_handles_inactive_webhooks()
    {
        $this->webhook->update(['is_active' => false]);

        Http::fake();

        $this->service->dispatchEvent('certificate.created', []);

        Http::assertNothingSent();
    }

    /** @test */
    public function it_respects_webhook_timeout()
    {
        $this->webhook->update(['timeout' => 1]);

        Http::fake([
            'https://example.com/webhook' => Http::response()->delay(2000)
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $this->webhook->id,
            'event' => 'certificate.created',
            'payload' => ['test' => 'data'],
            'status' => 'pending'
        ]);

        $success = $this->service->processDelivery($delivery);

        $this->assertFalse($success);
        $this->assertEquals('failed', $delivery->fresh()->status);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndiamartWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.indiamart.webhook_secret' => self::SECRET]);
        AiBotSetting::current()->update(['enabled' => true]);
    }

    public function test_it_rejects_a_push_with_an_incorrect_secret(): void
    {
        $this->postJson('/api/webhooks/indiamart/wrong-secret', $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_it_rejects_every_push_when_no_secret_is_configured(): void
    {
        config(['services.indiamart.webhook_secret' => null]);

        $this->postJson('/api/webhooks/indiamart/'.self::SECRET, $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_it_stores_a_pushed_lead(): void
    {
        $this->postJson($this->endpoint(), $this->payload())
            ->assertOk()
            ->assertJson(['CODE' => 200, 'STATUS' => 'SUCCESS']);

        $lead = Lead::sole();

        $this->assertSame('621654886', $lead->external_lead_id);
        $this->assertSame('B', $lead->query_type);
        $this->assertSame(LeadStatus::Received, $lead->status);
        $this->assertSame('IndiaMART', $lead->leadSource->name);
        $this->assertSame('Prabhat', $lead->payload['SENDER_NAME']);
    }

    public function test_it_holds_a_lead_instead_of_dropping_it_when_the_bot_is_off(): void
    {
        AiBotSetting::current()->update(['enabled' => false]);

        $this->postJson($this->endpoint(), $this->payload())
            ->assertOk()
            ->assertJson(['CODE' => 200, 'STATUS' => 'SUCCESS']);

        $this->assertSame(LeadStatus::Held, Lead::sole()->status);
    }

    public function test_it_converts_the_arrival_time_from_ist_to_utc(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $this->assertSame(
            '2024-04-10 05:47:14',
            Lead::sole()->arrived_at->utc()->toDateTimeString(),
        );
    }

    public function test_it_does_not_create_a_duplicate_lead_when_a_push_is_replayed(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_it_acknowledges_a_push_that_has_no_query_id(): void
    {
        $payload = $this->payload();
        unset($payload['body']['RESPONSE']['UNIQUE_QUERY_ID']);

        $this->postJson($this->endpoint(), $payload)->assertOk();

        $this->assertDatabaseCount('leads', 0);
        $this->assertSame('ignored', WebhookEvent::sole()->outcome);
    }

    public function test_it_does_not_require_csrf_protection(): void
    {
        $this->postJson($this->endpoint(), $this->payload())
            ->assertOk()
            ->assertJson(['CODE' => 200]);
    }

    private function endpoint(): string
    {
        return '/api/webhooks/indiamart/'.self::SECRET;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'method' => 'POST',
            'json' => true,
            'headers' => ['Content-Type' => 'application/json'],
            'url' => 'https://example.test/api/webhooks/indiamart/'.self::SECRET,
            'body' => [
                'CODE' => 200,
                'STATUS' => 'SUCCESS',
                'RESPONSE' => [
                    'UNIQUE_QUERY_ID' => '621654886',
                    'QUERY_TYPE' => 'B',
                    'QUERY_TIME' => '2024-04-10 11:17:14',
                    'SENDER_NAME' => 'Prabhat',
                    'SENDER_MOBILE' => '+91-9999999999',
                    'SENDER_EMAIL' => 'buyer@example.test',
                    'SENDER_COUNTRY_ISO' => 'NG',
                    'SENDER_COUNTRY_NAME' => 'Nigeria',
                    'QUERY_PRODUCT_NAME' => 'Mineral Water Bottle',
                    'QUERY_MESSAGE' => 'I want to purchase an Empty Mineral Water Bottle.',
                ],
            ],
            'timeout' => 10000,
        ];
    }
}

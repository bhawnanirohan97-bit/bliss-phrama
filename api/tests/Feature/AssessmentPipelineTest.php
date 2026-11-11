<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\MatchState;
use App\Enums\ProductReviewStatus;
use App\Enums\ScoreBand;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole deterministic path: webhook -> stored lead -> assessment -> band.
 */
class AssessmentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.indiamart.webhook_secret' => self::SECRET]);
        AiBotSetting::current()->update(['enabled' => true]);

        $this->product();
    }

    public function test_a_webhook_lead_is_stored_and_assessed(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $lead = Lead::sole();
        $assessment = $lead->latestAssessment;

        $this->assertNotNull($assessment);
        $this->assertSame(1, $lead->assessments()->count());
        $this->assertSame('v1.1.0', $assessment->policy_version);
        $this->assertSame(MatchState::Strong, $assessment->match_state);
        $this->assertSame(28, $assessment->product_match);
        $this->assertSame(20, $assessment->commercial_value);
        $this->assertSame(73, $assessment->overall_score);
        $this->assertSame(ScoreBand::Review, $assessment->band);
        $this->assertSame(LeadStatus::Received, $lead->status);
    }

    public function test_a_domestic_lead_is_hard_rejected_and_leaves_the_inbox(): void
    {
        $this->postJson($this->endpoint(), $this->payload([
            'SENDER_COUNTRY_ISO' => 'IN',
            'SENDER_COUNTRY_NAME' => 'India',
        ]))->assertOk();

        $lead = Lead::sole();

        $this->assertSame(LeadStatus::HardRejected, $lead->status);
        $this->assertSame(ScoreBand::HardReject, $lead->latestAssessment->band);
        $this->assertContains('destination_blocked', $lead->latestAssessment->gates);
    }

    public function test_a_held_lead_is_assessed_when_it_is_released(): void
    {
        AiBotSetting::current()->update(['enabled' => false]);

        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $lead = Lead::sole();
        $this->assertSame(LeadStatus::Held, $lead->status);
        $this->assertSame(0, $lead->assessments()->count());

        $this->post('/inbox/'.$lead->id.'/status', ['status' => 'received'])->assertRedirect();

        $this->assertSame(1, $lead->fresh()->assessments()->count());
    }

    public function test_a_replayed_push_does_not_create_a_second_assessment(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $this->assertSame(1, Lead::sole()->assessments()->count());
    }

    public function test_reassessment_appends_rather_than_updates(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $lead = Lead::sole();
        $first = $lead->latestAssessment;

        $this->post('/inbox/'.$lead->id.'/assess')->assertRedirect();

        $lead->refresh();

        $this->assertSame(2, $lead->assessments()->count());
        $this->assertNotSame($first->id, $lead->latestAssessment->id);
    }

    public function test_switching_the_bot_on_releases_and_scores_the_held_backlog(): void
    {
        AiBotSetting::current()->update(['enabled' => false]);

        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $lead = Lead::sole();
        $this->assertSame(LeadStatus::Held, $lead->status);
        $this->assertSame(0, $lead->assessments()->count());

        $this->post('/settings/bot', ['enabled' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '1 held lead(s)'));

        $lead->refresh();

        $this->assertSame(LeadStatus::Received, $lead->status);
        $this->assertSame(1, $lead->assessments()->count());
    }

    public function test_a_hard_rejected_lead_is_hidden_from_the_default_inbox(): void
    {
        $this->postJson($this->endpoint(), $this->payload([
            'SENDER_COUNTRY_ISO' => 'IN',
            'SENDER_COUNTRY_NAME' => 'India',
        ]))->assertOk();

        $this->get('/inbox')
            ->assertOk()
            ->assertDontSee('Acme Pharma');

        $this->get('/inbox?status=hard_rejected')
            ->assertOk()
            ->assertSee('Acme Pharma');
    }

    public function test_the_lead_screen_shows_the_assessment(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $this->get('/inbox/'.Lead::sole()->id)
            ->assertOk()
            ->assertSee('Assessment')
            ->assertSee('Strong')
            ->assertSee('Product match')
            ->assertSee('Reassess now')
            ->assertSee('Letrozole 2.5 mg');
    }

    public function test_the_inbox_shows_the_band_and_score(): void
    {
        $this->postJson($this->endpoint(), $this->payload())->assertOk();

        $this->get('/inbox')
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('73/100');
    }

    private function endpoint(): string
    {
        return '/api/webhooks/indiamart/'.self::SECRET;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'UNIQUE_QUERY_ID' => '621654886',
            'QUERY_TYPE' => 'B',
            'QUERY_TIME' => '2024-04-10 11:17:14',
            'SENDER_NAME' => 'Prabhat',
            'SENDER_COMPANY' => 'Acme Pharma',
            'SENDER_EMAIL' => 'buyer@example.test',
            'SENDER_MOBILE' => '+234-8000000000',
            'SENDER_COUNTRY_ISO' => 'NG',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
            'SENDER_CITY' => 'Lagos',
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips of Letrozole 2.5 mg within 15 days.',
        ], $overrides);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Letrozole 2.5 mg',
            'slug' => 'letrozole-2-5-mg',
            'category' => 'Anti Cancer Tablets',
            'strength' => '2.5 mg',
            'dosage_form' => 'Tablet',
            'packaging' => '10 tablets',
            'moq' => '10 tablets',
            'product_type' => 'formulation',
            'is_service' => false,
            'review_status' => ProductReviewStatus::Approved,
            'search_text' => 'letrozole 2 5 mg tablet',
        ]);
    }
}

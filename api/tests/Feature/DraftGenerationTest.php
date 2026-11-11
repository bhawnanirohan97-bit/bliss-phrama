<?php

namespace Tests\Feature;

use App\Enums\DraftChannel;
use App\Enums\FactCheckStatus;
use App\Enums\LeadStatus;
use App\Enums\ProductReviewStatus;
use App\Models\AiBotSetting;
use App\Models\Draft;
use App\Models\Lead;
use App\Models\Product;
use App\Services\Drafting\DraftContext;
use App\Services\Drafting\DraftFactChecker;
use App\Services\Drafting\DraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 6: the three outreach drafts and the fact check that gates them.
 */
class DraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.indiamart.webhook_secret' => self::SECRET]);
        config([
            'pharmalead.operator.company' => 'Bliss Pharmex',
            'pharmalead.operator.desk' => 'Bliss Pharmex Export Desk',
            'pharmalead.operator.city' => 'Nagpur',
            'pharmalead.operator.country' => 'India',
            'pharmalead.operator.phone' => '+91 78210 27619',
            'pharmalead.operator.email' => 'exports@blisspharmex.in',
        ]);

        AiBotSetting::current()->update(['enabled' => true]);
    }

    public function test_an_eligible_lead_gets_three_checked_drafts(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();
        $drafts = $lead->drafts()->get();

        $this->assertCount(3, $drafts);
        $this->assertSame(
            [DraftChannel::Call->value, DraftChannel::Email->value, DraftChannel::Whatsapp->value],
            $drafts->pluck('channel')->map(fn (DraftChannel $channel) => $channel->value)->sort()->values()->all(),
        );

        foreach ($drafts as $draft) {
            $this->assertSame(FactCheckStatus::Passed, $draft->fact_check_status);
            $this->assertSame([], $draft->violations());
        }

        $email = $this->draft($lead, DraftChannel::Email);

        $this->assertNotNull($email->subject);
        $this->assertStringContainsString('Emmet', $email->body);
        $this->assertStringContainsString('Generic Medicine Drop Shipping', $email->body);
        $this->assertStringContainsString('third-party', $email->body);
        $this->assertStringContainsString('Bliss Pharmex', $email->subject);
    }

    public function test_the_drafts_adapt_to_the_buyer_and_the_stated_requirement(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();
        $email = $this->draft($lead, DraftChannel::Email);
        $whatsapp = $this->draft($lead, DraftChannel::Whatsapp);
        $call = $this->draft($lead, DraftChannel::Call);

        $this->assertStringStartsWith('Dear Emmet,', $email->body);
        $this->assertStringContainsString('your enquiry for Generic Medicine Drop Shipping', $email->body);
        $this->assertStringContainsString('Hi Emmet,', $whatsapp->body);
        $this->assertStringContainsString('Hi Emmet, this is the export desk', $call->body);

        // No approved SKU matched, so the draft asks for the product list
        // instead of inventing a catalogue item.
        $this->assertStringContainsString('product list', $email->body);
        $this->assertStringNotContainsString('Letrozole', $email->body);
    }

    public function test_a_matched_sku_is_quoted_without_claiming_manufacture(): void
    {
        $this->product();

        $this->push($this->emmetPayload([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips of Letrozole 2.5 mg within 15 days.',
        ]))->assertOk();

        $lead = Lead::sole();
        $email = $this->draft($lead, DraftChannel::Email);

        $this->assertStringContainsString('Letrozole 2.5 mg', $email->body);
        $this->assertStringContainsString('we can quote', $email->body);
        $this->assertStringContainsString('(5,000 strip)', $email->body);
        $this->assertSame(FactCheckStatus::Passed, $email->fact_check_status);
        $this->assertStringNotContainsString('we manufacture', strtolower($email->body));
    }

    public function test_a_hard_rejected_lead_gets_no_drafts(): void
    {
        $this->push($this->emmetPayload([
            'SENDER_COUNTRY_ISO' => 'IN',
            'SENDER_COUNTRY_NAME' => 'India',
        ]))->assertOk();

        $lead = Lead::sole();

        $this->assertSame(LeadStatus::HardRejected, $lead->status);
        $this->assertSame(0, $lead->drafts()->count());
    }

    public function test_a_held_lead_is_not_drafted_until_it_is_assessed(): void
    {
        AiBotSetting::current()->update(['enabled' => false]);

        $this->push($this->emmetPayload())->assertOk();

        $this->assertSame(0, Lead::sole()->drafts()->count());
    }

    public function test_the_fact_check_rejects_a_manufacturing_claim(): void
    {
        $lead = $this->assessedLead();

        $result = app(DraftFactChecker::class)->check(
            DraftChannel::Email,
            'Quotation',
            'We manufacture this product in our factory and guarantee delivery.',
            DraftContext::from($lead, $lead->latestAssessment),
        );

        $this->assertFalse($result->passed);
        $this->assertContains('manufacturing_claim: "We manufacture"', $result->violations);
        $this->assertContains('factory_claim: "our factory"', $result->violations);
        $this->assertContains('guarantee_claim: "guarantee"', $result->violations);
    }

    public function test_the_fact_check_rejects_an_untraceable_number(): void
    {
        $lead = $this->assessedLead();

        $result = app(DraftFactChecker::class)->check(
            DraftChannel::Whatsapp,
            null,
            'We can supply 9999 strips at USD 3.50 per strip.',
            DraftContext::from($lead, $lead->latestAssessment),
        );

        $this->assertFalse($result->passed);
        $this->assertContains('untraceable_number: "9999"', $result->violations);
    }

    public function test_the_fact_check_rejects_a_certification_claim(): void
    {
        $lead = $this->assessedLead();

        $result = app(DraftFactChecker::class)->check(
            DraftChannel::Email,
            'Quotation',
            'Our plant is WHO-GMP certified and approved for your market.',
            DraftContext::from($lead, $lead->latestAssessment),
        );

        $this->assertFalse($result->passed);
        $this->assertTrue($this->hasViolation($result->violations, 'certification_claim'));
        $this->assertTrue($this->hasViolation($result->violations, 'regulatory_approval_claim'));
    }

    public function test_regenerating_all_channels_appends_a_new_set(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();
        $firstIds = $lead->drafts()->orderBy('id')->pluck('id')->all();

        $this->post('/inbox/'.$lead->id.'/drafts')->assertRedirect();

        $this->assertSame(6, $lead->drafts()->count());
        $this->assertSame(3, $lead->drafts()->whereNotIn('id', $firstIds)->count());

        $latest = app(DraftService::class)->latestForLead($lead);

        $this->assertCount(3, $latest);
        $this->assertNotContains($firstIds[0], array_map(fn (Draft $draft) => $draft->id, $latest));
    }

    public function test_regenerating_one_channel_only_touches_that_channel(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();

        $this->post('/inbox/'.$lead->id.'/drafts', ['channel' => 'email'])->assertRedirect();

        $this->assertSame(2, $lead->drafts()->where('channel', DraftChannel::Email->value)->count());
        $this->assertSame(1, $lead->drafts()->where('channel', DraftChannel::Whatsapp->value)->count());
    }

    public function test_the_lead_screen_shows_the_drafts_and_the_prefilled_links(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();

        $this->get('/inbox/'.$lead->id)
            ->assertOk()
            ->assertSee('Reply drafts')
            ->assertSee('Fact check passed')
            ->assertSee('Dear Emmet,')
            ->assertSee('Generic Medicine Drop Shipping')
            ->assertSee('Regenerate all three')
            ->assertSee('wa.me', false)
            ->assertSee('mailto:', false);
    }

    public function test_a_failed_draft_is_stored_but_withheld_from_the_screen(): void
    {
        $this->push($this->emmetPayload())->assertOk();

        $lead = Lead::sole();

        Draft::create([
            'lead_id' => $lead->id,
            'lead_assessment_id' => $lead->latestAssessment->id,
            'channel' => DraftChannel::Email->value,
            'language' => 'en',
            'template_id' => 'email.first_response',
            'template_version' => '1.0.0',
            'subject' => 'Quotation',
            'body' => 'We manufacture this in our own factory.',
            'fact_check_status' => FactCheckStatus::Failed->value,
            'fact_check_detail' => [
                'passed' => false,
                'violations' => ['manufacturing_claim: "We manufacture"'],
            ],
            'provider' => 'template',
        ]);

        $this->get('/inbox/'.$lead->id)
            ->assertOk()
            ->assertSee('Withheld: the fact check failed.')
            ->assertSee('manufacturing_claim')
            ->assertDontSee('We manufacture this in our own factory.');
    }

    private function draft(Lead $lead, DraftChannel $channel): Draft
    {
        $draft = $lead->drafts()->where('channel', $channel->value)->orderByDesc('id')->first();

        $this->assertInstanceOf(Draft::class, $draft);

        return $draft;
    }

    /**
     * @param  list<string>  $violations
     */
    private function hasViolation(array $violations, string $rule): bool
    {
        foreach ($violations as $violation) {
            if (str_starts_with($violation, $rule.':')) {
                return true;
            }
        }

        return false;
    }

    private function assessedLead(): Lead
    {
        $this->push($this->emmetPayload())->assertOk();

        return Lead::sole();
    }

    private function push(array $payload): TestResponse
    {
        return $this->postJson('/api/webhooks/indiamart/'.self::SECRET, $payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function emmetPayload(array $overrides = []): array
    {
        return array_merge([
            'UNIQUE_QUERY_ID' => 'DUMMY-'.$this->uniqueId(),
            'QUERY_TYPE' => 'B',
            'QUERY_TIME' => '2026-11-11 20:00:00',
            'SENDER_NAME' => 'Emmet Schepers',
            'SENDER_COMPANY' => 'Schepers Imports',
            'SENDER_EMAIL' => 'emmet@example.test',
            'SENDER_MOBILE' => '+234-8000000000',
            'SENDER_COUNTRY_ISO' => 'NG',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
            'SENDER_CITY' => 'Lagos',
            'QUERY_PRODUCT_NAME' => 'Generic Medicine Drop Shipping',
            'QUERY_MESSAGE' => 'Please share more details about your requirement.',
        ], $overrides);
    }

    private function uniqueId(): string
    {
        static $sequence = 0;
        $sequence++;

        return (string) (1_700_000_000 + $sequence);
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

<?php

namespace Tests\Feature;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;
use App\Enums\MatchState;
use App\Enums\ProductReviewStatus;
use App\Enums\ScoreBand;
use App\Models\Country;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Product;
use App\Services\Matching\ProductMatch;
use App\Services\Policy\Assessment;
use App\Services\Policy\Assessor;
use App\Services\Qualification\InquiryExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Component maths, band admission rules and hard gates (PRD 5.2 and 5.3).
 *
 * These tests pass matches and qualifications explicitly instead of going
 * through the matcher, so a failure points at the scoring rule rather than at
 * tokenisation.
 */
class AssessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_lead_scores_100_and_reaches_act_now(): void
    {
        Country::create([
            'iso2' => 'NG',
            'name' => 'Nigeria',
            'destination_class' => DestinationClass::AllowedProven,
        ]);

        $product = $this->product(['moq' => '10 tablets']);
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips of Letrozole 2.5 mg, delivery to Lagos within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($product, 0.90));

        $this->assertSame(35, $assessment->productMatch);
        $this->assertSame(20, $assessment->commercialValue);
        $this->assertSame(20, $assessment->destination);
        $this->assertSame(15, $assessment->buyerTypeIntent);
        $this->assertSame(10, $assessment->completeness);
        $this->assertSame(100, $assessment->overallScore);
        $this->assertSame(ScoreBand::ActNow, $assessment->band);
        $this->assertSame(MatchState::Exact, $assessment->matchState);
        $this->assertSame([], $assessment->gates);
    }

    public function test_destination_is_an_admission_rule_not_just_points(): void
    {
        // 35 + 20 + 8 + 15 + 10 = 88, high enough for act now, but a restricted
        // destination is only worth 8 and the band minimum cannot be waived.
        Country::create([
            'iso2' => 'NG',
            'name' => 'Nigeria',
            'destination_class' => DestinationClass::Restricted,
        ]);

        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips of Letrozole 2.5 mg within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(['moq' => '10 tablets']), 0.90));

        $this->assertSame(88, $assessment->overallScore);
        $this->assertSame(ScoreBand::Good, $assessment->band);
    }

    public function test_an_unclassified_country_caps_the_band_at_review(): void
    {
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips of Letrozole 2.5 mg within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(['moq' => '10 tablets']), 0.90));

        $this->assertSame(80, $assessment->overallScore);
        $this->assertSame(0, $assessment->destination);
        $this->assertSame(DestinationClass::Unknown, $assessment->destinationClass);
        $this->assertSame(ScoreBand::Review, $assessment->band);
        $this->assertContains('unproven_destination', $assessment->riskFlags);
    }

    public function test_a_personal_buyer_is_hard_rejected(): void
    {
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'Need 2 strips for personal use.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(), 0.90));

        $this->assertSame(BuyerType::Personal, $assessment->buyerType);
        // The buyer-type half of the component is zeroed; only the 4 intent
        // points survive, and the gate still overrides the band.
        $this->assertSame(4, $assessment->buyerTypeIntent);
        $this->assertSame(ScoreBand::HardReject, $assessment->band);
        $this->assertContains('buyer_personal', $assessment->gates);
    }

    public function test_a_domestic_buyer_is_hard_rejected_by_default(): void
    {
        $lead = $this->lead([
            'SENDER_COUNTRY_ISO' => 'IN',
            'SENDER_COUNTRY_NAME' => 'India',
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'Need 5000 strips, delivery to Mumbai within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(), 0.90));

        $this->assertContains('destination_blocked', $assessment->gates);
        $this->assertSame(ScoreBand::HardReject, $assessment->band);
    }

    public function test_a_blocked_sku_is_hard_rejected(): void
    {
        $product = $this->product(['review_status' => ProductReviewStatus::Blocked]);
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($product, 0.90));

        $this->assertContains('product_not_export_eligible', $assessment->gates);
        $this->assertContains('restricted_sku', $assessment->riskFlags);
        $this->assertSame(ScoreBand::HardReject, $assessment->band);
    }

    public function test_a_lead_without_contact_details_is_hard_rejected(): void
    {
        $lead = $this->lead([
            'SENDER_EMAIL' => null,
            'SENDER_MOBILE' => null,
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips within 15 days.',
        ], stripContact: true);

        $assessment = $this->assess($lead, $this->match($this->product(), 0.90));

        $this->assertContains('contact_missing', $assessment->gates);
        $this->assertContains('contact_incomplete', $assessment->riskFlags);
        $this->assertSame(ScoreBand::HardReject, $assessment->band);
    }

    public function test_commercial_value_falls_back_to_absolute_bands_without_an_moq(): void
    {
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 5000 strips within 15 days.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(['moq' => null]), 0.90));

        // No MOQ to compare against, so 5,000 units earns the 14-point band,
        // deliberately below the 20 a real MOQ comparison can earn.
        $this->assertSame(14, $assessment->commercialValue);
    }

    public function test_conflicting_quantities_cap_the_commercial_component(): void
    {
        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'We are an exporter. Need 500 strips now and 5000 strips next month.',
        ]);

        $assessment = $this->assess($lead, $this->match($this->product(['moq' => '10 tablets']), 0.90));

        $this->assertSame(8, $assessment->commercialValue);
        $this->assertContains('ambiguous_quantity', $assessment->riskFlags);
    }

    public function test_a_category_name_alone_scores_seven(): void
    {
        $this->product(['name' => 'Letrozole 2.5 mg', 'category' => 'Anti Cancer Tablets']);

        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Anti Cancer Tablets',
            'QUERY_MESSAGE' => 'Please share your catalogue.',
        ]);

        $assessment = $this->assess($lead, []);

        $this->assertSame(MatchState::CategoryOnly, $assessment->matchState);
        $this->assertSame(7, $assessment->productMatch);
        $this->assertNull($assessment->product);
    }

    public function test_an_ambiguous_category_is_not_guessed(): void
    {
        $this->product(['name' => 'Letrozole 2.5 mg', 'category' => 'Anti Cancer Tablets']);
        $this->product(['name' => 'Capecitabine 500 mg', 'slug' => 'capecitabine-500-mg', 'category' => 'Anti Cancer Capsule']);

        $lead = $this->lead([
            'QUERY_PRODUCT_NAME' => 'Anti Cancer',
            'QUERY_MESSAGE' => 'Please share your catalogue.',
        ]);

        $assessment = $this->assess($lead, []);

        $this->assertSame(MatchState::None, $assessment->matchState);
        $this->assertSame(0, $assessment->productMatch);
        $this->assertContains('no_product_candidate', $assessment->riskFlags);
    }

    public function test_the_assessment_explains_itself(): void
    {
        $lead = $this->lead();

        $assessment = $this->assess($lead, []);

        $this->assertNotEmpty($assessment->reasons);
        $this->assertStringContainsString('Product match', implode(' ', $assessment->reasons));
        $this->assertStringContainsString('Skip', $assessment->explanation);
        $this->assertContains('quantity', $assessment->missingInformation);
        $this->assertContains('target_date', $assessment->missingInformation);
    }

    /**
     * @param  list<ProductMatch>  $matches
     */
    private function assess(Lead $lead, array $matches): Assessment
    {
        $qualification = app(InquiryExtractor::class)->extract($lead);

        return app(Assessor::class)->assess($lead, $qualification, $matches);
    }

    private function match(Product $product, float $score): array
    {
        return [new ProductMatch(
            product: $product,
            score: $score,
            matchedTokens: ['letrozole'],
            strengthMatched: true,
            strengthConflicts: false,
            formMatched: true,
        )];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'UNIQUE_QUERY_ID' => '621654886',
            'QUERY_TYPE' => 'B',
            'SENDER_NAME' => 'Prabhat',
            'SENDER_COMPANY' => 'Acme Pharma',
            'SENDER_EMAIL' => 'buyer@example.test',
            'SENDER_MOBILE' => '+234-8000000000',
            'SENDER_COUNTRY_ISO' => 'NG',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
            'SENDER_CITY' => 'Lagos',
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'Please quote.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lead(array $overrides = [], bool $stripContact = false): Lead
    {
        $payload = $this->payload($overrides);

        if ($stripContact) {
            $payload['SENDER_EMAIL'] = null;
            $payload['SENDER_MOBILE'] = null;
            $payload['SENDER_ALTERNATE_MOBILE'] = null;
        }

        $source = LeadSource::query()->firstOrCreate(
            ['name' => 'IndiaMART'],
            ['connector_type' => 'indiamart_push', 'integration_status' => 'active'],
        );

        return Lead::create([
            'lead_source_id' => $source->id,
            'external_lead_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'query_type' => 'B',
            'status' => 'received',
            'arrived_at' => now(),
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(array $overrides = []): Product
    {
        static $sequence = 0;
        $sequence++;

        return Product::create(array_merge([
            'name' => 'Letrozole 2.5 mg',
            'slug' => 'letrozole-2-5-mg-'.$sequence,
            'category' => 'Anti Cancer Tablets',
            'strength' => '2.5 mg',
            'dosage_form' => 'Tablet',
            'packaging' => '10 tablets',
            'product_type' => 'formulation',
            'is_service' => false,
            'review_status' => ProductReviewStatus::Approved,
            'search_text' => 'letrozole 2 5 mg tablet',
        ], $overrides));
    }
}

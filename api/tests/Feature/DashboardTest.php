<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\ProductReviewStatus;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_with_an_empty_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('No SKUs are approved yet');
    }

    public function test_the_dashboard_lists_the_latest_lead(): void
    {
        $lead = $this->lead();

        $this->get('/')
            ->assertOk()
            ->assertSee('Acme Pharma')
            ->assertSee('Letrozole 2.5 mg');
    }

    public function test_the_inbox_lists_leads_and_filters_by_status(): void
    {
        $this->lead([
            'payload' => $this->payload(['SENDER_COMPANY' => 'Received Buyer Co']),
        ]);
        $this->lead([
            'external_lead_id' => '2',
            'status' => LeadStatus::Held,
            'payload' => $this->payload(['SENDER_COMPANY' => 'Held Buyer Co']),
        ]);

        $this->get('/inbox')
            ->assertOk()
            ->assertSee('Received Buyer Co')
            ->assertSee('Held Buyer Co');

        $this->get('/inbox?status=held')
            ->assertOk()
            ->assertSee('Held Buyer Co')
            ->assertDontSee('Received Buyer Co');
    }

    public function test_the_inbox_searches_the_payload(): void
    {
        $this->lead([
            'payload' => $this->payload([
                'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
                'SENDER_COMPANY' => 'Acme Pharma',
            ]),
        ]);

        $this->get('/inbox?q=letrozole')
            ->assertOk()
            ->assertSee('Acme Pharma');

        $this->get('/inbox?q=nonexistent')
            ->assertOk()
            ->assertSee('No leads match this view');
    }

    public function test_the_lead_detail_page_shows_the_enquiry_and_buyer(): void
    {
        $lead = $this->lead();

        $this->get('/inbox/'.$lead->id)
            ->assertOk()
            ->assertSee('Letrozole 2.5 mg')
            ->assertSee('Acme Pharma')
            ->assertSee('buyer@example.test');
    }

    public function test_a_lead_can_be_marked_contacted(): void
    {
        $lead = $this->lead();

        $this->post('/inbox/'.$lead->id.'/status', ['status' => 'contacted'])
            ->assertRedirect();

        $this->assertSame(LeadStatus::Contacted, $lead->fresh()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $lead = $this->lead();

        $this->post('/inbox/'.$lead->id.'/status', ['status' => 'deleted'])
            ->assertSessionHasErrors('status');

        $this->assertSame(LeadStatus::Received, $lead->fresh()->status);
    }

    public function test_the_catalogue_lists_matchable_products_only(): void
    {
        $this->product(['name' => 'Letrozole 2.5 mg', 'slug' => 'letrozole-2-5-mg']);
        $this->product([
            'name' => 'Drop Shipping Service',
            'slug' => 'drop-shipping-service',
            'is_service' => true,
            'product_type' => 'service',
        ]);

        $this->get('/catalogue')
            ->assertOk()
            ->assertSee('Letrozole 2.5 mg')
            ->assertSee('Nothing is approved');
    }

    public function test_selected_skus_can_be_approved_in_bulk(): void
    {
        $first = $this->product(['slug' => 'letrozole-2-5-mg']);
        $second = $this->product(['name' => 'Amoxicillin 500 mg', 'slug' => 'amoxicillin-500-mg']);

        $this->post('/catalogue/review', [
            'status' => 'approved',
            'slugs' => [$first->slug, $second->slug],
        ])->assertRedirect();

        $this->assertSame(ProductReviewStatus::Approved, $first->fresh()->review_status);
        $this->assertSame(ProductReviewStatus::Approved, $second->fresh()->review_status);
        $this->assertNotNull($first->fresh()->reviewed_at);
    }

    public function test_a_review_request_must_name_its_targets(): void
    {
        $product = $this->product();

        $this->post('/catalogue/review', ['status' => 'approved'])
            ->assertSessionHasErrors('slugs');

        $this->assertSame(ProductReviewStatus::Unreviewed, $product->fresh()->review_status);
    }

    public function test_a_review_request_for_unknown_slugs_changes_nothing(): void
    {
        $product = $this->product();

        $this->post('/catalogue/review', [
            'status' => 'approved',
            'slugs' => ['does-not-exist'],
        ])->assertSessionHasErrors('review');

        $this->assertSame(ProductReviewStatus::Unreviewed, $product->fresh()->review_status);
    }

    public function test_settings_show_the_current_bot_state_and_toggle_it(): void
    {
        AiBotSetting::current()->update(['enabled' => false]);

        $this->get('/settings')
            ->assertOk()
            ->assertSee('Switch bot on');

        $this->post('/settings/bot', ['enabled' => '1'])->assertRedirect();

        $this->assertTrue(AiBotSetting::current()->enabled);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $payload
     */
    private function lead(array $attributes = [], ?array $payload = null): Lead
    {
        $source = LeadSource::query()->firstOrCreate(
            ['name' => 'IndiaMART'],
            ['connector_type' => 'indiamart_push', 'integration_status' => 'active'],
        );

        return Lead::create(array_merge([
            'lead_source_id' => $source->id,
            'external_lead_id' => '621654886',
            'query_type' => 'B',
            'status' => LeadStatus::Received,
            'arrived_at' => now(),
            'payload' => $payload ?? $this->payload(),
        ], $attributes));
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
            'SENDER_MOBILE' => '+91-9999999999',
            'SENDER_COUNTRY_ISO' => 'NG',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
            'SENDER_CITY' => 'Lagos',
            'QUERY_PRODUCT_NAME' => 'Letrozole 2.5 mg',
            'QUERY_MESSAGE' => 'Please quote for 500 strips.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Letrozole 2.5 mg',
            'slug' => 'letrozole-2-5-mg',
            'category' => 'Anti Cancer Tablets',
            'strength' => '2.5 mg',
            'dosage_form' => 'Tablet',
            'packaging' => '10 tablets',
            'product_type' => 'formulation',
            'is_service' => false,
            'review_status' => ProductReviewStatus::Unreviewed,
            'search_text' => 'letrozole 2 5 mg tablet',
        ], $attributes));
    }
}

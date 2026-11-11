<?php

namespace Tests\Feature;

use App\Models\AiBotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiBotSetting::current()->update([
            'enabled' => true,
            'reject_domestic' => true,
            'country_filters' => null,
            'skip_personal' => true,
            'skip_job_vendor' => true,
        ]);
    }

    public function test_it_rejects_domestic_indian_leads_by_country_code(): void
    {
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'IN',
            'inquiry_text' => 'Need 5000 boxes of Paracetamol 500mg tablets',
        ]);

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => false,
                'reason' => 'Domestic Indian lead rejected (export-only policy enabled)',
            ]);
    }

    public function test_it_rejects_domestic_indian_leads_by_country_name(): void
    {
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'India',
            'inquiry_text' => 'Need 5000 boxes of Paracetamol 500mg tablets',
        ]);

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => false,
            ]);
    }

    public function test_it_rejects_domestic_indian_leads_by_indian_city(): void
    {
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'city' => 'Mumbai',
            'inquiry_text' => 'Need 1000 strips for hospital supply',
        ]);

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => false,
            ]);
    }

    public function test_it_rejects_domestic_indian_leads_by_pcd_franchise_keywords(): void
    {
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'inquiry_text' => 'Looking for PCD pharma franchise monopoly rights for antibiotics',
        ]);

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => false,
            ]);
    }

    public function test_it_rejects_domestic_indian_leads_by_phone_prefix(): void
    {
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'phone' => '+919876543210',
            'inquiry_text' => 'Need urgent supply of amoxicillin capsules',
        ]);

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => false,
            ]);
    }

    public function test_it_allows_multi_country_targets_such_as_usa_uk_australia_philippines_korea_japan_china(): void
    {
        AiBotSetting::current()->update([
            'country_filters' => ['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN'],
        ]);

        // Philippines
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'PH',
            'inquiry_text' => 'We are importer in Manila, need 5000 boxes Paracetamol',
        ])->assertOk()->assertJson(['eligible' => true]);

        // USA
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'USA',
            'inquiry_text' => 'Need bulk formulation supply to USA',
        ])->assertOk()->assertJson(['eligible' => true]);

        // UK
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'UK',
            'inquiry_text' => 'Quote for London buyer',
        ])->assertOk()->assertJson(['eligible' => true]);

        // Australia
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'Australia',
            'inquiry_text' => 'Quote for Sydney delivery',
        ])->assertOk()->assertJson(['eligible' => true]);

        // South Korea
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'South Korea',
            'inquiry_text' => 'Distributor in Seoul',
        ])->assertOk()->assertJson(['eligible' => true]);

        // Japan
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'Japan',
            'inquiry_text' => 'Formulation requirement Tokyo',
        ])->assertOk()->assertJson(['eligible' => true]);

        // China
        $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'China',
            'inquiry_text' => 'Bulk tablets requirement Shanghai',
        ])->assertOk()->assertJson(['eligible' => true]);
    }

    public function test_it_rejects_leads_destined_for_unselected_countries(): void
    {
        AiBotSetting::current()->update([
            'country_filters' => ['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN'],
        ]);

        // Brazil is not in the whitelist
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'BR',
            'inquiry_text' => 'Need shipment to Sao Paulo, Brazil',
        ]);

        $response->assertOk()
            ->assertJson([
                'eligible' => false,
            ]);
        $this->assertStringContainsString('not in your allowed target countries', $response->json('reason'));
    }

    public function test_settings_update_api_saves_reject_domestic_and_country_filters(): void
    {
        $response = $this->putJson('/api/ai-bot/settings', [
            'enabled' => true,
            'reject_domestic' => true,
            'country_filters' => ['USA', 'UK', 'Australia', 'Philippines'],
        ]);

        $response->assertOk();

        $settings = AiBotSetting::current();
        $this->assertTrue($settings->reject_domestic);
        $this->assertEquals(['US', 'GB', 'AU', 'PH'], $settings->country_filters);
    }

    public function test_it_filters_leads_by_target_products(): void
    {
        AiBotSetting::current()->update([
            'country_filters' => ['US', 'PH'],
            'product_filters' => ['Paracetamol', 'Amoxicillin'],
        ]);

        // Matches Paracetamol -> Approved
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'PH',
            'inquiry_text' => 'Requirement for Paracetamol 500mg tablets in Manila',
        ]);
        $response->assertOk()->assertJson(['eligible' => true]);

        // Matches Amoxicillin -> Approved
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'US',
            'product_name' => 'Amoxicillin capsules',
            'inquiry_text' => 'Need 10000 boxes for New York',
        ]);
        $response->assertOk()->assertJson(['eligible' => true]);

        // Unlisted product (Metformin) -> Rejected
        $response = $this->postJson('/api/ai-bot/evaluate', [
            'country' => 'US',
            'inquiry_text' => 'Need Metformin 500mg tablets for export',
        ]);
        $response->assertOk()->assertJson(['eligible' => false]);
        $this->assertStringContainsString('Product does not match your allowed target products', $response->json('reason'));
    }

    public function test_it_adds_and_removes_target_products_via_settings(): void
    {
        // Add single/multiple products
        $this->post('/settings/products/add', [
            'product' => 'Paracetamol 500mg, Ciprofloxacin',
        ])->assertRedirect();

        $settings = AiBotSetting::current()->fresh();
        $this->assertEquals(['Paracetamol 500mg', 'Ciprofloxacin'], $settings->product_filters);

        // Remove a product
        $this->post('/settings/products/remove', [
            'product' => 'Ciprofloxacin',
        ])->assertRedirect();

        $settings = AiBotSetting::current()->fresh();
        $this->assertEquals(['Paracetamol 500mg'], $settings->product_filters);
    }
}

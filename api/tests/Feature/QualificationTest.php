<?php

namespace Tests\Feature;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;
use App\Models\Country;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\Qualification\InquiryExtractor;
use App\Services\Qualification\Qualification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_an_importer(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'We are an importer in Lagos and need 5000 strips of Amoxicillin.',
        ]);

        $this->assertSame(BuyerType::Importer, $qualification->buyerType);
        $this->assertContains('importer', $qualification->buyerTypeKeywords);
    }

    public function test_a_personal_buyer_is_detected(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'Need 2 strips for personal use.',
        ]);

        $this->assertSame(BuyerType::Personal, $qualification->buyerType);
    }

    public function test_a_job_vendor_is_detected_before_a_personal_buyer(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'Job vendor registration for pharma supply, personal visit.',
        ]);

        $this->assertSame(BuyerType::JobVendor, $qualification->buyerType);
    }

    public function test_a_bare_distributor_is_treated_as_a_trader(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'We are a distributor looking for oncology products.',
        ]);

        $this->assertSame(BuyerType::Trader, $qualification->buyerType);
    }

    public function test_an_exporter_is_detected(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'We are an exporter based in Dubai.',
        ]);

        $this->assertSame(BuyerType::ExporterDistributor, $qualification->buyerType);
    }

    public function test_a_pharmacy_is_an_institution(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'Our pharmacy needs a monthly supply.',
        ]);

        $this->assertSame(BuyerType::Institution, $qualification->buyerType);
    }

    public function test_it_reads_intent_keywords(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'Please send your best quotation and price list.',
        ]);

        $this->assertTrue($qualification->hasIntent('price_request'));
        $this->assertTrue($qualification->hasIntent('catalogue_request'));
        $this->assertTrue($qualification->isCommercial());
    }

    public function test_a_target_date_is_detected(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'We need delivery within 15 days.',
        ]);

        $this->assertTrue($qualification->hasTargetDate);
    }

    public function test_the_destination_comes_from_the_payload(): void
    {
        $qualification = $this->extract([
            'SENDER_COUNTRY_ISO' => 'ng',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
        ]);

        $this->assertSame('NG', $qualification->destinationCountry);
        $this->assertSame('payload', $qualification->destinationSource);
    }

    public function test_a_city_resolves_the_destination_when_the_country_is_missing(): void
    {
        $qualification = $this->extract([
            'QUERY_MESSAGE' => 'Delivery to Lagos please.',
        ], withoutCountry: true);

        $this->assertSame('NG', $qualification->destinationCountry);
        $this->assertSame('city', $qualification->destinationSource);
    }

    public function test_an_unclassified_country_is_unknown_and_not_guessed(): void
    {
        $qualification = $this->extract(['SENDER_COUNTRY_ISO' => 'NG']);

        $this->assertSame(DestinationClass::Unknown, $qualification->destinationClass);
    }

    public function test_a_classified_country_takes_its_class_from_the_table(): void
    {
        Country::create([
            'iso2' => 'NG',
            'name' => 'Nigeria',
            'destination_class' => DestinationClass::AllowedProven,
        ]);

        $qualification = $this->extract(['SENDER_COUNTRY_ISO' => 'NG']);

        $this->assertSame(DestinationClass::AllowedProven, $qualification->destinationClass);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function extract(array $overrides = [], bool $withoutCountry = false): Qualification
    {
        $payload = array_merge([
            'UNIQUE_QUERY_ID' => '1',
            'QUERY_TYPE' => 'B',
            'SENDER_NAME' => 'Buyer',
            'SENDER_COMPANY' => 'Buyer Co',
            'SENDER_EMAIL' => 'buyer@example.test',
            'SENDER_MOBILE' => '+234-8000000000',
            'SENDER_COUNTRY_ISO' => 'NG',
            'SENDER_COUNTRY_NAME' => 'Nigeria',
            'QUERY_PRODUCT_NAME' => 'Amoxicillin 500 mg',
            'QUERY_MESSAGE' => 'Please quote.',
        ], $overrides);

        if ($withoutCountry) {
            unset($payload['SENDER_COUNTRY_ISO'], $payload['SENDER_COUNTRY_NAME']);
        }

        $source = LeadSource::create([
            'name' => 'IndiaMART',
            'connector_type' => 'indiamart',
            'integration_status' => 'connected',
        ]);

        $lead = Lead::create([
            'lead_source_id' => $source->id,
            'external_lead_id' => (string) fake()->unique()->numberBetween(1, 1000000),
            'query_type' => 'B',
            'status' => 'received',
            'arrived_at' => now(),
            'payload' => $payload,
        ]);

        return app(InquiryExtractor::class)->extract($lead);
    }
}

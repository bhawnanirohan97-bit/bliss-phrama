<?php

namespace Tests\Unit;

use App\Enums\ProductType;
use App\Services\Catalogue\ProductNameParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductNameParserTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null, string|null}>
     */
    public static function nameProvider(): array
    {
        return [
            'brand with leading strength' => ['2.5mg Letroheal Letrozole Tablets', '2.5 mg', 'Tablets'],
            'strength after generic' => ['Pomabuzz Pomalidomide 4mg Capsule', '4 mg', 'Capsule'],
            'strength with space' => ['Abevmy Bevacizumab 400 Mg Injection', '400 mg', 'Injection'],
            'no strength in name' => ['Kamagra Oral Jelly', null, 'Oral Jelly'],
            'eye drops plural' => ['Careprost Bimatoprost Eye Drops', null, 'Eye Drops'],
            'inhaler' => ['Asthalin Salbutamol Inhaler', null, 'Inhaler'],
            'percentage strength' => ['0.1% Retino A Tretinoin Cream', '0.1%', 'Cream'],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_it_extracts_strength_and_dosage_form(string $name, ?string $strength, ?string $form): void
    {
        $parsed = (new ProductNameParser)->parse($name, null, null, null);

        $this->assertSame($strength, $parsed['strength']);
        $this->assertSame($form, $parsed['dosage_form']);
    }

    public function test_the_strength_column_wins_over_the_name(): void
    {
        $parsed = (new ProductNameParser)->parse('Some Product 500mg Tablet', '250 mg', null, null);

        $this->assertSame('250 mg', $parsed['strength']);
    }

    public function test_it_keeps_both_brand_and_generic_tokens_searchable(): void
    {
        $parsed = (new ProductNameParser)->parse('2.5mg Letroheal Letrozole Tablets', '2.5 mg', '10x10 Tablets', 'Anti Cancer Tablets');

        $this->assertStringContainsString('letroheal', $parsed['search_text']);
        $this->assertStringContainsString('letrozole', $parsed['search_text']);
        $this->assertStringContainsString('10x10', $parsed['search_text']);
    }

    public function test_it_flags_drop_shipping_listings_as_services(): void
    {
        $parsed = (new ProductNameParser)->parse('Anticancer Medicine Drop Shipping Service', null, null, 'Drop Shipping Services');

        $this->assertTrue($parsed['is_service']);
        $this->assertSame(ProductType::Service, $parsed['product_type']);
    }

    public function test_it_flags_a_service_category_even_when_the_name_looks_like_a_product(): void
    {
        $parsed = (new ProductNameParser)->parse('Anti Cancer Medicine Service', null, null, 'Medicine Drop Shippers');

        $this->assertTrue($parsed['is_service']);
    }

    public function test_it_does_not_flag_a_normal_product_as_a_service(): void
    {
        $parsed = (new ProductNameParser)->parse('Cabita Capecitabine Tablets', '500 mg', null, 'Anti Cancer Tablets');

        $this->assertFalse($parsed['is_service']);
        $this->assertSame(ProductType::Formulation, $parsed['product_type']);
    }

    public function test_it_defaults_brand_and_generic_to_null(): void
    {
        $parsed = (new ProductNameParser)->parse('Letroheal Letrozole Tablets', '2.5 mg', null, null);

        $this->assertArrayNotHasKey('brand', $parsed);
        $this->assertArrayNotHasKey('generic_name', $parsed);
    }
}

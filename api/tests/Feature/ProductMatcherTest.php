<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Services\Catalogue\ProductNameParser;
use App\Services\Matching\ProductMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMatcherTest extends TestCase
{
    use RefreshDatabase;

    private ProductMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = app(ProductMatcher::class);
    }

    public function test_it_matches_a_generic_name_with_strength(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg');

        $matches = $this->matcher->match('I need Letrozole 2.5 mg tablets, quantity 5000 boxes');

        $this->assertNotEmpty($matches);
        $this->assertSame('2.5mg Letroheal Letrozole Tablets', $matches[0]->product->name);
        $this->assertTrue($matches[0]->strengthMatched);
        $this->assertTrue($matches[0]->formMatched);
    }

    public function test_it_matches_a_brand_name_alone(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg');

        $matches = $this->matcher->match('want to purchase letroheal');

        $this->assertNotEmpty($matches);
        $this->assertSame('2.5mg Letroheal Letrozole Tablets', $matches[0]->product->name);
    }

    public function test_it_ranks_the_matching_strength_first(): void
    {
        $this->product('5mg Vidalista Tadalafil Tablets', '5 mg');
        $this->product('20mg Vidalista Tadalafil Tablets', '20 mg');

        $matches = $this->matcher->match('Vidalista 20mg tablets');

        $this->assertSame('20mg Vidalista Tadalafil Tablets', $matches[0]->product->name);
        $this->assertTrue($matches[0]->strengthMatched);
    }

    public function test_it_flags_a_strength_conflict(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg');

        $matches = $this->matcher->match('Letrozole 10 mg tablets');

        $this->assertNotEmpty($matches);
        $this->assertFalse($matches[0]->strengthMatched);
        $this->assertTrue($matches[0]->strengthConflicts);
    }

    public function test_it_returns_nothing_for_a_product_it_does_not_stock(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg');

        $matches = $this->matcher->match('Augmentin 625 tablets');

        $this->assertSame([], $matches);
    }

    public function test_it_ignores_unapproved_products_by_default(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg', ProductReviewStatus::Unreviewed);

        $this->assertSame([], $this->matcher->match('letrozole 2.5 mg tablets'));
    }

    public function test_it_can_include_unapproved_products_for_preview(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg', ProductReviewStatus::Unreviewed);

        $matches = $this->matcher->match('letrozole 2.5 mg tablets', approvedOnly: false);

        $this->assertCount(1, $matches);
    }

    public function test_it_never_matches_a_service_listing(): void
    {
        $this->product('Anticancer Medicine Drop Shipping Service', null, ProductReviewStatus::Approved);

        $matches = $this->matcher->match('anticancer medicine drop shipping service', approvedOnly: false);

        $this->assertSame([], $matches);
    }

    public function test_it_returns_at_most_the_requested_number_of_matches(): void
    {
        $this->product('100mg Sildigra Sildenafil Tablets', '100 mg');
        $this->product('100mg Malegra Sildenafil Tablets', '100 mg');
        $this->product('100mg Leeforce Sildenafil Tablets', '100 mg');

        $matches = $this->matcher->match('sildenafil 100mg tablets', limit: 2);

        $this->assertCount(2, $matches);
    }

    public function test_it_returns_nothing_for_an_unrelated_enquiry(): void
    {
        $this->product('2.5mg Letroheal Letrozole Tablets', '2.5 mg');

        $this->assertSame([], $this->matcher->match('I want empty mineral water bottle'));
    }

    private function product(
        string $name,
        ?string $strength,
        ProductReviewStatus $status = ProductReviewStatus::Approved,
    ): Product {
        $category = 'Test Category';
        $parsed = (new ProductNameParser)->parse($name, $strength, null, $category);

        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $category,
            'strength' => $parsed['strength'],
            'dosage_form' => $parsed['dosage_form'],
            'product_type' => $parsed['product_type'],
            'is_service' => $parsed['is_service'],
            'review_status' => $status,
            'search_text' => $parsed['search_text'],
        ]);
    }
}

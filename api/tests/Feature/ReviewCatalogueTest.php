<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_approves_every_product_in_a_category(): void
    {
        $this->product('Letroheal Letrozole Tablets', 'Anti Cancer Tablets');
        $this->product('Fempro Letrozole Tablets', 'Anti Cancer Tablets');
        $this->product('Sildigra Sildenafil Tablets', 'Sildenafil Tablets');

        $this->artisan('catalogue:review', [
            '--category' => ['Anti Cancer Tablets'],
            '--status' => 'approved',
        ])->expectsConfirmation('Mark 2 product(s) as approved?', 'yes')
            ->assertSuccessful();

        $this->assertSame(2, Product::approved()->count());
        $this->assertSame(
            ProductReviewStatus::Unreviewed,
            Product::where('slug', Str::slug('Sildigra Sildenafil Tablets'))->sole()->review_status,
        );
    }

    public function test_it_blocks_by_slug(): void
    {
        $product = $this->product('Trenbolone Acetate Injection', 'Steroid Injections');

        $this->artisan('catalogue:review', [
            '--slug' => [$product->slug],
            '--status' => 'blocked',
        ])->expectsConfirmation('Mark 1 product(s) as blocked?', 'yes')
            ->assertSuccessful();

        $this->assertSame(ProductReviewStatus::Blocked, $product->fresh()->review_status);
    }

    public function test_it_never_touches_service_listings(): void
    {
        $this->product('Anticancer Medicine Drop Shipping Service', 'Drop Shipping Services');

        $this->artisan('catalogue:review', [
            '--category' => ['Drop Shipping Services'],
            '--status' => 'approved',
        ])->assertFailed();

        $this->assertSame(0, Product::approved()->count());
    }

    public function test_it_refuses_to_update_the_whole_catalogue_at_once(): void
    {
        $this->product('Letroheal Letrozole Tablets', 'Anti Cancer Tablets');

        $this->artisan('catalogue:review', ['--status' => 'approved'])
            ->expectsOutputToContain('Refusing to update the whole catalogue at once')
            ->assertFailed();

        $this->assertSame(0, Product::approved()->count());
    }

    public function test_it_can_reset_a_decision_to_unreviewed(): void
    {
        $product = $this->product('Letroheal Letrozole Tablets', 'Anti Cancer Tablets', ProductReviewStatus::Approved);

        $this->artisan('catalogue:review', [
            '--slug' => [$product->slug],
            '--status' => 'unreviewed',
        ])->expectsConfirmation('Mark 1 product(s) as unreviewed?', 'yes')
            ->assertSuccessful();

        $this->assertSame(ProductReviewStatus::Unreviewed, $product->fresh()->review_status);
    }

    private function product(
        string $name,
        string $category,
        ProductReviewStatus $status = ProductReviewStatus::Unreviewed,
    ): Product {
        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $category,
            'strength' => null,
            'dosage_form' => null,
            'product_type' => str_contains($name, 'Service') ? 'service' : 'formulation',
            'is_service' => str_contains($name, 'Service'),
            'review_status' => $status,
            'search_text' => Str::lower($name),
        ]);
    }
}

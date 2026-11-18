<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalogue_page_shows_the_bulk_add_form(): void
    {
        $this->get('/catalogue')
            ->assertOk()
            ->assertSee('Add products by paste')
            ->assertSee('name="products"', false);
    }

    public function test_it_adds_multiple_products_from_pasted_lines(): void
    {
        $response = $this->post('/catalogue/products', [
            'products' => "500mg Dolo Paracetamol Tablets | Pain Killer Medicine | 500mg | Tablets | 1x15 Tablets | 1000 strips\n"
                ."Metformin 500mg Tablets\tAnti Diabetic\t500mg\tTablets\t1x20 Tablets",
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('products', 2);

        $paracetamol = Product::query()->where('slug', '500mg-dolo-paracetamol-tablets')->sole();

        $this->assertSame('Pain Killer Medicine', $paracetamol->category);
        $this->assertSame('500mg', $paracetamol->strength);
        $this->assertSame('Tablets', $paracetamol->dosage_form);
        $this->assertSame('1x15 Tablets', $paracetamol->packaging);
        $this->assertSame('1000 strips', $paracetamol->moq);
        $this->assertSame(ProductType::Formulation, $paracetamol->product_type);
        $this->assertSame(ProductReviewStatus::Unreviewed, $paracetamol->review_status);
        $this->assertStringContainsString('paracetamol', $paracetamol->search_text);
        $this->assertStringContainsString('pain killer medicine', $paracetamol->search_text);

        $metformin = Product::query()->where('slug', 'metformin-500mg-tablets')->sole();

        $this->assertSame('Anti Diabetic', $metformin->category);
        $this->assertSame('500mg', $metformin->strength);
        $this->assertSame('Tablets', $metformin->dosage_form);
    }

    public function test_only_the_name_is_required_and_details_are_derived(): void
    {
        $this->post('/catalogue/products', [
            'products' => 'Paracetamol 500mg Tablets',
        ])->assertRedirect();

        $product = Product::sole();

        $this->assertSame('Uncategorised', $product->category);
        $this->assertSame('500 mg', $product->strength);
        $this->assertSame('Tablets', $product->dosage_form);
        $this->assertSame(ProductReviewStatus::Unreviewed, $product->review_status);
        $this->assertSame(0, Product::query()->approved()->count());
    }

    public function test_a_spreadsheet_header_row_is_skipped(): void
    {
        $this->post('/catalogue/products', [
            'products' => "Name | Category | Strength | Form | Pack size | MOQ\nParacetamol 500mg Tablets",
        ])->assertRedirect();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseMissing('products', ['name' => 'Name']);
    }

    public function test_duplicate_lines_are_skipped(): void
    {
        $response = $this->post('/catalogue/products', [
            'products' => "Paracetamol 500mg Tablets\nParacetamol 500mg Tablets",
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $response->assertSessionHas('status', '1 product(s) added as unreviewed. 1 duplicate(s) skipped.');
    }

    public function test_an_existing_product_is_not_added_again(): void
    {
        Product::query()->create([
            'name' => 'Paracetamol 500mg Tablets',
            'slug' => 'paracetamol-500mg-tablets',
            'search_text' => 'paracetamol 500mg tablets',
        ]);

        $this->post('/catalogue/products', [
            'products' => 'Paracetamol 500mg Tablets',
        ])->assertRedirect();

        $this->assertDatabaseCount('products', 1);
    }

    public function test_service_listings_are_skipped(): void
    {
        $response = $this->post('/catalogue/products', [
            'products' => 'Pharmacy Management Services | Services',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(0, Product::query()->matchable()->count());
        $response->assertSessionHas('product_import', function (array $report): bool {
            return count($report['issues']) === 1
                && str_contains($report['issues'][0], 'looks like a service');
        });
    }

    public function test_invalid_rows_do_not_block_valid_ones(): void
    {
        $response = $this->post('/catalogue/products', [
            'products' => "| Pain Killer Medicine\nParacetamol 500mg Tablets",
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('products', 1);
        $response->assertSessionHas('product_import', function (array $report): bool {
            return count($report['issues']) === 1
                && str_contains($report['issues'][0], 'missing product name');
        });
    }

    public function test_a_paste_is_limited_to_500_products(): void
    {
        $response = $this->post('/catalogue/products', [
            'products' => implode("\n", array_fill(0, 501, 'Paracetamol 500mg Tablets')),
        ]);

        $response->assertSessionHasErrors('products');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_two_names_with_the_same_slug_are_not_duplicated(): void
    {
        $this->post('/catalogue/products', [
            'products' => "Paracetamol 500mg Tablets!\nParacetamol 500mg Tablets",
        ])->assertRedirect();

        $this->assertDatabaseCount('products', 1);
    }
}

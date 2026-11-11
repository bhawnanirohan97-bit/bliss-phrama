<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'catalogue_').'.csv';

        // Mirrors a real spreadsheet export: UTF-8 BOM before the opening
        // quote, which is what broke the first column on the live import.
        $csv = "\xEF\xBB\xBF\"category\",\"name\",\"strength\",\"packaging\",\"moq\",\"slug\",\"url\"\n"
            .'"Anti Cancer Tablets","2.5mg Letroheal Letrozole Tablets","2.5 mg","10x10 Tablets","As per requirement","letroheal","https://example.test/letroheal"'."\n"
            .'"Drop Shipping Services","Anticancer Medicine Drop Shipping Service","","Drops","","drop-ship","https://example.test/drop-ship"'."\n"
            .'"Anti Cancer Tablets","No Strength Product Tablet","","Box","","no-strength",""'."\n";

        file_put_contents($this->path, $csv);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_imports_every_row(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        $this->assertDatabaseCount('products', 3);
    }

    public function test_it_reads_the_category_column_even_with_a_bom(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        $this->assertSame(
            'Anti Cancer Tablets',
            Product::where('slug', 'letroheal')->sole()->category,
        );
    }

    public function test_it_derives_strength_and_dosage_form(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        $product = Product::where('slug', 'letroheal')->sole();

        $this->assertSame('2.5 mg', $product->strength);
        $this->assertSame('Tablets', $product->dosage_form);
        $this->assertStringContainsString('letrozole', $product->search_text);
    }

    public function test_it_marks_service_listings_and_excludes_them_from_matching(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        $service = Product::where('slug', 'drop-ship')->sole();

        $this->assertTrue($service->is_service);
        $this->assertSame(ProductType::Service, $service->product_type);
        $this->assertSame(2, Product::matchable()->count());
    }

    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        $this->assertDatabaseCount('products', 3);
    }

    public function test_prune_removes_rows_that_disappeared_from_the_file(): void
    {
        $this->artisan('catalogue:import', ['path' => $this->path])->assertSuccessful();

        file_put_contents(
            $this->path,
            "\"category\",\"name\",\"strength\",\"packaging\",\"moq\",\"slug\",\"url\"\n"
            .'"Anti Cancer Tablets","2.5mg Letroheal Letrozole Tablets","2.5 mg","10x10 Tablets","As per requirement","letroheal","https://example.test/letroheal"'."\n",
        );

        $this->artisan('catalogue:import', ['path' => $this->path, '--prune' => true])->assertSuccessful();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['slug' => 'letroheal']);
    }

    public function test_it_fails_clearly_when_the_file_is_missing(): void
    {
        $this->artisan('catalogue:import', ['path' => 'C:\\does\\not\\exist.csv'])
            ->assertFailed();

        $this->assertDatabaseCount('products', 0);
    }
}

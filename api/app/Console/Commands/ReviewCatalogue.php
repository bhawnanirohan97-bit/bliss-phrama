<?php

namespace App\Console\Commands;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use Illuminate\Console\Command;

class ReviewCatalogue extends Command
{
    protected $signature = 'catalogue:review
        {--list : Show review progress by category}
        {--category=* : Category name to act on (repeatable, exact match)}
        {--slug=* : Product slug to act on (repeatable)}
        {--status= : approved, blocked, or unreviewed}';

    protected $description = 'Review catalogue SKUs for export eligibility, in bulk';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->showProgress();
        }

        $status = $this->option('status');

        if ($status === null) {
            if (! $this->option('list')) {
                $this->error('Nothing to do. Pass --list, or --status with --category/--slug.');
                $this->line('  php artisan catalogue:review --list');
                $this->line('  php artisan catalogue:review --category="Steroids Tablets" --status=blocked');
                $this->line('  php artisan catalogue:review --slug=letroheal --status=approved');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $reviewStatus = ProductReviewStatus::tryFrom($status);

        if ($reviewStatus === null) {
            $this->error("Unknown status: {$status}. Use approved, blocked, or unreviewed.");

            return self::FAILURE;
        }

        $categories = (array) $this->option('category');
        $slugs = (array) $this->option('slug');

        if ($categories === [] && $slugs === []) {
            $this->error('Refusing to update the whole catalogue at once. Narrow it with --category or --slug.');

            return self::FAILURE;
        }

        $query = Product::query()->matchable();

        $query->where(function ($inner) use ($categories, $slugs) {
            if ($categories !== []) {
                $inner->orWhereIn('category', $categories);
            }

            if ($slugs !== []) {
                $inner->orWhereIn('slug', $slugs);
            }
        });

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('No products matched. Run --list to see exact category names.');

            return self::FAILURE;
        }

        $this->table(
            ['Name', 'Category', 'Strength'],
            $products->take(10)->map(fn (Product $p) => [$p->name, $p->category, $p->strength ?? '—'])->all(),
        );

        if ($products->count() > 10) {
            $this->line('… and '.($products->count() - 10).' more.');
        }

        if (! $this->confirm("Mark {$products->count()} product(s) as {$status}?", true)) {
            return self::FAILURE;
        }

        foreach ($products as $product) {
            match ($reviewStatus) {
                ProductReviewStatus::Approved => $product->approve(),
                ProductReviewStatus::Blocked => $product->block(),
                ProductReviewStatus::Unreviewed => $product->forceFill([
                    'review_status' => ProductReviewStatus::Unreviewed,
                    'reviewed_at' => null,
                ])->save(),
            };
        }

        $this->info("Updated {$products->count()} product(s) to {$status}.");

        return self::SUCCESS;
    }

    private function showProgress(): void
    {
        $rows = Product::query()
            ->matchable()
            ->selectRaw('category, count(*) as total')
            ->selectRaw("sum(case when review_status = 'approved' then 1 else 0 end) as approved")
            ->selectRaw("sum(case when review_status = 'blocked' then 1 else 0 end) as blocked")
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                $row->category,
                $row->total,
                $row->approved,
                $row->blocked,
                $row->total - $row->approved - $row->blocked,
            ])
            ->all();

        $this->table(['Category', 'Total', 'Approved', 'Blocked', 'Unreviewed'], $rows);

        $this->table(['Total', 'Approved', 'Blocked', 'Unreviewed'], [[
            Product::matchable()->count(),
            Product::approved()->count(),
            Product::where('review_status', ProductReviewStatus::Blocked->value)->count(),
            Product::where('review_status', ProductReviewStatus::Unreviewed->value)->count(),
        ]]);
    }
}

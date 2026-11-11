<?php

namespace App\Http\Controllers;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();
        $search = $request->string('q')->trim()->toString();

        $products = Product::query()
            ->matchable()
            ->when($status !== '', fn ($query) => $query->where('review_status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $like = '%'.$search.'%';

                $inner->where('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('strength', 'like', $like);
            }))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $categorySummary = Product::query()
            ->matchable()
            ->selectRaw('category, count(*) as total')
            ->selectRaw("sum(case when review_status = 'approved' then 1 else 0 end) as approved")
            ->selectRaw("sum(case when review_status = 'blocked' then 1 else 0 end) as blocked")
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return view('catalogue.index', [
            'products' => $products,
            'categorySummary' => $categorySummary,
            'status' => $status,
            'category' => $category,
            'search' => $search,
            'statuses' => ProductReviewStatus::cases(),
            'totals' => [
                'matchable' => Product::query()->matchable()->count(),
                'approved' => Product::query()->approved()->count(),
                'blocked' => Product::query()->where('review_status', ProductReviewStatus::Blocked->value)->count(),
                'unreviewed' => Product::query()->where('review_status', ProductReviewStatus::Unreviewed->value)->count(),
            ],
        ]);
    }

    /**
     * Apply one review decision to an explicit set of SKUs.
     *
     * The target set is always named by slug, never by "everything that
     * matches this filter": an accidental click must not approve 923 SKUs.
     */
    public function review(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ProductReviewStatus::class)],
            'slugs' => ['required_without:category', 'array'],
            'slugs.*' => ['string'],
            'category' => ['required_without:slugs', 'string'],
        ]);

        $status = ProductReviewStatus::from($validated['status']);

        $query = Product::query()->matchable();

        if (! empty($validated['slugs'])) {
            $query->whereIn('slug', $validated['slugs']);
        } else {
            $query->where('category', $validated['category']);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            return back()->withErrors(['review' => 'No matchable products matched that selection.']);
        }

        foreach ($products as $product) {
            match ($status) {
                ProductReviewStatus::Approved => $product->approve(),
                ProductReviewStatus::Blocked => $product->block(),
                ProductReviewStatus::Unreviewed => $product->forceFill([
                    'review_status' => ProductReviewStatus::Unreviewed,
                    'reviewed_at' => null,
                ])->save(),
            };
        }

        $verb = match ($status) {
            ProductReviewStatus::Approved => 'approved',
            ProductReviewStatus::Blocked => 'blocked',
            ProductReviewStatus::Unreviewed => 'reset to unreviewed',
        };

        return back()->with('status', $products->count()." product(s) {$verb}.");
    }
}

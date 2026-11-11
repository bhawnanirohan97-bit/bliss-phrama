<?php

namespace App\Http\Controllers;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'dosage_form' => ['nullable', 'string', 'max:100'],
            'packaging' => ['nullable', 'string', 'max:100'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug ?: 'prod-' . time();
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $product = Product::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'category' => $validated['category'] ?: 'Pharmaceutical Formulation',
            'strength' => $validated['strength'] ?? null,
            'dosage_form' => $validated['dosage_form'] ?? 'Tablet',
            'packaging' => $validated['packaging'] ?? null,
            'is_service' => false,
            'review_status' => ProductReviewStatus::Approved,
            'reviewed_at' => now(),
            'search_text' => strtolower($validated['name'] . ' ' . ($validated['strength'] ?? '') . ' ' . ($validated['dosage_form'] ?? '')),
        ]);

        return back()->with('status', "Product '{$product->name}' added to catalogue and approved for AI buying.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $product = Product::where('slug', $validated['slug'])->first();
        if (! $product) {
            return back()->withErrors(['product' => 'Product not found.']);
        }

        $name = $product->name;
        $product->delete();

        return back()->with('status', "Product '{$name}' permanently removed from catalogue.");
    }

    public function toggleStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string'],
            'action' => ['required', 'in:block,approve'],
        ]);

        $product = Product::where('slug', $validated['slug'])->first();
        if (! $product) {
            return back()->withErrors(['product' => 'Product not found.']);
        }

        if ($validated['action'] === 'block') {
            $product->block();
            return back()->with('status', "Product '{$product->name}' blocked and removed from AI buying.");
        } else {
            $product->approve();
            return back()->with('status', "Product '{$product->name}' approved for AI buying.");
        }
    }
}

<?php

namespace App\Services\Matching;

use App\Models\Product;

/**
 * Resolves a therapeutic category from free text when no SKU matches.
 *
 * "Anti cancer tablets" is a category, not a product. Knowing it matched is
 * worth 7 points, but only when exactly one category fits: after stopword
 * removal "Anti Cancer Tablets", "Anti Cancer Capsule" and "Anti Cancer
 * Injection" are the same token set, and picking one at random would invent
 * information.
 */
final class CategoryResolver
{
    /**
     * @var list<array{tokens: list<string>, name: string}>|null
     */
    private ?array $categories = null;

    public function __construct(private readonly Tokenizer $tokenizer) {}

    public function resolve(string $query): ?string
    {
        $queryTokens = $this->tokenizer->tokens($query);

        if ($queryTokens === []) {
            return null;
        }

        $hits = [];

        foreach ($this->categories() as $category) {
            if ($category['tokens'] === []) {
                continue;
            }

            if (array_diff($category['tokens'], $queryTokens) === []) {
                $hits[] = $category['name'];
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * @return list<array{tokens: list<string>, name: string}>
     */
    private function categories(): array
    {
        if ($this->categories !== null) {
            return $this->categories;
        }

        $this->categories = Product::query()
            ->matchable()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $name) => [
                'name' => $name,
                'tokens' => $this->tokenizer->tokens($name),
            ])
            ->all();

        return $this->categories;
    }
}

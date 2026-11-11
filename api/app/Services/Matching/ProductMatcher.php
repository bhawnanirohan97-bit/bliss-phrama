<?php

namespace App\Services\Matching;

use App\Models\Product;
use App\Services\Catalogue\ProductNameParser;
use Illuminate\Support\Collection;

/**
 * Ranks catalogue SKUs against whatever a buyer wrote.
 *
 * Buyers do not quote catalogue names. They write "Amoxy 500 cap", "letrozole
 * 2.5mg tab", or just a brand. Scoring therefore uses inverse document
 * frequency over catalogue tokens: a rare token like "letrozole" carries far
 * more signal than a common one like "tablet", so a generic name, a brand, or a
 * misspelling with a shared root still surfaces the right SKU.
 *
 * Strength and dosage form are compared separately and add a bounded bonus,
 * because "2.5 mg" and "10 mg" of the same drug are different products.
 */
final class ProductMatcher
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly ProductNameParser $parser,
    ) {}

    /**
     * @return list<ProductMatch>
     */
    public function match(
        string $query,
        int $limit = 5,
        float $minimumScore = 0.30,
        bool $approvedOnly = true,
    ): array {
        $queryTokens = $this->tokenizer->tokens($query);

        if ($queryTokens === []) {
            return [];
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->matchable()
            ->when($approvedOnly, fn ($builder) => $builder->approved())
            ->get(['id', 'name', 'slug', 'category', 'strength', 'dosage_form', 'packaging', 'moq', 'search_text', 'review_status']);

        if ($products->isEmpty()) {
            return [];
        }

        $documentTokens = [];
        $nameTokens = [];
        $documentFrequency = [];

        foreach ($products as $product) {
            $tokens = array_flip($this->tokenizer->tokens($product->search_text));
            $documentTokens[$product->id] = $tokens;
            $nameTokens[$product->id] = array_values(array_unique($this->tokenizer->tokens($product->name)));

            foreach (array_keys($tokens) as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }

        $documentCount = $products->count();
        $queryStrength = $this->normaliseStrength($this->parser->detectStrength($query));
        $queryForm = $this->normaliseForm($this->parser->detectDosageForm($query, anywhere: true));

        $totalWeight = 0.0;

        foreach ($queryTokens as $token) {
            // A token the catalogue has never seen cannot identify an SKU, so
            // it must not dilute coverage. Otherwise every chatty IndiaMART
            // message ("within 15 days", "kindly advise") would score the
            // right product below the floor.
            if (($documentFrequency[$token] ?? 0) === 0) {
                continue;
            }

            $totalWeight += $this->idf($token, $documentFrequency, $documentCount);
        }

        if ($totalWeight <= 0.0) {
            return [];
        }

        $matches = [];

        foreach ($products as $product) {
            $matched = array_values(array_intersect($queryTokens, array_keys($documentTokens[$product->id])));

            if ($matched === []) {
                continue;
            }

            $weight = 0.0;

            foreach ($matched as $token) {
                $weight += $this->idf($token, $documentFrequency, $documentCount);
            }

            $coverage = $weight / $totalWeight;

            // Precision guards against a product whose name also carries a
            // dozen unrelated words: it must not outrank a tighter match.
            $productNameTokens = $nameTokens[$product->id];
            $nameHits = $productNameTokens === []
                ? 0
                : count(array_intersect($matched, $productNameTokens));
            $precision = $productNameTokens === []
                ? 0.0
                : $nameHits / count($productNameTokens);

            $productStrength = $this->normaliseStrength($product->strength);

            $strengthMatched = $queryStrength !== null && $productStrength === $queryStrength;
            $strengthConflicts = $queryStrength !== null
                && $productStrength !== null
                && $productStrength !== $queryStrength;

            $formMatched = $queryForm !== null
                && $this->normaliseForm($product->dosage_form) === $queryForm;

            $score = ($coverage * 0.55)
                + ($precision * 0.20)
                + ($strengthMatched ? 0.15 : 0.0)
                + ($formMatched ? 0.10 : 0.0);

            $score = round(min(1.0, $score), 3);

            if ($score < $minimumScore) {
                continue;
            }

            $matches[] = new ProductMatch(
                $product,
                $score,
                $matched,
                $strengthMatched,
                $strengthConflicts,
                $formMatched,
            );
        }

        usort($matches, function (ProductMatch $a, ProductMatch $b): int {
            return [$b->score, $b->strengthMatched, $b->product->id]
                <=> [$a->score, $a->strengthMatched, $a->product->id];
        });

        return array_slice($matches, 0, $limit);
    }

    /**
     * A token present in every product is worthless; one present in a single
     * product identifies it.
     *
     * @param  array<string, int>  $documentFrequency
     */
    private function idf(string $token, array $documentFrequency, int $documentCount): float
    {
        $frequency = $documentFrequency[$token] ?? 0;

        return log(($documentCount + 1) / ($frequency + 1)) + 1.0;
    }

    private function normaliseStrength(?string $strength): ?string
    {
        if ($strength === null) {
            return null;
        }

        $strength = mb_strtolower(str_replace(' ', '', $strength));

        return $strength === '' ? null : $strength;
    }

    private function normaliseForm(?string $form): ?string
    {
        if ($form === null) {
            return null;
        }

        $form = mb_strtolower(trim($form));

        // "Tablet" and "Tablets" are the same dosage form.
        return $form !== '' && str_ends_with($form, 's') ? mb_substr($form, 0, -1) : $form;
    }
}

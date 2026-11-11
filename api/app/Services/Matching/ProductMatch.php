<?php

namespace App\Services\Matching;

use App\Models\Product;

final readonly class ProductMatch
{
    /**
     * @param  list<string>  $matchedTokens
     */
    public function __construct(
        public Product $product,
        public float $score,
        public array $matchedTokens,
        public bool $strengthMatched,
        public bool $strengthConflicts,
        public bool $formMatched,
    ) {}
}

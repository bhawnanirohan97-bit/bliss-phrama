<?php

namespace App\Services\Qualification;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;

/**
 * Everything the deterministic extractor could read out of one lead.
 *
 * It records what the buyer wrote and nothing more. Intent, completeness and
 * the final band are decided by the Assessor, because those depend on the
 * catalogue match as well.
 */
final readonly class Qualification
{
    /**
     * @param  list<string>  $buyerTypeKeywords
     * @param  list<string>  $intents  fired intent rule names, e.g. `price_request`
     */
    public function __construct(
        public string $text,
        public BuyerType $buyerType,
        public array $buyerTypeKeywords,
        public bool $buyerTypeConflict,
        public array $intents,
        public ?Quantity $quantity,
        public ?string $destinationCountry,
        public ?string $destinationName,
        public DestinationClass $destinationClass,
        public string $destinationSource,
        public bool $hasTargetDate,
    ) {}

    public function hasIntent(string $intent): bool
    {
        return in_array($intent, $this->intents, true);
    }

    /**
     * The buyer asked to buy, not merely to browse.
     */
    public function isCommercial(): bool
    {
        return $this->intents !== [];
    }
}

<?php

namespace App\Services\Policy;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;
use App\Enums\MatchState;
use App\Enums\PurchaseIntent;
use App\Enums\ScoreBand;
use App\Models\Product;

/**
 * The complete evidence for one scoring decision.
 *
 * `toAttributes()` is the only way this reaches the database, which keeps the
 * stored row an exact copy of what the policy computed.
 */
final readonly class Assessment
{
    /**
     * @param  list<string>  $gates
     * @param  list<string>  $reasons
     * @param  list<string>  $missingInformation
     * @param  list<string>  $riskFlags
     * @param  list<array<string, mixed>>  $candidates
     */
    public function __construct(
        public string $policyVersion,
        public MatchState $matchState,
        public ?Product $product,
        public ?float $matchScore,
        public int $productMatch,
        public int $commercialValue,
        public int $destination,
        public int $buyerTypeIntent,
        public int $completeness,
        public int $overallScore,
        public ScoreBand $band,
        public ?string $destinationCountry,
        public DestinationClass $destinationClass,
        public ?int $requestedQuantity,
        public ?string $requestedQuantityUnit,
        public BuyerType $buyerType,
        public PurchaseIntent $purchaseIntent,
        public array $gates,
        public array $reasons,
        public array $missingInformation,
        public array $riskFlags,
        public array $candidates,
        public string $explanation,
    ) {}

    public function hardRejected(): bool
    {
        return $this->gates !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(int $leadId): array
    {
        return [
            'lead_id' => $leadId,
            'policy_version' => $this->policyVersion,
            'sku_id' => $this->product?->id,
            'match_state' => $this->matchState->value,
            'match_score' => $this->matchScore,
            'product_match' => $this->productMatch,
            'commercial_value' => $this->commercialValue,
            'destination' => $this->destination,
            'buyer_type_intent' => $this->buyerTypeIntent,
            'completeness' => $this->completeness,
            'overall_score' => $this->overallScore,
            'band' => $this->band->value,
            'band_rank' => $this->band->rank(),
            'destination_country' => $this->destinationCountry,
            'destination_class' => $this->destinationClass->value,
            'requested_quantity' => $this->requestedQuantity,
            'requested_quantity_unit' => $this->requestedQuantityUnit,
            'buyer_type' => $this->buyerType->value,
            'purchase_intent' => $this->purchaseIntent->value,
            'gates' => $this->gates,
            'reasons' => $this->reasons,
            'missing_information' => $this->missingInformation,
            'risk_flags' => $this->riskFlags,
            'candidates' => $this->candidates,
            'explanation' => $this->explanation,
        ];
    }
}

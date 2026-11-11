<?php

namespace App\Services\Policy;

use App\Enums\BuyerType;
use App\Enums\DestinationClass;
use App\Enums\MatchState;
use App\Enums\PurchaseIntent;
use App\Enums\ScoreBand;
use App\Models\Lead;
use App\Services\Matching\CategoryResolver;
use App\Services\Matching\ProductMatch;
use App\Services\Qualification\Qualification;

/**
 * The decision layer (PRD 5.2 and 5.3).
 *
 * Every number is computed from data, never from a model. The components are
 * weighted 35/20/20/15/10, hard gates are evaluated first, and the band is a
 * pure function of the total plus the component admission minimums.
 *
 * Two gates in the PRD are deliberately not reachable yet:
 *
 *  - `product_restricted_in_destination` needs a per-SKU restricted-country
 *    list, which the catalogue does not have.
 *  - `allowance_exhausted` belongs to the weekly allowance ledger, which is a
 *    later stage. The gate is not invented here.
 */
final class Assessor
{
    public function __construct(private readonly CategoryResolver $categories) {}

    /**
     * @param  list<ProductMatch>  $matches
     */
    public function assess(Lead $lead, Qualification $qualification, array $matches): Assessment
    {
        $floor = (float) config('pharmalead.matcher_floor', 0.55);

        /** @var list<ProductMatch> $rated */
        $rated = array_values(array_filter(
            $matches,
            fn (ProductMatch $match) => $match->score >= $floor,
        ));

        $top = $rated[0] ?? null;
        $category = $top === null
            ? $this->categories->resolve($lead->enquiryText())
            : null;

        $matchState = $this->matchState($top, $category);
        $productPresent = $lead->productName() !== null || $top !== null || $category !== null;

        $intent = $this->intent($qualification, $productPresent);
        $gates = $this->gates($lead, $qualification, $top);

        $commercial = $this->commercialValue($qualification, $top?->product?->moq);
        $destination = $qualification->destinationClass->points();
        $buyerType = $qualification->buyerType->firesGate() ? 0 : $qualification->buyerType->points();
        $buyerTypeIntent = $buyerType + $intent->points();
        $completeness = $this->completeness($lead, $qualification, $productPresent);

        $productPoints = $matchState->points();
        $overall = $productPoints + $commercial + $destination + $buyerTypeIntent + $completeness;
        $band = $this->band($overall, $productPoints, $destination, $gates);

        $missing = $this->missingInformation($lead, $qualification, $productPresent);
        $risks = $this->riskFlags($lead, $qualification, $matchState, $rated, $top);

        $reasons = $this->reasons(
            $top,
            $matchState,
            $qualification,
            $commercial,
            $destination,
            $buyerTypeIntent,
            $intent,
            $completeness,
            $gates,
        );

        return new Assessment(
            policyVersion: (string) config('pharmalead.policy_version', 'v1.1.0'),
            matchState: $matchState,
            product: $top?->product,
            matchScore: $top?->score,
            productMatch: $productPoints,
            commercialValue: $commercial,
            destination: $destination,
            buyerTypeIntent: $buyerTypeIntent,
            completeness: $completeness,
            overallScore: $overall,
            band: $band,
            destinationCountry: $qualification->destinationCountry,
            destinationClass: $qualification->destinationClass,
            requestedQuantity: $qualification->quantity?->value,
            requestedQuantityUnit: $qualification->quantity?->schemaUnit(),
            buyerType: $qualification->buyerType,
            purchaseIntent: $intent,
            gates: $gates,
            reasons: $reasons,
            missingInformation: $missing,
            riskFlags: $risks,
            candidates: $this->candidates($rated),
            explanation: $this->explanation($band, $overall, $reasons, $gates),
        );
    }

    private function matchState(?ProductMatch $top, ?string $category): MatchState
    {
        if ($top === null) {
            return $category !== null ? MatchState::CategoryOnly : MatchState::None;
        }

        if ($top->score >= 0.85 && $top->strengthMatched && $top->formMatched) {
            return MatchState::Exact;
        }

        if ($top->score >= 0.65 && $top->strengthMatched && ! $top->strengthConflicts) {
            return MatchState::Strong;
        }

        return MatchState::Partial;
    }

    private function intent(Qualification $qualification, bool $productPresent): PurchaseIntent
    {
        if ($qualification->quantity !== null && $qualification->hasTargetDate) {
            return PurchaseIntent::ExplicitWithDate;
        }

        if ($qualification->quantity !== null) {
            return PurchaseIntent::ExplicitWithQuantity;
        }

        if ($productPresent && ($qualification->hasIntent('price_request') || $qualification->hasIntent('catalogue_request'))) {
            return PurchaseIntent::PriceEnquiry;
        }

        if ($productPresent) {
            return PurchaseIntent::AvailabilityEnquiry;
        }

        return PurchaseIntent::NonCommercial;
    }

    /**
     * @return list<string>
     */
    private function gates(Lead $lead, Qualification $qualification, ?ProductMatch $top): array
    {
        $gates = [];

        if ($qualification->buyerType === BuyerType::Personal) {
            $gates[] = 'buyer_personal';
        }

        if ($qualification->buyerType === BuyerType::JobVendor) {
            $gates[] = 'buyer_job_vendor';
        }

        if ($qualification->destinationClass === DestinationClass::Blocked) {
            $gates[] = 'destination_blocked';
        }

        // The operator sells for export only. A domestic enquiry is not
        // rejected by the PRD's closed gate list, so it is recorded under the
        // destination gate and the switch can be turned off if a domestic
        // trader turns out to be a legitimate re-export buyer.
        if (
            (bool) config('pharmalead.reject_domestic', true)
            && $qualification->destinationCountry === 'IN'
            && ! in_array('destination_blocked', $gates, true)
        ) {
            $gates[] = 'destination_blocked';
        }

        if ($top !== null && $top->product->isBlocked()) {
            $gates[] = 'product_not_export_eligible';
        }

        if ($lead->email() === null && $lead->phone() === null) {
            $gates[] = 'contact_missing';
        }

        return array_values(array_unique($gates));
    }

    private function commercialValue(Qualification $qualification, ?string $moq): int
    {
        $quantity = $qualification->quantity;

        if ($quantity === null) {
            return 0;
        }

        $moqUnits = $this->leadingNumber($moq);

        if ($moqUnits !== null && $moqUnits > 0) {
            $ratio = $quantity->value / $moqUnits;

            $points = match (true) {
                $ratio >= 10 => 20,
                $ratio >= 3 => 15,
                $ratio >= 1 => 10,
                default => 5,
            };

            return $quantity->conflict ? min($points, 8) : $points;
        }

        // No usable MOQ. Fall back to absolute base-unit bands from the AI
        // engine spec, capped below the MOQ-based maximum so that a real MOQ
        // comparison always outranks a guess.
        $points = match (true) {
            $quantity->value >= 10_000 => 18,
            $quantity->value >= 1_000 => 14,
            $quantity->value >= 100 => 9,
            $quantity->value >= 10 => 5,
            default => 2,
        };

        return $quantity->conflict ? min($points, 8) : $points;
    }

    private function leadingNumber(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\d[\d,.]*/', $value, $match) !== 1) {
            return null;
        }

        $number = str_replace(',', '', $match[0]);

        return is_numeric($number) ? (int) $number : null;
    }

    private function completeness(Lead $lead, Qualification $qualification, bool $productPresent): int
    {
        $quantity = $qualification->quantity !== null;
        $destination = $qualification->destinationCountry !== null;
        $company = $lead->companyName() !== null;
        $contact = $lead->email() !== null || $lead->phone() !== null;

        return match (true) {
            $productPresent && $quantity && $destination && $company && $contact => 10,
            $productPresent && $quantity && $destination && $contact => 8,
            $productPresent && $quantity && $contact => 6,
            $productPresent && $contact => 4,
            $productPresent => 2,
            default => 0,
        };
    }

    /**
     * @param  list<string>  $gates
     */
    private function band(int $total, int $productMatch, int $destination, array $gates): ScoreBand
    {
        if ($gates !== []) {
            return ScoreBand::HardReject;
        }

        // Component minimums are admission rules, not extra gates.
        if ($total >= 85 && $productMatch >= 21 && $destination >= 14) {
            return ScoreBand::ActNow;
        }

        if ($total >= 70 && $productMatch >= 14 && $destination >= 8) {
            return ScoreBand::Good;
        }

        return match (true) {
            $total >= 55 => ScoreBand::Review,
            $total >= 40 => ScoreBand::Low,
            default => ScoreBand::Skip,
        };
    }

    /**
     * @return list<string>
     */
    private function missingInformation(Lead $lead, Qualification $qualification, bool $productPresent): array
    {
        $missing = [];

        if (! $productPresent) {
            $missing[] = 'product';
        }

        if ($qualification->quantity === null) {
            $missing[] = 'quantity';
        } elseif ($qualification->quantity->unit === null) {
            $missing[] = 'quantity_unit';
        }

        if ($qualification->destinationCountry === null) {
            $missing[] = 'destination';
        }

        if ($qualification->buyerType === BuyerType::Unknown) {
            $missing[] = 'buyer_type';
        }

        if ($lead->companyName() === null) {
            $missing[] = 'company_name';
        }

        if ($lead->buyerName() === null) {
            $missing[] = 'buyer_name';
        }

        if ($lead->email() === null) {
            $missing[] = 'email';
        }

        if ($lead->phone() === null) {
            $missing[] = 'phone';
        }

        if (! $qualification->hasTargetDate) {
            $missing[] = 'target_date';
        }

        return $missing;
    }

    /**
     * @param  list<ProductMatch>  $rated
     * @return list<string>
     */
    private function riskFlags(
        Lead $lead,
        Qualification $qualification,
        MatchState $matchState,
        array $rated,
        ?ProductMatch $top,
    ): array {
        $flags = [];

        if ($matchState === MatchState::None) {
            $flags[] = 'no_product_candidate';
        }

        if ($matchState === MatchState::Partial) {
            $flags[] = 'ambiguous_product';
        }

        if (count($rated) >= 2 && $top !== null && $rated[1]->score >= $top->score - 0.05) {
            $flags[] = 'multiple_product_candidates';
        }

        if ($qualification->quantity !== null && ($qualification->quantity->conflict || $qualification->quantity->approximate)) {
            $flags[] = 'ambiguous_quantity';
        }

        if (in_array($qualification->destinationClass, [DestinationClass::Unknown, DestinationClass::Restricted], true)) {
            $flags[] = 'unproven_destination';
        }

        if ($top !== null && $top->product->isBlocked()) {
            $flags[] = 'restricted_sku';
        }

        if ($lead->email() === null || $lead->phone() === null) {
            $flags[] = 'contact_incomplete';
        }

        return $flags;
    }

    /**
     * @param  list<ProductMatch>  $rated
     * @return list<array<string, mixed>>
     */
    private function candidates(array $rated): array
    {
        return array_map(fn (ProductMatch $match) => [
            'sku_id' => $match->product->id,
            'name' => $match->product->name,
            'category' => $match->product->category,
            'score' => $match->score,
            'match_state' => $this->matchState($match, null)->value,
            'matched_tokens' => $match->matchedTokens,
        ], array_slice($rated, 0, 5));
    }

    /**
     * @param  list<string>  $gates
     * @return list<string>
     */
    private function reasons(
        ?ProductMatch $top,
        MatchState $matchState,
        Qualification $qualification,
        int $commercial,
        int $destination,
        int $buyerTypeIntent,
        PurchaseIntent $intent,
        int $completeness,
        array $gates,
    ): array {
        $reasons = [];

        $reasons[] = $top === null
            ? 'Product match: none (0/35)'
            : sprintf('Product match: %s (%d/35) — %s', $matchState->label(), $matchState->points(), $top->product->name);

        $quantity = $qualification->quantity;

        $reasons[] = $quantity === null
            ? 'Commercial value: no quantity stated (0/20)'
            : sprintf(
                'Commercial value: %d/20 — %s %s',
                $commercial,
                number_format($quantity->value),
                $quantity->unit ?? 'units (unit not stated)',
            );

        $reasons[] = sprintf(
            'Destination: %d/20 — %s%s',
            $destination,
            $qualification->destinationCountry ?? 'country unknown',
            $qualification->destinationCountry === null ? '' : ' · '.$qualification->destinationClass->label(),
        );

        $reasons[] = sprintf(
            'Buyer and intent: %d/15 — %s, %s',
            $buyerTypeIntent,
            $qualification->buyerType->label(),
            $intent->label(),
        );

        $reasons[] = sprintf('Completeness: %d/10', $completeness);

        foreach ($gates as $gate) {
            $reasons[] = 'Hard gate: '.$gate;
        }

        return $reasons;
    }

    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $gates
     */
    private function explanation(ScoreBand $band, int $total, array $reasons, array $gates): string
    {
        if ($gates !== []) {
            return sprintf('Hard reject: %s. %s', implode(', ', $gates), $band->action());
        }

        return sprintf(
            '%d/100 → %s. %s. %s',
            $total,
            $band->label(),
            implode('. ', array_slice($reasons, 0, 5)),
            $band->action(),
        );
    }
}

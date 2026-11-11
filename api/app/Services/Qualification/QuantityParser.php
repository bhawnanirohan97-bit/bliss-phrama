<?php

namespace App\Services\Qualification;

/**
 * Deterministic quantity parsing, per AI engine spec 6.2 and 6.3.
 *
 * The hard part is not the number, it is not mistaking a strength for an order:
 * "Amoxicillin 250mg capsules" contains no quantity at all, so milligrams are
 * never accepted as a quantity unit here. That is why `250mg` is dropped while
 * `250 kg` is kept.
 */
final class QuantityParser
{
    /**
     * Raw alias => canonical unit.
     *
     * @var array<string, string>
     */
    private const UNITS = [
        'kg' => 'kg', 'kgs' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
        'g' => 'g', 'gm' => 'g', 'gms' => 'g', 'gram' => 'g', 'grams' => 'g',
        'mt' => 't', 'ton' => 't', 'tons' => 't', 'tonne' => 't', 'tonnes' => 't',
        'l' => 'litre', 'ltr' => 'litre', 'litre' => 'litre', 'litres' => 'litre',
        'liter' => 'litre', 'liters' => 'litre',
        'ml' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml',
        'mg' => 'mg', 'milligram' => 'mg', 'milligrams' => 'mg',
        'pcs' => 'pcs', 'pc' => 'pcs', 'piece' => 'pcs', 'pieces' => 'pcs',
        'unit' => 'pcs', 'units' => 'pcs',
        'box' => 'box', 'boxes' => 'box',
        'carton' => 'carton', 'cartons' => 'carton',
        'drum' => 'drum', 'drums' => 'drum',
        'bag' => 'bag', 'bags' => 'bag',
        'vial' => 'vial', 'vials' => 'vial',
        'ampoule' => 'ampoule', 'ampoules' => 'ampoule',
        'tablet' => 'tablet', 'tablets' => 'tablet', 'tab' => 'tablet', 'tabs' => 'tablet',
        'capsule' => 'capsule', 'capsules' => 'capsule', 'cap' => 'capsule', 'caps' => 'capsule',
        'sachet' => 'sachet', 'sachets' => 'sachet',
        'tube' => 'tube', 'tubes' => 'tube',
        'bottle' => 'bottle', 'bottles' => 'bottle',
        'strip' => 'strip', 'strips' => 'strip',
        'container' => 'container', 'containers' => 'container',
    ];

    /**
     * Parse the first credible order quantity in a buyer message.
     */
    public function parse(string $text): ?Quantity
    {
        if (trim($text) === '') {
            return null;
        }

        // Dates are not quantities. "Delivery by 10/12/2026" must not parse as
        // an order for 2026 units.
        $text = preg_replace('/\b\d{1,4}[\/.-]\d{1,2}[\/.-]\d{1,4}\b/', ' ', $text) ?? $text;

        /** @var list<Quantity> $candidates */
        $candidates = [];

        // Range first: "500-1000 strips" means a minimum of 500. A range
        // without a unit is left to the single-quantity pass.
        if (preg_match('/(?<min>\d[\d.,]*\d|\d)\s*(?:-|to)\s*(?<max>\d[\d.,]*\d|\d)\s*(?<unit>[A-Za-z]+)/i', $text, $range)) {
            $unit = $this->unit($range['unit']);

            if ($unit !== null && $unit !== 'mg') {
                $value = $this->number($range['min']);

                if ($value !== null && $value > 0) {
                    return new Quantity(
                        value: (int) round($value),
                        unit: $unit,
                        raw: trim($range[0]),
                    );
                }
            }
        }

        preg_match_all(
            '/(?<num>\d[\d.,]*\d|\d)\s*(?:(?<mult>k|thousand|lakhs?|lacs?|crores?|cr|million|mn)\b)?\s*(?<unit>[A-Za-z]+)?/i',
            $text,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $unit = $this->unit($match['unit'] ?? null);

            // "500mg Amoxicillin" is a strength. Never treat it as an order.
            if ($unit === 'mg') {
                continue;
            }

            $value = $this->number($match['num']);

            if ($value === null || $value <= 0) {
                continue;
            }

            $multiplier = $this->multiplier($match['mult'] ?? null);
            $value *= $multiplier;
            $value = (int) round($value);

            if ($value > 1_000_000_000) {
                continue;
            }

            $candidates[] = new Quantity(
                value: $value,
                unit: $unit,
                raw: trim($match[0]),
                approximate: $this->isApproximate($text, $match[0]),
            );
        }

        if ($candidates === []) {
            return null;
        }

        // Prefer a quantity with a recognised unit. "500 tablets" beats a bare
        // number that may be a strength, a pack size or a phone extension.
        $withUnit = array_values(array_filter($candidates, fn (Quantity $q) => $q->unit !== null));

        $chosen = $withUnit === [] ? $this->largest($candidates) : $this->largest($withUnit);

        if ($chosen === null) {
            return null;
        }

        $distinct = [];

        foreach ($withUnit as $candidate) {
            $distinct[$candidate->value.'|'.$candidate->unit] = true;
        }

        return new Quantity(
            value: $chosen->value,
            unit: $chosen->unit,
            raw: $chosen->raw,
            approximate: $chosen->approximate,
            conflict: count($distinct) > 1,
        );
    }

    private function unit(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = mb_strtolower(trim($raw));

        return self::UNITS[$raw] ?? null;
    }

    /**
     * Indian or Western digit grouping, decimal comma, and mixed separators.
     */
    private function number(string $raw): ?float
    {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', $raw);

        $hasDot = str_contains($value, '.');
        $hasComma = str_contains($value, ',');

        if ($hasDot && $hasComma) {
            // The rightmost separator is the decimal separator.
            $lastDot = strrpos($value, '.');
            $lastComma = strrpos($value, ',');
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $thousands = $decimal === '.' ? ',' : '.';

            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($hasComma) {
            $value = $this->resolveSingleSeparator($value, ',');
        } elseif ($hasDot) {
            $value = $this->resolveSingleSeparator($value, '.');
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number <= 0) {
            return null;
        }

        return $number;
    }

    /**
     * `1,00,000` and `1,000,000` are grouping; `1,5` and `1.000,50` are
     * decimals. The deciding question is the width of the final group.
     */
    private function resolveSingleSeparator(string $value, string $separator): string
    {
        $groups = explode($separator, $value);
        $lastGroup = end($groups);

        if ($lastGroup === false) {
            return $value;
        }

        $lastLength = strlen($lastGroup);

        // Exactly two trailing digits with no later three-digit group is a
        // decimal comma: "1,5" -> 1.5.
        if ($separator === ',' && $lastLength <= 2) {
            return implode('', array_slice($groups, 0, -1)).'.'.$lastGroup;
        }

        // Three-digit final groups are thousands separators.
        if ($lastLength === 3) {
            return implode('', $groups);
        }

        return $value;
    }

    private function multiplier(?string $raw): int
    {
        if ($raw === null) {
            return 1;
        }

        return match (mb_strtolower($raw)) {
            'k', 'thousand' => 1_000,
            'lakh', 'lakhs', 'lac', 'lacs' => 100_000,
            'crore', 'crores', 'cr' => 10_000_000,
            'million', 'mn' => 1_000_000,
            default => 1,
        };
    }

    private function isApproximate(string $text, string $match): bool
    {
        $position = strpos($text, $match);

        if ($position === false) {
            return false;
        }

        $before = mb_substr($text, max(0, $position - 12), 12);

        return (bool) preg_match('/\b(approx|about|around|roughly)\b|~/i', $before);
    }

    /**
     * @param  list<Quantity>  $candidates
     */
    private function largest(array $candidates): ?Quantity
    {
        $chosen = null;

        foreach ($candidates as $candidate) {
            if ($chosen === null || $candidate->value > $chosen->value) {
                $chosen = $candidate;
            }
        }

        return $chosen;
    }
}

<?php

namespace App\Services\Drafting;

use App\Enums\DraftChannel;

/**
 * The automated fact check every draft must pass before it is shown.
 *
 * It is intentionally a set of independent prohibitions rather than a check
 * that the template did what it promised:
 *
 *  1. no claim to manufacture, own a brand, hold stock, be certified or
 *     guarantee an outcome;
 *  2. no price or figure that cannot be traced to the buyer's enquiry, the
 *     SKU record or the operator's own contact details.
 *
 * A draft that fails is stored with its evidence and withheld from the screen.
 */
final class DraftFactChecker
{
    /**
     * Claims a third-party exporter must never make.
     *
     * @var array<string, string>
     */
    private const CLAIM_RULES = [
        'manufacturing_claim' => '/\bwe\s+(manufacture|produce|make|own)\b/i',
        'manufacturer_claim' => '/\bwe\s+are\s+(a\s+|the\s+)?manufacturer\b/i',
        'factory_claim' => '/\b(our|my)\s+(factory|plant|manufacturing\s+unit)\b/i',
        'brand_ownership' => '/\b(our|my)\s+own\s+(brand|formulation)\b/i',
        'stock_claim' => '/\b(in\s+stock|ready\s+stock|stock\s+available|available\s+immediately)\b/i',
        'certification_claim' => '/\b(iso|gmp|who[-\s]?gmp|usfda|fda)\b[^.]{0,30}\b(certified|approved|accredited)\b|\bcertified\b|\bwho[-\s]?gmp\b/i',
        'regulatory_approval_claim' => '/\b(approved|registered)\s+(in|for)\s+(your|the)\s+(market|country)\b/i',
        'guarantee_claim' => '/\bguarantee[ds]?\b|\bassured\s+delivery\b/i',
        'superlative_claim' => '/\b(best|lowest)\s+price\b|\b100%\s+(safe|effective|genuine)\b/i',
        'price_claim' => '/(?:usd|inr|rs\.?|₹|\$)\s?\d/i',
    ];

    public function check(
        DraftChannel $channel,
        ?string $subject,
        string $body,
        DraftContext $context,
    ): FactCheckResult {
        $violations = [];
        $text = trim(($subject ?? '')."\n".$body);

        if (trim($body) === '') {
            $violations[] = 'empty_body';
        }

        if ($channel === DraftChannel::Email && trim((string) $subject) === '') {
            $violations[] = 'missing_subject';
        }

        foreach (self::CLAIM_RULES as $rule => $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $violations[] = $rule.': "'.trim($match[0]).'"';
            }
        }

        $allowed = array_flip($context->allowedNumbers);

        preg_match_all('/\d+/', $text, $matches);

        foreach (array_unique($matches[0]) as $number) {
            if (! isset($allowed[$number])) {
                $violations[] = 'untraceable_number: "'.$number.'"';
            }
        }

        $violations = array_values(array_unique($violations));

        return new FactCheckResult($violations === [], $violations);
    }
}

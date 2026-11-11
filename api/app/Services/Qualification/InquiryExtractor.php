<?php

namespace App\Services\Qualification;

use App\Enums\BuyerType;
use App\Models\Country;
use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Deterministic field extraction from a lead's own text (AI engine spec 6.4-6.6).
 *
 * No model is involved. Everything here is a keyword rule, so the same input
 * always yields the same qualification and a failed extraction can be debugged
 * by reading the rule that fired.
 */
final class InquiryExtractor
{
    /**
     * Evaluated top to bottom; the first decisive hit wins.
     *
     * @var array<string, array{type: BuyerType, keywords: list<string>, decisive: bool}>
     */
    private const BUYER_RULES = [
        'job_vendor' => [
            'type' => BuyerType::JobVendor,
            'keywords' => ['job', 'jobs', 'vacancy', 'resume', 'employment', 'vendor registration', 'supplier registration', 'register as vendor', 'recruiter'],
            'decisive' => true,
        ],
        'personal' => [
            'type' => BuyerType::Personal,
            'keywords' => ['personal use', 'for myself', 'for my family', 'self use', 'individual use', 'student', 'sample for self'],
            'decisive' => true,
        ],
        'exporter' => [
            'type' => BuyerType::ExporterDistributor,
            'keywords' => ['exporter', 'we export', 'export house', 'exporting'],
            'decisive' => true,
        ],
        'importer' => [
            'type' => BuyerType::Importer,
            'keywords' => ['importer', 'we import', 'importing', 'import licence', 'import license'],
            'decisive' => true,
        ],
        'distributor' => [
            // A bare "distributor" claim is a trading company until the
            // operator knows otherwise; the 10-point exporter tier is reserved
            // for buyers who actually describe themselves as exporters.
            'type' => BuyerType::Trader,
            'keywords' => ['distributor', 'distribute', 'distribution', 'sole distributor', 'authorised distributor'],
            'decisive' => true,
        ],
        'wholesaler' => [
            'type' => BuyerType::Wholesaler,
            'keywords' => ['wholesaler', 'wholesale', 'bulk buyer', 'bulk purchaser'],
            'decisive' => true,
        ],
        'institution' => [
            'type' => BuyerType::Institution,
            'keywords' => ['hospital', 'health system', 'clinic', 'dispensary', 'medical centre', 'medical center', 'pharmacy', 'chemist', 'drug store', 'drugstore', 'retail pharmacy'],
            'decisive' => false,
        ],
        'trader' => [
            'type' => BuyerType::Trader,
            'keywords' => ['trader', 'trading company', 'trading', 'buy and sell'],
            'decisive' => false,
        ],
        'manufacturer' => [
            'type' => BuyerType::Manufacturer,
            'keywords' => ['manufacturer', 'manufacturing', 'we manufacture'],
            'decisive' => false,
        ],
    ];

    /**
     * Ordered by priority; the first matched keyword decides the intent.
     *
     * @var array<string, list<string>>
     */
    private const INTENT_RULES = [
        'bulk_order' => ['bulk', 'container', 'monthly', 'annual', 'tender', 'trial order', 'regular supply', 'long term'],
        'repeat_order' => ['repeat', 'again', 'last order', 'reorder', 'previous order', 'same as before'],
        'price_request' => ['price', 'quotation', 'quote', 'rate', 'cost', 'best price', 'offer'],
        'catalogue_request' => ['catalogue', 'catalog', 'brochure', 'product list', 'price list'],
        'general_enquiry' => ['enquiry', 'inquiry', 'details', 'information', 'interested'],
    ];

    /**
     * Small, factual city table from the AI engine spec. It only resolves a
     * destination; it never decides eligibility.
     *
     * @var array<string, string>
     */
    private const CITIES = [
        'lagos' => 'NG', 'abuja' => 'NG', 'nairobi' => 'KE', 'mombasa' => 'KE',
        'dubai' => 'AE', 'sharjah' => 'AE', 'manila' => 'PH', 'cebu' => 'PH',
        'chattogram' => 'BD', 'dhaka' => 'BD', 'yangon' => 'MM', 'accra' => 'GH',
        'dar es salaam' => 'TZ', 'kampala' => 'UG', 'lusaka' => 'ZM', 'harare' => 'ZW',
        'colombo' => 'LK', 'kathmandu' => 'NP', 'karachi' => 'PK',
        'ho chi minh city' => 'VN', 'mumbai' => 'IN', 'delhi' => 'IN', 'ahmedabad' => 'IN',
    ];

    public function extract(Lead $lead): Qualification
    {
        $text = $lead->enquiryText();
        $haystack = $this->haystack($lead);

        [$buyerType, $buyerKeywords, $buyerConflict] = $this->buyerType($haystack);
        $destination = $this->destinationCountry($lead, $haystack, $source);

        return new Qualification(
            text: $text,
            buyerType: $buyerType,
            buyerTypeKeywords: $buyerKeywords,
            buyerTypeConflict: $buyerConflict,
            intents: $this->intents($haystack),
            quantity: app(QuantityParser::class)->parse($text),
            destinationCountry: $destination,
            destinationName: $lead->countryName(),
            destinationClass: Country::classFor($destination),
            destinationSource: $source,
            hasTargetDate: $this->hasTargetDate($haystack),
        );
    }

    /**
     * Buyer type keywords are checked against the message, the product name
     * and the company name, because "we are importers" is often only in the
     * company field.
     *
     * @return array{0: BuyerType, 1: list<string>, 2: bool}
     */
    private function buyerType(string $haystack): array
    {
        $matched = [];
        $hasJobVendor = false;
        $hasPersonal = false;

        foreach (self::BUYER_RULES as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if ($this->contains($haystack, $keyword)) {
                    $matched[] = $keyword;
                    $hasJobVendor = $hasJobVendor || $rule['type'] === BuyerType::JobVendor;
                    $hasPersonal = $hasPersonal || $rule['type'] === BuyerType::Personal;
                }
            }
        }

        // Spec 6.5: a decisive rule wins, first match top-down. Job vendor and
        // personal both fire a gate, so job vendor is preferred when both apply.
        foreach (self::BUYER_RULES as $rule) {
            if (! $rule['decisive']) {
                continue;
            }

            foreach ($rule['keywords'] as $keyword) {
                if ($this->contains($haystack, $keyword)) {
                    return [$rule['type'], $matched, $hasJobVendor && $hasPersonal];
                }
            }
        }

        foreach (self::BUYER_RULES as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if ($this->contains($haystack, $keyword)) {
                    return [$rule['type'], $matched, $hasJobVendor && $hasPersonal];
                }
            }
        }

        return [BuyerType::Unknown, $matched, false];
    }

    /**
     * The names of the intent rules that fired, in priority order.
     *
     * The caller asks commercial questions ("is this a price request?"), so the
     * rule name is the useful value; the literal keyword is evidence and is
     * derivable from the same rules.
     *
     * @return list<string>
     */
    private function intents(string $haystack): array
    {
        $matched = [];

        foreach (self::INTENT_RULES as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if ($this->contains($haystack, $keyword)) {
                    $matched[$intent] = true;

                    break;
                }
            }
        }

        return array_keys($matched);
    }

    private function destinationCountry(Lead $lead, string $haystack, ?string &$source): ?string
    {
        $source = 'payload';

        $iso = $lead->countryIso();

        if ($iso !== null && preg_match('/^[A-Z]{2}$/', $iso) === 1) {
            return $iso;
        }

        // A written city is the next best evidence, e.g. "delivery to Lagos".
        foreach (self::CITIES as $city => $country) {
            if ($this->contains($haystack, $city)) {
                $source = 'city';

                return $country;
            }
        }

        $source = 'unknown';

        return null;
    }

    private function hasTargetDate(string $haystack): bool
    {
        $by = preg_match('/\b(by|before|within|delivery by|need by|required by)\b/', $haystack) === 1;
        $when = preg_match('/\b(day|days|week|weeks|month|months|\d{1,2}\/\d{1,2}\/\d{2,4})\b/', $haystack) === 1;

        return $by && $when;
    }

    private function haystack(Lead $lead): string
    {
        return Str::lower(implode(' ', array_filter([
            $lead->productName(),
            $lead->message(),
            $lead->companyName(),
            $lead->buyerName(),
        ])));
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }
}

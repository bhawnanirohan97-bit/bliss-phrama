<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\AiBotSetting;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\Policy\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiBotController extends Controller
{
    /**
     * Map of common country names, abbreviations, and trade hubs to standard ISO-2 codes.
     */
    private const COUNTRY_MAP = [
        // Target countries requested by user
        'us' => 'US', 'usa' => 'US', 'united states' => 'US', 'united states of america' => 'US', 'america' => 'US',
        'gb' => 'GB', 'uk' => 'GB', 'united kingdom' => 'GB', 'great britain' => 'GB', 'england' => 'GB',
        'au' => 'AU', 'aus' => 'AU', 'australia' => 'AU',
        'ph' => 'PH', 'phl' => 'PH', 'philippines' => 'PH', 'phillippine' => 'PH', 'philippine' => 'PH', 'manila' => 'PH', 'cebu' => 'PH',
        'kr' => 'KR', 'kor' => 'KR', 'south korea' => 'KR', 'korea' => 'KR', 'republic of korea' => 'KR', 'seoul' => 'KR',
        'jp' => 'JP', 'jpn' => 'JP', 'japan' => 'JP', 'tokyo' => 'JP', 'osaka' => 'JP',
        'cn' => 'CN', 'chn' => 'CN', 'china' => 'CN', 'beijing' => 'CN', 'shanghai' => 'CN', 'guangzhou' => 'CN',

        // Other major export markets
        'ng' => 'NG', 'nga' => 'NG', 'nigeria' => 'NG', 'lagos' => 'NG', 'abuja' => 'NG',
        'ae' => 'AE', 'are' => 'AE', 'uae' => 'AE', 'united arab emirates' => 'AE', 'dubai' => 'AE', 'sharjah' => 'AE',
        'ke' => 'KE', 'ken' => 'KE', 'kenya' => 'KE', 'nairobi' => 'KE', 'mombasa' => 'KE',
        'gh' => 'GH', 'gha' => 'GH', 'ghana' => 'GH', 'accra' => 'GH',
        'tz' => 'TZ', 'tza' => 'TZ', 'tanzania' => 'TZ', 'dar es salaam' => 'TZ',
        'ug' => 'UG', 'uga' => 'UG', 'uganda' => 'UG', 'kampala' => 'UG',
        'vn' => 'VN', 'vnm' => 'VN', 'vietnam' => 'VN', 'ho chi minh city' => 'VN', 'hanoi' => 'VN',
        'bd' => 'BD', 'bgd' => 'BD', 'bangladesh' => 'BD', 'dhaka' => 'BD', 'chattogram' => 'BD',
        'lk' => 'LK', 'lka' => 'LK', 'sri lanka' => 'LK', 'colombo' => 'LK',
        'mm' => 'MM', 'mmr' => 'MM', 'myanmar' => 'MM', 'burma' => 'MM', 'yangon' => 'MM',
        'np' => 'NP', 'npl' => 'NP', 'nepal' => 'NP', 'kathmandu' => 'NP',
        'de' => 'DE', 'deu' => 'DE', 'germany' => 'DE', 'berlin' => 'DE', 'frankfurt' => 'DE',
        'fr' => 'FR', 'fra' => 'FR', 'france' => 'FR', 'paris' => 'FR',
        'ca' => 'CA', 'can' => 'CA', 'canada' => 'CA', 'toronto' => 'CA', 'vancouver' => 'CA',
        'br' => 'BR', 'bra' => 'BR', 'brazil' => 'BR', 'sao paulo' => 'BR',
        'za' => 'ZA', 'zaf' => 'ZA', 'south africa' => 'ZA', 'johannesburg' => 'ZA', 'cape town' => 'ZA',

        // Domestic India
        'in' => 'IN', 'ind' => 'IN', 'india' => 'IN',
    ];

    /**
     * Prominent Indian cities, states, and union territories.
     */
    private const INDIAN_LOCATIONS = [
        'mumbai', 'delhi', 'new delhi', 'bangalore', 'bengaluru', 'hyderabad', 'ahmedabad',
        'chennai', 'kolkata', 'surat', 'pune', 'jaipur', 'lucknow', 'kanpur', 'nagpur',
        'indore', 'thane', 'bhopal', 'visakhapatnam', 'vadodara', 'ghaziabad', 'ludhiana',
        'agra', 'nashik', 'faridabad', 'meerut', 'rajkot', 'varanasi', 'srinagar',
        'aurangabad', 'dhanbad', 'amritsar', 'navi mumbai', 'allahabad', 'prayagraj',
        'ranchi', 'howrah', 'coimbatore', 'jabalpur', 'gwalior', 'vijayawada', 'jodhpur',
        'madurai', 'raipur', 'kota', 'guwahati', 'chandigarh', 'solapur', 'hubli',
        'bareilly', 'moradabad', 'mysore', 'gurgaon', 'gurugram', 'aligarh', 'jalandhar',
        'bhubaneswar', 'salem', 'warangal', 'kochi', 'dehradun', 'noida', 'jammu',
        'maharashtra', 'gujarat', 'karnataka', 'tamil nadu', 'uttar pradesh', 'rajasthan',
        'west bengal', 'madhya pradesh', 'andhra pradesh', 'bihar', 'punjab', 'haryana',
        'kerala', 'odisha', 'telangana', 'assam', 'jharkhand', 'chhattisgarh', 'uttarakhand',
    ];

    /**
     * Keywords specific to domestic Indian pharmaceutical trade.
     */
    private const DOMESTIC_KEYWORDS = [
        'pcd', 'pcd pharma', 'pcd franchise', 'franchise', 'monopoly rights',
        'distributorship in india', 'all india', 'domestic supply', 'gst invoice',
        'billing in india', 'chemist shop', 'retail pharmacy', 'cash on delivery',
        'cod delivery', 'doorstep delivery in india', 'ayurvedic franchise',
    ];

    public function index(): JsonResponse
    {
        $settings = AiBotSetting::current();

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'reject_domestic' => 'boolean',
            'criteria' => 'nullable|array',
            'product_filters' => 'nullable|array',
            'country_filters' => 'nullable|array',
            'country_filters.*' => 'string',
            'buyer_type_filters' => 'nullable|array',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0',
            'keywords_include' => 'nullable|array',
            'keywords_exclude' => 'nullable|array',
            'allowed_dosage_forms' => 'nullable|array',
            'only_new_buyers' => 'boolean',
            'skip_personal' => 'boolean',
            'skip_job_vendor' => 'boolean',
            'auto_draft' => 'boolean',
            'auto_prioritize' => 'boolean',
        ]);

        $settings = AiBotSetting::first();
        if (! $settings) {
            $settings = new AiBotSetting;
        }

        // Normalise country filter array to uppercase ISO2 codes / clean strings
        if (isset($validated['country_filters']) && is_array($validated['country_filters'])) {
            $validated['country_filters'] = array_values(array_unique(array_filter(array_map(
                fn ($c) => $this->canonicalIso2((string) $c) ?? strtoupper(trim((string) $c)),
                $validated['country_filters'],
            ))));
        }

        $settings->fill($validated);
        $settings->save();

        return response()->json(['message' => 'AI bot settings updated', 'settings' => $settings]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $settings = AiBotSetting::first();
        if (! $settings) {
            $settings = AiBotSetting::create(['enabled' => $validated['enabled']]);
        } else {
            $settings->enabled = $validated['enabled'];
            $settings->save();
        }

        return response()->json(['enabled' => $settings->enabled]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $settings = AiBotSetting::first();
        if (! $settings || ! $settings->enabled) {
            return response()->json(['enabled' => false, 'matches' => [], 'message' => 'AI bot is disabled']);
        }

        $lead = $request->all();
        $result = $this->checkLead($settings, $lead);

        return response()->json(['enabled' => true] + $result);
    }

    /**
     * Store and assess a newly purchased lead from IndiaMART so it appears on the dashboard immediately.
     */
    public function recordBoughtLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'nullable|string',
            'product' => 'nullable|string',
            'inquiry_text' => 'nullable|string',
            'requirement_text' => 'nullable|string',
            'quantity' => 'nullable|string',
            'location_text' => 'nullable|string',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'buyer_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'email' => 'nullable|string',
            'external_id' => 'nullable|string',
        ]);

        $inquiry = (string) ($validated['inquiry_text'] ?? $validated['requirement_text'] ?? '');
        $product = (string) ($validated['product_name'] ?? $validated['product'] ?? '');
        if ($product === '' && $inquiry !== '') {
            $product = Str::limit($inquiry, 50, '');
        }

        $country = (string) ($validated['country'] ?? '');
        $countryIso = $this->canonicalIso2($country)
            ?? $this->detectCountryFromInquiry($inquiry)
            ?? $this->detectCountryFromInquiry((string) ($validated['location_text'] ?? ''))
            ?? 'US';

        $source = LeadSource::firstOrCreate(
            ['connector_type' => 'indiamart'],
            ['name' => 'IndiaMART', 'integration_status' => 'connected']
        );

        $externalId = $validated['external_id'] ?? ('BUY_' . now()->format('YmdHis') . '_' . Str::random(5));

        $countryName = self::COUNTRY_MAP[strtolower($countryIso)] ?? $countryIso;

        $payload = [
            'UNIQUE_QUERY_ID' => $externalId,
            'QUERY_TYPE' => 'B',
            'QUERY_TIME' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i:s'),
            'QUERY_PRODUCT_NAME' => $product ?: 'Pharmaceutical Product',
            'QUERY_MESSAGE' => $inquiry,
            'QUERY_QUANTITY' => $validated['quantity'] ?? null,
            'SENDER_NAME' => $validated['buyer_name'] ?? ('Foreign Buyer (' . $countryIso . ')'),
            'SENDER_COUNTRY_ISO' => $countryIso,
            'SENDER_COUNTRY_NAME' => $countryName,
            'SENDER_CITY' => $validated['city'] ?? $validated['location_text'] ?? null,
            'SENDER_MOBILE' => $validated['phone'] ?? $validated['mobile'] ?? null,
            'SENDER_EMAIL' => $validated['email'] ?? null,
            'SOURCE' => 'chrome_extension_autobuy',
        ];

        $lead = Lead::updateOrCreate(
            [
                'lead_source_id' => $source->id,
                'external_lead_id' => $externalId,
            ],
            [
                'query_type' => 'B',
                'status' => LeadStatus::Received,
                'arrived_at' => now(),
                'payload' => $payload,
            ]
        );

        try {
            app(AssessmentService::class)->assess($lead);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'lead_id' => $lead->id,
            'message' => 'Lead successfully saved to database and displayed on dashboard.',
            'lead' => [
                'id' => $lead->id,
                'buyer' => $lead->displayName(),
                'product' => $lead->productName(),
                'country' => $lead->countryIso(),
                'status' => $lead->status->value,
            ],
        ]);
    }

    public function checkLead(AiBotSetting $settings, array $lead): array
    {
        $inquiry = strtolower((string) ($lead['inquiry_text'] ?? $lead['requirement_text'] ?? $lead['QUERY_MESSAGE'] ?? ''));
        $buyerType = strtolower((string) ($lead['buyer_type'] ?? ''));
        $quantity = $lead['quantity'] ?? $lead['parsed_quantity'] ?? null;
        $matches = [];

        // 1. Personal & Job Seeker Gates
        if ($settings->skip_personal && $buyerType === 'personal') {
            return ['eligible' => false, 'reason' => 'Personal buyer skipped'];
        }
        if ($settings->skip_job_vendor && $buyerType === 'job_vendor') {
            return ['eligible' => false, 'reason' => 'Job/vendor skipped'];
        }

        // 2. Reject Domestic Leads of India (Export Only)
        $rejectDomestic = $settings->reject_domestic ?? (bool) config('pharmalead.reject_domestic', true);
        if ($rejectDomestic && $this->isDomesticLead($lead, $inquiry)) {
            return [
                'eligible' => false,
                'reason' => 'Domestic Indian lead rejected (export-only policy enabled)',
                'matches' => [
                    ['type' => 'error', 'message' => 'Domestic lead from India rejected (export only)'],
                ],
            ];
        }

        // 3. Multi-Country Target Filtering
        if (! empty($settings->country_filters)) {
            $resolvedCountry = $this->resolveLeadCountry($lead, $inquiry);

            if ($resolvedCountry === null) {
                return [
                    'eligible' => false,
                    'reason' => 'Destination country is unknown and does not match target country list',
                    'matches' => [
                        ['type' => 'warning', 'message' => 'Destination country could not be identified'],
                    ],
                ];
            }

            if (! $this->matchesAllowedCountries($resolvedCountry, $settings->country_filters)) {
                return [
                    'eligible' => false,
                    'reason' => "Destination country '{$resolvedCountry}' is not in your allowed target countries: " . implode(', ', $settings->country_filters),
                    'matches' => [
                        ['type' => 'warning', 'message' => "Country '{$resolvedCountry}' does not match allowed countries: " . implode(', ', $settings->country_filters)],
                    ],
                ];
            }

            $matches[] = ['type' => 'ok', 'message' => "Destination '{$resolvedCountry}' matches target country list"];
        }

        // 4. Target Products / Medicines Filter
        if (! empty($settings->product_filters)) {
            $productName = strtolower((string) ($lead['product_name'] ?? $lead['product'] ?? ''));
            $matchedProduct = false;
            $matchedItem = null;

            foreach ($settings->product_filters as $filter) {
                $cleanFilter = strtolower(trim((string) $filter));
                if ($cleanFilter !== '' && (str_contains($inquiry, $cleanFilter) || str_contains($productName, $cleanFilter))) {
                    $matchedProduct = true;
                    $matchedItem = $filter;
                    break;
                }
            }

            if (! $matchedProduct) {
                $allowedPreview = implode(', ', array_slice($settings->product_filters, 0, 5));
                if (count($settings->product_filters) > 5) {
                    $allowedPreview .= '...';
                }

                return [
                    'eligible' => false,
                    'reason' => "Product does not match your allowed target products: {$allowedPreview}",
                    'matches' => [
                        ['type' => 'warning', 'message' => "Inquiry does not match allowed target products ({$allowedPreview})"],
                    ],
                ];
            }

            $matches[] = ['type' => 'ok', 'message' => "Product matches target list: {$matchedItem}"];
        }

        // 4. Keyword Include / Exclude
        if (! empty($settings->keywords_include)) {
            $found = false;
            foreach ($settings->keywords_include as $kw) {
                if ($kw && str_contains($inquiry, strtolower((string) $kw))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $matches[] = ['type' => 'warning', 'message' => 'No required keywords found'];
            } else {
                $matches[] = ['type' => 'ok', 'message' => 'Keywords match'];
            }
        }

        if (! empty($settings->keywords_exclude)) {
            foreach ($settings->keywords_exclude as $kw) {
                if ($kw && str_contains($inquiry, strtolower((string) $kw))) {
                    return ['eligible' => false, 'reason' => 'Excluded keyword found: '.$kw];
                }
            }
        }

        // 5. Quantity Thresholds
        if ($settings->min_quantity !== null && $quantity !== null && (int) $quantity < (int) $settings->min_quantity) {
            return ['eligible' => false, 'reason' => 'Quantity below minimum'];
        }
        if ($settings->max_quantity !== null && $quantity !== null && (int) $quantity > (int) $settings->max_quantity) {
            return ['eligible' => false, 'reason' => 'Quantity above maximum'];
        }

        $matches[] = ['type' => 'ok', 'message' => 'Lead meets AI bot criteria'];

        return ['eligible' => true, 'matches' => $matches];
    }

    /**
     * Detect if a lead originates in or is destined for domestic India.
     */
    public function isDomesticLead(array $lead, string $inquiry): bool
    {
        // 1. Explicit Country checks
        $countryFields = [
            $lead['country'] ?? null,
            $lead['destination_country'] ?? null,
            $lead['sender_country_iso'] ?? null,
            $lead['SENDER_COUNTRY_ISO'] ?? null,
            $lead['sender_country_name'] ?? null,
            $lead['SENDER_COUNTRY_NAME'] ?? null,
            $lead['country_iso'] ?? null,
        ];

        foreach ($countryFields as $field) {
            if ($field === null || trim((string) $field) === '') {
                continue;
            }

            $cleaned = strtolower(trim((string) $field));
            if ($cleaned === 'in' || $cleaned === 'ind' || $cleaned === 'india') {
                return true;
            }
        }

        // 2. City / State checks
        $cityFields = [
            $lead['city'] ?? null,
            $lead['sender_city'] ?? null,
            $lead['SENDER_CITY'] ?? null,
            $lead['state'] ?? null,
            $lead['sender_state'] ?? null,
            $lead['SENDER_STATE'] ?? null,
        ];

        foreach ($cityFields as $city) {
            if ($city === null || trim((string) $city) === '') {
                continue;
            }

            $cleaned = strtolower(trim((string) $city));
            if (in_array($cleaned, self::INDIAN_LOCATIONS, true)) {
                // If the lead specifies a separate foreign country, don't flag as domestic
                $foreignCountry = $this->resolveLeadCountry($lead, $inquiry);
                if ($foreignCountry !== null && $foreignCountry !== 'IN') {
                    continue;
                }

                return true;
            }
        }

        // 3. Phone number starting with India country code (+91 / 91)
        $phone = (string) ($lead['phone'] ?? $lead['mobile'] ?? $lead['sender_mobile'] ?? $lead['SENDER_MOBILE'] ?? '');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) >= 12) {
            // Check if there is an explicit foreign destination in the message
            $foreignCountry = $this->resolveLeadCountry($lead, $inquiry);
            if ($foreignCountry === null || $foreignCountry === 'IN') {
                return true;
            }
        }

        // 4. Domestic trading keywords in the inquiry
        foreach (self::DOMESTIC_KEYWORDS as $keyword) {
            if (str_contains($inquiry, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the lead's country to an uppercase ISO-2 code.
     */
    public function resolveLeadCountry(array $lead, string $inquiry): ?string
    {
        $countryFields = [
            $lead['country'] ?? null,
            $lead['destination_country'] ?? null,
            $lead['sender_country_iso'] ?? null,
            $lead['SENDER_COUNTRY_ISO'] ?? null,
            $lead['sender_country_name'] ?? null,
            $lead['SENDER_COUNTRY_NAME'] ?? null,
            $lead['country_iso'] ?? null,
        ];

        foreach ($countryFields as $field) {
            if ($field === null || trim((string) $field) === '') {
                continue;
            }

            $iso = $this->canonicalIso2((string) $field);
            if ($iso !== null) {
                return $iso;
            }
        }

        // Check inquiry text for country names or major cities
        $normalizedInquiry = ' ' . preg_replace('/[^a-z0-9]+/i', ' ', $inquiry) . ' ';

        foreach (self::COUNTRY_MAP as $name => $iso) {
            if (strlen($name) >= 3 && str_contains($normalizedInquiry, ' ' . $name . ' ')) {
                return $iso;
            }
        }

        return null;
    }

    /**
     * Check if a country code or name matches any item in the allowed country filters.
     */
    public function matchesAllowedCountries(string $country, array $allowed): bool
    {
        $canonical = $this->canonicalIso2($country) ?? strtoupper(trim($country));

        foreach ($allowed as $target) {
            $targetCanonical = $this->canonicalIso2((string) $target) ?? strtoupper(trim((string) $target));

            if ($canonical === $targetCanonical) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a raw country string or abbreviation to an uppercase ISO-2 code.
     */
    public function canonicalIso2(string $value): ?string
    {
        $cleaned = strtolower(trim($value));

        if (isset(self::COUNTRY_MAP[$cleaned])) {
            return self::COUNTRY_MAP[$cleaned];
        }

        // If it's already a 2-letter uppercase ISO code
        if (preg_match('/^[a-z]{2}$/i', $cleaned) === 1) {
            return strtoupper($cleaned);
        }

        return null;
    }
}

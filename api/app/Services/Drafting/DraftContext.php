<?php

namespace App\Services\Drafting;

use App\Models\Lead;
use App\Models\LeadAssessment;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * The facts a draft is allowed to use.
 *
 * This is deliberately a closed set: buyer-supplied strings are cleaned and
 * bounded, every other fact comes from the SKU record or the operator identity
 * in configuration. Templates cannot reach into the payload directly, which is
 * what keeps a buyer's message from altering the message the operator sends.
 */
final readonly class DraftContext
{
    /**
     * @param  list<string>  $allowedNumbers  digit runs a draft may contain
     */
    public function __construct(
        public ?string $firstName,
        public ?string $requirement,
        public ?string $quantityPhrase,
        public ?Product $product,
        public string $company,
        public string $desk,
        public string $city,
        public string $country,
        public string $phone,
        public string $email,
        public array $allowedNumbers,
    ) {}

    public static function from(Lead $lead, LeadAssessment $assessment): self
    {
        /** @var array<string, mixed> $operator */
        $operator = config('pharmalead.operator');

        $product = $assessment->product;

        // Prefer the buyer's own product wording; fall back to the IndiaMART
        // category. Never invent a product name the buyer did not use.
        $requirement = self::clean($lead->productName())
            ?? self::clean(self::string(data_get($lead->payload, 'QUERY_MCAT_NAME')));

        $quantityPhrase = self::quantityPhrase($assessment);

        $allowedNumbers = self::digits(implode(' ', array_filter([
            self::string($operator['phone'] ?? null),
            self::string($operator['email'] ?? null),
            $requirement,
            $product?->name,
            $product?->strength,
            $product?->packaging,
            $product?->moq,
            $quantityPhrase,
        ])));

        return new self(
            firstName: self::firstName($lead->buyerName()),
            requirement: $requirement,
            quantityPhrase: $quantityPhrase,
            product: $product,
            company: self::string($operator['company'] ?? null) ?? 'Bliss Pharmex',
            desk: self::string($operator['desk'] ?? null) ?? 'Bliss Pharmex Export Desk',
            city: self::string($operator['city'] ?? null) ?? 'Nagpur',
            country: self::string($operator['country'] ?? null) ?? 'India',
            phone: self::string($operator['phone'] ?? null) ?? '',
            email: self::string($operator['email'] ?? null) ?? '',
            allowedNumbers: array_values(array_unique($allowedNumbers)),
        );
    }

    /**
     * "your enquiry for Letrozole 2.5 mg", or a neutral phrase when the buyer
     * only sent a category. Used after "Thank you for".
     */
    public function enquiryPhrase(): string
    {
        return $this->requirement === null
            ? 'your recent enquiry'
            : 'your enquiry for '.$this->requirement;
    }

    /**
     * " (5,000 strips)" when the buyer stated a usable quantity.
     */
    public function quantitySuffix(): string
    {
        return $this->quantityPhrase === null ? '' : ' ('.$this->quantityPhrase.')';
    }

    /**
     * Strength and pack size are pulled from the SKU record, not the buyer's
     * message. They are only added when the product name does not already say
     * the same thing.
     */
    public function productSuffix(): string
    {
        if ($this->product === null) {
            return '';
        }

        $name = Str::lower($this->product->name);
        $details = [];

        foreach ([$this->product->strength, $this->product->packaging] as $value) {
            $value = self::string($value);

            if ($value === null || Str::contains($name, Str::lower($value))) {
                continue;
            }

            $details[] = $value;
        }

        return $details === [] ? '' : ' ('.implode(', ', $details).')';
    }

    public function addressedName(): string
    {
        return $this->firstName ?? 'there';
    }

    public function emailGreeting(): string
    {
        return $this->firstName ?? 'Sir/Madam';
    }

    private static function quantityPhrase(LeadAssessment $assessment): ?string
    {
        if ($assessment->requested_quantity === null) {
            return null;
        }

        $unit = self::string($assessment->requested_quantity_unit);

        return number_format($assessment->requested_quantity)
            .($unit === null || $unit === 'unknown' ? '' : ' '.$unit);
    }

    private static function firstName(?string $name): ?string
    {
        $name = self::clean($name, 60);

        if ($name === null) {
            return null;
        }

        $first = preg_split('/\s+/u', $name)[0] ?? '';

        return $first === '' ? null : Str::ucfirst(Str::lower($first));
    }

    private static function clean(?string $value, int $limit = 160): ?string
    {
        if ($value === null) {
            return null;
        }

        // Collapse all whitespace, including newlines, so buyer text cannot
        // inject line breaks that look like template structure.
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return $value === '' ? null : Str::limit($value, $limit, '…');
    }

    private static function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function digits(string $value): array
    {
        preg_match_all('/\d+/', $value, $matches);

        return array_values(array_unique($matches[0]));
    }
}

<?php

namespace App\Services\Catalogue;

use App\Enums\ProductType;
use Illuminate\Support\Str;

/**
 * Derives matchable facts from a catalogue row.
 *
 * Catalogue names are human-written and inconsistent ("2.5mg Letroheal
 * Letrozole Tablets", "Erlotero 100mg Tablet", "Abevmy Bevacizumab 400 Mg
 * Injection"). Matching cannot depend on string equality, so every row is
 * reduced to a normalised token string plus an explicit strength and form.
 *
 * Brand and generic are deliberately NOT guessed here. A wrong guess would
 * later drive a wrong quotation, so those columns stay null until a verified
 * brand -> generic mapping is supplied.
 */
final class ProductNameParser
{
    /**
     * Longest forms first so "Eye Drops" wins over "Drops" and "Tablets"
     * over "Tablet".
     *
     * @var list<string>
     */
    private const DOSAGE_FORMS = [
        'Dispersible Tablets', 'Chewable Tablets', 'Softgel Capsules',
        'Film-Coated Tablets', 'Extended Release Tablets', 'Oral Jelly',
        'Oral Powder', 'Oral Solution', 'Prefilled Pen', 'Kwikpen',
        'Flextouch Pen', 'Respules', 'Eye Drops', 'Eye Drop', 'Ear Drops',
        'Ear Drop', 'Inhaler', 'Injection', 'Injections', 'Tablets',
        'Tablet', 'Capsules', 'Capsule', 'Suspension', 'Ointment',
        'Solution', 'Lotion', 'Cream', 'Gel', 'Syrup', 'Powder', 'Spray',
        'Sachet', 'Vial', 'Cartridge', 'Pen', 'Strip',
    ];

    /**
     * @var list<string>
     */
    private const SERVICE_MARKERS = [
        'drop shipping', 'drop shipper', 'dropshipping', 'management service',
        'management services',
    ];

    /**
     * @return array{strength: string|null, dosage_form: string|null, product_type: ProductType, is_service: bool, search_text: string}
     */
    public function parse(string $name, ?string $strengthHint, ?string $packaging, ?string $category): array
    {
        $isService = $this->isService($name, $category);

        return [
            'strength' => $this->strength($name, $strengthHint),
            'dosage_form' => $this->detectDosageForm($name),
            'product_type' => $isService ? ProductType::Service : $this->productType($category),
            'is_service' => $isService,
            'search_text' => $this->searchText($name, $strengthHint, $packaging, $category),
        ];
    }

    private function isService(string $name, ?string $category): bool
    {
        // Any listing whose own title carries "service(s)" is a service, not
        // a physical product.
        if (preg_match('/\bservices?\b/i', $name) === 1) {
            return true;
        }

        $haystack = Str::lower($name.' '.($category ?? ''));

        foreach (self::SERVICE_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function productType(?string $category): ProductType
    {
        $category = Str::lower($category ?? '');

        if (str_contains($category, 'ayurved') || str_contains($category, 'nutraceutical') || str_contains($category, 'herbal')) {
            return ProductType::Ayurvedic;
        }

        if (str_contains($category, 'device') || str_contains($category, 'surgical') || str_contains($category, 'consumable')) {
            return ProductType::Device;
        }

        return ProductType::Formulation;
    }

    /**
     * The Strength column is authoritative when present. Falls back to the
     * first size-like token in the name.
     */
    private function strength(string $name, ?string $hint): ?string
    {
        $hint = trim((string) $hint);

        if ($hint !== '') {
            return $hint;
        }

        return $this->detectStrength($name);
    }

    /**
     * Extract a strength from free text, e.g. a buyer's enquiry.
     */
    public function detectStrength(string $text): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(mg|mcg|ml|g|iu|lu|%)(?![a-z])/i', $text, $matches) !== 1) {
            return null;
        }

        $unit = Str::lower($matches[2]);

        // The source spells international units both "IU" and "lU".
        $unit = $unit === 'lu' ? 'iu' : $unit;

        return $unit === '%' ? $matches[1].'%' : $matches[1].' '.$unit;
    }

    /**
     * Detect the dosage form from free text, e.g. a buyer's enquiry.
     *
     * Catalogue names end with the form. A buyer writes it anywhere
     * ("letrozole tablets 2.5mg"), so callers can loosen the anchor.
     */
    public function detectDosageForm(string $text, bool $anywhere = false): ?string
    {
        $suffix = $anywhere ? '\b' : '\s*$';

        foreach (self::DOSAGE_FORMS as $form) {
            if (preg_match('/\b'.preg_quote($form, '/').$suffix.'/i', $text) === 1) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Everything a buyer might type, reduced to lowercase alphanumeric tokens.
     * Both "Letroheal" and "Letrozole" survive, so either spelling matches.
     */
    private function searchText(string $name, ?string $strength, ?string $packaging, ?string $category): string
    {
        $parts = [$name, $strength, $packaging, $category];

        $normalised = Str::lower(implode(' ', array_filter($parts, fn ($p) => $p !== null && trim($p) !== '')));
        $normalised = preg_replace('/[^a-z0-9]+/', ' ', $normalised) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalised) ?? '');
    }
}

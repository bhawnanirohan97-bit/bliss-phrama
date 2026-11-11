<?php

namespace App\Services\Matching;

/**
 * Reduces free text to comparable tokens.
 *
 * The same tokenizer must run over both the catalogue and the buyer's enquiry,
 * otherwise a query for "Amoxy 500" can never line up with "Amoxicillin".
 */
final class Tokenizer
{
    /**
     * Enquiry boilerplate that carries no product signal.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'i', 'a', 'an', 'the', 'and', 'or', 'for', 'of', 'to', 'in', 'on', 'at', 'with',
        'want', 'wanted', 'need', 'needed', 'needs', 'require', 'required', 'requirement',
        'requirements', 'looking', 'look', 'please', 'kindly', 'send', 'share', 'give',
        'provide', 'quote', 'quotation', 'price', 'prices', 'rate', 'rates', 'details',
        'detail', 'information', 'info', 'best', 'good', 'quality', 'quantity', 'qty',
        'piece', 'pieces', 'pcs', 'unit', 'units', 'box', 'boxes', 'strip', 'strips',
        'pack', 'packs', 'packet', 'packets', 'bottle', 'bottles', 'vial', 'vials',
        'am', 'is', 'are', 'be', 'it', 'this', 'that', 'my', 'our', 'we', 'you', 'your',
        'buy', 'buyer', 'purchase', 'purchasing', 'order', 'ordering', 'supply', 'supplier',
        'business', 'use', 'personal', 'urgent', 'urgently', 'immediately', 'asap',
        'sample', 'samples', 'trial', 'full', 'also', 'more', 'some', 'any', 'all',
        'rs', 'inr', 'lakh', 'lakhs', 'crore', 'usd', 'dollar', 'dollars', 'per',
        'mg', 'mcg', 'ml', 'iu', 'lu', 'gm', 'kg', 'gram', 'grams', 'milligram',
        'tablet', 'tablets', 'capsule', 'capsules', 'injection', 'injections',
        'syrup', 'cream', 'gel', 'drop', 'drops', 'suspension', 'solution',
        'manufacturer', 'manufacturers', 'company', 'india', 'indian', 'export',
        'exporter', 'import', 'importer', 'worldwide', 'international',
        'medicine', 'medicines', 'drug', 'drugs', 'pharma', 'pharmaceutical',
        'pharmaceuticals', 'product', 'products', 'item', 'items',
    ];

    /**
     * Lowercase, split numeric strengths from units, and strip punctuation.
     */
    public function normalise(string $text): string
    {
        $text = mb_strtolower($text);

        // "500mg" and "500 MG" must become the same token stream.
        $text = preg_replace('/(\d)\s*(mg|mcg|ml|iu|lu|gm|kg|g|%)/u', '$1 $2', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $tokens = [];

        foreach (explode(' ', $this->normalise($text)) as $token) {
            if ($token === '' || mb_strlen($token) < 2) {
                continue;
            }

            // Pure numbers are quantities or strength digits; strength is
            // compared separately, and quantities are noise.
            if (ctype_digit($token)) {
                continue;
            }

            if (in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $tokens[] = $this->stem($token);
        }

        return array_values(array_unique($tokens));
    }

    private function stem(string $token): string
    {
        if (mb_strlen($token) > 4 && str_ends_with($token, 's')) {
            return mb_substr($token, 0, -1);
        }

        return $token;
    }
}

<?php

namespace App\Services\Qualification;

/**
 * A quantity exactly as the buyer stated it.
 *
 * Deliberately no conversion between count units: a carton of strips is not a
 * number of strips without a pack size, and inventing one would change the
 * commercial meaning of the request.
 */
final readonly class Quantity
{
    public function __construct(
        public int $value,
        public ?string $unit,
        public string $raw,
        public bool $approximate = false,
        public bool $conflict = false,
    ) {}

    /**
     * The unit vocabulary the assessment schema allows.
     */
    public function schemaUnit(): string
    {
        return match ($this->unit) {
            'tablet' => 'tablet',
            'capsule' => 'capsule',
            'bottle' => 'bottle',
            'vial' => 'vial',
            'box' => 'box',
            'strip' => 'strip',
            'kg' => 'kg',
            'litre' => 'litre',
            default => 'unknown',
        };
    }
}

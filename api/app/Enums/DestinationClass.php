<?php

namespace App\Enums;

/**
 * Export eligibility of a country for this operator (PRD 5.2.4).
 *
 * A country is only `allowed_proven` or `allowed_unproven` once the operator has
 * put it in the country table. Until then the class is `unknown`, which scores 0:
 * guessing a country's class would fabricate commercial policy.
 */
enum DestinationClass: string
{
    case AllowedProven = 'allowed_proven';
    case AllowedUnproven = 'allowed_unproven';
    case Restricted = 'restricted';
    case Blocked = 'blocked';
    case Unknown = 'unknown';

    public function points(): int
    {
        return match ($this) {
            self::AllowedProven => 20,
            self::AllowedUnproven => 14,
            self::Restricted => 8,
            self::Blocked, self::Unknown => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AllowedProven => 'Allowed · proven destination',
            self::AllowedUnproven => 'Allowed · not yet proven',
            self::Restricted => 'Unproven · treated as restricted',
            self::Blocked => 'Blocked',
            self::Unknown => 'Unknown',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::AllowedProven => 'green',
            self::AllowedUnproven => 'teal',
            self::Restricted => 'amber',
            self::Blocked => 'red',
            self::Unknown => 'grey',
        };
    }
}

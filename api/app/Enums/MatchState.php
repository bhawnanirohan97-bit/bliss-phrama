<?php

namespace App\Enums;

/**
 * How well the catalogue answers the buyer's request (PRD 5.2.2).
 */
enum MatchState: string
{
    case Exact = 'exact';
    case Strong = 'strong';
    case Partial = 'partial';
    case Weak = 'weak';
    case CategoryOnly = 'category_only';
    case None = 'none';

    public function points(): int
    {
        return match ($this) {
            self::Exact => 35,
            self::Strong => 28,
            self::Partial => 21,
            self::Weak => 14,
            self::CategoryOnly => 7,
            self::None => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::Strong => 'Strong',
            self::Partial => 'Partial',
            self::Weak => 'Weak',
            self::CategoryOnly => 'Category only',
            self::None => 'None',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Exact, self::Strong => 'green',
            self::Partial => 'teal',
            self::Weak => 'amber',
            self::CategoryOnly, self::None => 'grey',
        };
    }
}

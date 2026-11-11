<?php

namespace App\Enums;

/**
 * The operator-facing score band (PRD 5.3).
 */
enum ScoreBand: string
{
    case ActNow = 'act_now';
    case Good = 'good';
    case Review = 'review';
    case Low = 'low';
    case Skip = 'skip';
    case HardReject = 'hard_reject';

    public function label(): string
    {
        return match ($this) {
            self::ActNow => 'Act now',
            self::Good => 'Good',
            self::Review => 'Review',
            self::Low => 'Low',
            self::Skip => 'Skip',
            self::HardReject => 'Hard reject',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ActNow => 'green',
            self::Good => 'teal',
            self::Review => 'blue',
            self::Low => 'amber',
            self::Skip => 'grey',
            self::HardReject => 'red',
        };
    }

    /**
     * Ordinal rank used to order the inbox. `hard_reject` sits below `skip`
     * because it is excluded from the inbox entirely.
     */
    public function rank(): int
    {
        return match ($this) {
            self::ActNow => 6,
            self::Good => 5,
            self::Review => 4,
            self::Low => 3,
            self::Skip => 2,
            self::HardReject => 0,
        };
    }

    public function action(): string
    {
        return match ($this) {
            self::ActNow => 'Contact today.',
            self::Good => 'Contact today if the list is short.',
            self::Review => 'Read and decide by hand.',
            self::Low => 'Contact only on request.',
            self::Skip => 'Do not contact.',
            self::HardReject => 'Never contact; excluded from the inbox.',
        };
    }
}

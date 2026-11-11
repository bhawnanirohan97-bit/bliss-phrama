<?php

namespace App\Enums;

enum LeadStatus: string
{
    /** Retrieved from the source but not yet processed. */
    case Received = 'received';

    /** Retrieved while the bot is switched off. Stored, never processed, never dropped. */
    case Held = 'held';

    /** Persisted because the week's allowance was already fully allocated. */
    case QuotaExceeded = 'quota_exceeded';

    /** Failed a hard gate. Excluded from the inbox. */
    case HardRejected = 'hard_rejected';

    /** Scored, ranked and waiting for a human to make contact. */
    case Allocated = 'allocated';

    /** A human has logged at least one contact attempt. */
    case Contacted = 'contacted';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Held => 'Held',
            self::QuotaExceeded => 'Quota exceeded',
            self::HardRejected => 'Hard reject',
            self::Allocated => 'Allocated',
            self::Contacted => 'Contacted',
        };
    }

    /**
     * Chip tone, mapped in public/css/app.css.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Received => 'teal',
            self::Held => 'amber',
            self::QuotaExceeded => 'purple',
            self::HardRejected => 'red',
            self::Allocated => 'blue',
            self::Contacted => 'green',
        };
    }
}

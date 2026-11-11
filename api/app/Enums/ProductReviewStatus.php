<?php

namespace App\Enums;

enum ProductReviewStatus: string
{
    /**
     * Imported but not yet judged. Never used for an automatic reply.
     */
    case Unreviewed = 'unreviewed';

    /** The operator has confirmed this SKU may be offered for export. */
    case Approved = 'approved';

    /** The operator has ruled this SKU out: controlled, unlicensed, or not carried. */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Unreviewed => 'Unreviewed',
            self::Approved => 'Approved',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * Chip tone, mapped in public/css/app.css.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Unreviewed => 'grey',
            self::Approved => 'green',
            self::Blocked => 'red',
        };
    }
}

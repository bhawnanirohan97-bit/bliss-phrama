<?php

namespace App\Enums;

/**
 * The three outreach channels the Drafter produces (PRD Amendment 19).
 *
 * Nothing in this application sends on any of these channels: a draft is text
 * the operator copies into their own WhatsApp, mail client or phone script.
 */
enum DraftChannel: string
{
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Call = 'call';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
            self::Call => 'Call opener',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Whatsapp => 'green',
            self::Email => 'blue',
            self::Call => 'purple',
        };
    }
}

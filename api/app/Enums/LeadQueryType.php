<?php

namespace App\Enums;

/**
 * IndiaMART's QUERY_TYPE vocabulary.
 *
 * Only BuyLead, DirectEnquiry and WhatsApp are real buyer enquiries. The other
 * two are recorded so that nothing is lost, but they are never matched, scored
 * or alerted on: a catalogue view is anonymous browsing and a phone call has no
 * message to answer.
 */
enum LeadQueryType: string
{
    case BuyLead = 'B';
    case DirectEnquiry = 'W';
    case WhatsApp = 'WA';
    case CatalogueView = 'BIZ';
    case PhoneCall = 'P';

    public function label(): string
    {
        return match ($this) {
            self::BuyLead => 'Buy lead',
            self::DirectEnquiry => 'Direct enquiry',
            self::WhatsApp => 'WhatsApp',
            self::CatalogueView => 'Catalogue view',
            self::PhoneCall => 'Phone call',
        };
    }

    /**
     * Whether this lead type can produce a buyer conversation.
     */
    public function isActionable(): bool
    {
        return in_array($this, [self::BuyLead, self::DirectEnquiry, self::WhatsApp], true);
    }
}

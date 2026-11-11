<?php

namespace App\Enums;

/**
 * What the buyer actually asked for (PRD 5.2.5).
 */
enum PurchaseIntent: string
{
    case ExplicitWithDate = 'explicit_with_date';
    case ExplicitWithQuantity = 'explicit_with_quantity';
    case PriceEnquiry = 'price_enquiry';
    case AvailabilityEnquiry = 'availability_enquiry';
    case NonCommercial = 'non_commercial';

    public function points(): int
    {
        return match ($this) {
            self::ExplicitWithDate => 5,
            self::ExplicitWithQuantity => 4,
            self::PriceEnquiry => 3,
            self::AvailabilityEnquiry => 2,
            self::NonCommercial => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ExplicitWithDate => 'Purchase request with quantity and a target date',
            self::ExplicitWithQuantity => 'Purchase request with quantity',
            self::PriceEnquiry => 'Price or availability enquiry',
            self::AvailabilityEnquiry => 'General enquiry naming a product',
            self::NonCommercial => 'Non-commercial message',
        };
    }
}

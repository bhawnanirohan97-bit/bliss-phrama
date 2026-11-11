<?php

namespace App\Enums;

enum ProductType: string
{
    /** Finished dose form for human use: tablet, capsule, injection, cream. */
    case Formulation = 'formulation';

    /** Ayurvedic / herbal / nutraceutical. */
    case Ayurvedic = 'ayurvedic';

    /** Medical device or consumable: surgicals, dressings, disposables. */
    case Device = 'device';

    /**
     * A service listing rather than a physical product.
     *
     * The source catalogue mixes these in ("Drop Shipping Services",
     * "Pharmacy Management Services"). They must never be matched against a
     * buyer's product enquiry.
     */
    case Service = 'service';

    case Other = 'other';
}

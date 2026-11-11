<?php

namespace App\Enums;

/**
 * The buyer classification that feeds the buyer-type component (PRD 5.2.5).
 *
 * `personal` and `job_vendor` are hard gates. The rest are scored by how close
 * they are to the operator's actual customer: a business that resells imported
 * finished formulations.
 */
enum BuyerType: string
{
    case ExporterDistributor = 'exporter_distributor';
    case Wholesaler = 'wholesaler';
    case Importer = 'importer';
    case Institution = 'institution';
    case Trader = 'trader';
    case Manufacturer = 'manufacturer';
    case Personal = 'personal';
    case JobVendor = 'job_vendor';
    case Unknown = 'unknown';

    public function points(): int
    {
        return match ($this) {
            self::ExporterDistributor => 10,
            self::Wholesaler => 9,
            self::Importer => 8,
            self::Institution => 7,
            self::Trader => 5,
            self::Manufacturer => 4,
            self::Unknown => 4,
            self::Personal => 0,
            self::JobVendor => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ExporterDistributor => 'Exporter or distributor',
            self::Wholesaler => 'Wholesaler',
            self::Importer => 'Importer',
            self::Institution => 'Pharmacy, hospital or institution',
            self::Trader => 'Trading company',
            self::Manufacturer => 'Manufacturer',
            self::Personal => 'Personal individual',
            self::JobVendor => 'Job vendor or student',
            self::Unknown => 'Unknown',
        };
    }

    public function firesGate(): bool
    {
        return in_array($this, [self::Personal, self::JobVendor], true);
    }
}

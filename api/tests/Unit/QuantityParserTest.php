<?php

namespace Tests\Unit;

use App\Services\Qualification\QuantityParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: string|null}>
     */
    public static function quantities(): array
    {
        return [
            'strips' => ['Need 5000 Strips of Paracetamol 500mg Tablets.', 5000, 'strip'],
            'lakh multiplier' => ['We require 1.5 lakh pcs Amoxicillin 250mg caps monthly, Dubai', 150000, 'pcs'],
            'crore multiplier' => ['Hi sir price for 2 crore tablets of Metformin 500 please', 20000000, 'tablet'],
            'mass' => ['Kindly send quotation for 250 kg Ibuprofen IP to Chattogram', 250, 'kg'],
            'range takes the minimum' => ['500-1000 strips required', 500, 'strip'],
            'containers' => ['We are importer in Manila, need 3 containers of Losartan 50mg tabs', 3, 'container'],
            'indian grouping' => ['1,00,000 capsules needed', 100000, 'capsule'],
            'western grouping' => ['1,000,000 tablets needed', 1000000, 'tablet'],
            'cartons' => ['500 cartons Ceftriaxone 1g injection supply to Myanmar', 500, 'carton'],
            'no unit' => ['1.5 lakh', 150000, null],
            'case insensitive' => ['5000 PCS', 5000, 'pcs'],
        ];
    }

    #[DataProvider('quantities')]
    public function test_it_parses_a_stated_quantity(string $text, int $value, ?string $unit): void
    {
        $quantity = (new QuantityParser)->parse($text);

        $this->assertNotNull($quantity, "No quantity parsed from: {$text}");
        $this->assertSame($value, $quantity->value);
        $this->assertSame($unit, $quantity->unit);
    }

    public function test_a_strength_is_not_a_quantity(): void
    {
        $this->assertNull((new QuantityParser)->parse('Amoxicillin 250mg capsules'));
        $this->assertNull((new QuantityParser)->parse('Paracetamol 500 mg tablets'));
    }

    public function test_a_date_is_not_a_quantity(): void
    {
        $this->assertNull((new QuantityParser)->parse('Delivery by 10/12/2026'));
    }

    public function test_a_message_without_a_quantity_returns_null(): void
    {
        $this->assertNull((new QuantityParser)->parse('Please send your best quotation.'));
    }

    public function test_an_approximate_quantity_is_flagged(): void
    {
        $quantity = (new QuantityParser)->parse('We need about 5000 strips');

        $this->assertNotNull($quantity);
        $this->assertSame(5000, $quantity->value);
        $this->assertTrue($quantity->approximate);
    }

    public function test_two_different_quantities_are_flagged_as_a_conflict(): void
    {
        $quantity = (new QuantityParser)->parse('500 strips now and 5000 strips next month');

        $this->assertNotNull($quantity);
        $this->assertTrue($quantity->conflict);
        $this->assertSame(5000, $quantity->value);
    }

    public function test_a_comma_decimal_is_read_as_a_decimal(): void
    {
        $quantity = (new QuantityParser)->parse('1,5 lakh strips');

        $this->assertNotNull($quantity);
        $this->assertSame(150000, $quantity->value);
    }

    public function test_zero_is_rejected(): void
    {
        $this->assertNull((new QuantityParser)->parse('0 strips'));
    }
}

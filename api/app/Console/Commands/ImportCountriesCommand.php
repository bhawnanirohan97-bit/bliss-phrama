<?php

namespace App\Console\Commands;

use App\Enums\DestinationClass;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Load the operator's country eligibility table.
 *
 * The file is the source of truth for destination scoring. This command never
 * invents a class: a row with an unknown class is rejected rather than defaulted
 * to allowed.
 */
class ImportCountriesCommand extends Command
{
    protected $signature = 'countries:import {file : CSV with iso2,name,class,notes}';

    protected $description = 'Import or update the country eligibility table from a CSV file';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Cannot open {$path}.");

            return self::FAILURE;
        }

        $header = fgetcsv($handle, null, ',', '"', '');
        $header = array_map(
            fn ($column) => Str::lower(trim((string) $column, "\xEF\xBB\xBF \t\n\r\0\x0B\"")),
            $header ?: [],
        );

        $required = ['iso2', 'name', 'class'];

        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);
                $this->error("Missing required column: {$column}. Expected header: iso2,name,class,notes");

                return self::FAILURE;
            }
        }

        $imported = 0;
        $rejected = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $rowNumber++;

            $data = array_combine($header, array_pad($row, count($header), null));

            if ($data === false) {
                $rejected++;

                continue;
            }

            $iso2 = Str::upper(trim((string) ($data['iso2'] ?? '')));
            $name = trim((string) ($data['name'] ?? ''));
            $rawClass = trim((string) ($data['class'] ?? ''));
            $class = DestinationClass::tryFrom(Str::lower($rawClass));

            if (strlen($iso2) !== 2 || $name === '' || $class === null) {
                $this->warn("Row {$rowNumber}: skipped (iso2='{$iso2}', name='{$name}', class='{$rawClass}').");
                $rejected++;

                continue;
            }

            Country::query()->updateOrCreate(
                ['iso2' => $iso2],
                [
                    'name' => $name,
                    'destination_class' => $class,
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                ],
            );

            $imported++;
        }

        fclose($handle);

        $this->info("Imported {$imported} countr(ies).");
        $this->line(Country::query()->count().' countries in the table.');

        if ($rejected > 0) {
            $this->warn("Skipped {$rejected} row(s). Fix them and re-run; the import is idempotent.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalogue\ProductNameParser;
use Illuminate\Console\Command;
use Throwable;

class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import
        {path : Path to the catalogue CSV export}
        {--prune : Delete catalogue rows that are no longer present in the file}';

    protected $description = 'Import or refresh the product catalogue from a CSV export';

    public function handle(ProductNameParser $parser): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Unable to open: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle, null, ',', '"', '');

        if ($header === false) {
            fclose($handle);
            $this->error('The file is empty.');

            return self::FAILURE;
        }

        // Spreadsheet exports routinely carry a UTF-8 BOM. When the BOM sits
        // before the opening quote, fgetcsv keeps the quotes around the first
        // column name, so both the BOM and any stray quoting must be removed.
        $header = array_map(
            fn ($column) => strtolower(trim((string) $column, "\xEF\xBB\xBF \t\n\r\0\x0B\"")),
            $header,
        );

        $required = ['name', 'slug'];

        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);
                $this->error("Missing required column: {$column}");

                return self::FAILURE;
            }
        }

        $created = 0;
        $updated = 0;
        $services = 0;
        $skipped = 0;
        $keptSlugs = [];

        try {
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $data = [];

                foreach ($header as $index => $column) {
                    $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
                }

                $name = $data['name'] ?? null;
                $slug = $data['slug'] ?? null;

                if ($name === null || $name === '' || $slug === null || $slug === '') {
                    $skipped++;

                    continue;
                }

                $parsed = $parser->parse(
                    $name,
                    $data['strength'] ?? null,
                    $data['packaging'] ?? null,
                    $data['category'] ?? null,
                );

                $attributes = [
                    'name' => $name,
                    'category' => $this->nullIfEmpty($data['category'] ?? null),
                    'strength' => $parsed['strength'],
                    'dosage_form' => $parsed['dosage_form'],
                    'packaging' => $this->nullIfEmpty($data['packaging'] ?? null),
                    'moq' => $this->nullIfEmpty($data['moq'] ?? null),
                    'product_type' => $parsed['product_type'],
                    'is_service' => $parsed['is_service'],
                    'search_text' => $parsed['search_text'],
                    'source_url' => $this->nullIfEmpty($data['url'] ?? null),
                ];

                $product = Product::firstOrNew(['slug' => $slug]);

                if (! $product->exists) {
                    $created++;
                } else {
                    $updated++;
                }

                $product->fill($attributes)->save();

                if ($parsed['is_service']) {
                    $services++;
                }

                $keptSlugs[] = $slug;
            }
        } catch (Throwable $exception) {
            fclose($handle);
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        fclose($handle);

        $pruned = 0;

        if ($this->option('prune')) {
            $pruned = Product::whereNotIn('slug', $keptSlugs)->delete();
        }

        $this->newLine();
        $this->table(
            ['Created', 'Updated', 'Services', 'Skipped', 'Pruned', 'Total'],
            [[$created, $updated, $services, $skipped, $pruned, Product::count()]],
        );

        return self::SUCCESS;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

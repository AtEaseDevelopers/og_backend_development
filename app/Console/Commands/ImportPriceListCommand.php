<?php

namespace App\Console\Commands;

use App\Services\Imports\PriceListImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan og:import-price-list                 → import database/data/pricing-list-general.xlsx
 * php artisan og:import-price-list --dry-run       → preview only, nothing is written
 * php artisan og:import-price-list --reset         → delete ALL existing UOMs + rate tiers first
 * php artisan og:import-price-list path/to/file.xlsx
 */
class ImportPriceListCommand extends Command
{
    protected $signature = 'og:import-price-list
                            {file? : Path to the price list workbook (default: database/data/pricing-list-general.xlsx)}
                            {--dry-run : Parse and show what would be imported without writing}
                            {--reset : Delete all existing UOMs and their rate tiers before importing}
                            {--force : Skip the confirmation prompt for --reset}';

    protected $description = 'Sync the general price list (UOM rate tiers per destination) from the pricing workbook';

    public function handle(PriceListImporter $importer): int
    {
        $file = $this->argument('file') ?? database_path('data/pricing-list-general.xlsx');

        if (! is_file($file)) {
            $this->error("Price list not found: {$file}");

            return self::FAILURE;
        }

        try {
            $data = $importer->parse($file);
        } catch (Throwable $e) {
            $this->error('Could not read the price list: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Price list: '.$file);
        $this->line('Destinations: '.implode(', ', $data['locations']));
        $this->newLine();

        $this->table(
            ['Section', 'UOM', 'Basis', 'Tiers'],
            collect($data['uoms'])->map(fn (array $uom) => [
                $uom['section'],
                $uom['name'],
                $uom['basis'],
                collect($uom['tiers'])
                    ->groupBy('location')
                    ->map(fn ($tiers, $loc) => $loc.': '.$tiers->map(fn ($t) => $this->range($t).' RM'.rtrim(rtrim(number_format($t['price'], 2), '0'), '.'))->implode(' / '))
                    ->implode("\n"),
            ])->values()->all(),
        );

        $this->line(count($data['uoms']).' UOMs, '.collect($data['uoms'])->sum(fn ($u) => count($u['tiers'])).' tiers parsed.');

        foreach ($data['skipped'] as $line) {
            $this->warn('SKIPPED  '.$line);
        }

        foreach ($data['warnings'] as $line) {
            $this->comment('NOTE     '.$line);
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        if ($this->option('reset') && ! $this->option('force')
            && ! $this->confirm('--reset deletes ALL existing UOMs and their rate tiers before importing. Continue?', false)) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $stats = $importer->import($data, (bool) $this->option('reset'));

        $this->newLine();
        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all());
        $this->info('Price list synced.');

        return self::SUCCESS;
    }

    /** @param  array{min: float, max: ?float}  $tier */
    private function range(array $tier): string
    {
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        if ($tier['max'] === null) {
            return $tier['min'] <= 1 ? 'any' : $fmt($tier['min']).'+';
        }

        return $tier['min'] == $tier['max'] ? $fmt($tier['min']) : $fmt($tier['min']).'-'.$fmt($tier['max']);
    }
}

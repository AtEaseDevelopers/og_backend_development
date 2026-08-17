<?php

namespace App\Console\Commands;

use App\Services\Imports\OgMasterDataImporter;
use Illuminate\Console\Command;

class ImportOgMasterData extends Command
{
    protected $signature = 'og:import-master-data
                            {file? : Path to O&G.xlsx}
                            {--fresh-rates : Replace existing standard item/UOM rates before import}';

    protected $description = 'Import transport items, chartered lorries, UOM tiers, and location pricing from O&G.xlsx';

    public function handle(OgMasterDataImporter $importer): int
    {
        $file = $this->argument('file') ?? base_path('database/data/O&G.xlsx');

        if (! is_file($file)) {
            $fallback = '/Users/jackiets/Downloads/O&G.xlsx';

            if (is_file($fallback)) {
                $file = $fallback;
            } else {
                $this->error("Spreadsheet not found: {$file}");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh-rates')) {
            \App\Domains\MasterData\Models\ItemRate::query()->delete();
            \App\Domains\MasterData\Models\CharteredLorryRate::query()->delete();
            \App\Domains\MasterData\Models\UomRateTier::query()->delete();
            $this->warn('Cleared existing item, chartered lorry, and UOM rates.');
        }

        $this->info("Importing from {$file}...");

        $counts = $importer->importFromXlsx($file);

        $this->table(
            ['Metric', 'Count'],
            collect($counts)->map(fn ($count, $metric) => [$metric, $count])->values()->all(),
        );

        $this->info('Import completed.');

        return self::SUCCESS;
    }
}

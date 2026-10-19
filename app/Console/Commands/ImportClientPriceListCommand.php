<?php

namespace App\Console\Commands;

use App\Services\Imports\ClientPriceListImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan og:import-client-prices              → the client's default + special price workbooks in database/data
 * php artisan og:import-client-prices --dry-run    → read them only, nothing is written
 * php artisan og:import-client-prices path/to/file.xlsx [more.xlsx]
 */
class ImportClientPriceListCommand extends Command
{
    protected $signature = 'og:import-client-prices
                            {files?* : Price list workbooks (default: database/data/client-price-list-default.xlsx and client-price-list-special.xlsx)}
                            {--dry-run : Read the workbooks and show what would be imported without writing}';

    protected $description = 'Import the client\'s O&G Customer Pricing Setup workbooks (default price + customer special price)';

    public function handle(ClientPriceListImporter $importer): int
    {
        $files = $this->argument('files') ?: array_map(fn (string $name) => database_path('data/'.$name), ClientPriceListImporter::BUNDLED);

        foreach ($files as $file) {
            if (! is_file($file)) {
                $this->error("Price list not found: {$file}");

                return self::FAILURE;
            }

            $this->info('Price list: '.$file);

            try {
                $data = $importer->parse($file);

                if ($this->option('dry-run')) {
                    $this->line(sprintf('  %d row(s): %d product(s), %d lorry type(s), %d price row(s), %d customer(s) with special prices, %d destination(s)',
                        $data['rows'], count($data['products']), count($data['lorries']), count($data['rates']), count($data['customers']), count($data['locations'])));

                    foreach ($data['warnings'] as $warning) {
                        $this->comment('  Note: '.$warning);
                    }

                    continue;
                }

                foreach (ClientPriceListImporter::summaryLines($importer->import($data)) as $line) {
                    $this->line('  '.$line);
                }
            } catch (Throwable $e) {
                $this->error('  Could not import: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run — nothing was written.' : 'Price lists imported.');

        return self::SUCCESS;
    }
}

<?php

use App\Domains\MasterData\Models\Branch;
use App\Services\Imports\ClientPriceListImporter;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the client's price lists (database/data/client-price-list-default.xlsx and -special.xlsx, 19 Oct 2026) on
 * `php artisan migrate`: they replace the old price list. A fresh database without branches yet (migrate:fresh
 * before seeding) skips this; run `php artisan og:import-client-prices` after seeding.
 * Later price lists are imported with the "Import price list" button (Master Data → UOM) or the same command.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Branch::query()->exists()) {
            echo "  No branches yet: run php artisan og:import-client-prices after seeding.\n";

            return;
        }

        $importer = app(ClientPriceListImporter::class);

        foreach (ClientPriceListImporter::BUNDLED as $name) {
            $path = database_path('data/'.$name);

            if (! is_file($path)) {
                echo "  Missing {$path}: skipped.\n";

                continue;
            }

            echo "  {$name}\n";

            foreach (ClientPriceListImporter::summaryLines($importer->importFile($path)) as $line) {
                echo '    '.$line."\n";
            }
        }
    }

    public function down(): void
    {
        // the imported prices stay (the old price list can be imported again with og:import-price-list)
    }
};

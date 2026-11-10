<?php

use App\Domains\MasterData\Models\Branch;
use App\Services\Imports\ClientPriceListImporter;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the client's two special price workbooks of 11 Nov 2026 (database/data/client-special-price-a.xlsx and
 * -b.xlsx: customers A… and B…, all O & G KL, special prices only) on `php artisan migrate` (user, 11 Nov 2026).
 *
 * Nothing existing is cleared or changed: only the prices the system does not have yet are added ("Add the missing
 * prices only"), so running it again adds nothing. A sheet's customer is found by its debtor code when that code is
 * the same company in the system, else by company name, else it is created — the report lists those.
 * A fresh database without branches yet (migrate:fresh before seeding) skips this.
 */
return new class extends Migration
{
    /** file in database/data => the name shown as the source of its prices */
    private const FILES = [
        'client-special-price-a.xlsx' => 'Special Price A.xlsx',
        'client-special-price-b.xlsx' => 'Special Price B.xlsx',
    ];

    public function up(): void
    {
        if (! Branch::query()->exists()) {
            echo "  No branches yet: skipped (import the files with the Import price list button after seeding).\n";

            return;
        }

        foreach (self::FILES as $file => $name) {
            $path = database_path('data/'.$file);

            if (! is_file($path)) {
                echo "  Missing {$path}: skipped.\n";

                continue;
            }

            echo "  {$name}\n";
            $importer = app(ClientPriceListImporter::class);

            foreach (ClientPriceListImporter::summaryLines($importer->importFile($path, $name, false, true)) as $line) {
                echo '    '.$line."\n";
            }
        }
    }

    public function down(): void
    {
        // the imported prices and customers stay
    }
};

<?php

use App\Domains\MasterData\Models\Branch;
use App\Services\Imports\ClientPriceListImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Data → Lorries / Items / UOM by the price list's category (user, 21 Oct 2026): chartered lorries are
 * products of the Chartered Lorry category, priced like the others. The bundled price lists
 * (database/data/client-price-list-*.xlsx) are imported again, so each lorry becomes a product with all its columns
 * (the old chartered lorry prices had no minimum charges). The chartered lorry list stays as the fleet vehicles'
 * lorry sizes; its old prices are no longer used. Pending quotations that quoted a chartered lorry now quote its
 * product. Prices kept from accepted quotations stay (the import does not replace them).
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

        if (! Schema::hasTable('quotes')) {
            return;
        }

        // pending quotations: a quoted chartered lorry is its product now (same name)
        $products = DB::table('uoms')->where('category', 'lorry')->pluck('id', 'name');
        $lorries = DB::table('chartered_lorries')->pluck('name', 'id');
        $changed = 0;

        foreach (DB::table('quotes')->where('status', 'pending')->get(['id', 'pairs']) as $quote) {
            $pairs = json_decode((string) $quote->pairs, true) ?: [];
            $touched = false;

            foreach ($pairs as $p => $pair) {
                foreach ($pair['items'] ?? [] as $i => $item) {
                    if (! preg_match('/^chartered:(\d+)$/', (string) ($item['catalog_key'] ?? ''), $m)) {
                        continue;
                    }

                    $productId = $products[$lorries[(int) $m[1]] ?? ''] ?? null;

                    if ($productId) {
                        $pairs[$p]['items'][$i]['catalog_key'] = 'uom:'.$productId;
                        $pairs[$p]['items'][$i]['line_type'] = 'uom';
                        $touched = true;
                    }
                }
            }

            if ($touched) {
                DB::table('quotes')->where('id', $quote->id)->update(['pairs' => json_encode($pairs)]);
                $changed++;
            }
        }

        echo "  {$changed} pending quotation(s) now quote the lorry product.\n";
    }

    public function down(): void
    {
        // the products stay (orders and quotations may already use them)
    }
};

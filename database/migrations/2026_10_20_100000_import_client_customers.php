<?php

use App\Domains\MasterData\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the client's customer list (database/data/client-customers.csv: debtor code + company name, 929 customers,
 * from the Lists sheet of the price list workbooks, 20 Oct 2026) as new customers of the KL branch. Existing
 * customers are not changed: a debtor code already in the table is skipped, so the migration can run on any
 * server without conflicts. New customers start as Cash customers (no credit) without address or contact; the
 * special prices imported for their debtor codes apply to them from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('data/client-customers.csv');
        $branch = Branch::query()->where('code', 'KL')->first() ?? Branch::query()->orderBy('id')->first();

        if (! is_file($path) || ! $branch) {
            echo '  '.(! $branch ? 'No branches yet' : 'Missing '.$path).': customers not added (run this migration again after seeding).'."\n";

            return;
        }

        $companyId = $branch->defaultCompany()?->id;
        $existing = DB::table('customers')->whereNotNull('code')->pluck('code')->map(fn ($code) => strtoupper(trim((string) $code)))->flip();
        $now = now();
        $rows = [];

        $handle = fopen($path, 'r');
        fgetcsv($handle); // header: code, company_name

        while (($line = fgetcsv($handle)) !== false) {
            $code = trim((string) ($line[0] ?? ''));
            $name = trim((string) ($line[1] ?? ''));

            if ($code === '' || $name === '' || $existing->has(strtoupper($code))) {
                continue;
            }

            $existing[strtoupper($code)] = true;
            $rows[] = [
                'branch_id' => $branch->id,
                'company_id' => $companyId,
                'code' => $code,
                'company_name' => $name,
                'is_credit' => false,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('customers')->insert($chunk);
        }

        echo '  '.count($rows).' customer(s) added to '.$branch->name.'; codes already there were skipped.'."\n";
    }

    public function down(): void
    {
        // customers stay (orders may already use them)
    }
};

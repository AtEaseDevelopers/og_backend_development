<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CSN numbers in the client's final format (user, 30 Oct 2026): XX-YYMM##### — the location code, the month the
 * order was placed and a 5-digit running number per location code and month (KL-260800015). The company code is
 * no longer part of it. Existing CSN-EE-XX-YYMM##### numbers keep their running number where it is free; the
 * subsheets (CSN number + transfer code) and the numbers written into notes follow; the counters continue.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [];
        $taken = DB::table('consignment_notes')->pluck('number')->flip()->all();
        $last = [];

        foreach (DB::table('consignment_notes')->orderBy('id')->get(['id', 'number', 'source_branch_id']) as $csn) {
            if (! preg_match('/^CSN-([A-Z0-9]+)-([A-Z0-9]+)-(\d{4})(\d{5})$/', (string) $csn->number, $m)) {
                if (preg_match('/^([A-Z0-9]+)-(\d{4})(\d{5})$/', (string) $csn->number, $m)) {
                    $last[$csn->source_branch_id.'|'.$m[1].'|'.$m[2]] = max($last[$csn->source_branch_id.'|'.$m[1].'|'.$m[2]] ?? 0, (int) $m[3]);
                }

                continue; // already in the format, or not a CSN number
            }

            [, , $code, $period, $running] = $m;
            $number = sprintf('%s-%s%05d', $code, $period, (int) $running);

            // another company's CSN already holds this number: the next free one of the code and month
            while (isset($taken[$number])) {
                $running = (int) DB::table('consignment_notes')->where('number', 'like', $code.'-'.$period.'%')->pluck('number')->map(fn ($n) => (int) substr((string) $n, -5))->max() + 1;
                $number = sprintf('%s-%s%05d', $code, $period, $running);
            }

            $taken[$number] = true;
            $map[(string) $csn->number] = $number;
            DB::table('consignment_notes')->where('id', $csn->id)->update(['number' => $number, 'updated_at' => now()]);
            $key = $csn->source_branch_id.'|'.$code.'|'.$period;
            $last[$key] = max($last[$key] ?? 0, (int) $running);
        }

        // subsheets: the new CSN number + the transfer code (a second one with the same code: -2)
        if (Schema::hasTable('subsheets')) {
            $csnNumbers = DB::table('consignment_notes')->pluck('number', 'id');

            foreach (DB::table('subsheets')->whereNotNull('transfer_code')->where('transfer_code', '!=', '')->orderBy('id')->get(['id', 'number', 'transfer_code', 'consignment_note_id']) as $subsheet) {
                $csn = $csnNumbers[$subsheet->consignment_note_id] ?? null;

                if (! $csn || str_starts_with((string) $subsheet->number, $csn)) {
                    continue;
                }

                $base = $csn.strtoupper(trim((string) $subsheet->transfer_code));
                $number = $base;
                $n = 1;

                while (DB::table('subsheets')->where('number', $number)->where('id', '!=', $subsheet->id)->exists()) {
                    $number = $base.'-'.(++$n);
                }

                $map[(string) $subsheet->number] = $number;
                DB::table('subsheets')->where('id', $subsheet->id)->update(['number' => $number]);
            }
        }

        // the numbers written into notes and remarks
        if ($map !== []) {
            foreach (['quotations' => ['notes'], 'quotation_status_logs' => ['remarks'], 'consignment_notes' => ['remarks'], 'portal_enquiries' => ['notes']] as $table => $columns) {
                foreach ($columns as $column) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    foreach (DB::table($table)->whereNotNull($column)->where($column, 'like', '%CSN-%')->get(['id', $column]) as $row) {
                        $new = strtr((string) $row->{$column}, $map);

                        if ($new !== $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        }
                    }
                }
            }
        }

        // the counters continue after the renumbered CSNs
        foreach ($last as $key => $n) {
            [$branchId, $code, $period] = explode('|', $key);
            DB::table('document_number_sequences')->updateOrInsert(
                ['branch_id' => (int) $branchId, 'document_type' => 'CSN:'.$code, 'period' => $period],
                ['last_number' => $n, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        echo '  '.count(array_filter(array_keys($map), fn ($k) => str_starts_with($k, 'CSN-')))." CSN(s) renumbered to XX-YYMM#####.\n";
    }

    public function down(): void
    {
        // the previous CSN-EE-XX layout is not restored (the company code is no longer kept on the number)
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbers written into free text (user, 29 Oct 2026: the order's note "Order KL-ORD-202610-0009 · enquiry …"
 * printed on the order document) follow the new format too: the old EE-ZZ-YYYYMM-#### layout is rewritten as
 * ZZ-EE-YYMM##### (the same month and running number, as 2026_10_28_180000 did for the documents), and an old CSN
 * number is replaced by the CSN's new one.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'quotations' => ['notes', 'admin_notes', 'title'],
        'quotation_status_logs' => ['remarks'],
        'consignment_notes' => ['remarks'],
        'portal_enquiries' => ['notes', 'remarks'],
    ];

    public function up(): void
    {
        $csns = Schema::hasColumn('consignment_notes', 'old_number')
            ? DB::table('consignment_notes')->whereNotNull('old_number')->pluck('number', 'old_number')->all()
            : [];
        $changed = 0;

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                // a portable prefilter (SQLite in the tests has no REGEXP); the real match is the regex below
                foreach (DB::table($table)->whereNotNull($column)->where($column, 'like', '%-20__%-%')->get(['id', $column]) as $row) {
                    $text = (string) $row->{$column};
                    $new = $csns !== [] ? strtr($text, $csns) : $text;
                    $new = preg_replace_callback('/\b(PV-)?([A-Z0-9]+)-([A-Z]+)-(\d{4})(\d{2})-(\d{4})(-V\d+)?\b/', function (array $m): string {
                        return sprintf('%s-%s-%s%s%05d%s', $m[1] !== '' ? 'PV' : $m[3], $m[2], substr($m[4], 2), $m[5], (int) $m[6], $m[7] ?? '');
                    }, $new);

                    if ($new !== $text) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        $changed++;
                    }
                }
            }
        }

        echo "  {$changed} note(s) rewritten.\n";
    }

    public function down(): void
    {
        // free text: the old wording is not restored
    }
};

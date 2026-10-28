<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoice numbers in the client's format (user, 28 Oct 2026): ZZXXYYMM##### without hyphens, ZZ = IV for an
 * invoice and CB for a cash bill, XX = the company code, YYMM = the month, ##### = the running number per company,
 * type and month (DocumentNumberingService). Existing invoices are renumbered keeping their month and running
 * number (INV-KL-261000009 → IVKL261000009; a cash bill → CBKL261000009), the copies of an invoice number (a CSN's
 * invoice number, a refund note's knocked-off invoice) follow, and the counters continue after them.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> the columns holding a copy of an invoice number */
    private const COPIES = [
        'consignment_notes' => ['invoice_number'],
        'refund_notes' => ['knock_off_invoice_number'],
    ];

    public function up(): void
    {
        $branchByCode = DB::table('branches')->get(['id', 'code'])->mapWithKeys(fn ($b) => [strtoupper((string) $b->code) => (int) $b->id])->all();
        $map = [];
        $last = [];
        $counts = [];

        foreach (DB::table('invoices')->orderBy('id')->get(['id', 'number', 'type']) as $invoice) {
            $new = self::compact((string) $invoice->number, (string) $invoice->type);

            if ($new !== null && $new !== $invoice->number) {
                DB::table('invoices')->where('id', $invoice->id)->update(['number' => $new]);
                $map[(string) $invoice->number] = $new;
                $counts['invoices'] = ($counts['invoices'] ?? 0) + 1;
            }

            // the running number, for the counter of its company, type and month
            if (preg_match('/^(IV|CB)([A-Z]+)(\d{4})(\d{5})$/', $new ?? (string) $invoice->number, $m) && isset($branchByCode[$m[2]])) {
                $key = $branchByCode[$m[2]].'|'.($m[1] === 'CB' ? 'CBL' : 'INV').'|'.$m[3];
                $last[$key] = max($last[$key] ?? 0, (int) $m[4]);
            }
        }

        foreach (self::COPIES as $table => $columns) {
            foreach ($columns as $column) {
                foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get(['id', $column]) as $row) {
                    $old = (string) $row->{$column};
                    // the invoice's new number; an invoice no longer there is taken as an invoice (IV)
                    $new = $map[$old] ?? self::compact($old, 'invoice');

                    if ($new !== null && $new !== $old) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                        $counts[$table.'.'.$column] = ($counts[$table.'.'.$column] ?? 0) + 1;
                    }
                }
            }
        }

        foreach ($last as $key => $n) {
            [$branchId, $type, $period] = explode('|', $key);
            $current = (int) DB::table('document_number_sequences')->where('branch_id', $branchId)->where('document_type', $type)->where('period', $period)->value('last_number');

            if ($current < $n) {
                DB::table('document_number_sequences')->updateOrInsert(
                    ['branch_id' => (int) $branchId, 'document_type' => $type, 'period' => $period],
                    ['last_number' => $n, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        echo '  invoice numbers: '.(collect($counts)->map(fn ($n, $k) => "$k $n")->implode(', ') ?: 'nothing to renumber').".\n";
    }

    /** INV-EE-YYMM##### → IVEEYYMM##### (a cash bill: CBEEYYMM#####); null when not in that layout. */
    private static function compact(string $number, string $type): ?string
    {
        if (! preg_match('/^INV-([A-Z0-9]+)-(\d{4})(\d{5})$/', trim($number), $m)) {
            return null;
        }

        return ($type === 'cash_bill' ? 'CB' : 'IV').$m[1].$m[2].$m[3];
    }

    public function down(): void
    {
        $back = fn (string $number): ?string => preg_match('/^(IV|CB)([A-Z]+)(\d{9})$/', $number, $m) ? 'INV-'.$m[2].'-'.$m[3] : null;

        foreach (['invoices' => ['number']] + self::COPIES as $table => $columns) {
            foreach ($columns as $column) {
                foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get(['id', $column]) as $row) {
                    if ($old = $back((string) $row->{$column})) {
                        DB::table($table)->where('id', $row->id)->update([$column => $old]);
                    }
                }
            }
        }
    }
};

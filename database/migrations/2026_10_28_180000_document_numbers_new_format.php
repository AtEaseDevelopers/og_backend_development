<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every document number in the client's format (user, 28 Oct 2026): ZZ-EE-YYMM##### (ZZ = document type, EE =
 * company code, YYMM = month, ##### = running number per company, type and month), the CSN alone
 * CSN-EE-XX-YYMM##### (XX = the branch's CSN code; YYMM = the month the order was placed), a subsheet = its CSN
 * number + transfer code (CSN-KL-JB-261000005A), the Quotations page Q-EE-YYMM#####.
 *
 * Existing documents are renumbered: the old format EE-ZZ-YYYYMM-#### keeps its month and running number
 * (KL-INV-202610-0009 → INV-KL-261000009; PV-KL-OR-… → PV-KL-…), so numbers stay unique; copies of a number on
 * other rows (a CSN's invoice / proforma / DO / job no., a refund note's invoice) follow. CSNs are numbered again
 * per company, branch code and placement month in creation order (old number kept in consignment_notes.old_number,
 * subsheets likewise). The running counters continue after the renumbered documents.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> the columns holding a document number */
    private const COLUMNS = [
        'portal_enquiries' => ['order_number'],
        'quotations' => ['number'],
        'delivery_orders' => ['number'],
        'job_sheets' => ['number'],
        'proforma_invoices' => ['number'],
        'invoices' => ['number'],
        'receipts' => ['number'],
        'payment_vouchers' => ['number'],
        'refund_notes' => ['number', 'knock_off_invoice_number'],
        'commission_batches' => ['number'],
        'commission_slips' => ['number'],
        'commission_purchase_orders' => ['po_number', 'pi_number'],
        'break_bulks' => ['number'],
        'subsheets' => ['number'], // only the ones without a transfer code (the running SS number)
        'consignment_notes' => ['invoice_number', 'proforma_number', 'do_number', 'job_no', 'ps_job_no'],
    ];

    /** @var array<string, int> last running number per "branch|type|period" */
    private array $last = [];

    public function up(): void
    {
        foreach (['consignment_notes', 'subsheets'] as $table) {
            if (! Schema::hasColumn($table, 'old_number')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('old_number', 50)->nullable()->after('number'));
            }
        }

        $branchByCode = DB::table('branches')->get(['id', 'code'])->mapWithKeys(fn ($b) => [strtoupper((string) $b->code) => (int) $b->id])->all();
        $codeByBranch = array_flip($branchByCode);
        $counts = [];

        // 1. the generic documents: the same month and running number in the new layout
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')->get(['id', $column]) as $row) {
                    $new = $this->reformat((string) $row->{$column});

                    if ($new === null || $new === $row->{$column}) {
                        $this->note($new ?? (string) $row->{$column}, $branchByCode);

                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                    $this->note($new, $branchByCode);
                    $counts[$table.'.'.$column] = ($counts[$table.'.'.$column] ?? 0) + 1;
                }
            }
        }

        // a CSN's extra DO numbers (JSON list)
        if (Schema::hasColumn('consignment_notes', 'other_do_numbers')) {
            foreach (DB::table('consignment_notes')->whereNotNull('other_do_numbers')->get(['id', 'other_do_numbers']) as $row) {
                $list = json_decode((string) $row->other_do_numbers, true);

                if (! is_array($list) || $list === []) {
                    continue;
                }

                $new = array_map(fn ($n) => is_string($n) ? ($this->reformat($n) ?? $n) : $n, $list);

                if ($new !== $list) {
                    DB::table('consignment_notes')->where('id', $row->id)->update(['other_do_numbers' => json_encode(array_values($new))]);
                    $counts['consignment_notes.other_do_numbers'] = ($counts['consignment_notes.other_do_numbers'] ?? 0) + 1;
                }
            }
        }

        // 2. CSNs: CSN-EE-XX-YYMM#####, numbered again per company, branch code and placement month in creation order
        $locationCodes = DB::table('sa_locations')->pluck('csn_prefix', 'id')->map(fn ($p) => strtoupper((string) $p));
        $firstLocationCode = DB::table('sa_locations')->orderBy('id')->get(['branch_id', 'csn_prefix'])->groupBy('branch_id')->map(fn ($g) => strtoupper((string) $g->first()->csn_prefix));
        $orders = DB::table('quotations')->leftJoin('portal_enquiries', 'portal_enquiries.id', '=', 'quotations.portal_enquiry_id')
            ->get(['quotations.id', 'quotations.created_at', 'portal_enquiries.created_at as enquiry_created_at'])->keyBy('id');
        $csnCounters = [];

        foreach (DB::table('consignment_notes')->orderBy('id')->get(['id', 'number', 'old_number', 'sa_location_id', 'source_branch_id', 'quotation_id', 'issued_at', 'created_at']) as $csn) {
            if (preg_match('/^CSN-[A-Z0-9]+-[A-Z0-9]+-\d{9}$/', (string) $csn->number)) {
                $this->note((string) $csn->number, $branchByCode, true);

                continue; // already in the format
            }

            $company = $codeByBranch[(int) $csn->source_branch_id] ?? 'XX';
            $code = $locationCodes[$csn->sa_location_id] ?? $firstLocationCode[$csn->source_branch_id] ?? $company;
            $order = $csn->quotation_id ? ($orders[$csn->quotation_id] ?? null) : null;
            $placed = $order?->enquiry_created_at ?? $order?->created_at ?? $csn->issued_at ?? $csn->created_at;
            $period = Carbon::parse($placed)->format('ym');
            $key = $company.'|'.$code.'|'.$period;
            $csnCounters[$key] ??= (int) DB::table('consignment_notes')->where('number', 'like', 'CSN-'.$company.'-'.$code.'-'.$period.'%')->count();
            $number = sprintf('CSN-%s-%s-%s%05d', $company, $code, $period, ++$csnCounters[$key]);

            DB::table('consignment_notes')->where('id', $csn->id)->update([
                'old_number' => $csn->old_number ?: $csn->number,
                'number' => $number,
                'sa_prefix' => $code,
                'updated_at' => now(),
            ]);
            $this->note($number, $branchByCode, true);
            $counts['consignment_notes.number'] = ($counts['consignment_notes.number'] ?? 0) + 1;
        }

        // 3. subsheets with a transfer code: the CSN number + the code (a second one with the same code: -2)
        $csnNumbers = DB::table('consignment_notes')->pluck('number', 'id');

        foreach (DB::table('subsheets')->whereNotNull('transfer_code')->where('transfer_code', '!=', '')->orderBy('id')->get(['id', 'number', 'old_number', 'transfer_code', 'consignment_note_id']) as $subsheet) {
            $csn = $csnNumbers[$subsheet->consignment_note_id] ?? null;

            if (! $csn || str_starts_with((string) $subsheet->number, $csn)) {
                continue;
            }

            $base = $csn.strtoupper(trim((string) $subsheet->transfer_code));
            $number = $base;
            $n = 1;

            while (DB::table('subsheets')->where('number', $number)->exists()) {
                $number = $base.'-'.(++$n);
            }

            DB::table('subsheets')->where('id', $subsheet->id)->update(['old_number' => $subsheet->old_number ?: $subsheet->number, 'number' => $number]);
            $counts['subsheets.number'] = ($counts['subsheets.number'] ?? 0) + 1;
        }

        // 4. the Quotations page: Q##### → Q-EE-YYMM#####, running per company and month in creation order
        if (Schema::hasTable('quotes')) {
            $quoteCounters = [];

            foreach (DB::table('quotes')->orderBy('id')->get(['id', 'number', 'branch_id', 'company_id', 'created_at']) as $quote) {
                if (preg_match('/^Q-[A-Z0-9]+-\d{9}$/', (string) $quote->number)) {
                    $this->note((string) $quote->number, $branchByCode);

                    continue;
                }

                $branchId = (int) ($quote->branch_id ?: DB::table('companies')->where('id', $quote->company_id)->value('branch_id') ?: array_key_first($codeByBranch));
                $company = $codeByBranch[$branchId] ?? 'XX';
                $period = Carbon::parse($quote->created_at ?? now())->format('ym');
                $key = $company.'|'.$period;
                $quoteCounters[$key] ??= (int) DB::table('quotes')->where('number', 'like', 'Q-'.$company.'-'.$period.'%')->count();
                $number = sprintf('Q-%s-%s%05d', $company, $period, ++$quoteCounters[$key]);

                DB::table('quotes')->where('id', $quote->id)->update(['number' => $number]);
                $this->note($number, $branchByCode);
                $counts['quotes.number'] = ($counts['quotes.number'] ?? 0) + 1;
            }
        }

        // 5. the running counters continue after the renumbered documents
        foreach ($this->last as $key => $last) {
            [$branchId, $type, $period] = explode('|', $key);
            $current = (int) DB::table('document_number_sequences')->where('branch_id', $branchId)->where('document_type', $type)->where('period', $period)->value('last_number');

            if ($current < $last) {
                DB::table('document_number_sequences')->updateOrInsert(
                    ['branch_id' => (int) $branchId, 'document_type' => $type, 'period' => $period],
                    ['last_number' => $last, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        echo '  renumbered: '.(collect($counts)->map(fn ($n, $k) => "$k $n")->implode(', ') ?: 'nothing').".\n";
    }

    /**
     * EE-ZZ-YYYYMM-####(-V2) → ZZ-EE-YYMM#####(-V2); PV-EE-OR-YYYYMM-#### → PV-EE-YYMM#####. Null when the number is
     * not in the old layout (already converted, or free text).
     */
    private function reformat(string $number): ?string
    {
        if (! preg_match('/^(PV-)?([A-Z0-9]+)-([A-Z]+)-(\d{4})(\d{2})-(\d+)(-V\d+)?$/', trim($number), $m)) {
            return null;
        }

        return sprintf('%s-%s-%s%s%05d%s', $m[1] !== '' ? 'PV' : $m[3], $m[2], substr($m[4], 2), $m[5], (int) $m[6], $m[7] ?? '');
    }

    /** Remember the running number of a new-format number for the counters. */
    private function note(string $number, array $branchByCode, bool $csn = false): void
    {
        if ($csn && preg_match('/^CSN-([A-Z0-9]+)-([A-Z0-9]+)-(\d{4})(\d{5})$/', $number, $m)) {
            $key = ($branchByCode[$m[1]] ?? 0).'|CSN:'.$m[2].'|'.$m[3];
            $n = (int) $m[4];
        } elseif (! $csn && preg_match('/^([A-Z]+)-([A-Z0-9]+)-(\d{4})(\d{5})(-V\d+)?$/', $number, $m)) {
            $key = ($branchByCode[$m[2]] ?? 0).'|'.$m[1].'|'.$m[3];
            $n = (int) $m[4];
        } else {
            return;
        }

        if (! str_starts_with($key, '0|')) {
            $this->last[$key] = max($this->last[$key] ?? 0, $n);
        }
    }

    public function down(): void
    {
        foreach (['consignment_notes', 'subsheets'] as $table) {
            foreach (DB::table($table)->whereNotNull('old_number')->get(['id', 'old_number']) as $row) {
                DB::table($table)->where('id', $row->id)->update(['number' => $row->old_number, 'old_number' => null]);
            }
        }

        $back = function (string $number): ?string {
            if (! preg_match('/^([A-Z]+)-([A-Z0-9]+)-(\d{2})(\d{2})(\d{5})(-V\d+)?$/', $number, $m)) {
                return null;
            }

            return $m[1] === 'PV'
                ? sprintf('PV-%s-OR-20%s%s-%04d', $m[2], $m[3], $m[4], (int) $m[5])
                : sprintf('%s-%s-20%s%s-%04d%s', $m[2], $m[1], $m[3], $m[4], (int) $m[5], $m[6] ?? '');
        };

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (DB::table($table)->whereNotNull($column)->where($column, '!=', '')->get(['id', $column]) as $row) {
                    if ($old = $back((string) $row->{$column})) {
                        DB::table($table)->where('id', $row->id)->update([$column => $old]);
                    }
                }
            }
        }

        if (Schema::hasTable('quotes')) {
            foreach (DB::table('quotes')->orderBy('id')->get(['id', 'number']) as $quote) {
                if (preg_match('/^Q-[A-Z0-9]+-\d{4}(\d{5})$/', (string) $quote->number, $m)) {
                    DB::table('quotes')->where('id', $quote->id)->update(['number' => 'Q'.$m[1]]);
                }
            }
        }
    }
};

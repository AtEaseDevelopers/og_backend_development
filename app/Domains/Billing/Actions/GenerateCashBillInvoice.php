<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnBillingType;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateCashBillInvoice
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(ConsignmentNote $csn, User $actor): Invoice
    {
        if ($csn->billing_type !== CsnBillingType::CashBill) {
            throw new InvalidArgumentException('Cash Bill invoice only applies to Cash Bill CSNs.');
        }

        $existing = Invoice::query()->where('consignment_note_id', $csn->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($csn) {
            $csn->loadMissing('sourceBranch', 'lines');

            $subtotal = (float) $csn->subtotal;
            $tax = (float) $csn->tax_amount;
            $rawTotal = $subtotal + $tax;
            $rounded = round($rawTotal, 2);
            $rounding = round($rounded - $rawTotal, 2);

            $invoice = Invoice::query()->create([
                'number' => $this->numbering->next($csn->sourceBranch, DocumentType::Invoice),
                'source_branch_id' => $csn->source_branch_id,
                'customer_id' => $csn->customer_id,
                'consignment_note_id' => $csn->id,
                'type' => 'cash_bill',
                'billing_month' => now()->format('Y-m'),
                'status' => InvoiceStatus::Paid->value,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'rounding_amount' => $rounding,
                'total_amount' => $rounded,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
            ]);

            foreach ($csn->lines as $line) {
                $invoice->lines()->create([
                    'consignment_note_id' => $csn->id,
                    'description' => $line->item_name.' x '.$line->quantity,
                    'amount' => $line->line_total,
                ]);
            }

            return $invoice->load('lines');
        });
    }
}

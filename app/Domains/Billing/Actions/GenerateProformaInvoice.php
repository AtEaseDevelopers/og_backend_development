<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnBillingType;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateProformaInvoice
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(ConsignmentNote $csn): ProformaInvoice
    {
        if (! in_array($csn->billing_type, [CsnBillingType::Cod, CsnBillingType::CashBill], true)) {
            throw new InvalidArgumentException('Proforma is for COD / Advance Taken (or preview Cash Bill).');
        }

        $existing = ProformaInvoice::query()->where('consignment_note_id', $csn->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($csn) {
            $csn->loadMissing('sourceBranch');

            if ($csn->billing_type === CsnBillingType::Cod && $csn->payment_status === PaymentStatus::Unpaid->value) {
                $csn->update(['payment_status' => PaymentStatus::CodPending->value]);
            }

            return ProformaInvoice::query()->create([
                'number' => $this->numbering->next($csn->sourceBranch, DocumentType::Proforma),
                'consignment_note_id' => $csn->id,
                'source_branch_id' => $csn->source_branch_id,
                'total_amount' => $csn->total_amount,
                'status' => 'issued',
            ]);
        });
    }
}

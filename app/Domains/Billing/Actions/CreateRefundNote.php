<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\RefundNote;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\DocumentType;
use App\Models\User;
use App\Services\DocumentNumberingService;

/**
 * Section F: overpayment creates a Refund Note with payment remarks, the bank account
 * involved and the invoice number it is knocked off against. Completed notes join the
 * AutoCount sync (later phase).
 */
class CreateRefundNote
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(
        Quotation $quotation,
        float $amount,
        User $actor,
        ?Payment $payment = null,
        ?string $bankAccount = null,
        ?string $remarks = null,
    ): RefundNote {
        $quotation->loadMissing(['branch', 'invoices']);
        $invoice = $quotation->invoices->sortByDesc('id')->first();

        return RefundNote::query()->create([
            'number' => $this->numbering->next($quotation->branch, DocumentType::RefundNote),
            'company_id' => $quotation->company_id,
            'source_branch_id' => $quotation->branch_id,
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment?->id,
            'amount' => round($amount, 2),
            'bank_account' => $bankAccount,
            'knock_off_invoice_number' => $invoice?->number ?? $quotation->proformaInvoice?->number,
            'remarks' => $remarks,
            'status' => RefundNote::STATUS_DRAFT,
            'created_by' => $actor->id,
        ]);
    }

    public function complete(RefundNote $note, User $actor, ?string $bankAccount = null, ?string $remarks = null): RefundNote
    {
        $note->update([
            'status' => RefundNote::STATUS_COMPLETED,
            'completed_at' => now(),
            'bank_account' => $bankAccount ?? $note->bank_account,
            'remarks' => $remarks ?? $note->remarks,
        ]);

        activity()->performedOn($note)->causedBy($actor)->log('Refund note completed');

        return $note->fresh();
    }
}

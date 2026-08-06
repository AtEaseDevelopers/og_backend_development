<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Receipt;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnBillingType;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordPayment
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private GenerateCashBillInvoice $generateInvoice,
    ) {}

    public function execute(array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($data, $actor) {
            $csn = isset($data['consignment_note_id'])
                ? ConsignmentNote::query()->with('sourceBranch')->findOrFail($data['consignment_note_id'])
                : null;

            $branchId = $data['source_branch_id'] ?? $csn?->source_branch_id;
            if (! $branchId) {
                throw new InvalidArgumentException('source_branch_id is required.');
            }

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $payment = Payment::query()->create([
                'source_branch_id' => $branchId,
                'customer_id' => $data['customer_id'] ?? $csn?->customer_id,
                'consignment_note_id' => $csn?->id,
                'invoice_id' => $data['invoice_id'] ?? null,
                'delivery_order_id' => $data['delivery_order_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'method' => $data['method'],
                'amount' => $amount,
                'expected_amount' => $data['expected_amount'] ?? $csn?->total_amount,
                'shortage_amount' => $data['shortage_amount'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => $data['status'] ?? 'completed',
                'reconciliation_status' => $data['reconciliation_status'] ?? null,
                'slip_path' => $data['slip_path'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'received_by' => $actor->id,
            ]);

            $receiptType = $data['receipt_type'] ?? 'official';
            Receipt::query()->create([
                'number' => $this->numbering->next($branchId, DocumentType::Receipt),
                'source_branch_id' => $branchId,
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount' => $amount,
                'type' => $receiptType,
            ]);

            if ($csn) {
                $this->updateCsnPaymentStatus($csn, $amount, $data['method'] ?? null);
            }

            if (! empty($data['invoice_id'])) {
                $this->refreshInvoiceStatus((int) $data['invoice_id']);
            }

            if ($csn && $csn->billing_type === CsnBillingType::CashBill && $csn->fresh()->payment_status === PaymentStatus::Paid->value) {
                $invoice = $this->generateInvoice->execute($csn->fresh(), $actor);
                $payment->update(['invoice_id' => $invoice->id]);
            }

            return $payment->fresh(['receipt']);
        });
    }

    private function updateCsnPaymentStatus(ConsignmentNote $csn, float $amount, ?string $method): void
    {
        $paid = (float) Payment::query()
            ->where('consignment_note_id', $csn->id)
            ->where('status', 'completed')
            ->sum('amount');

        if ($csn->billing_type === CsnBillingType::Cod) {
            $status = $paid + 0.0001 >= (float) $csn->total_amount
                ? PaymentStatus::CodCollected->value
                : PaymentStatus::CodPending->value;
        } elseif ($paid + 0.0001 >= (float) $csn->total_amount) {
            $status = PaymentStatus::Paid->value;
        } elseif ($paid > 0) {
            $status = PaymentStatus::Partial->value;
        } else {
            $status = PaymentStatus::Unpaid->value;
        }

        if ($method === 'credit') {
            $status = PaymentStatus::Credit->value;
        }

        $csn->update(['payment_status' => $status]);
    }

    private function refreshInvoiceStatus(int $invoiceId): void
    {
        $invoice = Invoice::query()->find($invoiceId);
        if (! $invoice) {
            return;
        }

        $paid = (float) Payment::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');

        $status = match (true) {
            $paid + 0.0001 >= (float) $invoice->total_amount => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Outstanding,
        };

        $invoice->update(['status' => $status->value]);
    }
}

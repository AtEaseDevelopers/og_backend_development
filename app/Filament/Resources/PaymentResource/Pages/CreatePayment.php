<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Filament\Resources\PaymentResource;
use App\Support\CurrentCompany;
use App\Support\PaymentListingData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create Payment (invoice payment). The branch is the one being viewed. With invoices picked, the amount is split
 * over them oldest first (each up to what is still outstanding) and recorded as one payment per invoice against the
 * invoice's CSN and order: the invoice, CSN and order (paid amount → "Paid") follow, and the order's payment history
 * lists the payment number. Without invoices it is one payment for the customer.
 */
class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /** @var list<string> payment numbers recorded by this save (for the notification) */
    protected array $recordedNumbers = [];

    protected function handleRecordCreation(array $data): Model
    {
        $branchId = CurrentCompany::branchId();

        if (! $branchId) {
            throw ValidationException::withMessages(['data.customer_id' => 'Select a branch first (top of the page).']);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $invoiceIds = array_values(array_filter(array_map('intval', (array) ($data['invoice_ids'] ?? []))));
        $base = [
            'source_branch_id' => $branchId,
            'company_id' => CurrentCompany::id(),
            'customer_id' => $data['customer_id'] ?? null,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ];

        if ($invoiceIds === []) {
            $payment = app(RecordPayment::class)->execute($base + ['amount' => $amount], auth()->user());
            $this->recordedNumbers = [PaymentListingData::paymentNumber($payment)];

            return $payment;
        }

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
        $outstanding = round($invoices->sum(fn (Invoice $invoice) => PaymentResource::outstanding($invoice)), 2);

        if ($outstanding <= 0) {
            throw ValidationException::withMessages(['data.invoice_ids' => 'The invoices picked have nothing left to pay.']);
        }

        if ($amount > $outstanding + 0.004) {
            throw ValidationException::withMessages(['data.amount' => 'The amount is more than the outstanding on the invoices picked (RM '.number_format($outstanding, 2).').']);
        }

        return DB::transaction(function () use ($invoices, $amount, $base): Payment {
            $left = $amount;
            $last = null;

            foreach ($invoices as $invoice) {
                $due = PaymentResource::outstanding($invoice);
                $part = round(min($left, $due), 2);

                if ($part <= 0) {
                    continue;
                }

                // against the invoice's CSN and order: CSN payment status and the order's paid amount follow
                $last = app(RecordPayment::class)->execute(array_merge($base, [
                    'customer_id' => $invoice->customer_id ?? $base['customer_id'],
                    'invoice_id' => $invoice->id,
                    'consignment_note_id' => $invoice->consignment_note_id,
                    'quotation_id' => $invoice->quotation_id,
                    'amount' => $part,
                    'expected_amount' => $due,
                ]), auth()->user());
                $this->recordedNumbers[] = PaymentListingData::paymentNumber($last);
                $left = round($left - $part, 2);

                if ($left <= 0) {
                    break;
                }
            }

            return $last;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(count($this->recordedNumbers) > 1 ? count($this->recordedNumbers).' payments recorded' : 'Payment recorded')
            ->body(implode(', ', $this->recordedNumbers));
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('index');
    }
}

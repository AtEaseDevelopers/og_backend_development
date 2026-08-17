<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluateCreditEligibility
{
    /**
     * @return array{allowed: bool, reasons: array<int, string>, request: ?CreditApprovalRequest}
     */
    public function execute(Quotation $quotation, User $actor, bool $createRequest = true): array
    {
        $quotation->loadMissing('customer', 'branch');
        $customer = $quotation->customer;

        $reasons = [];

        if (! $customer->is_credit) {
            return ['allowed' => true, 'reasons' => [], 'request' => null];
        }

        if (! $this->hasPresetPricing($customer, $quotation)) {
            $reasons[] = 'No approved preset pricing for requested item/route';
        }

        $outstanding = $this->outstandingBalance($customer);
        if ($customer->credit_limit > 0 && ($outstanding + (float) $quotation->total_amount) > (float) $customer->credit_limit) {
            $reasons[] = sprintf(
                'Credit limit exceeded (limit RM %s, outstanding RM %s, this quote RM %s)',
                number_format((float) $customer->credit_limit, 2),
                number_format($outstanding, 2),
                number_format((float) $quotation->total_amount, 2)
            );
        }

        if ($customer->credit_term_days > 0 && $this->hasOverdueInvoices($customer)) {
            $reasons[] = 'Customer has overdue invoices beyond approved credit term';
        }

        if ($reasons === []) {
            return ['allowed' => true, 'reasons' => [], 'request' => null];
        }

        $request = null;
        if ($createRequest) {
            $request = CreditApprovalRequest::query()
                ->where('quotation_id', $quotation->id)
                ->where('status', 'pending')
                ->first();

            if (! $request) {
                $request = DB::transaction(function () use ($quotation, $customer, $actor, $reasons) {
                    $quotation->update(['status' => QuotationStatus::PendingApproval]);

                    return CreditApprovalRequest::query()->create([
                        'customer_id' => $customer->id,
                        'company_id' => $quotation->company_id,
                        'branch_id' => $quotation->branch_id,
                        'quotation_id' => $quotation->id,
                        'reason' => implode('; ', $reasons),
                        'requested_amount' => $quotation->total_amount,
                        'trigger_details' => ['reasons' => $reasons],
                        'status' => 'pending',
                        'requested_by' => $actor->id,
                    ]);
                });
            }
        }

        return ['allowed' => false, 'reasons' => $reasons, 'request' => $request];
    }

    private function hasPresetPricing(Customer $customer, Quotation $quotation): bool
    {
        if ($customer->pricing()->where('is_active', true)->exists()) {
            return true;
        }

        // Manual / previous / formula pricing recorded on quotation is acceptable.
        return in_array($quotation->pricing_source, ['previous', 'special', 'formula', 'manual', 'default'], true);
    }

    private function outstandingBalance(Customer $customer): float
    {
        return (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                InvoiceStatus::Confirmed->value,
                InvoiceStatus::Outstanding->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->sum('total_amount');
    }

    private function hasOverdueInvoices(Customer $customer): bool
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                InvoiceStatus::Confirmed->value,
                InvoiceStatus::Outstanding->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->exists();
    }
}

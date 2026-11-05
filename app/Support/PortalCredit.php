<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;

/**
 * Section P: the credit position a Credit / Term customer sees on an order: limit, outstanding, available,
 * credit term and overdue invoices, plus the Branch Manager approval request when the order needed one.
 */
class PortalCredit
{
    /** @return array<string, mixed>|null null when the order is not Credit / Term */
    public static function for(Quotation $quotation): ?array
    {
        $customer = $quotation->customer;

        if (! $customer || ($quotation->orderType() !== OrderType::Term && ! $customer->is_credit)) {
            return null;
        }

        $open = Invoice::query()
            ->with('payments')
            ->where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Confirmed->value, InvoiceStatus::Outstanding->value, InvoiceStatus::PartiallyPaid->value])
            ->get();

        $outstanding = round($open->sum(fn (Invoice $i) => (float) $i->total_amount - (float) $i->payments->where('status', 'completed')->sum('amount')), 2);
        $overdue = $open->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isBefore(today()))->count();
        $limit = (float) $customer->credit_limit;
        $request = $quotation->creditApprovalRequests->sortByDesc('id')->first();

        return [
            'is_term' => $quotation->orderType() === OrderType::Term,
            'limit' => $limit,
            'outstanding' => $outstanding,
            'available' => $limit > 0 ? round($limit - $outstanding, 2) : null,
            'term_days' => (int) $customer->credit_term_days,
            'overdue_invoices' => $overdue,
            'within_limit' => $limit <= 0 || ($outstanding + (float) $quotation->total_amount) <= $limit,
            'approval' => $request ? [
                'status' => $request->status,
                'label' => match ($request->status) {
                    'pending' => 'Pending Branch Manager approval',
                    'approved' => 'Approved by Branch Manager',
                    'rejected' => 'Rejected by Branch Manager',
                    'cancelled' => 'Not needed (cancelled)',
                    default => ucfirst((string) $request->status),
                },
                'reason' => $request->reason,
                'remarks' => $request->remarks,
                'decided_at' => $request->decided_at?->format('d M Y, H:i'),
            ] : null,
        ];
    }
}

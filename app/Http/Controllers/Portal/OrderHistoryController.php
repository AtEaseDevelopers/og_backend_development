<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Section P: history of the customer's quotations / orders with their payment status. */
class OrderHistoryController extends Controller
{
    use UsesPortalCustomer;

    public function __invoke(Request $request): View
    {
        $customer = $this->portalCustomer();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $quotations = Quotation::query()
            ->with(['branch', 'paymentSubmissions', 'onlinePayments', 'creditApprovalRequests', 'consignmentNotes:id,quotation_id,number,status'])
            ->where('customer_id', $customer->id)
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhere('customer_do_number', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.orders', [
            'quotations' => $quotations,
            'filters' => $filters,
            'statuses' => collect(QuotationStatus::cases())->mapWithKeys(fn (QuotationStatus $s) => [$s->value => $s->label()])->all(),
        ]);
    }
}

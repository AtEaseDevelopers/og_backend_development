<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Quotation\Actions\AcceptQuotation;
use App\Domains\Quotation\Actions\RejectQuotation;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Enums\QuotationRejectionCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class QuotationController extends Controller
{
    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorizeCustomer($request, $quotation);

        $quotation->load([
            'destinations', 'lines', 'branch', 'customer', 'salesperson', 'proformaInvoice',
            'paymentSubmissions' => fn ($q) => $q->latest('id'),
            'invoices', 'consignmentNotes.deliveryOrder.lorry', 'statusLogs' => fn ($q) => $q->latest('id')->limit(20),
        ]);

        $versions = Quotation::query()
            ->where(fn ($q) => $q->where('root_quotation_id', $quotation->rootId())->orWhere('id', $quotation->rootId()))
            ->orderBy('version')
            ->get(['id', 'number', 'version', 'status', 'total_amount', 'sent_at', 'confirmed_at']);

        return view('portal.quotation-show', [
            'quotation' => $quotation,
            'versions' => $versions,
            'isLatest' => $quotation->isLatestVersion(),
            'rejectionCategories' => QuotationRejectionCategory::options(),
            'paymentMethods' => PaymentMethod::options(customerFacing: true),
        ]);
    }

    public function confirm(Request $request, Quotation $quotation, AcceptQuotation $accept): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);

        try {
            $accept->execute(
                $quotation,
                AcceptQuotation::CHANNEL_PORTAL,
                $request->user()->name,
                $request->user(),
                'Accepted in Customer Portal by '.$request->user()->email.' from '.$request->ip(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Your order has been confirmed. A proforma invoice is now available below.');
    }

    public function reject(Request $request, Quotation $quotation, RejectQuotation $reject): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);

        $data = $request->validate([
            'rejection_category' => ['required', Rule::in(array_keys(QuotationRejectionCategory::options()))],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $reject->execute(
                $quotation,
                QuotationRejectionCategory::from($data['rejection_category']),
                $data['rejection_reason'] ?? null,
                $request->user(),
                'portal',
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', $result->status->value === 'negotiation'
            ? 'Your request has been sent to your salesperson. A revised quotation will follow.'
            : 'Quotation rejected.');
    }

    /** Amendment request = negotiation on scope (section D). */
    public function requestAmendment(Request $request, Quotation $quotation, RejectQuotation $reject): RedirectResponse
    {
        $this->authorizeCustomer($request, $quotation);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        try {
            $reject->execute($quotation, QuotationRejectionCategory::ScopeChange, $data['remarks'], $request->user(), 'portal');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Amendment request submitted. Your salesperson will issue a revised quotation.');
    }

    private function authorizeCustomer(Request $request, Quotation $quotation): void
    {
        $customerIds = $request->user()->customers()->pluck('customers.id');
        if ($request->user()->customer_id) {
            $customerIds->push($request->user()->customer_id);
        }

        abort_unless($customerIds->contains($quotation->customer_id), 403);
    }
}

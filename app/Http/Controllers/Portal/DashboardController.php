<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\CsnStatus;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use App\Support\StatementData;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use UsesPortalCustomer;

    public function __invoke(): View
    {
        $customer = $this->portalCustomer();

        if (! config('og.portal_full_menu')) {
            return view('portal.dashboard', [
                'customer' => $customer,
                'enquiries' => PortalEnquiry::query()->where('customer_id', $customer->id)->latest()->limit(20)->get(),
            ]);
        }

        $quotations = Quotation::query()
            ->with(['branch', 'paymentSubmissions', 'onlinePayments', 'creditApprovalRequests'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(10)
            ->get();

        $csns = ConsignmentNote::query()
            ->with('deliveryOrder')
            ->where('customer_id', $customer->id)
            ->latest()
            ->limit(5)
            ->get();

        $statement = app(StatementData::class)->build($customer);

        return view('portal.dashboard', [
            'customer' => $customer,
            'quotations' => $quotations,
            'csns' => $csns,
            'enquiries' => PortalEnquiry::query()->where('customer_id', $customer->id)->latest()->limit(10)->get(),
            'counts' => [
                'awaiting_you' => Quotation::query()->where('customer_id', $customer->id)
                    ->whereIn('status', [QuotationStatus::Sent->value, QuotationStatus::PendingReview->value])->count(),
                'in_transit' => ConsignmentNote::query()->where('customer_id', $customer->id)
                    ->whereIn('status', [CsnStatus::Assigned->value, CsnStatus::InTransit->value])->count(),
                'delivered_30d' => ConsignmentNote::query()->where('customer_id', $customer->id)
                    ->where('status', CsnStatus::Delivered->value)->where('updated_at', '>=', now()->subDays(30))->count(),
                'outstanding' => $statement['outstanding'],
            ],
        ]);
    }
}

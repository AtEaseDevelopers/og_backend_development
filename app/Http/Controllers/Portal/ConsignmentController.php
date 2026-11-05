<?php

namespace App\Http\Controllers\Portal;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use App\Support\PortalDeliveryView;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Section P: the customer's consignment notes and deliveries, each delivery order tracked separately. */
class ConsignmentController extends Controller
{
    use UsesPortalCustomer;

    public function index(Request $request): View
    {
        $customer = $this->portalCustomer();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $csns = ConsignmentNote::query()
            ->with(['quotation:id,number', 'deliveryOrders:id,consignment_note_id,number,status,parent_do_id,subsheet_id,tracking_token'])
            ->where('customer_id', $customer->id)
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhere('customer_do_number', 'like', "%{$s}%")
                ->orWhere('do_number', 'like', "%{$s}%")
                ->orWhere('consignee_name', 'like', "%{$s}%")
                ->orWhereHas('deliveryOrders', fn ($d) => $d->where('number', 'like', "%{$s}%"))))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('portal.csns', [
            'csns' => $csns,
            'filters' => $filters,
            'statuses' => collect(CsnStatus::cases())->reject(fn (CsnStatus $s) => $s === CsnStatus::Draft)
                ->mapWithKeys(fn (CsnStatus $s) => [$s->value => $s->getLabel()])->all(),
        ]);
    }

    public function show(ConsignmentNote $csn, PortalDeliveryView $view): View
    {
        $this->authorizeCustomerRecord($csn->customer_id);
        $csn->load(array_merge(PortalDeliveryView::RELATIONS, ['lines', 'invoice', 'sourceBranch']));

        return view('portal.csn-show', [
            'csn' => $csn,
            'timeline' => $view->timeline($csn),
            'deliveryOrders' => $view->deliveryOrders($csn),
        ]);
    }
}

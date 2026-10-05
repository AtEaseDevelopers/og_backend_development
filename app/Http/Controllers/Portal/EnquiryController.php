<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function create(Request $request): View
    {
        $branches = Branch::query()->where('is_active', true)->get();
        $selectedBranch = PortalSelection::branch();
        $linkedSalesperson = AssignEnquirySalesperson::resolveByToken($request->session()->get(SalespersonLinkController::SESSION_KEY));

        $salespersons = User::query()
            ->role('salesperson')
            ->where('is_active', true)
            ->when($selectedBranch, fn ($q) => $q->whereHas('branches', fn ($b) => $b->where('branches.id', $selectedBranch->id)))
            ->orderBy('name')
            ->get();

        $customer = $request->user()->customer;

        return view('portal.enquiry-create', [
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'linkedSalesperson' => $linkedSalesperson,
            'salespersons' => $salespersons,
            'orderTypes' => $this->orderTypeOptions($customer?->is_credit ?? false),
            'paymentMethods' => PaymentMethod::options(customerFacing: true),
            'serviceTypes' => \App\Enums\ServiceType::options(),
            'dropOffTypes' => DropOffType::options(),
            'defaultOrderType' => $customer?->default_order_type ?? ($customer?->is_credit ? OrderType::Term->value : OrderType::Cash->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()->customer;
        $allowedOrderTypes = array_keys($this->orderTypeOptions($customer?->is_credit ?? false));

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'order_type' => ['required', Rule::in($allowedOrderTypes)],
            'service_type' => ['required', Rule::in(array_keys(\App\Enums\ServiceType::options()))],
            'payment_method' => ['required', Rule::in(array_keys(PaymentMethod::options(customerFacing: true)))],
            'customer_do_number' => ['required', 'string', 'max:100'],
            'pickup_address' => ['required', 'string'],
            'pickup_maps_url' => ['nullable', 'url'],
            'preferred_delivery_date' => ['nullable', 'date'],
            'special_requirements' => ['nullable', 'string'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.address' => ['required', 'string'],
            'destinations.*.consignee_name' => ['nullable', 'string'],
            'destinations.*.consignee_phone' => ['nullable', 'string', 'max:40'],
            'destinations.*.postcode' => ['nullable', 'string'],
            'destinations.*.state' => ['nullable', 'string'],
            'destinations.*.city' => ['nullable', 'string'],
            'destinations.*.drop_off_type' => ['required', Rule::in(array_keys(DropOffType::options()))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.uom' => ['nullable', 'string'],
            'items.*.weight' => ['nullable', 'numeric'],
            'items.*.destination_index' => ['nullable', 'integer', 'min:0'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $customerId = $request->user()->customer_id
            ?? $request->user()->customers()->wherePivot('status', 'approved')->value('customers.id');

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $companyId = PortalSelection::companyId()
            ?? Company::query()->where('branch_id', $branch->id)->value('id');

        $linkedSalesperson = AssignEnquirySalesperson::resolveByToken($request->session()->get(SalespersonLinkController::SESSION_KEY));
        $chosenSalesperson = $linkedSalesperson
            ?? (filled($data['salesperson_id'] ?? null) ? User::query()->find($data['salesperson_id']) : null);

        $enquiry = DB::transaction(function () use ($request, $data, $customerId, $companyId, $linkedSalesperson, $chosenSalesperson, $branch) {
            $enquiry = PortalEnquiry::query()->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'branch_id' => $data['branch_id'],
                'user_id' => $request->user()->id,
                'reference_no' => 'ENQ-'.Str::upper(Str::random(8)),
                'order_number' => app(\App\Services\DocumentNumberingService::class)->next($branch, \App\Enums\DocumentType::Order),
                'source' => $linkedSalesperson ? PortalEnquiry::SOURCE_SALESPERSON_LINK : PortalEnquiry::SOURCE_PORTAL,
                'order_type' => $data['order_type'],
                'service_type' => $data['service_type'],
                'payment_method' => $data['payment_method'],
                'customer_do_number' => $data['customer_do_number'],
                'pickup_address' => $data['pickup_address'],
                'pickup_maps_url' => $data['pickup_maps_url'] ?? null,
                'preferred_delivery_date' => $data['preferred_delivery_date'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'status' => 'pending',
                'payload' => [
                    'destinations' => array_values($data['destinations']),
                    'items' => array_values($data['items']),
                ],
            ]);

            $attachments = [];

            foreach ($request->file('photos', []) as $file) {
                $path = $file->store('portal-enquiries/'.$enquiry->id, 'public');
                $attachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->name,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }

            if ($attachments !== []) {
                $enquiry->update(['attachments' => $attachments]);
            }

            if ($chosenSalesperson) {
                app(AssignEnquirySalesperson::class)->execute(
                    $enquiry,
                    $chosenSalesperson,
                    null,
                    lock: $linkedSalesperson !== null,
                    source: $linkedSalesperson ? PortalEnquiry::SOURCE_SALESPERSON_LINK : PortalEnquiry::SOURCE_PORTAL,
                );
            }

            return $enquiry;
        });

        $request->session()->forget(SalespersonLinkController::SESSION_KEY);

        return redirect()->route('portal.dashboard')
            ->with('status', 'Order '.$enquiry->reference_no.' submitted for review.');
    }

    /** @return array<string, string> */
    private function orderTypeOptions(bool $isCredit): array
    {
        $options = OrderType::options();

        if (! $isCredit) {
            unset($options[OrderType::Term->value]);
        }

        return $options;
    }
}

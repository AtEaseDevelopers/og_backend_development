<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Uom;
use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalSelection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

        $customer = PortalSelection::customer();
        $customer?->loadMissing(['addresses', 'pics']);
        $defaultPic = $customer?->pics?->firstWhere('is_default', true) ?? $customer?->pics?->first();

        return view('portal.enquiry-create', [
            'customer' => $customer,
            'pickupAddresses' => $customer?->addresses->where('type', 'pickup')->values() ?? collect(),
            'deliveryAddresses' => $customer?->addresses->where('type', '!=', 'pickup')->values() ?? collect(),
            'productKinds' => Uom::CATEGORIES,
            'productCatalog' => $this->productCatalog($selectedBranch?->id ?? $customer?->branch_id),
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'linkedSalesperson' => $linkedSalesperson,
            'salespersons' => $salespersons,
            'orderTypes' => $this->orderTypeOptions($customer?->is_credit ?? false),
            'paymentMethods' => PaymentMethod::options(customerFacing: true),
            'serviceTypes' => \App\Enums\ServiceType::options(),
            'dropOffTypes' => DropOffType::options(),
            'defaultOrderType' => $customer?->default_order_type ?? ($customer?->is_credit ? OrderType::Term->value : OrderType::Cash->value),
            'customerDefaults' => [
                'name' => (string) ($customer?->company_name ?? ''),
                'address' => (string) ($customer?->address ?? ''),
                'pic_name' => (string) ($defaultPic?->name ?? $customer?->attention ?? ''),
                'pic_phone' => (string) ($defaultPic?->phone ?? $customer?->phone ?? $customer?->mobile ?? ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = PortalSelection::customer();
        $customer?->loadMissing(['pics']);
        $allowedOrderTypes = array_keys($this->orderTypeOptions($customer?->is_credit ?? false));

        if (! $request->has('orders')) {
            $request->merge(['orders' => [$request->except(['_token', 'orders'])]]);
        }

        $orders = collect($request->input('orders', []))->map(function ($block) use ($customer, $allowedOrderTypes) {
            $block = is_array($block) ? $block : [];
            $pic = $customer?->pics?->firstWhere('is_default', true) ?? $customer?->pics?->first();
            $block['customer_name'] = $block['customer_name'] ?? $customer?->company_name;
            $block['customer_address'] = $block['customer_address'] ?? $customer?->address;
            $block['customer_pic_name'] = $block['customer_pic_name'] ?? ($pic?->name ?? $customer?->attention);
            $block['customer_pic_phone'] = $block['customer_pic_phone'] ?? ($pic?->phone ?? $customer?->phone ?? $customer?->mobile);
            $block['branch_id'] = $block['branch_id'] ?? PortalSelection::branch()?->id ?? $customer?->branch_id;
            $block['order_type'] = $block['order_type']
                ?? $customer?->default_order_type
                ?? ($customer?->is_credit ? OrderType::Term->value : OrderType::Cash->value);
            if (! in_array($block['order_type'], $allowedOrderTypes, true)) {
                $block['order_type'] = $allowedOrderTypes[0] ?? OrderType::Cash->value;
            }
            $block['service_type'] = $block['service_type'] ?? \App\Enums\ServiceType::Pickup->value;
            $block['payment_method'] = $block['payment_method']
                ?? array_key_first(PaymentMethod::options(customerFacing: true));
            $block['customer_do_number'] = $block['customer_do_number'] ?? null;

            return $block;
        })->all();
        $request->merge(['orders' => $orders]);

        $data = $request->validate([
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.customer_name' => ['required', 'string', 'max:255'],
            'orders.*.customer_address' => ['nullable', 'string', 'max:2000'],
            'orders.*.customer_pic_name' => ['nullable', 'string', 'max:255'],
            'orders.*.customer_pic_phone' => ['nullable', 'string', 'max:50'],
            'orders.*.branch_id' => ['required', 'exists:branches,id'],
            'orders.*.order_type' => ['required', Rule::in($allowedOrderTypes)],
            'orders.*.service_type' => ['required', Rule::in(array_keys(\App\Enums\ServiceType::options()))],
            'orders.*.payment_method' => ['required', Rule::in(array_keys(PaymentMethod::options(customerFacing: true)))],
            'orders.*.customer_do_number' => ['nullable', 'string', 'max:100'],
            'orders.*.pickup_address' => ['required', 'string'],
            'orders.*.pickup_maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.preferred_delivery_date' => ['nullable', 'date'],
            'orders.*.special_requirements' => ['nullable', 'string'],
            'orders.*.route_mode' => ['nullable', Rule::in(['single', 'multi_pickup', 'multi_dropoff'])],
            'orders.*.extra_pickups' => ['nullable', 'array'],
            'orders.*.extra_pickups.*.address' => ['required', 'string'],
            'orders.*.extra_pickups.*.maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.extra_pickups.*.special_requirements' => ['nullable', 'string'],
            'orders.*.destinations' => ['required', 'array', 'min:1'],
            'orders.*.destinations.*.address' => ['required', 'string'],
            'orders.*.destinations.*.consignee_name' => ['nullable', 'string'],
            'orders.*.destinations.*.consignee_phone' => ['nullable', 'string', 'max:40'],
            'orders.*.destinations.*.postcode' => ['nullable', 'string'],
            'orders.*.destinations.*.state' => ['nullable', 'string'],
            'orders.*.destinations.*.city' => ['nullable', 'string'],
            'orders.*.destinations.*.drop_off_type' => ['required', Rule::in(array_keys(DropOffType::options()))],
            'orders.*.destinations.*.maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.destinations.*.special_requirements' => ['nullable', 'string'],
            'orders.*.items' => ['required', 'array', 'min:1'],
            'orders.*.items.*.kind' => ['nullable', Rule::in(array_keys(Uom::CATEGORIES))],
            'orders.*.items.*.sub_category' => ['nullable', 'string', 'max:100'],
            'orders.*.items.*.uom_id' => ['nullable', 'integer'],
            'orders.*.items.*.item_name' => ['required', 'string'],
            'orders.*.items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'orders.*.items.*.uom' => ['nullable', 'string'],
            'orders.*.items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.length' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.length_max' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.width' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.width_max' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.height' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.height_max' => ['nullable', 'numeric', 'min:0'],
            'orders.*.items.*.handling' => ['nullable', 'string', 'max:255'],
            'orders.*.items.*.destination_index' => ['nullable', 'integer', 'min:0'],
            'orders.*.contacts' => ['nullable', 'array', 'max:10'],
            'orders.*.contacts.*.name' => ['nullable', 'string', 'max:255'],
            'orders.*.contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'orders.*.contacts.*.role' => ['nullable', 'string', 'max:100'],
            'orders.*.photos' => ['nullable', 'array', 'max:10'],
            'orders.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv', 'max:10240'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv', 'max:10240'],
        ]);

        $linkedSalesperson = AssignEnquirySalesperson::resolveByToken($request->session()->get(SalespersonLinkController::SESSION_KEY));
        $chosenSalesperson = $linkedSalesperson
            ?? (filled($data['salesperson_id'] ?? null) ? User::query()->find($data['salesperson_id']) : null);

        $enquiries = DB::transaction(function () use ($request, $data, $customer, $linkedSalesperson, $chosenSalesperson) {
            $created = [];

            foreach (array_values($data['orders']) as $index => $block) {
                $created[] = $this->storeOrderBlock($request, $block, $index, $customer, $linkedSalesperson, $chosenSalesperson);
            }

            return $created;
        });

        $request->session()->forget(SalespersonLinkController::SESSION_KEY);

        foreach ($enquiries as $enquiry) {
            event(new \App\Events\PortalOrderSubmitted($enquiry->loadMissing('customer')));
        }

        $refs = collect($enquiries)->pluck('reference_no')->implode(', ');

        return redirect()->route('portal.dashboard')
            ->with('status', count($enquiries) === 1
                ? 'Order '.$refs.' submitted for review.'
                : count($enquiries).' orders submitted for review: '.$refs.'.');
    }

    public function pdf(PortalEnquiry $enquiry): Response
    {
        abort_unless((int) $enquiry->customer_id === (int) PortalSelection::customerId(), 404);

        $enquiry->loadMissing(['customer', 'branch', 'company']);
        $number = $enquiry->order_number ?: $enquiry->reference_no;

        return Pdf::loadView('pdf.portal-enquiry', [
            'enquiry' => $enquiry,
            'payload' => is_array($enquiry->payload) ? $enquiry->payload : [],
            'number' => $number,
            'kinds' => Uom::CATEGORIES,
            'dropOffTypes' => DropOffType::options(),
        ])->setPaper('a4')->stream($number.'.pdf');
    }

    private function storeOrderBlock(
        Request $request,
        array $block,
        int $index,
        ?\App\Domains\MasterData\Models\Customer $customer,
        $linkedSalesperson,
        $chosenSalesperson,
    ): PortalEnquiry {
        $hasLorry = collect($block['items'])->contains(fn (array $item) => ($item['kind'] ?? '') === Uom::LORRY);
        $extraPickups = array_values(array_filter($block['extra_pickups'] ?? [], fn ($pickup) => is_array($pickup) && filled($pickup['address'] ?? null)));
        $destCount = count($block['destinations'] ?? []);
        if (! $hasLorry) {
            $routeMode = 'single';
        } elseif ($extraPickups !== [] && $destCount > 1) {
            throw ValidationException::withMessages([
                "orders.$index.destinations" => 'Add extra pickups or extra drop-offs, not both.',
            ]);
        } elseif ($extraPickups !== []) {
            $routeMode = 'multi_pickup';
        } elseif ($destCount > 1) {
            $routeMode = 'multi_dropoff';
        } else {
            $routeMode = 'single';
        }

        if ($routeMode !== 'multi_dropoff' && $destCount > 1) {
            throw ValidationException::withMessages([
                "orders.$index.destinations" => 'Only a chartered lorry trip can have several drop-offs.',
            ]);
        }

        if ($routeMode !== 'multi_pickup') {
            $block['extra_pickups'] = [];
        } else {
            $block['extra_pickups'] = $extraPickups;
        }

        $block['route_mode'] = $routeMode;
        $block['items'] = array_map(function (array $item) {
            $item['category'] = $item['sub_category'] ?? null;
            if (filled($item['length'] ?? null)) {
                $item['dimensions'] = collect([
                    ($item['length'] ?? '').(filled($item['length_max'] ?? null) ? '–'.$item['length_max'] : ''),
                    ($item['width'] ?? '').(filled($item['width_max'] ?? null) ? '–'.$item['width_max'] : ''),
                    ($item['height'] ?? '').(filled($item['height_max'] ?? null) ? '–'.$item['height_max'] : ''),
                ])->filter()->implode(' × ').' cm';
            }

            return $item;
        }, $block['items']);

        $contacts = collect($block['contacts'] ?? [])->filter(fn ($c) => filled($c['name'] ?? null) || filled($c['phone'] ?? null))->values()->all();
        $branch = Branch::query()->findOrFail($block['branch_id']);
        $companyId = PortalSelection::companyId()
            ?? Company::query()->where('branch_id', $branch->id)->value('id');

        $enquiry = PortalEnquiry::query()->create([
            'company_id' => $companyId,
            'customer_id' => $customer?->id,
            'branch_id' => $block['branch_id'],
            'user_id' => $request->user()->id,
            'reference_no' => 'ENQ-'.Str::upper(Str::random(8)),
            'order_number' => app(\App\Services\DocumentNumberingService::class)->next($branch, \App\Enums\DocumentType::Order),
            'source' => $linkedSalesperson ? PortalEnquiry::SOURCE_SALESPERSON_LINK : PortalEnquiry::SOURCE_PORTAL,
            'order_type' => $block['order_type'],
            'service_type' => $block['service_type'],
            'payment_method' => $block['payment_method'],
            'customer_do_number' => $block['customer_do_number'] ?? null,
            'pickup_address' => $block['pickup_address'],
            'pickup_maps_url' => $block['pickup_maps_url'] ?? null,
            'preferred_delivery_date' => $block['preferred_delivery_date'] ?? null,
            'special_requirements' => $block['special_requirements'] ?? null,
            'status' => 'pending',
            'payload' => [
                'customer_name' => $block['customer_name'] ?? $customer?->company_name,
                'customer_address' => $block['customer_address'] ?? null,
                'customer_pic_name' => $block['customer_pic_name'] ?? null,
                'customer_pic_phone' => $block['customer_pic_phone'] ?? null,
                'route_mode' => $block['route_mode'],
                'pickups' => array_values(array_filter([
                    [
                        'address' => $block['pickup_address'],
                        'maps_url' => $block['pickup_maps_url'] ?? null,
                        'special_requirements' => $block['special_requirements'] ?? null,
                    ],
                    ...($block['extra_pickups'] ?? []),
                ], fn (array $pickup) => filled($pickup['address'] ?? null))),
                'destinations' => array_values($block['destinations']),
                'items' => array_values($block['items']),
                'contacts' => $contacts,
            ],
        ]);

        $files = $request->file('orders.'.$index.'.photos') ?: ($index === 0 ? $request->file('photos', []) : []);
        $attachments = [];
        foreach ($files ?? [] as $file) {
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

    /**
     * Active products of this company, grouped like the admin order form (type → sub-category → product).
     *
     * @return array<string, array<string, list<array{id: int, name: string}>>>
     */
    private function productCatalog(?int $branchId): array
    {
        $catalog = array_fill_keys(array_keys(Uom::CATEGORIES), []);
        $rows = Uom::withoutGlobalScopes()
            ->where('is_active', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('sub_category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'sub_category', 'base_item']);

        foreach ($rows as $row) {
            $kind = $row->category && isset($catalog[$row->category]) ? $row->category : Uom::MEASUREMENT;
            $sub = $row->sub_category ?: $row->base_item ?: $row->name;
            $catalog[$kind][$sub] ??= [];
            $catalog[$kind][$sub][] = ['id' => (int) $row->id, 'name' => $row->name];
        }

        return $catalog;
    }
}

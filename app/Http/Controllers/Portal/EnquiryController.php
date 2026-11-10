<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Store;
use App\Domains\MasterData\Models\Uom;
use App\Domains\Quotation\Actions\AssignEnquirySalesperson;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MeasurementBand;
use App\Support\OrderFormOptions;
use App\Support\PortalSelection;
use App\Support\PriceListRates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
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
            // the O&G stores of the company: where the customer can send the goods, or the consignee can collect them
            'stores' => $this->stores()->map(fn (Store $store) => [
                'id' => (int) $store->id,
                'label' => $store->label().($store->branch ? ' · '.$store->branch->name : ''),
                'address' => OrderFormOptions::storeAddress($store),
            ])->values()->all(),
            'declaration' => PortalEnquiry::DECLARATION,
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
            // Pickup: the address O&G collects from; Store: the O&G store the customer sends the goods to
            'orders.*.pickup_address' => ['nullable', 'string'],
            'orders.*.store_id' => ['nullable', 'integer'],
            'orders.*.pickup_maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.pickup_date' => ['nullable', 'date'],
            'orders.*.preferred_delivery_date' => ['nullable', 'date'],
            'orders.*.special_requirements' => ['nullable', 'string'],
            'orders.*.route_mode' => ['nullable', Rule::in(['single', 'multi_pickup', 'multi_dropoff'])],
            'orders.*.extra_pickups' => ['nullable', 'array'],
            'orders.*.extra_pickups.*.address' => ['required', 'string'],
            'orders.*.extra_pickups.*.maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.extra_pickups.*.special_requirements' => ['nullable', 'string'],
            'orders.*.destinations' => ['required', 'array', 'min:1'],
            // Delivery to an address, or Own collection at an O&G store
            'orders.*.destinations.*.mode' => ['nullable', Rule::in(['delivery', 'collect'])],
            'orders.*.destinations.*.collect_store_id' => ['nullable', 'integer'],
            'orders.*.destinations.*.address' => ['nullable', 'string'],
            'orders.*.destinations.*.consignee_name' => ['nullable', 'string'],
            'orders.*.destinations.*.consignee_phone' => ['nullable', 'string', 'max:40'],
            'orders.*.destinations.*.postcode' => ['nullable', 'string'],
            'orders.*.destinations.*.state' => ['nullable', 'string'],
            'orders.*.destinations.*.city' => ['nullable', 'string'],
            'orders.*.destinations.*.drop_off_type' => ['required', Rule::in(array_keys(DropOffType::options()))],
            'orders.*.destinations.*.maps_url' => ['nullable', 'url', 'max:500'],
            'orders.*.destinations.*.special_requirements' => ['nullable', 'string'],
            'orders.*.items' => ['required', 'array', 'min:1'],
            // "other": the customer describes the goods instead of choosing a product
            'orders.*.items.*.kind' => ['nullable', Rule::in([...array_keys(Uom::CATEGORIES), 'other'])],
            'orders.*.items.*.sub_category' => ['nullable', 'string', 'max:100'],
            'orders.*.items.*.uom_id' => ['nullable', 'integer'],
            'orders.*.items.*.item_name' => ['nullable', 'string'],
            'orders.*.items.*.description' => ['nullable', 'string', 'max:500'],
            'orders.*.items.*.pickup_index' => ['nullable', 'integer', 'min:0'],
            'orders.*.items.*.photos' => ['nullable', 'array', 'max:5'],
            'orders.*.items.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
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
            'declaration' => ['accepted'],
        ], [
            'declaration.accepted' => 'Please tick the declaration before submitting.',
        ]);

        $this->checkOrders($data['orders']);

        $linkedSalesperson = AssignEnquirySalesperson::resolveByToken($request->session()->get(SalespersonLinkController::SESSION_KEY));
        // the link's salesperson, else the one chosen on the form, else the customer's own (set at registration or on
        // the customer page; user, 11 Nov 2026) while that person is still an active salesperson
        $chosenSalesperson = $linkedSalesperson
            ?? (filled($data['salesperson_id'] ?? null) ? User::query()->find($data['salesperson_id']) : null)
            ?? ($customer?->salesperson_id ? User::query()->role('salesperson')->where('is_active', true)->find($customer->salesperson_id) : null);

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

        return \App\Support\PdfPageNumbers::stamp(Pdf::loadView('pdf.portal-enquiry', [
            'enquiry' => $enquiry,
            'payload' => is_array($enquiry->payload) ? $enquiry->payload : [],
            'number' => $number,
            'kinds' => Uom::CATEGORIES,
            'dropOffTypes' => DropOffType::options(),
        ])->setPaper('a4'))->stream($number.'.pdf');
    }

    private function storeOrderBlock(
        Request $request,
        array $block,
        int $index,
        ?\App\Domains\MasterData\Models\Customer $customer,
        $linkedSalesperson,
        $chosenSalesperson,
    ): PortalEnquiry {
        // several pickup addresses or several drop-off addresses, for any goods (client, 11 Nov 2026: like the
        // admin order's Multiple address / Multiple consignees); not both — checkOrders() refused that already
        $isStore = ($block['service_type'] ?? '') === \App\Enums\ServiceType::Store->value;
        $extraPickups = $isStore ? [] : array_values(array_filter($block['extra_pickups'] ?? [], fn ($pickup) => is_array($pickup) && filled($pickup['address'] ?? null)));
        $destCount = count($block['destinations'] ?? []);
        $routeMode = $extraPickups !== [] ? 'multi_pickup' : ($destCount > 1 ? 'multi_dropoff' : 'single');
        $block['extra_pickups'] = $extraPickups;
        $block['route_mode'] = $routeMode;

        // Store: the customer sends the goods to an O&G store — the record takes the store, its branch, address
        // (as the pickup location), From location and person in charge, the same as the admin order's Store block
        $serviceStore = $isStore ? OrderFormOptions::storeDefaults($block['store_id'] ?? null) : null;

        if ($serviceStore) {
            $block['pickup_address'] = $serviceStore['pickup_location'];
            $block['pickup_maps_url'] = null;
        }

        $block['destinations'] = array_map(function (array $destination) use ($serviceStore): array {
            // Own collection: the consignee collects the goods at an O&G store
            $collectStore = ($destination['mode'] ?? '') === 'collect' ? Store::query()->find($destination['collect_store_id'] ?? null) : null;

            if ($collectStore) {
                $destination['consignee_mode'] = 'store';
                $destination['consignee_store_id'] = (int) $collectStore->id;
                $destination['address'] = OrderFormOptions::storeAddress($collectStore);
                $destination['collect_store'] = $collectStore->label();

                if ($collectStore->location_id) {
                    $destination['to_location_id'] = (int) $collectStore->location_id;
                }
            }

            unset($destination['collect_store_id']);

            if ($serviceStore) {
                $destination['service_type'] = \App\Enums\ServiceType::Store->value;
                $destination['store_id'] = $serviceStore['store_id'];
                $destination['store_branch_id'] = $serviceStore['branch_id'];
                $destination['pickup_location'] = $serviceStore['pickup_location'];
                $destination['from_location_id'] = $serviceStore['from_location_id'] ?: null;
                $destination['consignor_pic_name'] = $serviceStore['consignor_pic_name'] ?: null;
                $destination['consignor_pic_phone'] = $serviceStore['consignor_pic_phone'] ?: null;
            }

            return $destination;
        }, array_values($block['destinations'] ?? []));

        $itemFiles = $request->file('orders.'.$index.'.items') ?? [];
        $itemPhotos = [];

        foreach (array_keys($block['items']) as $position => $key) {
            $itemPhotos[$position] = is_array($itemFiles[$key]['photos'] ?? null) ? $itemFiles[$key]['photos'] : [];
        }

        $block['items'] = array_map(function (array $item) {
            unset($item['photos']);
            // goods described only (no product chosen): the description is the item, staff choose the product and price
            $item['kind'] = ($item['kind'] ?? '') === 'other' ? null : ($item['kind'] ?? null);
            $item['description'] = filled($item['description'] ?? null) ? trim((string) $item['description']) : null;

            if (blank($item['item_name'] ?? null)) {
                $item['item_name'] = Str::limit((string) $item['description'], 190, '');
            }

            $item['category'] = $item['sub_category'] ?? null;
            if (filled($item['length'] ?? null)) {
                $item['dimensions'] = collect([
                    ($item['length'] ?? '').(filled($item['length_max'] ?? null) ? '–'.$item['length_max'] : ''),
                    ($item['width'] ?? '').(filled($item['width_max'] ?? null) ? '–'.$item['width_max'] : ''),
                    ($item['height'] ?? '').(filled($item['height_max'] ?? null) ? '–'.$item['height_max'] : ''),
                ])->filter()->implode(' × ').' cm';
            }

            return $item;
        }, array_values($block['items']));

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
            // the customer gives a pickup date now (client, 11 Nov 2026), kept in the payload: no delivery date is asked
            'preferred_delivery_date' => $block['preferred_delivery_date'] ?? null,
            'special_requirements' => $block['special_requirements'] ?? null,
            'status' => 'pending',
            'payload' => [
                'pickup_date' => $block['pickup_date'] ?? null,
                'service_store' => $serviceStore ? ['id' => $serviceStore['store_id'], 'name' => Store::query()->find($serviceStore['store_id'])?->label()] : null,
                'declaration' => [
                    'text' => PortalEnquiry::DECLARATION,
                    'accepted_at' => now()->toDateTimeString(),
                    'accepted_by' => $request->user()->name,
                ],
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

        // the photos of each item follow the item (client, 11 Nov 2026): kept on the item, shown on its product line
        $payload = $enquiry->payload;
        $withPhotos = false;

        foreach ($itemPhotos as $position => $files) {
            foreach ($files as $file) {
                $payload['items'][$position]['attachments'][] = [
                    'path' => $file->store('portal-enquiries/'.$enquiry->id.'/items', 'public'),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->name,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
                $withPhotos = true;
            }
        }

        if ($withPhotos) {
            $enquiry->update(['payload' => $payload]);
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

    /** The active O&G stores of the customer's company. @return \Illuminate\Support\Collection<int, Store> */
    private function stores(): \Illuminate\Support\Collection
    {
        return Store::query()
            ->with('branch:id,name,address')
            ->where('is_active', true)
            ->when(PortalSelection::companyId(), fn ($query, $id) => $query->where('company_id', $id))
            ->orderBy('name')
            ->get();
    }

    /**
     * What the order form requires beyond the field rules (client, 11 Nov 2026), with the order and row in each message.
     *
     * @param  array<int, array<string, mixed>>  $orders
     */
    private function checkOrders(array $orders): void
    {
        $errors = [];
        $storeIds = $this->stores()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $many = count($orders) > 1;

        foreach (array_values($orders) as $i => $block) {
            $at = $many ? 'Order '.($i + 1).': ' : '';
            $isStore = ($block['service_type'] ?? '') === \App\Enums\ServiceType::Store->value;

            if ($isStore && ! in_array((int) ($block['store_id'] ?? 0), $storeIds, true)) {
                $errors["orders.$i.store_id"] = $at.'Choose the O&G store you will send the goods to.';
            }

            if (! $isStore && blank($block['pickup_address'] ?? null)) {
                $errors["orders.$i.pickup_address"] = $at.'Enter the pickup address.';
            }

            $extraPickups = $isStore ? 0 : count(array_filter($block['extra_pickups'] ?? [], fn ($p) => is_array($p) && filled($p['address'] ?? null)));

            if ($extraPickups > 0 && count($block['destinations'] ?? []) > 1) {
                $errors["orders.$i.destinations"] = $at.'An order can have several pickup addresses or several drop-off addresses, not both. Use "Add another order" for the rest.';
            }

            foreach (array_values($block['destinations'] ?? []) as $j => $destination) {
                $row = $at.'Drop-off '.($j + 1).': ';

                if (blank($destination['consignee_name'] ?? null)) {
                    $errors["orders.$i.destinations.$j.consignee_name"] = $row.'enter the consignee (receiver).';
                }

                if (($destination['mode'] ?? '') === 'collect') {
                    if (! in_array((int) ($destination['collect_store_id'] ?? 0), $storeIds, true)) {
                        $errors["orders.$i.destinations.$j.collect_store_id"] = $row.'choose the O&G store the goods are collected from.';
                    }
                } elseif (blank($destination['address'] ?? null)) {
                    $errors["orders.$i.destinations.$j.address"] = $row.'enter the delivery address.';
                }
            }

            foreach (array_values($block['items'] ?? []) as $j => $item) {
                $row = $at.'Item '.($j + 1).': ';
                $kind = (string) ($item['kind'] ?? '');

                // an item is a product chosen from the list, or the customer's own description
                if (blank($item['item_name'] ?? null) && blank($item['description'] ?? null)) {
                    $errors["orders.$i.items.$j.item_name"] = $row.'choose the item, or describe the goods in Description.';
                }

                // every item except a chartered lorry: its size and weight
                if ($kind !== Uom::LORRY) {
                    foreach (['length' => 'L', 'width' => 'W', 'height' => 'H', 'weight' => 'Weight'] as $key => $label) {
                        if (! is_numeric($item[$key] ?? null)) {
                            $errors["orders.$i.items.$j.$key"] = $row.'enter L, W, H (cm) and Weight (kg).';

                            break;
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The price of an item row on the order form (client, 11 Nov 2026: the portal shows the general price and the
     * customer's quotation / special price): for the product or the band its sizes fit, to the price-list location of
     * the row's drop-off address, for its quantity and drop-off type. An estimate — the quotation is the price.
     */
    public function price(Request $request): JsonResponse
    {
        $customer = PortalSelection::customer();
        $branchId = PortalSelection::branchId() ?? $customer?->branch_id;
        $kind = (string) $request->query('kind');
        $sub = trim((string) $request->query('sub_category'));
        $values = collect(['length', 'width', 'height', 'weight'])
            ->mapWithKeys(fn (string $key) => [$key => is_numeric($request->query($key)) ? (float) $request->query($key) : null])->all();

        $uom = is_numeric($request->query('uom_id'))
            ? Uom::withoutGlobalScopes()->where('is_active', true)->whereKey((int) $request->query('uom_id'))->first()
            : ($sub !== '' && in_array($kind, [Uom::MEASUREMENT, Uom::ITEM], true)
                ? MeasurementBand::forGoods($branchId ? (int) $branchId : null, $kind, $sub, null, $values)
                : null);

        if (! $uom) {
            return response()->json(['status' => 'no_product']);
        }

        $collectStore = is_numeric($request->query('collect_store_id')) ? Store::query()->with('location')->find((int) $request->query('collect_store_id')) : null;
        $location = $collectStore?->location ?? \App\Domains\Quotation\Actions\CreateOrderFromEnquiry::matchLocation(
            $collectStore
                ? ['address' => OrderFormOptions::storeAddress($collectStore)]
                : $request->only(['city', 'state', 'address', 'consignee_name']),
        );

        if (! $location) {
            return response()->json(['status' => 'no_location', 'product' => $uom->name]);
        }

        $rates = app(PriceListRates::class);
        $quantity = max(0.01, is_numeric($request->query('quantity')) ? (float) $request->query('quantity') : 1.0);
        $dropOffType = filled($request->query('drop_off_type')) ? (string) $request->query('drop_off_type') : null;
        $unit = fn (array $set) => $rates->tierFor($set['tiers'], $quantity)?->unitPriceFor($quantity, $values['weight']);

        $general = $unit($rates->uomTiers($uom, (int) $location->id, null, $dropOffType, $branchId ? (int) $branchId : null));
        $own = $customer ? $rates->uomTiers($uom, (int) $location->id, (int) $customer->id, $dropOffType, $branchId ? (int) $branchId : null) : null;
        $yours = $own && $own['special'] ? $unit($own) : null;
        $fromQuotation = $yours !== null && str_starts_with((string) $rates->tierFor($own['tiers'], $quantity)?->source, 'Quotation');

        return response()->json([
            'status' => 'ok',
            'product' => $uom->name,
            'location' => $location->name,
            'quantity' => $quantity,
            'general' => $general,
            'yours' => $yours,
            'yours_label' => $fromQuotation ? 'Your quotation price' : 'Your special price',
        ]);
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

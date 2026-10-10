<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPricing;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Store;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\ServiceType;
use App\Filament\Resources\StoreResource;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\CustomerQuotationPriceHistory;
use App\Support\OrderFormOptions;
use App\Support\QuotationPricingLookup;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Admin-assisted order entry: customer & ownership once, then one Consignor & consignee block
 * per delivery (each becomes its own order record under the same order number).
 */
class CreateOrder extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'orders/new';

    protected static string $view = 'filament.pages.create-order';

    public array $form = [];

    /**
     * One consignor & consignee block per entry. Each product row keeps its photos picked on the page (not saved
     * yet) in 'photos' (TemporaryUploadedFile list) and the ones already saved on an edited record in
     * 'existing_photos'; 'manual_price' is the price keyed in for a product without a rate.
     *
     * @var list<array<string, mixed>>
     */
    public array $pairs = [];

    /**
     * Photo picker of each product row ([block index][row index] => files just uploaded): moved into that row's
     * 'photos' as soon as the upload finishes, so picking more files adds to the ones already chosen.
     *
     * @var array<int|string, mixed>
     */
    public array $photoUploads = [];

    /** Photos / DO attachments: images or PDF, up to 8 MB each (same rule as the earlier order-level upload). */
    public const PHOTO_RULE = 'file|mimes:jpg,jpeg,png,webp,pdf|max:8192';

    /**
     * Billing address entered per customer on this page (customer id => address): picking that customer
     * again brings it back instead of the customer's saved address.
     *
     * @var array<int|string, string>
     */
    public array $billingAddresses = [];

    /**
     * The customer selected before this request changed it (set by updatingForm, this request only): the
     * billing address on the page belongs to that customer, even when its typed value arrives in the same
     * request as the new customer (deferred textarea + live select).
     */
    protected ?string $outgoingCustomerId = null;

    public function mount(): void
    {
        $this->form = [
            'customer_id' => '',
            // the order's billing address (every record's customer_address), prefilled from the customer
            'customer_address' => '',
            // the customer's person in charge and contact number (every record's attention / customer_pic_phone)
            'customer_pic_name' => '',
            'customer_pic_phone' => '',
            // optional: how the order reached us
            'received_through' => '',
            'salesperson_id' => '',
            // follows the customer's type once a customer is picked (see orderTypeOptions)
            'order_type' => OrderType::Cash->value,
            // service (pickup / store) is chosen per consignor block; the payment method is captured when the payment is recorded
        ];

        $this->pairs = [$this->pairTemplate()];
    }

    public function getTitle(): string
    {
        return 'Create order for customer';
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function getRelativeRouteName(): string
    {
        return 'orders.create';
    }

    /*
    |--------------------------------------------------------------------------
    | Page mode (the Edit order page reuses this page and its view)
    |--------------------------------------------------------------------------
    */

    public function isEditing(): bool
    {
        return false;
    }

    /**
     * Texts and links of the page chrome.
     *
     * @return array{back_url: string, back_label: string, crumb: string, title: string, sub: string, cancel_url: string, submit: string}
     */
    public function pageCopy(): array
    {
        return [
            'back_url' => Orders::getUrl(),
            'back_label' => '← Back to orders',
            'crumb' => 'Orders / Admin assisted entry',
            'title' => 'Create order for customer',
            'sub' => 'Record the customer\'s instructions and prepare a quotation in the same record.',
            'cancel_url' => Orders::getUrl(),
            'submit' => 'Save order & prepare pricing →',
        ];
    }

    /** Customer, billing address, received through and payment term can be changed. */
    public function headerEditable(): bool
    {
        return true;
    }

    public function salespersonEditable(): bool
    {
        return true;
    }

    /** Offer the "No salesperson yet" choice. */
    public function allowNoSalesperson(): bool
    {
        return true;
    }

    public function showReceivedThrough(): bool
    {
        return true;
    }

    /** Offer the "— Not specified —" choice for Received through (it is optional). */
    public function receivedThroughClearable(): bool
    {
        return true;
    }

    public function canAddPair(): bool
    {
        return true;
    }

    public function canRemovePair(int $index): bool
    {
        return count($this->pairs) > 1;
    }

    public function isPairLocked(int $index): bool
    {
        return false;
    }

    public function needsHeartbeat(): bool
    {
        return false;
    }

    /**
     * Files saved earlier for the whole order (before photos were kept per consignor & consignee block): shown
     * read-only on Edit order. A new order has none.
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function existingAttachments(): array
    {
        return [];
    }

    /** Records of this order, left out of a product's previous records (none while creating). @return list<int> */
    public function historyExcludedQuotationIds(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    /** @return array<int|string, string> */
    public function customerOptions(): array
    {
        $query = Customer::query()->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->get()->mapWithKeys(fn (Customer $c) => [$c->id => trim(($c->code ? $c->code.' — ' : '').$c->company_name)])->all();
    }

    /**
     * The customer type tag shown beside each customer in the picker (as in the Orders list).
     *
     * @return array<int|string, array{label: string, tone: string}>
     */
    public function customerTypeTags(): array
    {
        return Customer::query()
            ->when(CurrentCompany::id(), fn ($query, $id) => $query->where('company_id', $id))
            ->get(['id', 'default_order_type', 'is_credit'])
            ->mapWithKeys(function (Customer $c) {
                $type = $c->customerType();

                return [$c->id => ['label' => $type->shortLabel(), 'tone' => $type === OrderType::Term ? 'credit' : $type->value]];
            })
            ->all();
    }

    /** @return array<int|string, string> */
    public function salespersonOptions(): array
    {
        return User::query()->role('salesperson')->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.($u->saLocation ? ' · '.$u->saLocation->code : '')])
            ->all();
    }

    /** @return array<string, string> */
    public function receivedThroughOptions(): array
    {
        return CreateAdminOrder::RECEIVED_THROUGH;
    }

    /**
     * Payment terms by customer type (Customer::customerType()): a Credit / Term customer may order on
     * Credit / Term, Cash or COD; a COD customer on COD only; a Cash customer on Cash only. Credit / Term
     * also needs a credit customer (is_credit): one typed Term without it orders on Cash or COD. All three
     * until a customer is picked.
     *
     * @return array<string, string>
     */
    public function orderTypeOptions(): array
    {
        $customer = filled($this->form['customer_id'] ?? null) ? Customer::query()->find($this->form['customer_id']) : null;

        if (! $customer) {
            return OrderType::options();
        }

        return collect($this->orderTypesFor($customer))->mapWithKeys(fn (OrderType $type) => [$type->value => $type->getLabel()])->all();
    }

    /** @return list<OrderType> */
    protected function orderTypesFor(Customer $customer): array
    {
        $allowed = match ($customer->customerType()) {
            OrderType::Term => [OrderType::Term, OrderType::Cash, OrderType::Cod],
            OrderType::Cod => [OrderType::Cod],
            OrderType::Cash => [OrderType::Cash],
        };

        // the credit check on acceptance only runs for credit customers: no Credit / Term without credit
        return $customer->is_credit ? $allowed : array_values(array_filter($allowed, fn (OrderType $type) => $type !== OrderType::Term));
    }

    /** The payment term an order starts with: the selected customer's type when offered, else its first term (Cash without a customer). */
    protected function defaultOrderType(): string
    {
        $customer = filled($this->form['customer_id'] ?? null) ? Customer::query()->find($this->form['customer_id']) : null;

        if (! $customer) {
            return OrderType::Cash->value;
        }

        $allowed = $this->orderTypesFor($customer);
        $type = $customer->customerType();

        return (in_array($type, $allowed, true) ? $type : ($allowed[0] ?? OrderType::Cash))->value;
    }

    /** Consignor toggle: Pick up (collected from the consignor) or Store (brought to an O&G branch). @return array<string, string> */
    public function serviceTypeOptions(): array
    {
        return [ServiceType::Pickup->value => 'Pickup', ServiceType::Store->value => 'Store'];
    }

    /** @return array<string, string> */
    public function dropOffTypeOptions(): array
    {
        return DropOffType::options();
    }

    /**
     * Stores (Master Data → Stores) for the consignor's Store mode, each with its address / From / PIC line; a
     * store already chosen on a block stays listed.
     *
     * @return array<int, array{label: string, sub: string}>
     */
    public function storeOptions(): array
    {
        return OrderFormOptions::storeOptions(array_column($this->pairs, 'store_id'));
    }

    /**
     * What the page shows of the chosen store: its branch, address, PIC and contact number, and where to edit it.
     *
     * @return array{name: string, branch: string, address: string, pic: string, phone: string, edit_url: ?string}|null
     */
    public function storeInfo(mixed $storeId): ?array
    {
        $store = filled($storeId) ? Store::query()->with('branch')->find($storeId) : null;

        if (! $store) {
            return null;
        }

        try {
            $editUrl = StoreResource::getUrl('edit', ['record' => $store]);
        } catch (Throwable) {
            $editUrl = null;
        }

        return [
            'name' => (string) $store->name,
            'branch' => (string) ($store->branch?->name ?? ''),
            'address' => trim((string) $store->address),
            'pic' => trim((string) $store->pic_name),
            'phone' => trim((string) $store->pic_phone),
            'edit_url' => $editUrl,
        ];
    }

    /** @return array<int, string> */
    public function locationOptions(): array
    {
        return OrderFormOptions::locationOptions();
    }

    /** @return array<int|string, string> */
    public function addressOptions(): array
    {
        return OrderFormOptions::customerAddressOptions(filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null);
    }

    /** @return array<string, string> */
    public function catalogOptions(string $type): array
    {
        return app(QuotationPricingLookup::class)->catalogOptionsForType($type ?: 'uom');
    }

    /** @return list<string> */
    public function consignorSuggestions(): array
    {
        return array_values($this->customerOptions());
    }

    public function customerName(): ?string
    {
        return filled($this->form['customer_id'] ?? null) ? Customer::query()->whereKey($this->form['customer_id'])->value('company_name') : null;
    }

    public function showPrices(): bool
    {
        return filled($this->form['salesperson_id'] ?? null);
    }

    /*
    |--------------------------------------------------------------------------
    | Form behaviour
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    protected function pairTemplate(): array
    {
        $consignor = filled($this->form['customer_id'] ?? null) ? OrderFormOptions::consignorStateForCustomer((string) $this->form['customer_id']) : [];

        return [
            // consignor: starts as the customer (optional: cleared, it is saved blank)
            'consignor_name' => $this->customerName() ?? '',
            // Pickup (collected from the consignor) or Store (the consignor brings the goods to a store, Master Data → Stores)
            'service_type' => ServiceType::Pickup->value,
            'store_id' => '',
            'from_location_id' => $consignor['from_location_id'] ?? '',
            'consignor_pic_name' => '',
            'consignor_pic_phone' => '',
            // saved customer address id, OrderFormOptions::NEW_ADDRESS (typed in pickup_location) or blank
            'pickup_preset' => $consignor['pickup_location_preset'] ?? '',
            'pickup_location' => $consignor['pickup_location'] ?? '',
            'consignee_name' => '',
            'to_location_id' => '',
            'consignee_pic_name' => '',
            'consignee_pic_phone' => '',
            // saved customer address id, OrderFormOptions::NEW_ADDRESS (typed in drop_off_location) or blank
            'drop_off_preset' => '',
            'drop_off_location' => '',
            'drop_off_type' => DropOffType::Other->value,
            'customer_do_number' => '',
            'expected_delivery_date' => '',
            'instructions' => '',
            'items' => [$this->itemTemplate()],
        ];
    }

    /** @return array<string, mixed> */
    protected function itemTemplate(): array
    {
        return [
            'line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'quantity' => 1,
            'unit_price' => null, 'tier' => null, 'source' => null, 'available' => null,
            // price keyed in when the product has no special / price-list rate
            'manual_price' => '',
            // photos of this product: picked on the page (not saved yet) / already saved on the record
            'photos' => [], 'existing_photos' => [],
        ];
    }

    /** Runs before the new value is set: remembers which customer the billing address on the page belongs to. */
    public function updatingForm(mixed $value, ?string $key = null): void
    {
        if ($key === 'customer_id' && $this->outgoingCustomerId === null) {
            $this->outgoingCustomerId = (string) ($this->form['customer_id'] ?? '');
        }
    }

    public function updatedForm($value, string $key): void
    {
        if ($key === 'customer_address') {
            // remembered per customer when the customer changes (below), not here: a typed address can arrive in
            // the same request as the new customer, and is the outgoing customer's
            return;
        }

        if ($key === 'customer_id') {
            $consignor = filled($value) ? OrderFormOptions::consignorStateForCustomer((string) $value) : [];
            $name = $this->customerName() ?? '';
            $knownNames = Customer::query()->pluck('company_name')->all();

            // the address on the page (every value of this request is already set) is the outgoing customer's
            $outgoing = (string) $this->outgoingCustomerId;
            $this->outgoingCustomerId = null;

            if ($outgoing !== '' && $outgoing !== (string) $value) {
                $this->billingAddresses[$outgoing] = (string) ($this->form['customer_address'] ?? '');
            }

            // billing address: the one already entered for this customer, else the customer's saved address
            $this->form['customer_address'] = filled($value)
                ? (string) ($this->billingAddresses[(string) $value] ?? ($consignor['customer_address'] ?? ''))
                : '';
            // the customer's person in charge and contact number
            $this->form['customer_pic_name'] = (string) ($consignor['attention'] ?? '');
            $this->form['customer_pic_phone'] = (string) ($consignor['customer_pic_phone'] ?? '');

            foreach ($this->pairs as &$pair) {
                // keep a consignor the user typed; replace the default (a customer name) when the customer changes.
                // A consignor cleared on purpose stays blank; only the first customer picked fills an empty one.
                if (in_array($pair['consignor_name'] ?? null, $knownNames, true) || (blank($pair['consignor_name'] ?? null) && $outgoing === '')) {
                    $pair['consignor_name'] = $name;
                }

                // a drop-off picked from the outgoing customer's saved addresses stays as typed text (new address)
                if (! in_array((string) ($pair['drop_off_preset'] ?? ''), ['', OrderFormOptions::NEW_ADDRESS], true)) {
                    $pair['drop_off_preset'] = filled($pair['drop_off_location'] ?? null) ? OrderFormOptions::NEW_ADDRESS : '';
                }
                // a Store block keeps its store and the From that came with it (a switch back to Pickup takes the new customer's)
                // (and its pickup location: the store's address; a switch back to Pickup takes the new customer's)
                if (($pair['service_type'] ?? ServiceType::Pickup->value) !== ServiceType::Store->value) {
                    $pair['from_location_id'] = $consignor['from_location_id'] ?? '';
                    $pair['pickup_preset'] = $consignor['pickup_location_preset'] ?? '';
                    $pair['pickup_location'] = $consignor['pickup_location'] ?? '';
                } else {
                    $pair['pickup_from_location_id'] = $consignor['from_location_id'] ?? '';
                    $pair['pickup_restore'] = ['preset' => $consignor['pickup_location_preset'] ?? '', 'location' => $consignor['pickup_location'] ?? ''];
                }
            }
            unset($pair);

            // the payment term starts as the customer's type (Cash, COD or Credit / Term)
            $this->form['order_type'] = $this->defaultOrderType();

            $this->refreshAllPrices();
        }

        if ($key === 'salesperson_id') {
            $this->refreshAllPrices();
        }
    }

    public function updatedPairs($value, string $key): void
    {
        if (preg_match('/^(\d+)\.(service_type|store_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];
            $store = OrderFormOptions::storeDefaults($pair['store_id'] ?? null);

            if (($pair['service_type'] ?? '') !== ServiceType::Store->value) {
                $pair['service_type'] = ServiceType::Pickup->value;

                // back to Pickup: what still came from the store returns to the Pickup values (the From and pickup
                // location before Store was chosen, else the customer's defaults); anything changed by hand stays
                if ($m[2] === 'service_type' && $store) {
                    $customer = OrderFormOptions::consignorStateForCustomer(filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null);

                    if ((string) ($pair['from_location_id'] ?? '') === $store['from_location_id']) {
                        $pair['from_location_id'] = array_key_exists('pickup_from_location_id', $pair)
                            ? (string) $pair['pickup_from_location_id']
                            : (string) ($customer['from_location_id'] ?? '');
                    }

                    if (trim((string) ($pair['pickup_location'] ?? '')) === $store['pickup_location']) {
                        $restore = $pair['pickup_restore'] ?? ['preset' => $customer['pickup_location_preset'] ?? '', 'location' => $customer['pickup_location'] ?? ''];
                        $pair['pickup_preset'] = (string) ($restore['preset'] ?? '');
                        $pair['pickup_location'] = (string) ($restore['location'] ?? '');
                    }

                    foreach (['consignor_pic_name', 'consignor_pic_phone'] as $field) {
                        if ($store[$field] !== '' && trim((string) ($pair[$field] ?? '')) === $store[$field]) {
                            $pair[$field] = '';
                        }
                    }
                }

                unset($pair['pickup_from_location_id'], $pair['pickup_restore']);

                return;
            }

            if ($m[2] === 'service_type') {
                // remember the Pickup From and pickup location (before the store's replace them) for a switch back
                if (! array_key_exists('pickup_from_location_id', $pair)) {
                    $pair['pickup_from_location_id'] = (string) ($pair['from_location_id'] ?? '');
                }

                if (! array_key_exists('pickup_restore', $pair)) {
                    $pair['pickup_restore'] = ['preset' => (string) ($pair['pickup_preset'] ?? ''), 'location' => (string) ($pair['pickup_location'] ?? '')];
                }

                // no store picked yet: the only store of the order's branch, when it has just one
                if (! $store) {
                    $branchStores = Store::query()->active()->where('branch_id', CurrentCompany::branchId())->pluck('id');

                    if ($branchStores->count() === 1) {
                        $pair['store_id'] = (string) $branchStores->first();
                        $store = OrderFormOptions::storeDefaults($pair['store_id']);
                    }
                }
            }

            // the store's address is the pickup location; its From, PIC and contact number fill the consignor fields
            if ($store) {
                $pair['legacy_store_branch_id'] = '';
                $pair['pickup_preset'] = OrderFormOptions::NEW_ADDRESS;
                $pair['pickup_location'] = $store['pickup_location'];

                if ($store['from_location_id'] !== '') {
                    $pair['from_location_id'] = $store['from_location_id'];
                }

                foreach (['consignor_pic_name', 'consignor_pic_phone'] as $field) {
                    if ($store[$field] !== '') {
                        $pair[$field] = $store[$field];
                    }
                }
            }

            return;
        }

        if (preg_match('/^(\d+)\.(pickup_preset|drop_off_preset|to_location_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];

            if ($m[2] === 'pickup_preset') {
                if ($value === OrderFormOptions::NEW_ADDRESS) {
                    // "New address…": start from an empty line unless the text was already typed by hand
                    $customerId = filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null;

                    if (OrderFormOptions::pickupPresetFor($customerId, $pair['pickup_location'] ?? '') !== OrderFormOptions::NEW_ADDRESS) {
                        $pair['pickup_location'] = '';
                    }
                } else {
                    $address = filled($value) ? CustomerAddress::query()->find($value) : null;
                    $pair['pickup_location'] = $address ? $this->formatAddress($address) : '';
                }
            } elseif ($m[2] === 'drop_off_preset') {
                // same picker as the pickup location: a saved address, or "New address…" typed below it
                if ($value === OrderFormOptions::NEW_ADDRESS) {
                    $customerId = filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null;

                    if (OrderFormOptions::pickupPresetFor($customerId, $pair['drop_off_location'] ?? '') !== OrderFormOptions::NEW_ADDRESS) {
                        $pair['drop_off_location'] = '';
                    }
                } else {
                    $address = filled($value) ? CustomerAddress::query()->find($value) : null;

                    if ($address) {
                        $pair['consignee_name'] = $address->label ?: $pair['consignee_name'];
                    }

                    $pair['drop_off_location'] = $address ? $this->formatAddress($address) : '';
                }
            } else {
                $this->refreshPairPrices($index);
            }

            return;
        }

        if (preg_match('/^(\d+)\.items\.(\d+)\.(line_type|catalog_key|quantity)$/', $key, $m)) {
            $index = (int) $m[1];
            $itemIndex = (int) $m[2];
            $item = &$this->pairs[$index]['items'][$itemIndex];
            $lookup = app(QuotationPricingLookup::class);

            if ($m[3] === 'line_type') {
                $item['catalog_key'] = '';
                $item['item_name'] = '';
                $item['uom'] = '';
                $item['quantity'] = 1;
                $item['unit_price'] = null;
                $item['tier'] = null;

                return;
            }

            if ($m[3] === 'quantity') {
                $item['quantity'] = max(1, (int) round((float) $value)); // whole units only
            }

            if ($m[3] === 'catalog_key') {
                $name = filled($value) ? $lookup->resolveCatalogName($value) : null;
                $item['item_name'] = $name ?? '';
                $item['uom'] = $item['line_type'] === 'uom' ? ($lookup->resolveUomCode($value, $name) ?? '') : '';
            }

            $this->refreshItemPrice($index, $itemIndex);
        }
    }

    protected function refreshAllPrices(): void
    {
        foreach (array_keys($this->pairs) as $index) {
            $this->refreshPairPrices($index);
        }
    }

    protected function refreshPairPrices(int $index): void
    {
        foreach (array_keys($this->pairs[$index]['items'] ?? []) as $itemIndex) {
            $this->refreshItemPrice($index, $itemIndex);
        }
    }

    /** Unit price from the UOM price list (tier by destination + quantity); only shown once a salesperson owns the order. */
    protected function refreshItemPrice(int $index, int $itemIndex): void
    {
        $pair = &$this->pairs[$index];
        $item = &$pair['items'][$itemIndex];
        $item['unit_price'] = null;
        $item['tier'] = null;
        $item['source'] = null;
        $item['available'] = null;

        if (! $this->showPrices() || blank($item['item_name']) || ($item['line_type'] ?? '') === 'lorry') {
            return;
        }

        $item['available'] = $this->ratedLocations($item['item_name'], max(0.01, (float) ($item['quantity'] ?: 1)));
        $locationName = filled($pair['to_location_id']) ? Location::query()->whereKey($pair['to_location_id'])->value('name') : null;

        if (! $locationName) {
            return;
        }

        $lookup = app(QuotationPricingLookup::class);
        $quantity = max(0.01, (float) ($item['quantity'] ?: 1));
        $resolved = $lookup->lookupForCustomer(filled($this->form['customer_id']) ? (int) $this->form['customer_id'] : null, $item['item_name'], $locationName, $quantity);
        $tier = $lookup->matchedUomTier($item['item_name'], $locationName, $quantity);

        $item['unit_price'] = $resolved['price'] !== null ? (float) $resolved['price'] : null;
        $item['source'] = $resolved['source'];
        $item['tier'] = $tier ? 'Tier '.($tier->max_qty ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0) : number_format((float) $tier->min_qty, 0).'+') : null;
    }

    /** "Johor RM 30.00 · Melaka RM 11.00" — where this product has a price-list rate for the quantity. */
    protected function ratedLocations(string $itemName, float $quantity): ?string
    {
        $lookup = app(QuotationPricingLookup::class);

        $rated = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(function (Location $location) use ($lookup, $itemName, $quantity) {
                $tier = $lookup->matchedUomTier($itemName, $location->name, $quantity);

                return $tier ? $location->name.' RM '.number_format((float) $tier->price, 2) : null;
            })
            ->filter()
            ->values();

        return $rated->isEmpty() ? null : $rated->implode(' · ');
    }

    public function addPair(): void
    {
        $this->pairs[] = $this->pairTemplate();
    }

    /** Quick action: empty the consignor (optional: left blank it is saved blank, not as the customer). */
    public function clearConsignor(int $index): void
    {
        if (isset($this->pairs[$index])) {
            $this->pairs[$index]['consignor_name'] = '';
        }
    }

    /** Quick action: the customer is the consignor again. */
    public function useCustomerAsConsignor(int $index): void
    {
        if (isset($this->pairs[$index])) {
            $this->pairs[$index]['consignor_name'] = $this->customerName() ?? '';
        }
    }

    public function removePair(int $index): void
    {
        if (count($this->pairs) <= 1) {
            return;
        }

        unset($this->pairs[$index]);
        $this->pairs = array_values($this->pairs);
        // a picker still uploading belonged to a block that may have moved
        $this->photoUploads = [];
    }

    public function addItem(int $index): void
    {
        $this->pairs[$index]['items'][] = $this->itemTemplate();
    }

    public function removeItem(int $index, int $itemIndex): void
    {
        if (count($this->pairs[$index]['items']) <= 1) {
            return;
        }

        unset($this->pairs[$index]['items'][$itemIndex]);
        $this->pairs[$index]['items'] = array_values($this->pairs[$index]['items']);
        // a picker still uploading belonged to a row that may have moved
        $this->photoUploads = [];
    }

    /*
    |--------------------------------------------------------------------------
    | Photos per product row
    |--------------------------------------------------------------------------
    */

    /**
     * Files just uploaded with a product row's photo picker: checked (images / PDF, 8 MB) and added to the row's
     * photos; a file that fails is left out with its message under the row.
     */
    public function updatedPhotoUploads(mixed $value, ?string $key = null): void
    {
        $parts = explode('.', (string) $key);
        $index = (int) ($parts[0] ?? -1);
        $itemIndex = (int) ($parts[1] ?? -1);
        $files = array_values(array_filter(Arr::wrap($this->photoUploads[$index][$itemIndex] ?? []), fn ($file) => $file instanceof TemporaryUploadedFile));
        $this->photoUploads[$index][$itemIndex] = [];

        if (! isset($this->pairs[$index]['items'][$itemIndex]) || $this->isPairLocked($index) || $files === []) {
            return;
        }

        $errorKey = 'pairs.'.$index.'.items.'.$itemIndex.'.photos';
        $this->resetErrorBag([$errorKey, 'photoUploads.'.$index.'.'.$itemIndex]);
        $accepted = [];

        foreach ($files as $file) {
            $check = Validator::make(['photo' => $file], ['photo' => self::PHOTO_RULE], [], ['photo' => $file->getClientOriginalName()]);

            if ($check->fails()) {
                $this->addError($errorKey, $check->errors()->first('photo'));

                continue;
            }

            $accepted[] = $file;
        }

        $item = &$this->pairs[$index]['items'][$itemIndex];
        $item['photos'] = array_values(array_merge(
            array_filter($item['photos'] ?? [], fn ($file) => $file instanceof TemporaryUploadedFile),
            $accepted,
        ));
    }

    /** Takes a photo picked on the page off its product row before the order is saved. */
    public function removePhoto(int $index, int $itemIndex, int $photoIndex): void
    {
        if (! isset($this->pairs[$index]['items'][$itemIndex]['photos'][$photoIndex]) || $this->isPairLocked($index)) {
            return;
        }

        unset($this->pairs[$index]['items'][$itemIndex]['photos'][$photoIndex]);
        $this->pairs[$index]['items'][$itemIndex]['photos'] = array_values($this->pairs[$index]['items'][$itemIndex]['photos']);
    }

    /**
     * Photos picked for a product row as the page shows them (not saved yet).
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function newPhotos(int $index, int $itemIndex): array
    {
        return collect($this->pairs[$index]['items'][$itemIndex]['photos'] ?? [])
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
            ->map(function (TemporaryUploadedFile $file): array {
                $image = str_starts_with((string) $file->getMimeType(), 'image/');

                try {
                    $url = $image && $file->isPreviewable() ? $file->temporaryUrl() : null;
                } catch (Throwable) {
                    $url = null;
                }

                return ['name' => $file->getClientOriginalName(), 'url' => $url, 'is_image' => $image];
            })
            ->values()
            ->all();
    }

    /**
     * A block as the order actions take it (pairData) with the photos picked for each product row stored now
     * (none for a locked block). Photos are kept per product; the block itself takes none.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    protected function pairDataWithPhotos(array $pair, int $index): array
    {
        $data = $this->pairData($pair, $index);
        $data['attachments'] = [];

        foreach ($data['items'] as $j => $item) {
            $data['items'][$j]['attachments'] = $this->isPairLocked($index) ? [] : $this->storePhotos($pair['items'][$j]['photos'] ?? []);
        }

        return $data;
    }

    /**
     * Stores picked photos on the public disk, each under its own folder with its original (cleaned) name, so a
     * record's file list reads well.
     *
     * @param  array<int, mixed>  $photos
     * @return list<array{path: string, name: string, mime: ?string, size: int|false, uploaded_by: ?string, uploaded_at: string}>
     */
    protected function storePhotos(array $photos): array
    {
        $files = [];

        foreach ($photos as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
            $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'photo';
            $path = $file->storeAs('portal-enquiries/admin/'.now()->format('Ym').'/'.Str::lower(Str::random(12)), Str::limit($base, 80, '').'.'.$extension, 'public');

            $files[] = ['path' => $path, 'name' => $original, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->user()?->name, 'uploaded_at' => now()->toDateTimeString()];
        }

        return $files;
    }

    /*
    |--------------------------------------------------------------------------
    | Previous records of a product for the customer
    |--------------------------------------------------------------------------
    */

    /** History icon of a product row: the customer's special price, the price list and previous order lines of that product (information only). */
    public function productHistoryAction(): Action
    {
        return Action::make('productHistory')
            ->label('Previous records')
            ->modalHeading(fn (array $arguments) => 'Prices & previous records · '.($this->historyItem($arguments)['item_name'] ?? 'product'))
            ->modalDescription(fn () => $this->customerName() ? 'Special price, price list and earlier orders of '.$this->customerName().' for this product.' : null)
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.order-product-history', $this->productHistoryData($arguments)))
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * What the previous-records modal shows for one product row.
     *
     * @param  array<string, mixed>  $arguments  pair (block index) and item (row index)
     * @return array{item_name: string, location: ?string, customer: ?string, rows: list<array<string, mixed>>}
     */
    public function productHistoryData(array $arguments): array
    {
        $item = $this->historyItem($arguments);
        // only a customer of the current company (form.customer_id is client-side state)
        $customerId = filled($this->form['customer_id'] ?? null)
            ? Customer::query()->whereKey($this->form['customer_id'])->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))->value('id')
            : null;
        $toLocationId = $this->pairs[(int) ($arguments['pair'] ?? -1)]['to_location_id'] ?? null;
        $location = filled($toLocationId) ? Location::query()->whereKey($toLocationId)->value('name') : null;

        $name = (string) ($item['item_name'] ?? '');
        $quantity = max(0.01, (float) (($item['quantity'] ?? 1) ?: 1));

        return [
            'item_name' => $name,
            'location' => $location,
            'customer' => $this->customerName(),
            'special' => $customerId && $name !== '' ? $this->specialPricesFor((int) $customerId, $name, $location) : [],
            'default' => $name !== '' ? $this->defaultPricesFor($name, $location, $quantity) : [],
            'rows' => $customerId && filled($item['item_name'] ?? null)
                ? app(CustomerQuotationPriceHistory::class)->productHistory((int) $customerId, (string) $item['item_name'], $location, $this->historyExcludedQuotationIds(), companyId: CurrentCompany::id())
                : [],
        ];
    }

    /**
     * The customer's special prices for the product (Customer → Special pricing), this row's destination first.
     *
     * @return list<array{destination: string, uom: ?string, price: ?float, min_charge: ?float, same: bool}>
     */
    protected function specialPricesFor(int $customerId, string $itemName, ?string $location): array
    {
        return CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('item_name', $itemName)
            ->get()
            ->map(fn (CustomerPricing $row) => [
                'destination' => filled($row->destination) ? (string) $row->destination : 'All destinations',
                'uom' => filled($row->uom) ? (string) $row->uom : null,
                'price' => ($row->unit_rate ?? $row->base_price) !== null ? (float) ($row->unit_rate ?? $row->base_price) : null,
                'min_charge' => $row->min_charge !== null ? (float) $row->min_charge : null,
                'same' => $location !== null && (string) $row->destination === $location,
            ])
            ->sortBy(fn (array $row) => [$row['same'] ? 0 : 1, $row['destination']])
            ->values()
            ->all();
    }

    /**
     * The product's price-list (default) rates per location: the quantity tiers of a UOM product, else the one
     * rate of a transport item / lorry; this row's destination first, with the tier its quantity falls in.
     *
     * @return list<array{location: string, same: bool, tiers: list<array{range: string, price: float, active: bool}>, price: ?float}>
     */
    protected function defaultPricesFor(string $itemName, ?string $location, float $quantity): array
    {
        $lookup = app(QuotationPricingLookup::class);

        return Location::query()->where('is_active', true)->orderBy('name')->pluck('name')
            ->map(function (string $name) use ($lookup, $itemName, $location, $quantity): ?array {
                $active = $lookup->matchedUomTier($itemName, $name, $quantity);
                $tiers = collect($lookup->uomTierBreakdown($itemName, $name))
                    ->map(fn (array $tier) => $tier + ['active' => $active !== null && abs((float) $active->price - $tier['price']) < 0.001 && $tier['range'] === ($active->max_qty ? number_format((float) $active->min_qty, 0).'–'.number_format((float) $active->max_qty, 0) : number_format((float) $active->min_qty, 0).'+')])
                    ->all();
                $price = $tiers === [] ? $lookup->lookup($itemName, $name, $quantity) : null;

                if ($tiers === [] && $price === null) {
                    return null;
                }

                return ['location' => $name, 'same' => $name === $location, 'tiers' => $tiers, 'price' => $price];
            })
            ->filter()
            ->sortBy(fn (array $row) => [$row['same'] ? 0 : 1, $row['location']])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    protected function historyItem(array $arguments): ?array
    {
        $item = $this->pairs[(int) ($arguments['pair'] ?? -1)]['items'][(int) ($arguments['item'] ?? -1)] ?? null;

        return is_array($item) ? $item : null;
    }

    /** @return array{items: float, lines: array<int, array<int, float>>} */
    public function pairTotals(): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($this->pairs as $i => $pair) {
            foreach ($pair['items'] as $j => $item) {
                // non-UOM rows only exist when editing an order: they keep the record's quantity
                $qty = max(0.01, (float) ($item['quantity'] ?: 1));
                $price = $this->effectivePrice($item);
                $lines[$i][$j] = $price !== null ? round($price * $qty, 2) : 0.0;
                $total += $lines[$i][$j];
            }
        }

        return ['items' => round($total, 2), 'lines' => $lines];
    }

    /** A row's unit price: its rate, else the price keyed in for a product without one. */
    public function effectivePrice(array $item): ?float
    {
        if ($item['unit_price'] !== null) {
            return (float) $item['unit_price'];
        }

        return is_numeric($item['manual_price'] ?? null) ? round((float) $item['manual_price'], 2) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    /** @return array<string, string> */
    protected function orderValidationRules(): array
    {
        return [
            'form.customer_id' => 'required|exists:customers,id',
            'form.customer_address' => 'nullable|string|max:2000',
            'form.customer_pic_name' => 'nullable|string|max:255',
            'form.customer_pic_phone' => 'nullable|string|max:50',
            // optional; when picked it must be one of the listed channels
            'form.received_through' => 'nullable|in:'.implode(',', array_keys($this->receivedThroughOptions())),
            'form.salesperson_id' => 'nullable|exists:users,id',
            // only the payment terms the customer's type allows (see orderTypeOptions)
            'form.order_type' => 'required|in:'.implode(',', array_keys($this->orderTypeOptions())),
            'pairs' => 'required|array|min:1',
            // optional: a blank consignor / consignee is saved blank (see pairData)
            'pairs.*.consignor_name' => 'nullable|string|max:255',
            'pairs.*.consignee_name' => 'nullable|string|max:255',
            'pairs.*.service_type' => 'required|in:'.implode(',', array_keys($this->serviceTypeOptions())),
            'pairs.*.store_id' => 'nullable|required_if:pairs.*.service_type,'.ServiceType::Store->value.'|exists:stores,id',
            'pairs.*.consignor_pic_name' => 'nullable|string|max:255',
            'pairs.*.consignor_pic_phone' => 'nullable|string|max:50',
            'pairs.*.consignee_pic_name' => 'nullable|string|max:255',
            'pairs.*.consignee_pic_phone' => 'nullable|string|max:50',
            'pairs.*.pickup_location' => 'nullable|string|max:2000',
            'pairs.*.drop_off_location' => 'nullable|string|max:2000',
            'pairs.*.to_location_id' => 'nullable|exists:locations,id',
            'pairs.*.from_location_id' => 'nullable|exists:locations,id',
            'pairs.*.customer_do_number' => 'required|string|max:100',
            // becomes the CSN date when the record's CSN is created
            // optional free text (a remark, e.g. "Mon 13/10 before noon"); not the CSN date
            'pairs.*.expected_delivery_date' => 'nullable|string|max:255',
            'pairs.*.drop_off_type' => 'required|in:'.implode(',', array_keys(DropOffType::options())),
            'pairs.*.items' => 'required|array|min:1',
            'pairs.*.items.*.item_name' => 'required|string|max:255',
            'pairs.*.items.*.quantity' => 'required|integer|min:1',
            // price keyed in for a product without a rate
            'pairs.*.items.*.manual_price' => 'nullable|numeric|min:0|max:9999999',
            // photos of each product row (same file rule as before: images or PDF, 8 MB)
            'pairs.*.items.*.photos' => 'nullable|array',
            'pairs.*.items.*.photos.*' => self::PHOTO_RULE,
        ];
    }

    /** @return array<string, string> */
    protected function orderValidationMessages(): array
    {
        return [
            'form.order_type.in' => 'This payment term is not available for the customer\'s type.',
            'pairs.*.store_id.required_if' => 'Select the store the consignor brings the goods to.',
            'pairs.*.customer_do_number.required' => 'Enter the DO number for every consignor & consignee block.',
            'pairs.*.items.*.item_name.required' => 'Select a product for every item row.',
        ];
    }

    /**
     * One consignor & consignee block as the order actions take it (Create and Edit order):
     * - a blank consignor or consignee is saved blank (the record's price column keeps the To location's name);
     * - Pickup: the pickup location typed or picked; Store: the store (and its branch), the pickup location shown
     *   (the store's address unless edited);
     * - the drop-off location: the saved address picked or the new one typed.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    protected function pairData(array $pair, int $index): array
    {
        $text = fn (mixed $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;
        $store = ($pair['service_type'] ?? '') === ServiceType::Store->value;
        $storeRow = $store && filled($pair['store_id'] ?? null) ? Store::query()->find($pair['store_id']) : null;
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $pickup = $text($pair['pickup_location'] ?? null) ?? ($store ? $text(OrderFormOptions::storeAddress($storeRow)) : null);

        return [
            'consignor_name' => $text($pair['consignor_name'] ?? null),
            'service_type' => $store ? ServiceType::Store->value : ServiceType::Pickup->value,
            'store_id' => $storeRow?->id,
            'store_branch_id' => $storeRow?->branch_id ?? ($store && filled($pair['legacy_store_branch_id'] ?? null) ? (int) $pair['legacy_store_branch_id'] : null),
            'from_location_id' => filled($pair['from_location_id'] ?? null) ? (int) $pair['from_location_id'] : null,
            'consignor_pic_name' => $text($pair['consignor_pic_name'] ?? null),
            'consignor_pic_phone' => $text($pair['consignor_pic_phone'] ?? null),
            'pickup_location' => $pickup,
            'consignee_name' => $text($pair['consignee_name'] ?? null),
            'to_location_id' => $toLocationId,
            'consignee_pic_name' => $text($pair['consignee_pic_name'] ?? null),
            'consignee_pic_phone' => $text($pair['consignee_pic_phone'] ?? null),
            'drop_off_location' => $text($pair['drop_off_location'] ?? null),
            'drop_off_type' => ($pair['drop_off_type'] ?? '') ?: DropOffType::Other->value,
            'customer_do_number' => $text($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $text($pair['expected_delivery_date'] ?? null),
            'instructions' => $text($pair['instructions'] ?? null),
            'items' => array_map(fn (array $item) => [
                'line_type' => ($item['line_type'] ?? null) ?: null,
                'catalog_key' => ($item['catalog_key'] ?? null) ?: null,
                'item_name' => (string) ($item['item_name'] ?? ''),
                'uom' => ($item['uom'] ?? null) ?: null,
                'quantity' => $item['quantity'] ?? 1,
                // used only when the product has no special / price-list rate (and a salesperson owns the order)
                'unit_price' => ($item['unit_price'] ?? null) === null && is_numeric($item['manual_price'] ?? null) ? round((float) $item['manual_price'], 2) : null,
            ], $pair['items'] ?? []),
        ];
    }

    public function save(): void
    {
        $this->validate($this->orderValidationRules(), $this->orderValidationMessages());

        $branch = CurrentCompany::branch();
        $company = Filament::getTenant();

        if (! $branch) {
            Notification::make()->title('Select a branch first')->danger()->send();

            return;
        }

        try {
            // each block with the photos of its products (stored now, kept with that block's record)
            $blocks = array_values($this->pairs);
            $pairs = array_map(fn (array $pair, int $index) => $this->pairDataWithPhotos($pair, $index), $blocks, array_keys($blocks));

            $result = app(CreateAdminOrder::class)->execute([
                'customer_id' => $this->form['customer_id'],
                // one billing address for the whole order (each record's customer_address)
                'customer_address' => trim((string) ($this->form['customer_address'] ?? '')) ?: null,
                'customer_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
                'customer_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
                'received_through' => ($this->form['received_through'] ?? '') ?: null,
                'salesperson_id' => $this->form['salesperson_id'] ?: null,
                'order_type' => $this->form['order_type'],
                // pickup / store is per record; the order form keeps the first block's
                'service_type' => $pairs[0]['service_type'] ?? ServiceType::Pickup->value,
                // captured when the payment is recorded
                'payment_method' => null,
                // photos are kept per block (pairs.*.attachments); nothing for the whole order
                'attachments' => [],
                'pairs' => $pairs,
            ], auth()->user(), $branch, $company instanceof \App\Domains\MasterData\Models\Company ? $company : null);

            $orders = $result['orders'];
            $enquiry = $result['enquiry'];
            // products kept without a price yet (no price-list rate): priced under Items & pricing
            $unpriced = $orders->flatMap(fn ($order) => $order->lines->whereNull('unit_price')->pluck('item_name'))->unique()->values();

            Notification::make()
                ->title('Order '.$enquiry->orderNumber().' created · '.$orders->count().' record(s)')
                ->body($orders->pluck('number')->implode(', ').($enquiry->salesperson_id ? ' · continue with pricing.' : ' · assign a salesperson to continue.')
                    .($enquiry->salesperson_id && $unpriced->isNotEmpty() ? ' No price yet for '.$unpriced->implode(', ').': enter it under Items & pricing.' : ''))
                ->success()
                ->send();

            $this->redirect(OrderDetail::urlFor('order', $orders->first()->id).'?tab=pricing', navigate: false);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    protected function formatAddress(CustomerAddress $address): string
    {
        return OrderFormOptions::formatAddress($address);
    }
}

<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\ServiceType;
use App\Filament\Resources\BranchResource;
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
     * One consignor & consignee block per entry; its photos picked on the page (not saved yet) are in 'photos'
     * (TemporaryUploadedFile list), the ones already saved on an edited record in 'existing_photos'.
     *
     * @var list<array<string, mixed>>
     */
    public array $pairs = [];

    /**
     * Photo picker of each block (block index => files just uploaded): moved into that block's 'photos' as soon
     * as the upload finishes, so picking more files adds to the ones already chosen.
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
     * Stores (O&G branches) for the consignor's Store mode; a branch already chosen on a block stays listed.
     *
     * @return array<int, string>
     */
    public function storeOptions(): array
    {
        return OrderFormOptions::storeOptions(array_column($this->pairs, 'store_branch_id'));
    }

    /**
     * What the page shows of the chosen store: its address and phone from the Branches master, and where to
     * add them when missing.
     *
     * @return array{name: string, address: string, phone: string, edit_url: ?string}|null
     */
    public function storeInfo(mixed $branchId): ?array
    {
        $branch = filled($branchId) ? Branch::query()->find($branchId) : null;

        if (! $branch) {
            return null;
        }

        try {
            $editUrl = BranchResource::getUrl('edit', ['record' => $branch]);
        } catch (Throwable) {
            $editUrl = null;
        }

        return [
            'name' => (string) $branch->name,
            'address' => trim((string) $branch->address),
            'phone' => trim((string) $branch->phone),
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
            // Pickup (collected from the consignor) or Store (the consignor brings the goods to an O&G branch)
            'service_type' => ServiceType::Pickup->value,
            'store_branch_id' => '',
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
            // photos / DO attachments picked for this block (saved with its record)
            'photos' => [],
            'instructions' => '',
            'items' => [$this->itemTemplate()],
        ];
    }

    /** @return array<string, mixed> */
    protected function itemTemplate(): array
    {
        return ['line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'quantity' => 1, 'unit_price' => null, 'tier' => null, 'source' => null, 'available' => null];
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
                if (($pair['service_type'] ?? ServiceType::Pickup->value) !== ServiceType::Store->value) {
                    $pair['from_location_id'] = $consignor['from_location_id'] ?? '';
                } else {
                    $pair['pickup_from_location_id'] = $consignor['from_location_id'] ?? '';
                }
                $pair['pickup_preset'] = $consignor['pickup_location_preset'] ?? '';
                $pair['pickup_location'] = $consignor['pickup_location'] ?? '';
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
        if (preg_match('/^(\d+)\.(service_type|store_branch_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];

            if (($pair['service_type'] ?? '') !== ServiceType::Store->value) {
                $pair['service_type'] = ServiceType::Pickup->value;

                // back to Pickup: a From still set to the store's price-list location returns to the Pickup one
                // (the From before Store was chosen, else the customer's default); a From picked by hand stays
                $storeFrom = $m[2] === 'service_type' && filled($pair['store_branch_id'] ?? null)
                    ? OrderFormOptions::fromLocationIdForBranch((int) $pair['store_branch_id'])
                    : null;

                if ($storeFrom && (string) ($pair['from_location_id'] ?? '') === (string) $storeFrom) {
                    $pair['from_location_id'] = array_key_exists('pickup_from_location_id', $pair)
                        ? (string) $pair['pickup_from_location_id']
                        : (string) (OrderFormOptions::consignorStateForCustomer(filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null, withPickupPreset: false)['from_location_id'] ?? '');
                }

                unset($pair['pickup_from_location_id']);

                return;
            }

            // Store: the order's branch until another store is picked; its price-list location becomes From
            if ($m[2] === 'service_type' && blank($pair['store_branch_id'] ?? null)) {
                $pair['store_branch_id'] = (string) (CurrentCompany::branchId() ?? '');
            }

            // remember the Pickup From (before the store's replaces it) for a switch back to Pickup
            if ($m[2] === 'service_type' && ! array_key_exists('pickup_from_location_id', $pair)) {
                $pair['pickup_from_location_id'] = (string) ($pair['from_location_id'] ?? '');
            }

            $fromId = filled($pair['store_branch_id'] ?? null) ? OrderFormOptions::fromLocationIdForBranch((int) $pair['store_branch_id']) : null;

            if ($fromId) {
                $pair['from_location_id'] = (string) $fromId;
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
    }

    /*
    |--------------------------------------------------------------------------
    | Photos per consignor & consignee block
    |--------------------------------------------------------------------------
    */

    /**
     * Files just uploaded with a block's photo picker: checked (images / PDF, 8 MB) and added to the block's
     * photos; a file that fails is left out with its message under the picker.
     */
    public function updatedPhotoUploads(mixed $value, ?string $key = null): void
    {
        $index = (int) explode('.', (string) $key)[0];
        $files = array_values(array_filter(Arr::wrap($this->photoUploads[$index] ?? []), fn ($file) => $file instanceof TemporaryUploadedFile));
        $this->photoUploads[$index] = [];

        if (! isset($this->pairs[$index]) || $this->isPairLocked($index) || $files === []) {
            return;
        }

        $this->resetErrorBag(['pairs.'.$index.'.photos', 'photoUploads.'.$index]);
        $accepted = [];

        foreach ($files as $file) {
            $check = Validator::make(['photo' => $file], ['photo' => self::PHOTO_RULE], [], ['photo' => $file->getClientOriginalName()]);

            if ($check->fails()) {
                $this->addError('pairs.'.$index.'.photos', $check->errors()->first('photo'));

                continue;
            }

            $accepted[] = $file;
        }

        $this->pairs[$index]['photos'] = array_values(array_merge(
            array_filter($this->pairs[$index]['photos'] ?? [], fn ($file) => $file instanceof TemporaryUploadedFile),
            $accepted,
        ));
    }

    /** Takes a photo picked on the page off its block before the order is saved. */
    public function removePhoto(int $index, int $photoIndex): void
    {
        if (! isset($this->pairs[$index]['photos'][$photoIndex]) || $this->isPairLocked($index)) {
            return;
        }

        unset($this->pairs[$index]['photos'][$photoIndex]);
        $this->pairs[$index]['photos'] = array_values($this->pairs[$index]['photos']);
    }

    /**
     * Photos picked for a block as the page shows them (not saved yet).
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function newPhotos(int $index): array
    {
        return collect($this->pairs[$index]['photos'] ?? [])
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
     * Stores a block's picked photos on the public disk, each under its own folder with its original (cleaned)
     * name, so a record's file list reads well.
     *
     * @param  array<string, mixed>  $pair
     * @return list<array{path: string, name: string, mime: ?string, size: int|false, uploaded_by: ?string, uploaded_at: string}>
     */
    protected function storePairPhotos(array $pair): array
    {
        $files = [];

        foreach ($pair['photos'] ?? [] as $file) {
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

    /** History icon of a product row: the customer's previous order lines of that product (information only). */
    public function productHistoryAction(): Action
    {
        return Action::make('productHistory')
            ->label('Previous records')
            ->modalHeading(fn (array $arguments) => 'Previous records · '.($this->historyItem($arguments)['item_name'] ?? 'product'))
            ->modalDescription(fn () => $this->customerName() ? 'Earlier orders of '.$this->customerName().' for this product, newest first.' : null)
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

        return [
            'item_name' => $item['item_name'] ?? '',
            'location' => $location,
            'customer' => $this->customerName(),
            'rows' => $customerId && filled($item['item_name'] ?? null)
                ? app(CustomerQuotationPriceHistory::class)->productHistory((int) $customerId, (string) $item['item_name'], $location, $this->historyExcludedQuotationIds(), companyId: CurrentCompany::id())
                : [],
        ];
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
                $lines[$i][$j] = $item['unit_price'] !== null ? round((float) $item['unit_price'] * $qty, 2) : 0.0;
                $total += $lines[$i][$j];
            }
        }

        return ['items' => round($total, 2), 'lines' => $lines];
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
            'pairs.*.store_branch_id' => 'nullable|required_if:pairs.*.service_type,'.ServiceType::Store->value.'|exists:branches,id',
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
            'pairs.*.expected_delivery_date' => 'required|date',
            'pairs.*.drop_off_type' => 'required|in:'.implode(',', array_keys(DropOffType::options())),
            'pairs.*.items' => 'required|array|min:1',
            'pairs.*.items.*.item_name' => 'required|string|max:255',
            'pairs.*.items.*.quantity' => 'required|integer|min:1',
            // photos / DO attachments of each block (same file rule as before: images or PDF, 8 MB)
            'pairs.*.photos' => 'nullable|array',
            'pairs.*.photos.*' => self::PHOTO_RULE,
        ];
    }

    /** @return array<string, string> */
    protected function orderValidationMessages(): array
    {
        return [
            'form.order_type.in' => 'This payment term is not available for the customer\'s type.',
            'pairs.*.store_branch_id.required_if' => 'Select the store the consignor brings the goods to.',
            'pairs.*.customer_do_number.required' => 'Enter the DO number for every consignor & consignee block.',
            'pairs.*.expected_delivery_date.required' => 'Enter the expected delivery date for every consignor & consignee block.',
            'pairs.*.items.*.item_name.required' => 'Select a product for every item row.',
        ];
    }

    /**
     * One consignor & consignee block as the order actions take it (Create and Edit order):
     * - a blank consignor or consignee is saved blank (the record's price column keeps the To location's name);
     * - Pickup: the pickup location typed or picked; Store: the branch, its address as the pickup location;
     * - the drop-off location: the saved address picked or the new one typed.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    protected function pairData(array $pair, int $index): array
    {
        $text = fn (mixed $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;
        $store = ($pair['service_type'] ?? '') === ServiceType::Store->value;
        $branch = $store && filled($pair['store_branch_id'] ?? null) ? Branch::query()->find($pair['store_branch_id']) : null;
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $pickup = $store ? $text(OrderFormOptions::storeAddress($branch)) : $text($pair['pickup_location'] ?? null);

        return [
            'consignor_name' => $text($pair['consignor_name'] ?? null),
            'service_type' => $store ? ServiceType::Store->value : ServiceType::Pickup->value,
            'store_branch_id' => $branch?->id,
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
            // each block with its own photos (stored now, kept with that block's record)
            $blocks = array_values($this->pairs);
            $pairs = array_map(fn (array $pair, int $index) => $this->pairData($pair, $index) + ['attachments' => $this->storePairPhotos($pair)], $blocks, array_keys($blocks));

            $result = app(CreateAdminOrder::class)->execute([
                'customer_id' => $this->form['customer_id'],
                // one billing address for the whole order (each record's customer_address)
                'customer_address' => trim((string) ($this->form['customer_address'] ?? '')) ?: null,
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

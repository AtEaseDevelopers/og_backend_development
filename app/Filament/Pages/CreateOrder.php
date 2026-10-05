<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\OrderFormOptions;
use App\Support\QuotationPricingLookup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    /** @var list<array<string, mixed>> */
    public array $pairs = [];

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        $this->form = [
            'customer_id' => '',
            'received_through' => 'phone_call',
            'salesperson_id' => '',
            'order_type' => OrderType::Cash->value,
            'service_type' => ServiceType::Pickup->value,
            'payment_method' => PaymentMethod::BankTransfer->value,
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

    /** Customer, received through and payment term can be changed. */
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

    /** @return list<array{name: string, url: ?string}> */
    public function existingAttachments(): array
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

    /** @return array<string, string> */
    public function orderTypeOptions(): array
    {
        $options = OrderType::options();
        $customer = filled($this->form['customer_id'] ?? null) ? Customer::query()->find($this->form['customer_id']) : null;

        if (! ($customer?->is_credit)) {
            unset($options[OrderType::Term->value]);
        }

        return $options;
    }

    /** @return array<string, string> */
    public function serviceTypeOptions(): array
    {
        return ServiceType::options();
    }

    /** @return array<string, string> */
    public function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    /** @return array<string, string> */
    public function dropOffTypeOptions(): array
    {
        return DropOffType::options();
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
            'consignor_name' => $this->customerName() ?? '',
            'from_location_id' => $consignor['from_location_id'] ?? '',
            'consignor_brn' => $consignor['consignor_brn'] ?? '',
            'customer_address' => $consignor['customer_address'] ?? '',
            'pickup_preset' => $consignor['pickup_location_preset'] ?? '',
            'pickup_location' => $consignor['pickup_location'] ?? '',
            'consignee_name' => '',
            'to_location_id' => '',
            'consignee_brn' => '',
            'consignee_address' => '',
            'drop_off_preset' => '',
            'drop_off_location' => '',
            'customer_do_number' => '',
            'expected_delivery_date' => '',
            'drop_off_type' => DropOffType::Other->value,
            'instructions' => '',
            'items' => [$this->itemTemplate()],
        ];
    }

    /** @return array<string, mixed> */
    protected function itemTemplate(): array
    {
        return ['line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'quantity' => 1, 'unit_price' => null, 'tier' => null, 'source' => null, 'available' => null];
    }

    public function updatedForm($value, string $key): void
    {
        if ($key === 'customer_id') {
            $consignor = filled($value) ? OrderFormOptions::consignorStateForCustomer((string) $value) : [];
            $name = $this->customerName() ?? '';
            $knownNames = Customer::query()->pluck('company_name')->all();

            foreach ($this->pairs as &$pair) {
                // keep a consignor the user typed; replace the default (a customer name) when the customer changes
                if (blank($pair['consignor_name'] ?? null) || in_array($pair['consignor_name'], $knownNames, true)) {
                    $pair['consignor_name'] = $name;
                }
                $pair['from_location_id'] = $consignor['from_location_id'] ?? '';
                $pair['consignor_brn'] = $consignor['consignor_brn'] ?? '';
                $pair['customer_address'] = $consignor['customer_address'] ?? '';
                $pair['pickup_preset'] = $consignor['pickup_location_preset'] ?? '';
                $pair['pickup_location'] = $consignor['pickup_location'] ?? '';
            }
            unset($pair);

            if (! array_key_exists($this->form['order_type'], $this->orderTypeOptions())) {
                $this->form['order_type'] = OrderType::Cash->value;
            }

            $this->refreshAllPrices();
        }

        if ($key === 'salesperson_id') {
            $this->refreshAllPrices();
        }
    }

    public function updatedPairs($value, string $key): void
    {
        if (preg_match('/^(\d+)\.(pickup_preset|drop_off_preset|to_location_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];

            if ($m[2] === 'pickup_preset') {
                $address = filled($value) ? CustomerAddress::query()->find($value) : null;
                $pair['pickup_location'] = $address ? $this->formatAddress($address) : $pair['pickup_location'];
            } elseif ($m[2] === 'drop_off_preset') {
                $address = filled($value) ? CustomerAddress::query()->find($value) : null;

                if ($address) {
                    $pair['consignee_name'] = $address->label ?: $pair['consignee_name'];
                    $pair['consignee_address'] = $address->address;
                    $pair['drop_off_location'] = $this->formatAddress($address);
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

    public function removePair(int $index): void
    {
        if (count($this->pairs) <= 1) {
            return;
        }

        unset($this->pairs[$index]);
        $this->pairs = array_values($this->pairs);
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

    /** @return array{items: float, lines: array<int, array<int, float>>} */
    public function pairTotals(): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($this->pairs as $i => $pair) {
            foreach ($pair['items'] as $j => $item) {
                $qty = ($item['line_type'] ?? '') === 'uom' ? max(0.01, (float) ($item['quantity'] ?: 1)) : 1.0;
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
            'form.received_through' => 'required|string',
            'form.salesperson_id' => 'nullable|exists:users,id',
            'form.order_type' => 'required|in:'.implode(',', array_keys(OrderType::options())),
            'form.service_type' => 'required|in:'.implode(',', array_keys(ServiceType::options())),
            'form.payment_method' => 'required|in:'.implode(',', array_keys(PaymentMethod::options())),
            'pairs' => 'required|array|min:1',
            'pairs.*.consignor_name' => 'required|string|max:255',
            'pairs.*.consignee_name' => 'required|string|max:255',
            'pairs.*.to_location_id' => 'nullable|exists:locations,id',
            'pairs.*.from_location_id' => 'nullable|exists:locations,id',
            'pairs.*.customer_do_number' => 'nullable|string|max:100',
            'pairs.*.expected_delivery_date' => 'required|date',
            'pairs.*.drop_off_type' => 'required|in:'.implode(',', array_keys(DropOffType::options())),
            'pairs.*.items' => 'required|array|min:1',
            'pairs.*.items.*.item_name' => 'required|string|max:255',
            'pairs.*.items.*.quantity' => 'required|integer|min:1',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ];
    }

    /** @return array<string, string> */
    protected function orderValidationMessages(): array
    {
        return [
            'pairs.*.consignor_name.required' => 'Enter the consignor for every consignor & consignee block.',
            'pairs.*.consignee_name.required' => 'Enter the consignee for every consignor & consignee block.',
            'pairs.*.expected_delivery_date.required' => 'Enter the expected delivery date for every consignor & consignee block.',
            'pairs.*.items.*.item_name.required' => 'Select a product for every item row.',
        ];
    }

    /**
     * Uploaded photos / DO attachments, stored on the public disk.
     *
     * @return list<array<string, mixed>>
     */
    protected function storeAttachments(): array
    {
        $files = [];
        foreach ($this->attachments as $file) {
            $path = $file->store('portal-enquiries/admin/'.now()->format('Ym'), 'public');
            $files[] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->user()?->name, 'uploaded_at' => now()->toDateTimeString()];
        }

        return $files;
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

        $files = $this->storeAttachments();

        try {
            $result = app(CreateAdminOrder::class)->execute([
                'customer_id' => $this->form['customer_id'],
                'received_through' => $this->form['received_through'],
                'salesperson_id' => $this->form['salesperson_id'] ?: null,
                'order_type' => $this->form['order_type'],
                'service_type' => $this->form['service_type'],
                'payment_method' => $this->form['payment_method'],
                'attachments' => $files,
                'pairs' => array_map(fn (array $pair) => [
                    'consignor_name' => $pair['consignor_name'] ?: null,
                    'from_location_id' => $pair['from_location_id'] ?: null,
                    'consignor_brn' => $pair['consignor_brn'] ?: null,
                    'customer_address' => $pair['customer_address'] ?: null,
                    'pickup_location' => $pair['pickup_location'] ?: null,
                    'consignee_name' => $pair['consignee_name'],
                    'to_location_id' => $pair['to_location_id'] ?: null,
                    'consignee_brn' => $pair['consignee_brn'] ?: null,
                    'consignee_address' => $pair['consignee_address'] ?: null,
                    'drop_off_location' => $pair['drop_off_location'] ?: null,
                    'customer_do_number' => $pair['customer_do_number'] ?: null,
                    'expected_delivery_date' => $pair['expected_delivery_date'],
                    'drop_off_type' => $pair['drop_off_type'],
                    'instructions' => $pair['instructions'] ?: null,
                    'items' => array_map(fn (array $item) => [
                        'line_type' => $item['line_type'],
                        'catalog_key' => $item['catalog_key'] ?: null,
                        'item_name' => $item['item_name'],
                        'uom' => $item['uom'] ?: null,
                        'quantity' => $item['quantity'],
                    ], $pair['items']),
                ], $this->pairs),
            ], auth()->user(), $branch, $company instanceof \App\Domains\MasterData\Models\Company ? $company : null);

            $orders = $result['orders'];
            $enquiry = $result['enquiry'];

            Notification::make()
                ->title('Order '.$enquiry->orderNumber().' created · '.$orders->count().' record(s)')
                ->body($orders->pluck('number')->implode(', ').($enquiry->salesperson_id ? ' · continue with pricing.' : ' · assign a salesperson to continue.'))
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

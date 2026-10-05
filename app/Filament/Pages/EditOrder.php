<?php

namespace App\Filament\Pages;

use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PortalEnquiryStatus;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\OrderFormOptions;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Throwable;

/**
 * Edit order: the "Create order for customer" layout in edit mode for an existing order
 * (route orders/{type}/{id}/edit, type = order | enquiry, like the order detail page).
 *
 * One block per consignor & consignee record of the order. Records in draft / negotiation can be
 * changed, removed (cancelled) or added to; confirmed records are shown read-only and change through
 * Revise on the order page. An enquiry that is not priced yet edits its submitted order form.
 */
class EditOrder extends CreateOrder
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'orders/{type}/{id}/edit';

    #[Locked]
    public string $recordType = 'order';

    #[Locked]
    public int $recordId = 0;

    #[Locked]
    public ?int $enquiryId = null;

    /** Order record without an enquiry (edited alone). */
    #[Locked]
    public ?int $singleRecordId = null;

    #[Locked]
    public string $orderNumber = '';

    /** The enquiry has no order record yet: its submitted order form is edited. */
    #[Locked]
    public bool $enquiryOnly = false;

    /** Customer, received through and payment term are read-only (a record is no longer editable). */
    #[Locked]
    public bool $headerLocked = false;

    #[Locked]
    public bool $salespersonLocked = false;

    /** @var list<int> */
    #[Locked]
    public array $lockedRecordIds = [];

    /** @var list<int> */
    #[Locked]
    public array $removableRecordIds = [];

    /** Header values when the page opened (read-only fields are restored from it). */
    #[Locked]
    public array $originalForm = [];

    public static function getRelativeRouteName(): string
    {
        return 'orders.edit';
    }

    public static function urlFor(string $type, int $id): string
    {
        return static::getUrl(['type' => $type, 'id' => $id]);
    }

    public function mount(string $type = 'order', int $id = 0): void
    {
        abort_unless(in_array($type, ['order', 'enquiry'], true), 404);

        $this->recordType = $type;
        $this->recordId = $id;

        [$enquiry, $single] = $this->resolveTarget($type, $id);
        abort_unless($enquiry || $single, 404);

        $user = auth()->user();
        $detailUrl = OrderDetail::urlFor($type, $id);

        if ($enquiry && in_array($enquiry->status, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true)) {
            Notification::make()->title('This order is '.strtolower((string) $enquiry->status->getLabel()).' and can no longer be edited')->warning()->send();
            $this->redirect($detailUrl, navigate: false);

            return;
        }

        if ($enquiry && $user && $enquiry->isLockedByOther($user)) {
            Notification::make()->title('Currently being attended')->body(($enquiry->locker?->name ?? 'Another user').' is attending this order. Try again when they finish.')->warning()->send();
            $this->redirect($detailUrl, navigate: false);

            return;
        }

        $records = $enquiry ? UpdateOrderRecords::recordsFor($enquiry) : collect([$single->loadMissing(['destinations', 'lines', 'customer'])]);

        if ($records->isNotEmpty() && ! $records->contains(fn (Quotation $q) => UpdateOrderRecords::isEditable($q))) {
            Notification::make()->title('Nothing to edit')->body('Every record of this order is confirmed or locked. Change it with Revise (new version) on the order page.')->warning()->send();
            $this->redirect($detailUrl, navigate: false);

            return;
        }

        if ($enquiry && $user) {
            $enquiry->acquireLock($user);
        }

        $this->enquiryId = $enquiry?->id;
        $this->singleRecordId = $enquiry ? null : $single->id;
        $this->enquiryOnly = $enquiry !== null && $records->isEmpty();
        $this->orderNumber = $enquiry ? $enquiry->orderNumber() : $single->orderNumber();
        $this->headerLocked = ! $records->every(fn (Quotation $q) => UpdateOrderRecords::isEditable($q));
        $this->lockedRecordIds = $records->reject(fn (Quotation $q) => UpdateOrderRecords::isEditable($q))->map(fn (Quotation $q) => (int) $q->id)->values()->all();
        $this->removableRecordIds = $enquiry ? $records->filter(fn (Quotation $q) => UpdateOrderRecords::isRemovable($q))->map(fn (Quotation $q) => (int) $q->id)->values()->all() : [];
        $this->salespersonLocked = $enquiry && $enquiry->salesperson_locked && $enquiry->salesperson_id && ! $user?->isSuperadmin();

        $this->form = $this->formFrom($enquiry, $records);
        $this->originalForm = $this->form;
        $this->pairs = $this->enquiryOnly ? $this->pairsFromPayload($enquiry) : $this->pairsFromRecords($records, $enquiry);

        if ($this->pairs === []) {
            $this->pairs = [$this->pairTemplate()];
        }

        $this->refreshAllPrices();
    }

    public function getTitle(): string
    {
        return trim('Edit order '.$this->orderNumber);
    }

    /*
    |--------------------------------------------------------------------------
    | Loading
    |--------------------------------------------------------------------------
    */

    /**
     * The enquiry of the order (or, for an order record without one, that record), within the current company.
     *
     * @return array{0: ?PortalEnquiry, 1: ?Quotation}
     */
    protected function resolveTarget(string $type, int $id): array
    {
        $companyId = CurrentCompany::id();

        if ($type === 'order') {
            $order = Quotation::query()->with('portalEnquiry')->find($id);

            if (! $order || ($companyId && (int) $order->company_id !== (int) $companyId)) {
                return [null, null];
            }

            return $order->portalEnquiry ? [$order->portalEnquiry, null] : [null, $order];
        }

        $enquiry = PortalEnquiry::query()->find($id);

        if (! $enquiry || ($companyId && $enquiry->company_id && (int) $enquiry->company_id !== (int) $companyId)) {
            return [null, null];
        }

        return [$enquiry, null];
    }

    /**
     * @param  Collection<int, Quotation>  $records
     * @return array<string, string>
     */
    protected function formFrom(?PortalEnquiry $enquiry, Collection $records): array
    {
        $first = $records->first();
        $editable = $records->first(fn (Quotation $q) => UpdateOrderRecords::isEditable($q)) ?? $first;

        $receivedThrough = $enquiry?->received_through
            ?: ($enquiry?->payload['received_through'] ?? null)
            ?: match ($enquiry?->source) {
                PortalEnquiry::SOURCE_PORTAL => 'portal',
                PortalEnquiry::SOURCE_SALESPERSON_LINK => 'salesperson_link',
                PortalEnquiry::SOURCE_WALK_IN => 'walk_in',
                default => 'phone_call',
            };

        return [
            'customer_id' => (string) ($enquiry?->customer_id ?? $first?->customer_id ?? ''),
            'received_through' => (string) $receivedThrough,
            'salesperson_id' => (string) ($enquiry?->salesperson_id ?? $first?->salesperson_id ?? ''),
            'order_type' => ($first?->orderType() ?? $enquiry?->order_type)?->value ?? OrderType::Cash->value,
            'service_type' => ($editable?->service_type ?? $enquiry?->service_type)?->value ?? ServiceType::Pickup->value,
            'payment_method' => (string) ($editable?->payment_method ?: ($enquiry?->payment_method ?: PaymentMethod::BankTransfer->value)),
        ];
    }

    /**
     * One block per order record (oldest first). Products come from the record's lines with the
     * price already entered; a record that was never priced shows the products the enquiry asked for.
     *
     * @param  Collection<int, Quotation>  $records
     * @return list<array<string, mixed>>
     */
    protected function pairsFromRecords(Collection $records, ?PortalEnquiry $enquiry): array
    {
        $service = app(UpdateOrderRecords::class);

        return $records->values()->map(function (Quotation $q) use ($records, $enquiry, $service): array {
            $lock = UpdateOrderRecords::lockInfo($q);
            $existing = UpdateOrderRecords::productLines($q)
                ->groupBy('item_name')
                ->map(fn (Collection $lines, $name) => ['name' => (string) $name, 'price' => $lines->first()->unit_price !== null ? (float) $lines->first()->unit_price : null])
                ->filter(fn (array $row) => $row['price'] !== null)
                ->values()
                ->all();
            $type = collect($q->destination_types ?? [])->first()['drop_off_type'] ?? $q->destinations->sortBy('sequence')->first()?->drop_off_type;
            $type = $type instanceof \BackedEnum ? $type->value : $type;
            $items = array_map(fn (array $item) => $this->formItem($item), $service->itemsForRecord($q, $enquiry, $records));

            return [
                'record_id' => (int) $q->id,
                'record_number' => $q->number,
                'locked' => $lock !== null,
                'lock_label' => $lock['label'] ?? null,
                'lock_note' => $lock['note'] ?? null,
                'existing_prices' => $existing,
                'payload_index' => null,
                'consignor_name' => (string) ($q->consignor_name ?: ($q->customer?->company_name ?? '')),
                'from_location_id' => (string) ($q->from_location_id ?? ''),
                'consignor_brn' => (string) ($q->consignor_brn ?? ''),
                'customer_address' => (string) ($q->customer_address ?? ''),
                'pickup_preset' => '',
                'pickup_location' => (string) ($q->pickup_location ?? ''),
                'consignee_name' => (string) ($q->consignee_name ?? ''),
                'to_location_id' => (string) ($q->to_location_id ?? ''),
                'consignee_brn' => (string) ($q->consignee_brn ?? ''),
                'consignee_address' => (string) ($q->consignee_address ?? ''),
                'drop_off_preset' => '',
                'drop_off_location' => (string) ($q->drop_off_location ?? ''),
                'customer_do_number' => (string) ($q->customer_do_number ?? ''),
                'expected_delivery_date' => $q->expected_delivery_date?->toDateString() ?? '',
                'drop_off_type' => DropOffType::tryFrom((string) $type)?->value ?? DropOffType::Other->value,
                'instructions' => UpdateOrderRecords::instructionsFromNotes($q->notes),
                'items' => $items !== [] ? $items : [$this->itemTemplate()],
            ];
        })->all();
    }

    /**
     * Enquiry not priced yet: one block per submitted destination.
     *
     * @return list<array<string, mixed>>
     */
    protected function pairsFromPayload(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = array_values(array_filter($payload['destinations'] ?? [], 'is_array'));
        $derived = app(CreateOrderFromEnquiry::class)->pairsFromPayload($enquiry);
        $service = app(UpdateOrderRecords::class);
        $consignor = $enquiry->customer_id ? OrderFormOptions::consignorStateForCustomer((string) $enquiry->customer_id, withPickupPreset: false) : [];
        $customerName = $enquiry->customer?->company_name ?? '';

        return collect($destinations)->map(function (array $d, int $i) use ($enquiry, $derived, $service, $consignor, $customerName): array {
            $p = $derived[$i] ?? [];
            $items = collect(UpdateOrderRecords::payloadItemsFor($enquiry, $i))
                ->map(fn (array $item) => $service->itemFromPayload($item))
                ->filter(fn (array $item) => $item['item_name'] !== '')
                ->map(fn (array $item) => $this->formItem($item))
                ->values()
                ->all();

            return [
                'record_id' => null,
                'record_number' => null,
                'locked' => false,
                'lock_label' => null,
                'lock_note' => null,
                'existing_prices' => [],
                'payload_index' => $i,
                'consignor_name' => (string) ($d['consignor_name'] ?? $customerName),
                'from_location_id' => (string) ($d['from_location_id'] ?? $consignor['from_location_id'] ?? ''),
                'consignor_brn' => (string) ($d['consignor_brn'] ?? $consignor['consignor_brn'] ?? ''),
                'customer_address' => (string) ($d['customer_address'] ?? $consignor['customer_address'] ?? ''),
                'pickup_preset' => '',
                'pickup_location' => (string) ($d['pickup_location'] ?? $enquiry->pickup_address ?? ''),
                'consignee_name' => (string) ($d['consignee_name'] ?? ''),
                'to_location_id' => (string) ($d['to_location_id'] ?? $p['to_location_id'] ?? ''),
                'consignee_brn' => (string) ($d['consignee_brn'] ?? ''),
                'consignee_address' => (string) ($d['consignee_address'] ?? $d['address'] ?? ''),
                'drop_off_preset' => '',
                'drop_off_location' => (string) ($d['drop_off_location'] ?? $p['drop_off_location'] ?? ''),
                'customer_do_number' => (string) ($d['customer_do_number'] ?? $enquiry->customer_do_number ?? ''),
                'expected_delivery_date' => (string) ($d['expected_delivery_date'] ?? $enquiry->preferred_delivery_date?->toDateString() ?? ''),
                'drop_off_type' => DropOffType::tryFrom((string) ($d['drop_off_type'] ?? ''))?->value ?? DropOffType::Other->value,
                'instructions' => (string) (array_key_exists('instructions', $d) ? ($d['instructions'] ?? '') : ($i === 0 ? ($enquiry->special_requirements ?? '') : '')),
                'items' => $items !== [] ? $items : [$this->itemTemplate()],
            ];
        })->values()->all();
    }

    /**
     * @param  array{line_type: string, catalog_key: ?string, item_name: string, uom: ?string, quantity: int, unit_price: ?float}  $item
     * @return array<string, mixed>
     */
    protected function formItem(array $item): array
    {
        $priced = $item['unit_price'] !== null;

        return [
            'line_type' => $item['line_type'] ?: 'uom',
            'catalog_key' => (string) ($item['catalog_key'] ?? ''),
            'item_name' => $item['item_name'],
            'uom' => (string) ($item['uom'] ?? ''),
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tier' => $priced ? 'Current price · kept' : null,
            'source' => $priced ? 'existing' : null,
            'available' => null,
        ];
    }

    /** @return array<string, mixed> */
    protected function pairTemplate(): array
    {
        return parent::pairTemplate() + [
            'record_id' => null,
            'record_number' => null,
            'locked' => false,
            'lock_label' => null,
            'lock_note' => null,
            'existing_prices' => [],
            'payload_index' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Page mode
    |--------------------------------------------------------------------------
    */

    public function isEditing(): bool
    {
        return true;
    }

    public function pageCopy(): array
    {
        $detailUrl = $this->recordId ? OrderDetail::urlFor($this->recordType, $this->recordId) : Orders::getUrl();
        $count = count($this->pairs);

        return [
            'back_url' => $detailUrl,
            'back_label' => '← Back to order',
            'crumb' => 'Orders / Edit order',
            'title' => 'Edit order',
            'sub' => collect([
                'Order '.$this->orderNumber,
                $this->customerName(),
                $this->enquiryOnly ? 'not priced yet' : $count.' '.Str::plural('record', $count),
            ])->filter()->implode(' · '),
            'cancel_url' => $detailUrl,
            'submit' => 'Save changes',
        ];
    }

    public function headerEditable(): bool
    {
        return ! $this->headerLocked;
    }

    public function salespersonEditable(): bool
    {
        return ! $this->salespersonLocked;
    }

    /** A salesperson cannot be taken off an order once assigned. */
    public function allowNoSalesperson(): bool
    {
        return blank($this->originalForm['salesperson_id'] ?? null);
    }

    public function showReceivedThrough(): bool
    {
        return $this->enquiryId !== null;
    }

    /** New consignor & consignee blocks need an order number (enquiry) to be created under. */
    public function canAddPair(): bool
    {
        return $this->enquiryId !== null;
    }

    public function canRemovePair(int $index): bool
    {
        if (count($this->pairs) <= 1 || ! isset($this->pairs[$index])) {
            return false;
        }

        $recordId = (int) ($this->pairs[$index]['record_id'] ?? 0);

        return $recordId === 0 || in_array($recordId, $this->removableRecordIds, true);
    }

    public function isPairLocked(int $index): bool
    {
        $recordId = (int) ($this->pairs[$index]['record_id'] ?? 0);

        return $recordId !== 0 && in_array($recordId, $this->lockedRecordIds, true);
    }

    public function needsHeartbeat(): bool
    {
        return $this->enquiryId !== null;
    }

    /** Keeps the enquiry held while it is being edited (the order page shows it as being attended). */
    #[Renderless]
    public function heartbeat(): void
    {
        $user = auth()->user();

        if ($this->enquiryId && $user) {
            PortalEnquiry::query()->find($this->enquiryId)?->heartbeat($user);
        }
    }

    /** @return list<array{name: string, url: ?string}> */
    public function existingAttachments(): array
    {
        if ($this->enquiryId) {
            return collect(PortalEnquiry::query()->find($this->enquiryId)?->attachments ?? [])
                ->filter(fn ($file) => is_array($file))
                ->map(fn (array $file) => [
                    'name' => (string) ($file['name'] ?? basename((string) ($file['path'] ?? ''))),
                    'url' => isset($file['path']) ? Storage::disk('public')->url($file['path']) : null,
                ])
                ->values()
                ->all();
        }

        $record = $this->singleRecordId ? Quotation::query()->find($this->singleRecordId) : null;

        return collect($record?->attachments ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->map(fn (string $path) => ['name' => basename($path), 'url' => Storage::disk('public')->url($path)])
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Options (keep the current values selectable)
    |--------------------------------------------------------------------------
    */

    public function salespersonOptions(): array
    {
        $options = parent::salespersonOptions();
        $current = $this->originalForm['salesperson_id'] ?? null;

        if (filled($current) && ! array_key_exists((int) $current, $options) && ($user = User::query()->find($current))) {
            $options[$user->id] = $user->name;
        }

        return $options;
    }

    public function receivedThroughOptions(): array
    {
        $options = parent::receivedThroughOptions();
        $current = (string) ($this->originalForm['received_through'] ?? '');

        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options = [$current => match ($current) {
                'portal' => 'Customer portal',
                'salesperson_link' => 'Salesperson link',
                default => ucfirst(str_replace('_', ' ', $current)),
            }] + $options;
        }

        return $options;
    }

    public function orderTypeOptions(): array
    {
        $options = parent::orderTypeOptions();
        $current = (string) ($this->form['order_type'] ?? '');

        // read-only: show the order's own payment term even when the customer could not pick it now
        if ($this->headerLocked && $current !== '' && ! array_key_exists($current, $options) && ($type = OrderType::tryFrom($current))) {
            $options[$type->value] = $type->getLabel();
        }

        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | Form behaviour (locked parts stay as they are)
    |--------------------------------------------------------------------------
    */

    public function updatedForm($value, string $key): void
    {
        $readOnly = ($this->headerLocked && in_array($key, ['customer_id', 'received_through', 'order_type'], true))
            || ($key === 'salesperson_id' && ($this->salespersonLocked || (blank($value) && ! $this->allowNoSalesperson())));

        if ($readOnly) {
            $this->form[$key] = $this->originalForm[$key] ?? '';

            return;
        }

        parent::updatedForm($value, $key);
    }

    public function updatedPairs($value, string $key): void
    {
        if (preg_match('/^(\d+)\./', $key, $m) && $this->isPairLocked((int) $m[1])) {
            return;
        }

        parent::updatedPairs($value, $key);
    }

    /** Products already on the record keep their price; others take the price-list rate (shown once a salesperson owns the order). */
    protected function refreshItemPrice(int $index, int $itemIndex): void
    {
        if ($this->isPairLocked($index) || ! isset($this->pairs[$index]['items'][$itemIndex])) {
            return;
        }

        $name = (string) ($this->pairs[$index]['items'][$itemIndex]['item_name'] ?? '');
        $existing = $name !== '' ? collect($this->pairs[$index]['existing_prices'] ?? [])->firstWhere('name', $name) : null;

        if ($existing && $existing['price'] !== null) {
            $this->pairs[$index]['items'][$itemIndex]['unit_price'] = (float) $existing['price'];
            $this->pairs[$index]['items'][$itemIndex]['tier'] = 'Current price · kept';
            $this->pairs[$index]['items'][$itemIndex]['source'] = 'existing';
            $this->pairs[$index]['items'][$itemIndex]['available'] = null;

            return;
        }

        parent::refreshItemPrice($index, $itemIndex);
    }

    public function addPair(): void
    {
        if ($this->canAddPair()) {
            parent::addPair();
        }
    }

    public function removePair(int $index): void
    {
        if ($this->canRemovePair($index)) {
            parent::removePair($index);
        }
    }

    public function addItem(int $index): void
    {
        if (! $this->isPairLocked($index) && isset($this->pairs[$index])) {
            parent::addItem($index);
        }
    }

    public function removeItem(int $index, int $itemIndex): void
    {
        if (! $this->isPairLocked($index) && isset($this->pairs[$index])) {
            parent::removeItem($index, $itemIndex);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    /** Same rules as Create order; read-only (locked) blocks are not validated. */
    protected function orderValidationRules(): array
    {
        $rules = [];

        foreach (parent::orderValidationRules() as $key => $rule) {
            if (! str_starts_with($key, 'pairs.*.')) {
                $rules[$key] = $rule;

                continue;
            }

            foreach (array_keys($this->pairs) as $index) {
                if (! $this->isPairLocked($index)) {
                    $rules['pairs.'.$index.substr($key, strlen('pairs.*'))] = $rule;
                }
            }
        }

        $rules['pairs.*.record_id'] = 'nullable|integer';

        return $rules;
    }

    public function save(): void
    {
        // read-only header fields always keep their original value
        if ($this->headerLocked) {
            foreach (['customer_id', 'received_through', 'order_type'] as $key) {
                $this->form[$key] = $this->originalForm[$key] ?? '';
            }
        }

        if ($this->salespersonLocked || (blank($this->form['salesperson_id'] ?? null) && ! $this->allowNoSalesperson())) {
            $this->form['salesperson_id'] = $this->originalForm['salesperson_id'] ?? '';
        }

        $this->validate($this->orderValidationRules(), $this->orderValidationMessages());

        [$enquiry, $single] = $this->resolveTarget($this->recordType, $this->recordId);

        if (! $enquiry && ! $single) {
            Notification::make()->title('This order no longer exists')->danger()->send();

            return;
        }

        $user = auth()->user();

        try {
            $result = app(UpdateOrderRecords::class)->execute($enquiry, $single, [
                'customer_id' => $this->form['customer_id'],
                'received_through' => $this->form['received_through'],
                'salesperson_id' => $this->form['salesperson_id'] ?: null,
                'order_type' => $this->form['order_type'],
                'service_type' => $this->form['service_type'],
                'payment_method' => $this->form['payment_method'],
                'attachments' => $this->storeAttachments(),
                'pairs' => array_map(fn (array $pair) => [
                    'record_id' => filled($pair['record_id'] ?? null) ? (int) $pair['record_id'] : null,
                    'payload_index' => $pair['payload_index'] ?? null,
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
                    'expected_delivery_date' => $pair['expected_delivery_date'] ?: null,
                    'drop_off_type' => $pair['drop_off_type'] ?: null,
                    'instructions' => $pair['instructions'] ?: null,
                    'items' => array_map(fn (array $item) => [
                        'line_type' => $item['line_type'] ?? null,
                        'catalog_key' => ($item['catalog_key'] ?? null) ?: null,
                        'item_name' => $item['item_name'] ?? '',
                        'uom' => ($item['uom'] ?? null) ?: null,
                        'quantity' => $item['quantity'] ?? 1,
                    ], $pair['items'] ?? []),
                ], array_values($this->pairs)),
            ], $user);

            $result['enquiry']?->releaseLock($user);

            $body = collect([
                $result['updated'] !== [] ? 'Updated '.collect($result['updated'])->pluck('number')->implode(', ') : null,
                $result['created'] !== [] ? 'Added '.collect($result['created'])->pluck('number')->implode(', ') : null,
                $result['cancelled'] !== [] ? 'Removed '.collect($result['cancelled'])->pluck('number')->implode(', ') : null,
            ])->filter()->implode(' · ');

            Notification::make()
                ->title('Order '.$this->orderNumber.' updated')
                ->body($body !== '' ? $body : null)
                ->success()
                ->send();

            if (($result['unpriced'] ?? []) !== []) {
                Notification::make()
                    ->title('Some products have no price yet')
                    ->body(collect($result['unpriced'])->map(fn (array $names, $number) => $number.': '.implode(', ', $names))->implode(' · ').' · no price-list rate for the destination. Add these products with their price under Items & pricing on the order page.')
                    ->warning()
                    ->persistent()
                    ->send();
            }

            $this->redirect($this->returnUrl($result), navigate: false);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** Back to the page the editor was opened from (or the first remaining record when that one was removed). */
    protected function returnUrl(array $result): string
    {
        $openedRecordRemoved = $this->recordType === 'order'
            && collect($result['cancelled'] ?? [])->contains(fn (Quotation $q) => (int) $q->id === $this->recordId);

        if ($openedRecordRemoved && ($first = collect($result['records'] ?? [])->first())) {
            return OrderDetail::urlFor('order', (int) $first->id);
        }

        return OrderDetail::urlFor($this->recordType, $this->recordId);
    }
}

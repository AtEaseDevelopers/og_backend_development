<?php

namespace App\Filament\Pages;

use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\DropOffType;
use App\Enums\OrderType;
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

    /** Customer, billing address, received through and payment term are read-only (a record is no longer editable). */
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

    /**
     * Records the page was opened with (none while the enquiry is not priced): a record created since then
     * makes the save stop instead of cancelling it as removed.
     *
     * @var list<int>
     */
    #[Locked]
    public array $knownRecordIds = [];

    /** Header values when the page opened (read-only fields are restored from it). */
    #[Locked]
    public array $originalForm = [];

    /**
     * Payment terms of the order's records when the page opened: while the order keeps its customer, a new
     * term must be one every record may change to (OrderType::allowedTransitions, as the save enforces).
     *
     * @var list<string>
     */
    #[Locked]
    public array $recordOrderTypes = [];

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
        $this->knownRecordIds = $records->map(fn (Quotation $q) => (int) $q->id)->values()->all();
        $this->recordOrderTypes = $records->map(fn (Quotation $q) => $q->orderType()?->value)->filter()->unique()->values()->all();
        $this->salespersonLocked =$enquiry && $enquiry->salesperson_locked && $enquiry->salesperson_id && ! $user?->isSuperadmin();

        $this->form = $this->formFrom($enquiry, $records);
        $this->originalForm = $this->form;
        // switching the customer away and back brings the order's own billing address back
        $this->billingAddresses = filled($this->form['customer_id']) ? [$this->form['customer_id'] => $this->form['customer_address']] : [];
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

        // optional: an admin entry without a channel stays blank
        $receivedThrough = $enquiry?->received_through
            ?: ($enquiry?->payload['received_through'] ?? null)
            ?: match ($enquiry?->source) {
                PortalEnquiry::SOURCE_PORTAL => 'portal',
                PortalEnquiry::SOURCE_SALESPERSON_LINK => 'salesperson_link',
                PortalEnquiry::SOURCE_WALK_IN => 'walk_in',
                default => '',
            };

        // pickup / store is per block and the payment method is captured with the payment: neither is a header field
        return [
            'customer_id' => (string) ($enquiry?->customer_id ?? $first?->customer_id ?? ''),
            'customer_address' => UpdateOrderRecords::billingAddressFor($enquiry, $records),
            'received_through' => (string) $receivedThrough,
            'salesperson_id' => (string) ($enquiry?->salesperson_id ?? $first?->salesperson_id ?? ''),
            'order_type' => ($first?->orderType() ?? $enquiry?->order_type)?->value ?? OrderType::Cash->value,
        ];
    }

    /**
     * Pickup / store state of a block: a Store block shows its store (an older Store record without one: the
     * order's branch) and keeps the customer's default pickup address ready for a switch to Pickup; a Pickup
     * block shows its pickup location (the saved address it came from, else typed as a new address).
     *
     * @return array{service_type: string, store_branch_id: string, pickup_preset: string, pickup_location: string}
     */
    protected function consignorModeState(?string $customerId, ?string $serviceType, mixed $storeBranchId, ?string $pickupLocation, mixed $fallbackBranchId): array
    {
        if ($serviceType === ServiceType::Store->value) {
            $default = $customerId ? OrderFormOptions::consignorStateForCustomer($customerId) : [];

            return [
                'service_type' => ServiceType::Store->value,
                'store_branch_id' => (string) ($storeBranchId ?: ($fallbackBranchId ?: '')),
                'pickup_preset' => (string) ($default['pickup_location_preset'] ?? ''),
                'pickup_location' => (string) ($default['pickup_location'] ?? ''),
            ];
        }

        return [
            'service_type' => ServiceType::Pickup->value,
            'store_branch_id' => '',
            'pickup_preset' => OrderFormOptions::pickupPresetFor($customerId, $pickupLocation),
            'pickup_location' => trim((string) $pickupLocation),
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
            $mode = $this->consignorModeState($q->customer_id ? (string) $q->customer_id : null, $q->service_type?->value, $q->store_branch_id, $q->pickup_location, $q->branch_id);

            return [
                'record_id' => (int) $q->id,
                'record_number' => $q->number,
                'locked' => $lock !== null,
                'lock_label' => $lock['label'] ?? null,
                'lock_note' => $lock['note'] ?? null,
                'existing_prices' => $existing,
                'payload_index' => null,
                // blank on the record stays blank (no customer name filled in)
                'consignor_name' => (string) ($q->consignor_name ?? ''),
                'service_type' => $mode['service_type'],
                'store_branch_id' => $mode['store_branch_id'],
                'from_location_id' => (string) ($q->from_location_id ?? ''),
                'consignor_pic_name' => (string) ($q->consignor_pic_name ?? ''),
                'consignor_pic_phone' => (string) ($q->consignor_pic_phone ?? ''),
                'pickup_preset' => $mode['pickup_preset'],
                'pickup_location' => $mode['pickup_location'],
                'consignee_name' => (string) ($q->consignee_name ?? ''),
                'to_location_id' => (string) ($q->to_location_id ?? ''),
                'consignee_pic_name' => (string) ($q->consignee_pic_name ?? ''),
                'consignee_pic_phone' => (string) ($q->consignee_pic_phone ?? ''),
                // the saved address it was taken from, else typed as a new address
                'drop_off_preset' => OrderFormOptions::pickupPresetFor($q->customer_id ? (string) $q->customer_id : null, $q->drop_off_location),
                'drop_off_location' => trim((string) ($q->drop_off_location ?? '')),
                'customer_do_number' => (string) ($q->customer_do_number ?? ''),
                'expected_delivery_date' => $q->expected_delivery_date?->toDateString() ?? '',
                'drop_off_type' => DropOffType::tryFrom((string) $type)?->value ?? DropOffType::Other->value,
                'photos' => [],
                'existing_photos' => $this->recordPhotos($q, $enquiry),
                'instructions' => UpdateOrderRecords::instructionsFromNotes($q->notes),
                'items' => $items !== [] ? $items : [$this->itemTemplate()],
            ];
        })->all();
    }

    /**
     * Photos saved with a record: its own files (the order's shared files are listed once, see
     * existingAttachments), named as uploaded where the order form knows the name.
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    protected function recordPhotos(Quotation $q, ?PortalEnquiry $enquiry): array
    {
        $shared = CreateOrderFromEnquiry::attachmentPaths($enquiry?->attachments ?? []);
        $known = collect($enquiry?->payload['destinations'] ?? [])
            ->filter(fn ($destination) => is_array($destination))
            ->flatMap(fn (array $destination) => array_values(array_filter($destination['attachments'] ?? [], 'is_array')))
            ->keyBy('path');

        return $this->photoList(collect(CreateOrderFromEnquiry::attachmentPaths($q->attachments ?? []))
            ->reject(fn (string $path) => in_array($path, $shared, true))
            ->map(fn (string $path) => $known->get($path, ['path' => $path]))
            ->values()
            ->all());
    }

    /**
     * Saved files as the page shows them (thumbnail for an image, file link otherwise).
     *
     * @param  list<array<string, mixed>|string>  $files  {path, name, mime, …} or bare paths
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    protected function photoList(array $files): array
    {
        return collect($files)
            ->map(fn ($file) => is_array($file) ? $file : ['path' => $file])
            ->filter(fn (array $file) => is_string($file['path'] ?? null) && $file['path'] !== '')
            ->map(fn (array $file) => [
                'name' => (string) (($file['name'] ?? null) ?: basename($file['path'])),
                'url' => Storage::disk('public')->url($file['path']),
                'is_image' => str_starts_with((string) ($file['mime'] ?? ''), 'image/')
                    || in_array(strtolower(pathinfo($file['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            ])
            ->values()
            ->all();
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
            $mode = $this->consignorModeState(
                $enquiry->customer_id ? (string) $enquiry->customer_id : null,
                (string) (($d['service_type'] ?? null) ?: ($enquiry->service_type?->value ?? ServiceType::Pickup->value)),
                $d['store_branch_id'] ?? null,
                (string) ($d['pickup_location'] ?? $enquiry->pickup_address ?? ''),
                $enquiry->branch_id,
            );
            $dropOff = trim((string) ($d['drop_off_location'] ?? $p['drop_off_location'] ?? ''));

            return [
                'record_id' => null,
                'record_number' => null,
                'locked' => false,
                'lock_label' => null,
                'lock_note' => null,
                'existing_prices' => [],
                'payload_index' => $i,
                // saved on the order form (blank stays blank); a portal destination has none: starts as the customer
                'consignor_name' => (string) (array_key_exists('consignor_name', $d) ? ($d['consignor_name'] ?? '') : $customerName),
                'service_type' => $mode['service_type'],
                'store_branch_id' => $mode['store_branch_id'],
                'from_location_id' => (string) ($d['from_location_id'] ?? $consignor['from_location_id'] ?? ''),
                'consignor_pic_name' => (string) ($d['consignor_pic_name'] ?? ''),
                'consignor_pic_phone' => (string) ($d['consignor_pic_phone'] ?? ''),
                'pickup_preset' => $mode['pickup_preset'],
                'pickup_location' => $mode['pickup_location'],
                'consignee_name' => (string) ($d['consignee_name'] ?? ''),
                'to_location_id' => (string) ($d['to_location_id'] ?? $p['to_location_id'] ?? ''),
                'consignee_pic_name' => (string) ($d['consignee_pic_name'] ?? ''),
                // a portal destination has the consignee's phone only (until an edit saves the contact number)
                'consignee_pic_phone' => (string) (array_key_exists('consignee_pic_phone', $d) ? ($d['consignee_pic_phone'] ?? '') : ($d['consignee_phone'] ?? '')),
                // the saved address it was taken from, else typed as a new address
                'drop_off_preset' => OrderFormOptions::pickupPresetFor($enquiry->customer_id ? (string) $enquiry->customer_id : null, $dropOff),
                'drop_off_location' => $dropOff,
                'customer_do_number' => (string) ($d['customer_do_number'] ?? $enquiry->customer_do_number ?? ''),
                'expected_delivery_date' => (string) ($d['expected_delivery_date'] ?? $enquiry->preferred_delivery_date?->toDateString() ?? ''),
                'drop_off_type' => DropOffType::tryFrom((string) ($d['drop_off_type'] ?? ''))?->value ?? DropOffType::Other->value,
                'photos' => [],
                // photos saved for this block on the order form (they go to its record when pricing starts)
                'existing_photos' => $this->photoList(array_values(array_filter($d['attachments'] ?? [], 'is_array'))),
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
            'existing_photos' => [],
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

    /**
     * "— Not specified —" only where a save can clear it: a portal / salesperson-link origin shown on the page
     * is not a channel and is never cleared (UpdateOrderRecords::updateEnquiryHeader leaves it alone).
     */
    public function receivedThroughClearable(): bool
    {
        $original = (string) ($this->originalForm['received_through'] ?? '');

        return $original === '' || array_key_exists($original, parent::receivedThroughOptions());
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

    /**
     * Files saved for the whole order (the customer's portal upload, or the order-level upload used before
     * photos were kept per block): every record of the order shows them. A record without an order number
     * has its files on its own block instead.
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function existingAttachments(): array
    {
        if (! $this->enquiryId) {
            return [];
        }

        return $this->photoList(array_values(array_filter(PortalEnquiry::query()->find($this->enquiryId)?->attachments ?? [], 'is_array')));
    }

    /**
     * Every version of this order's records (cancelled ones too): a product's previous records are other orders.
     *
     * @return list<int>
     */
    public function historyExcludedQuotationIds(): array
    {
        $roots = $this->enquiryId
            ? Quotation::query()->where('portal_enquiry_id', $this->enquiryId)->pluck('id')->all()
            : ($this->singleRecordId ? [(int) (Quotation::query()->whereKey($this->singleRecordId)->value('root_quotation_id') ?? $this->singleRecordId)] : []);

        if ($roots === []) {
            return [];
        }

        return Quotation::query()
            ->whereIn('id', $roots)
            ->orWhereIn('root_quotation_id', $roots)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
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

    /**
     * The payment terms of the customer's type. While the order keeps its customer they are limited to the
     * terms every record may change to (Cash stays Cash, COD only to Cash: UpdateOrderRecords enforces the
     * same), and the order's own term is always listed (read-only when the header is locked), so an order
     * whose term the customer type would not offer now can keep it. An order moved to another customer
     * takes one of that customer's terms.
     */
    public function orderTypeOptions(): array
    {
        $options = parent::orderTypeOptions();
        $current = (string) ($this->originalForm['order_type'] ?? ($this->form['order_type'] ?? ''));
        $sameCustomer = (string) ($this->form['customer_id'] ?? '') === (string) ($this->originalForm['customer_id'] ?? '');

        if ($sameCustomer && ! $this->headerLocked && $this->recordOrderTypes !== []) {
            $allowed = null;

            foreach ($this->recordOrderTypes as $value) {
                $transitions = array_map(fn (OrderType $type) => $type->value, OrderType::tryFrom((string) $value)?->allowedTransitions() ?? []);
                $allowed = $allowed === null ? $transitions : array_values(array_intersect($allowed, $transitions));
            }

            $options = array_intersect_key($options, array_flip($allowed ?? []));
        }

        if (($this->headerLocked || $sameCustomer) && $current !== '' && ! array_key_exists($current, $options) && ($type = OrderType::tryFrom($current))) {
            $options[$type->value] = $type->getLabel().' (current)';
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
        $readOnly = ($this->headerLocked && in_array($key, ['customer_id', 'customer_address', 'received_through', 'order_type'], true))
            || ($key === 'salesperson_id' && ($this->salespersonLocked || (blank($value) && ! $this->allowNoSalesperson())));

        if ($readOnly) {
            $this->form[$key] = $this->originalForm[$key] ?? '';

            return;
        }

        parent::updatedForm($value, $key);

        // back on the order's own customer: its own payment term again (not the customer type's default)
        if ($key === 'customer_id' && (string) $value === (string) ($this->originalForm['customer_id'] ?? '') && filled($this->originalForm['order_type'] ?? null)) {
            $this->form['order_type'] = $this->originalForm['order_type'];
        }
    }

    public function updatedPairs($value, string $key): void
    {
        if (preg_match('/^(\d+)\./', $key, $m) && $this->isPairLocked((int) $m[1])) {
            return;
        }

        parent::updatedPairs($value, $key);
    }

    public function clearConsignor(int $index): void
    {
        if (! $this->isPairLocked($index)) {
            parent::clearConsignor($index);
        }
    }

    public function useCustomerAsConsignor(int $index): void
    {
        if (! $this->isPairLocked($index)) {
            parent::useCustomerAsConsignor($index);
        }
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
                    // a rule naming another field of the block (required_if:pairs.*.service_type,…) names this block's
                    $rules['pairs.'.$index.substr($key, strlen('pairs.*'))] = is_string($rule) ? str_replace('pairs.*.', 'pairs.'.$index.'.', $rule) : $rule;
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
            foreach (['customer_id', 'customer_address', 'received_through', 'order_type'] as $key) {
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
            // same block data as Create order (pickup / store, drop-off), plus the record / order-form position and
            // the photos picked for the block (stored now, added to its record / order-form destination); a locked
            // block cannot take photos
            $blocks = array_values($this->pairs);
            $pairs = array_map(fn (array $pair, int $index) => [
                'record_id' => filled($pair['record_id'] ?? null) ? (int) $pair['record_id'] : null,
                'payload_index' => $pair['payload_index'] ?? null,
            ] + $this->pairData($pair, $index) + [
                'attachments' => $this->isPairLocked($index) ? [] : $this->storePairPhotos($pair),
            ], $blocks, array_keys($blocks));

            $result = app(UpdateOrderRecords::class)->execute($enquiry, $single, [
                'customer_id' => $this->form['customer_id'],
                // the order's billing address (every record's customer_address)
                'customer_address' => trim((string) ($this->form['customer_address'] ?? '')),
                'received_through' => (string) ($this->form['received_through'] ?? ''),
                'salesperson_id' => $this->form['salesperson_id'] ?: null,
                'order_type' => $this->form['order_type'],
                // the order form's pickup / store (the first block's); each record takes its own block's
                'service_type' => $pairs[0]['service_type'] ?? null,
                // only a header value the user changed is applied to every record (one set per record is kept)
                'header_changed' => collect(['order_type', 'customer_address'])
                    ->mapWithKeys(fn (string $key) => [$key => trim((string) ($this->form[$key] ?? '')) !== trim((string) ($this->originalForm[$key] ?? ''))])
                    ->all(),
                'known_record_ids' => $this->knownRecordIds,
                // photos are kept per block (pairs.*.attachments); nothing new for the whole order
                'attachments' => [],
                'pairs' => $pairs,
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
                    ->body(collect($result['unpriced'])->map(fn (array $names, $number) => $number.': '.implode(', ', $names))->implode(' · ').' · no price-list rate for the destination. They are kept on the order: enter their price under Items & pricing on the order page.')
                    ->warning()
                    ->persistent()
                    ->send();
            }

            $this->redirect($this->returnUrl($result), navigate: false);
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /**
     * Back to the page the editor was opened from; the first remaining record when that one was removed,
     * or when it was opened from the enquiry of an order that has records (that page would offer pricing again).
     */
    protected function returnUrl(array $result): string
    {
        $openedRecordRemoved = $this->recordType === 'order'
            && collect($result['cancelled'] ?? [])->contains(fn (Quotation $q) => (int) $q->id === $this->recordId);

        if (($openedRecordRemoved || $this->recordType === 'enquiry') && ($first = collect($result['records'] ?? [])->first())) {
            return OrderDetail::urlFor('order', (int) $first->id);
        }

        return OrderDetail::urlFor($this->recordType, $this->recordId);
    }
}

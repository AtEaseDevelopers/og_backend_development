<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\OrderFormOptions;
use App\Support\QuotationMatrix;
use App\Support\QuotationPricingLookup;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Applies the "Edit order" page (the Create order layout in edit mode) to an existing order.
 *
 * One order = one enquiry (order number) with one record per consignor & consignee. Records still
 * in draft / negotiation are updated in place (their lines rebuilt, prices already entered kept),
 * confirmed records are left alone (they change through Revise), new blocks become new records and
 * removed blocks cancel their record (never deleted). An enquiry that has no record yet (not priced)
 * only has its submitted order form (payload) updated.
 */
class UpdateOrderRecords
{
    public const INSTRUCTIONS_PREFIX = 'Customer instructions: ';

    /** Quotation columns edited per consignor & consignee block, with the label used in the change summary. */
    private const PAIR_FIELDS = [
        'consignor_name' => 'consignor',
        'from_location_id' => 'from',
        'consignor_brn' => 'consignor company no.',
        'customer_address' => 'consignor billing address',
        'pickup_location' => 'pickup location',
        'consignee_name' => 'consignee',
        'to_location_id' => 'to',
        'consignee_brn' => 'consignee company no.',
        'consignee_address' => 'consignee billing address',
        'drop_off_location' => 'drop-off location',
        'customer_do_number' => 'DO number',
        'expected_delivery_date' => 'expected delivery',
    ];

    /** Fields whose old / new values are written into the change summary (the others only say "updated"). */
    private const SHORT_FIELDS = ['consignor_name', 'from_location_id', 'consignee_name', 'to_location_id', 'customer_do_number', 'expected_delivery_date', 'customer_id', 'order_type', 'service_type', 'payment_method', 'drop_off_type'];

    public function __construct(
        private CreateOrderFromEnquiry $createOrders,
        private AssignEnquirySalesperson $assign,
        private QuotationMatrix $matrix,
        private QuotationPricingLookup $lookup,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Shared rules (also used by the Edit order page and the order detail page)
    |--------------------------------------------------------------------------
    */

    /**
     * Live order records of an enquiry: latest versions that are not superseded or cancelled, oldest first.
     *
     * @return Collection<int, Quotation>
     */
    public static function recordsFor(PortalEnquiry $enquiry, bool $lock = false): Collection
    {
        $query = Quotation::query()
            ->where('portal_enquiry_id', $enquiry->id)
            ->whereNotIn('status', [QuotationStatus::Superseded->value, QuotationStatus::Cancelled->value])
            ->whereDoesntHave('newerVersion')
            ->with(['destinations', 'lines', 'customer'])
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Why a record cannot be changed on the Edit order page; null when it can.
     *
     * @return array{label: string, note: string}|null
     */
    public static function lockInfo(Quotation $order): ?array
    {
        $status = $order->status instanceof QuotationStatus ? $order->status : QuotationStatus::tryFrom((string) $order->status);

        if (! $status || ! $status->isEditable()) {
            return [
                'label' => 'Locked · '.($status?->getLabel() ?? 'Unknown status'),
                'note' => 'This record is '.strtolower((string) ($status?->getLabel() ?? 'locked')).' and is shown read-only. Confirmed records are changed with the Revise action on the order page.',
            ];
        }

        if (! $order->isLatestVersion()) {
            return ['label' => 'Locked · Older version', 'note' => 'A newer version of this record exists; edit that version instead.'];
        }

        if ($order->destinations->count() > 1) {
            return ['label' => 'Locked · Several destinations', 'note' => 'This record is priced for several destinations. Change it under Items & pricing on the order page.'];
        }

        return null;
    }

    public static function isEditable(Quotation $order): bool
    {
        return static::lockInfo($order) === null;
    }

    /** An editable record that has no proforma, invoice or CSN may be taken off the order (it is cancelled, never deleted). */
    public static function isRemovable(Quotation $order): bool
    {
        return static::isEditable($order)
            && ! $order->proformaInvoice()->exists()
            && ! $order->invoices()->exists()
            && ! $order->consignmentNotes()->exists();
    }

    /** Instructions recovered from the record notes ("Customer instructions: …" line). */
    public static function instructionsFromNotes(?string $notes): string
    {
        foreach (preg_split('/\R/', (string) $notes) ?: [] as $line) {
            if (str_starts_with($line, self::INSTRUCTIONS_PREFIX)) {
                return trim(substr($line, strlen(self::INSTRUCTIONS_PREFIX)));
            }
        }

        return '';
    }

    /** Record notes with the "Customer instructions: …" line replaced (kept in place), added or removed. */
    public static function notesWithInstructions(?string $notes, ?string $instructions): ?string
    {
        $instructions = trim((string) preg_replace('/\s+/', ' ', (string) $instructions));
        $out = [];
        $placed = false;

        foreach (filled($notes) ? (preg_split('/\R/', (string) $notes) ?: []) : [] as $line) {
            if (str_starts_with($line, self::INSTRUCTIONS_PREFIX)) {
                if (! $placed && $instructions !== '') {
                    $out[] = self::INSTRUCTIONS_PREFIX.$instructions;
                }
                $placed = true;

                continue;
            }

            $out[] = $line;
        }

        if (! $placed && $instructions !== '') {
            $out[] = self::INSTRUCTIONS_PREFIX.$instructions;
        }

        $text = trim(implode("\n", $out));

        return $text !== '' ? $text : null;
    }

    /**
     * Index of the enquiry payload destination that belongs to a record (null when none matches):
     * by the record id stored on an earlier edit, else by consignee name, else by creation order.
     *
     * @param  Collection<int, Quotation>  $records
     */
    public static function payloadIndexFor(PortalEnquiry $enquiry, Quotation $order, Collection $records): ?int
    {
        $destinations = array_values(array_filter($enquiry->payload['destinations'] ?? [], 'is_array'));

        if ($destinations === []) {
            return null;
        }

        foreach ($destinations as $index => $destination) {
            if ((int) ($destination['record_id'] ?? 0) === $order->rootId()) {
                return $index;
            }
        }

        $name = mb_strtolower(trim((string) $order->consignee_name));

        if ($name !== '') {
            foreach ($destinations as $index => $destination) {
                if (! isset($destination['record_id']) && mb_strtolower(trim((string) ($destination['consignee_name'] ?? ''))) === $name) {
                    return $index;
                }
            }
        }

        $position = $records->sortBy(fn (Quotation $q) => $q->rootId())->values()->search(fn (Quotation $q) => (int) $q->id === (int) $order->id);

        return $position !== false && isset($destinations[$position]) && ! isset($destinations[$position]['record_id']) ? (int) $position : null;
    }

    /**
     * Raw payload items of one destination (same grouping as CreateOrderFromEnquiry::pairsFromPayload).
     *
     * @return list<array<string, mixed>>
     */
    public static function payloadItemsFor(PortalEnquiry $enquiry, int $index): array
    {
        $payload = $enquiry->payload ?? [];
        $count = count(array_filter($payload['destinations'] ?? [], 'is_array'));

        return collect($payload['items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->filter(function (array $item) use ($index, $count): bool {
                $target = isset($item['destination_index']) && $item['destination_index'] !== '' ? (int) $item['destination_index'] : 0;

                return $target === $index || $target >= $count;
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, \App\Domains\Quotation\Models\QuotationLine> */
    public static function productLines(Quotation $order): Collection
    {
        return $order->lines->reject(fn ($line) => in_array($line->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true))->sortBy('id')->values();
    }

    /**
     * Products of a record as edit-form rows: its lines (existing unit price kept) plus the products saved
     * on an earlier edit that had no price yet (a line needs a price), or, while the record has no line
     * at all, the products the enquiry asked for (no price).
     *
     * @param  Collection<int, Quotation>|null  $records
     * @return list<array{line_type: string, catalog_key: ?string, item_name: string, uom: ?string, quantity: int, unit_price: ?float}>
     */
    public function itemsForRecord(Quotation $order, ?PortalEnquiry $enquiry, ?Collection $records = null): array
    {
        $lines = static::productLines($order);
        $index = $enquiry ? static::payloadIndexFor($enquiry, $order, $records ?? static::recordsFor($enquiry)) : null;
        $payloadItems = $index !== null
            ? collect(static::payloadItemsFor($enquiry, $index))
                ->map(fn (array $item) => $this->itemFromPayload($item) + ['unpriced' => ! empty($item['unpriced'])])
                ->filter(fn (array $item) => $item['item_name'] !== '')
                ->values()
            : collect();

        if ($lines->isEmpty()) {
            return $payloadItems->map(fn (array $item) => Arr::except($item, ['unpriced']))->all();
        }

        if ($order->destinations->count() > 1) {
            $lines = $lines->unique('item_name')->values();
        }

        $items = $lines->map(function ($line): array {
            $catalogKey = $this->lookup->resolveCatalogKey($line->item_name);
            $lineType = $this->lookup->inferLineType($catalogKey, $line->item_name);

            return [
                'line_type' => $lineType,
                'catalog_key' => $catalogKey,
                'item_name' => (string) $line->item_name,
                'uom' => $line->uom,
                'quantity' => $lineType === 'uom' ? max(1, (int) round((float) $line->quantity)) : 1,
                'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
            ];
        })->values();

        $priced = $items->pluck('item_name')->all();

        return $items
            ->concat($payloadItems->filter(fn (array $item) => $item['unpriced'] && ! in_array($item['item_name'], $priced, true))->map(fn (array $item) => Arr::except($item, ['unpriced'])))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item  raw payload item (portal or admin entry)
     * @return array{line_type: string, catalog_key: ?string, item_name: string, uom: ?string, quantity: int, unit_price: null}
     */
    public function itemFromPayload(array $item): array
    {
        $name = trim((string) ($item['item_name'] ?? ''));
        $catalogKey = filled($item['catalog_key'] ?? null) ? (string) $item['catalog_key'] : $this->lookup->resolveCatalogKey($name);
        $lineType = filled($item['line_type'] ?? null) ? (string) $item['line_type'] : $this->lookup->inferLineType($catalogKey, $name);

        return [
            'line_type' => $lineType,
            'catalog_key' => $catalogKey,
            'item_name' => $name,
            'uom' => filled($item['uom'] ?? null) ? strtoupper(trim((string) $item['uom'])) : $this->lookup->resolveUomCode($catalogKey, $name),
            'quantity' => $lineType === 'uom' ? max(1, (int) round((float) ($item['quantity'] ?? 1))) : 1,
            'unit_price' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data  validated Edit order form: customer_id, received_through, salesperson_id, order_type,
     *                                      service_type, payment_method, attachments, pairs[] (record_id, payload_index and the
     *                                      Create order pair keys with items[])
     * @return array{enquiry: ?PortalEnquiry, records: Collection<int, Quotation>, updated: list<Quotation>, created: list<Quotation>, cancelled: list<Quotation>, locked: list<Quotation>}
     */
    public function execute(?PortalEnquiry $enquiry, ?Quotation $singleRecord, array $data, User $actor): array
    {
        if (! $enquiry && ! $singleRecord) {
            throw new InvalidArgumentException('There is no order to update.');
        }

        $pairs = collect($data['pairs'] ?? [])->filter(fn ($pair) => is_array($pair))->values();

        if ($pairs->isEmpty()) {
            throw new InvalidArgumentException('Add at least one consignor & consignee.');
        }

        return DB::transaction(function () use ($enquiry, $singleRecord, $data, $actor, $pairs): array {
            if ($enquiry) {
                $enquiry = PortalEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);

                if (in_array($enquiry->status, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true)) {
                    throw new InvalidArgumentException('This order is '.strtolower((string) $enquiry->status->getLabel()).' and can no longer be edited.');
                }

                if ($enquiry->isLockedByOther($actor)) {
                    throw new InvalidArgumentException(($enquiry->locker?->name ?? 'Another user').' is attending this order. Try again when they finish.');
                }

                $records = static::recordsFor($enquiry, lock: true);

                if ($records->isEmpty()) {
                    return $this->updateEnquiryOnly($enquiry, $pairs, $data, $actor);
                }
            } else {
                $records = Quotation::query()->whereKey($singleRecord->id)->lockForUpdate()->with(['destinations', 'lines', 'customer'])->get();
            }

            return $this->updateRecords($enquiry, $records, $pairs, $data, $actor);
        });
    }

    /**
     * @param  Collection<int, Quotation>  $records
     * @param  Collection<int, array<string, mixed>>  $pairs
     * @return array<string, mixed>
     */
    private function updateRecords(?PortalEnquiry $enquiry, Collection $records, Collection $pairs, array $data, User $actor): array
    {
        $byId = $records->keyBy('id');
        $headerEditable = $records->every(fn (Quotation $q) => static::isEditable($q));
        $seen = [];

        foreach ($pairs as $pair) {
            $id = (int) ($pair['record_id'] ?? 0);

            if ($id === 0) {
                continue;
            }

            if (! $byId->has($id)) {
                throw new InvalidArgumentException('A consignor & consignee block belongs to a record that is no longer part of this order. Reload the page and try again.');
            }

            if (isset($seen[$id])) {
                throw new InvalidArgumentException('The same record appears twice. Reload the page and try again.');
            }

            $seen[$id] = true;
        }

        $removed = $records->reject(fn (Quotation $q) => isset($seen[$q->id]))->values();

        foreach ($removed as $order) {
            if (! static::isRemovable($order)) {
                throw new InvalidArgumentException($order->number.' cannot be removed from the order: it is '.strtolower((string) $order->status->getLabel()).' or already has a proforma, invoice or CSN.');
            }
        }

        if (! $enquiry && $pairs->contains(fn (array $pair) => blank($pair['record_id'] ?? null))) {
            throw new InvalidArgumentException('This record has no order number, so another consignor & consignee cannot be added to it. Create a new order instead.');
        }

        // Payload destination + previous products of every record, read before anything changes
        $oldIndexes = $enquiry
            ? $records->mapWithKeys(fn (Quotation $q) => [$q->id => static::payloadIndexFor($enquiry, $q, $records)])->all()
            : [];
        $oldItems = $records->mapWithKeys(fn (Quotation $q) => [$q->id => $this->itemsForRecord($q, $enquiry, $records)])->all();

        // 1. Order-level fields
        $enquiryChanges = [];
        $singleChanges = [];

        if ($enquiry) {
            // service, payment method, payment term and customer also land on every edited record and are
            // logged there (old → new); only what exists on the enquiry alone is summarised on the enquiry
            $enquiryChanges = array_values(Arr::only($this->updateEnquiryHeader($enquiry, $data, $headerEditable, $actor), ['received_through']));
            $ownerId = $enquiry->salesperson_id ? (int) $enquiry->salesperson_id : null;
        } else {
            $singleChanges = $this->updateSingleRecordSalesperson($records->first(), $data);
            $ownerId = $records->first()->salesperson_id ? (int) $records->first()->salesperson_id : null;
        }

        // 2. Existing records, in the order of the blocks on the page
        $updated = [];
        $locked = [];
        $specs = [];
        $entries = [];

        foreach ($pairs as $position => $pair) {
            $id = (int) ($pair['record_id'] ?? 0);

            if ($id === 0) {
                $specs[$position] = $this->pairSpec($pair, $data);
                $entries[$position] = ['record' => null, 'pair' => $pair, 'old_index' => null];

                continue;
            }

            /** @var Quotation $order */
            $order = $byId->get($id);
            $oldIndex = $oldIndexes[$id] ?? null;

            if (! static::isEditable($order)) {
                $locked[] = $order;
                $entries[$position] = ['record' => $order, 'pair' => null, 'old_index' => $oldIndex];

                continue;
            }

            if ($this->updateRecord($order, $pair, $data, $headerEditable, $ownerId, $actor, $oldItems[$id] ?? [], $singleChanges)) {
                $updated[] = $order;
            }

            $entries[$position] = ['record' => $order, 'pair' => $pair, 'old_index' => $oldIndex];
        }

        // 3. New consignor & consignee blocks become new records under the same order number
        $created = [];

        if ($specs !== []) {
            $new = $this->createOrders->execute($enquiry, $actor, array_values($specs))->values();

            foreach (array_keys($specs) as $k => $position) {
                $entries[$position]['record'] = $new->get($k);
            }

            $created = $new->all();
        }

        // 4. Removed blocks: cancel the record (never delete it)
        $cancelled = [];

        foreach ($removed as $order) {
            $this->cancelRecord($order, $actor);
            $cancelled[] = $order;
        }

        if ($enquiry) {
            if ($cancelled !== [] && collect($cancelled)->contains(fn (Quotation $q) => (int) $q->id === (int) $enquiry->quotation_id)) {
                $enquiry->update(['quotation_id' => collect($entries)->sortKeys()->pluck('record')->filter()->first()?->id]);
            }

            $this->syncPayload($enquiry, $entries, $data);
        }

        $attached = $this->appendAttachments($enquiry, $enquiry ? null : $records->first(), $data['attachments'] ?? []);

        // Order-level summary (record-level changes are in each record's status log)
        if ($enquiry) {
            $summary = array_merge(
                $enquiryChanges,
                $created !== [] ? ['added '.collect($created)->pluck('number')->implode(', ')] : [],
                $cancelled !== [] ? ['removed '.collect($cancelled)->pluck('number')->implode(', ')] : [],
                $attached > 0 ? [$attached.' '.Str::plural('attachment', $attached).' added'] : [],
            );

            if ($summary !== []) {
                activity()
                    ->performedOn($enquiry)
                    ->causedBy($actor)
                    ->withProperties(['changes' => $summary])
                    ->log('Order details edited by '.$actor->name.' · '.implode('; ', $summary));
            }
        } elseif ($attached > 0 && $updated === []) {
            $order = $records->first();
            QuotationStatusLog::query()->create([
                'quotation_id' => $order->id,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'user_id' => $actor->id,
                'remarks' => 'Order details edited by '.$actor->name.' · '.$attached.' '.Str::plural('attachment', $attached).' added',
            ]);
        }

        $affected = collect($entries)->sortKeys()->pluck('record')->filter()->values();

        return [
            'enquiry' => $enquiry?->fresh(),
            'records' => $affected->map(fn (Quotation $q) => $q->fresh())->filter()->values(),
            'updated' => $updated,
            'created' => $created,
            'cancelled' => $cancelled,
            'locked' => $locked,
        ];
    }

    /**
     * Enquiry not priced yet (no record): the submitted order form is edited instead.
     *
     * @param  Collection<int, array<string, mixed>>  $pairs
     * @return array<string, mixed>
     */
    private function updateEnquiryOnly(PortalEnquiry $enquiry, Collection $pairs, array $data, User $actor): array
    {
        if ($pairs->contains(fn (array $pair) => filled($pair['record_id'] ?? null))) {
            throw new InvalidArgumentException('Order records were created for this order in the meantime. Reload the page and try again.');
        }

        $before = $this->formSnapshot($enquiry);
        $changes = array_values($this->updateEnquiryHeader($enquiry, $data, true, $actor));
        $first = $pairs->first();

        $enquiry->update([
            'customer_do_number' => $this->clean($first['customer_do_number'] ?? null),
            'pickup_address' => $this->clean($first['pickup_location'] ?? null),
            'preferred_delivery_date' => $this->clean($first['expected_delivery_date'] ?? null),
            'special_requirements' => $pairs->map(fn (array $pair) => trim((string) ($pair['instructions'] ?? '')))->filter()->implode("\n") ?: null,
        ]);

        $entries = $pairs->map(fn (array $pair) => [
            'record' => null,
            'pair' => $pair,
            'old_index' => isset($pair['payload_index']) && $pair['payload_index'] !== '' && $pair['payload_index'] !== null ? (int) $pair['payload_index'] : null,
        ])->all();

        $this->syncPayload($enquiry, $entries, $data);
        $enquiry->refresh();

        if ($before !== $this->formSnapshot($enquiry)) {
            $count = $pairs->count();
            $changes[] = 'consignor & consignee details and products updated ('.$count.' '.Str::plural('block', $count).')';
        }

        $attached = $this->appendAttachments($enquiry, null, $data['attachments'] ?? []);

        if ($attached > 0) {
            $changes[] = $attached.' '.Str::plural('attachment', $attached).' added';
        }

        if ($changes !== []) {
            activity()
                ->performedOn($enquiry)
                ->causedBy($actor)
                ->withProperties(['changes' => $changes])
                ->log('Order details edited by '.$actor->name.' · '.implode('; ', $changes));
        }

        return [
            'enquiry' => $enquiry->fresh(),
            'records' => collect(),
            'updated' => [],
            'created' => [],
            'cancelled' => [],
            'locked' => [],
        ];
    }

    /**
     * What the user sees of a submitted order form, to tell whether an edit changed it.
     *
     * @return array<int, mixed>
     */
    private function formSnapshot(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        // portal destinations carry no DO number / delivery date of their own: they inherit the enquiry's
        $fallback = ['customer_do_number' => $enquiry->customer_do_number, 'expected_delivery_date' => $enquiry->preferred_delivery_date?->toDateString()];
        $keys = ['consignee_name', 'address', 'city', 'drop_off_type', 'customer_do_number', 'expected_delivery_date'];

        return [
            collect($payload['destinations'] ?? [])->filter(fn ($d) => is_array($d))
                ->map(fn (array $d) => collect($keys)->mapWithKeys(fn (string $k) => [$k => trim((string) ($d[$k] ?? $fallback[$k] ?? ''))])->all())
                ->values()->all(),
            collect($payload['items'] ?? [])->filter(fn ($i) => is_array($i))
                ->map(fn (array $i) => [trim((string) ($i['item_name'] ?? '')), (int) round((float) ($i['quantity'] ?? 1)), (int) ($i['destination_index'] ?? 0)])
                ->values()->all(),
            trim((string) $enquiry->special_requirements),
            trim((string) $enquiry->pickup_address),
            trim((string) $enquiry->customer_do_number),
            $enquiry->preferred_delivery_date?->toDateString(),
        ];
    }

    /**
     * Customer, payment term and received through change only while every record is editable;
     * service and payment method always; the salesperson through AssignEnquirySalesperson (all records follow).
     *
     * @return array<string, string> change summary keyed by field
     */
    private function updateEnquiryHeader(PortalEnquiry $enquiry, array $data, bool $headerEditable, User $actor): array
    {
        $updates = [];
        $changes = [];

        $service = ServiceType::tryFrom((string) ($data['service_type'] ?? ''));
        if ($service && $enquiry->service_type !== $service) {
            $updates['service_type'] = $service->value;
            $changes['service_type'] = 'service → '.$service->getLabel();
        }

        $method = (string) ($data['payment_method'] ?? '');
        if ($method !== '' && (string) $enquiry->payment_method !== $method) {
            $updates['payment_method'] = $method;
            $changes['payment_method'] = 'payment method → '.(PaymentMethod::tryFrom($method)?->getLabel() ?? $method);
        }

        if ($headerEditable) {
            $type = OrderType::tryFrom((string) ($data['order_type'] ?? ''));
            if ($type && $enquiry->order_type !== $type) {
                $updates['order_type'] = $type->value;
                $changes['order_type'] = 'payment term → '.$type->getLabel();
            }

            $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
            if ($customerId && $customerId !== (int) $enquiry->customer_id) {
                $updates['customer_id'] = $customerId;
                $changes['customer_id'] = 'customer → '.(Customer::query()->whereKey($customerId)->value('company_name') ?? '#'.$customerId);
            }

            $received = (string) ($data['received_through'] ?? '');
            if ($received !== '' && $received !== (string) $enquiry->received_through && array_key_exists($received, CreateAdminOrder::RECEIVED_THROUGH)) {
                $payload = $enquiry->payload ?? [];
                $payload['received_through'] = $received;
                $updates['received_through'] = $received;
                $updates['payload'] = $payload;

                if (in_array($enquiry->source, [PortalEnquiry::SOURCE_ADMIN, PortalEnquiry::SOURCE_WALK_IN], true)) {
                    $updates['source'] = $received === 'walk_in' ? PortalEnquiry::SOURCE_WALK_IN : PortalEnquiry::SOURCE_ADMIN;
                }

                $changes['received_through'] = 'received through → '.CreateAdminOrder::RECEIVED_THROUGH[$received];
            }
        }

        if ($updates !== []) {
            $enquiry->update($updates);
        }

        $salespersonId = filled($data['salesperson_id'] ?? null) ? (int) $data['salesperson_id'] : null;

        if ($salespersonId && $salespersonId !== (int) $enquiry->salesperson_id) {
            $salesperson = User::query()->findOrFail($salespersonId);
            // logs its own "Salesperson assigned" activity on the enquiry
            $this->assign->execute($enquiry, $salesperson, $actor, lock: false, source: $enquiry->source);
            $enquiry->refresh();
        }

        return $changes;
    }

    /**
     * Record without an enquiry (older admin entry): its salesperson is set directly.
     *
     * @return list<string>
     */
    private function updateSingleRecordSalesperson(Quotation $order, array $data): array
    {
        $salespersonId = filled($data['salesperson_id'] ?? null) ? (int) $data['salesperson_id'] : null;

        if (! $salespersonId || $salespersonId === (int) $order->salesperson_id) {
            return [];
        }

        $salesperson = User::query()->findOrFail($salespersonId);
        $order->update(['salesperson_id' => $salesperson->id, 'sa_location_id' => $salesperson->sa_location_id ?? $order->sa_location_id]);

        return ['salesperson → '.$salesperson->name];
    }

    /**
     * Updates one editable record from its consignor & consignee block and rebuilds its lines.
     *
     * @param  list<array<string, mixed>>  $oldItems  products before the edit (for the change summary)
     * @param  list<string>  $extraChanges
     * @return bool whether anything changed
     */
    private function updateRecord(Quotation $order, array $pair, array $data, bool $headerEditable, ?int $ownerId, User $actor, array $oldItems, array $extraChanges = []): bool
    {
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $toName = $toLocationId ? Location::query()->whereKey($toLocationId)->value('name') : null;
        $consignee = trim((string) ($pair['consignee_name'] ?? ''));
        $column = (string) ($toName ?: ($consignee !== '' ? $consignee : 'Destination'));
        $serviceType = ServiceType::tryFrom((string) ($data['service_type'] ?? ''))?->value ?? $order->service_type?->value;
        $dropOffType = DropOffType::tryFrom((string) ($pair['drop_off_type'] ?? ''))?->value ?? DropOffType::Other->value;

        $fields = [];
        foreach (array_keys(self::PAIR_FIELDS) as $key) {
            $fields[$key] = $this->clean($pair[$key] ?? null);
        }

        $fields['from_location_id'] = filled($pair['from_location_id'] ?? null) ? (int) $pair['from_location_id'] : null;
        $fields['to_location_id'] = $toLocationId;
        $fields['consignee_name'] = $consignee;
        $fields['service_type'] = $serviceType;
        $fields['destination_types'] = [['column' => $column, 'drop_off_type' => $dropOffType, 'service_type' => $serviceType]];
        $fields['notes'] = static::notesWithInstructions($order->notes, $pair['instructions'] ?? null);

        if (filled($data['payment_method'] ?? null)) {
            $fields['payment_method'] = (string) $data['payment_method'];
        }

        if ($headerEditable) {
            if ($type = OrderType::tryFrom((string) ($data['order_type'] ?? ''))) {
                $fields['order_type'] = $type->value;
            }

            if (filled($data['customer_id'] ?? null) && (int) $data['customer_id'] !== (int) $order->customer_id) {
                $fields['customer_id'] = (int) $data['customer_id'];
                $consignor = OrderFormOptions::consignorStateForCustomer((string) $data['customer_id'], withPickupPreset: false);
                $fields += Arr::only($consignor, ['attention', 'terms_of_payment']);
            }
        }

        $changes = array_merge($extraChanges, $this->fieldChanges($order, $fields, $dropOffType));

        // Products: keep the unit price already on this record (admin-negotiated prices are never lost);
        // a product new to the record takes the price-list rate, and only once a salesperson owns the order.
        $customerId = (int) ($fields['customer_id'] ?? $order->customer_id) ?: null;
        $productLines = static::productLines($order);
        $existingPrices = $productLines->groupBy('item_name')->map(fn (Collection $lines) => $lines->first()->unit_price !== null ? (float) $lines->first()->unit_price : null);
        $rows = [];
        $newItems = [];

        foreach ($pair['items'] ?? [] as $item) {
            $name = trim((string) ($item['item_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $catalogKey = filled($item['catalog_key'] ?? null) ? (string) $item['catalog_key'] : $this->lookup->resolveCatalogKey($name);
            $lineType = filled($item['line_type'] ?? null) ? (string) $item['line_type'] : $this->lookup->inferLineType($catalogKey, $name);
            $quantity = $lineType === 'uom' ? max(1, (int) round((float) ($item['quantity'] ?? 1))) : 1;

            if ($existingPrices->has($name) && $existingPrices->get($name) !== null) {
                $price = $existingPrices->get($name);
            } else {
                $price = $lineType === 'lorry' || ! $ownerId
                    ? null
                    : ($this->lookup->lookupForCustomer($customerId, $name, $column, (float) $quantity)['price'] ?? null);
            }

            $rows[] = [
                'line_type' => $lineType,
                'item_name' => $name,
                'catalog_key' => $catalogKey,
                'quantity' => $quantity,
                'prices' => [$column => $price !== null ? round((float) $price, 2) : null],
            ];
            $newItems[] = ['item_name' => $name, 'quantity' => $quantity];
        }

        $changes = array_merge($changes, $this->itemChanges($oldItems, $newItems));

        $currentSignature = $productLines
            ->map(fn ($line) => $line->item_name.'|'.max(1, (int) round((float) $line->quantity)).'|'.number_format((float) $line->unit_price, 2, '.', ''))
            ->sort()->values()->all();
        $newSignature = collect($rows)
            ->filter(fn (array $row) => $row['prices'][$column] !== null)
            ->map(fn (array $row) => $row['item_name'].'|'.$row['quantity'].'|'.number_format((float) $row['prices'][$column], 2, '.', ''))
            ->sort()->values()->all();

        if ($changes === [] && $currentSignature === $newSignature) {
            return false;
        }

        if ($changes === []) {
            $changes[] = 'product prices filled from the price list';
        }

        // Pickup / drop-off / other charges stay as they are
        $chargeRows = $order->lines
            ->filter(fn ($line) => in_array($line->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true))
            ->map(fn ($line) => [
                'line_type' => 'item',
                'item_name' => $line->item_name,
                'catalog_key' => null,
                'quantity' => 1,
                'prices' => [$column => (float) $line->unit_price],
            ])
            ->values()
            ->all();

        $before = Arr::only($order->getAttributes(), array_keys($fields));
        $oldTotal = (float) $order->total_amount;

        // The change is recorded below in one readable entry instead of the automatic "updated" activity
        activity()->withoutLogs(fn () => $order->update($fields));

        $this->matrix->sync($order, [$column], array_merge($rows, $chargeRows));
        $order->refresh();

        if (abs($oldTotal - (float) $order->total_amount) >= 0.005) {
            $changes[] = 'total RM '.number_format($oldTotal, 2).' → RM '.number_format((float) $order->total_amount, 2);
        }

        activity()
            ->performedOn($order)
            ->causedBy($actor)
            ->withProperties(['old' => $before, 'attributes' => Arr::only($order->getAttributes(), array_keys($fields)), 'changes' => $changes])
            ->log('Order details edited');

        QuotationStatusLog::query()->create([
            'quotation_id' => $order->id,
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'user_id' => $actor->id,
            'remarks' => Str::limit('Order details edited by '.$actor->name.' · '.implode('; ', $changes), 1000),
        ]);

        return true;
    }

    /** @return list<string> */
    private function fieldChanges(Quotation $order, array $fields, string $dropOffType): array
    {
        $labels = self::PAIR_FIELDS + [
            'customer_id' => 'customer',
            'order_type' => 'payment term',
            'service_type' => 'service',
            'payment_method' => 'payment method',
        ];

        $changes = [];

        foreach ($fields as $key => $new) {
            if (! isset($labels[$key])) {
                continue;
            }

            $old = $order->getAttribute($key);

            if ($this->comparable($key, $old) === $this->comparable($key, $new)) {
                continue;
            }

            $changes[] = in_array($key, self::SHORT_FIELDS, true)
                ? $labels[$key].' '.$this->display($key, $old).' → '.$this->display($key, $new)
                : $labels[$key].' updated';
        }

        $oldDropOff = collect($order->destination_types ?? [])->first()['drop_off_type'] ?? $order->destinations->sortBy('sequence')->first()?->drop_off_type;
        $oldDropOff = $oldDropOff instanceof BackedEnum ? $oldDropOff->value : (string) $oldDropOff;

        if ($oldDropOff !== $dropOffType) {
            $changes[] = 'drop-off type '.$this->display('drop_off_type', $oldDropOff).' → '.$this->display('drop_off_type', $dropOffType);
        }

        if (static::instructionsFromNotes($order->notes) !== static::instructionsFromNotes($fields['notes'] ?? null)) {
            $changes[] = 'instructions updated';
        }

        return $changes;
    }

    /**
     * @param  list<array<string, mixed>>  $old
     * @param  list<array<string, mixed>>  $new
     * @return list<string>
     */
    private function itemChanges(array $old, array $new): array
    {
        $sum = fn (array $items) => collect($items)
            ->groupBy(fn (array $item) => (string) $item['item_name'])
            ->map(fn (Collection $group) => (int) $group->sum(fn (array $item) => (int) $item['quantity']));

        $before = $sum($old);
        $after = $sum($new);
        $changes = [];

        foreach ($after as $name => $qty) {
            if (! $before->has($name)) {
                $changes[] = 'added '.$name.' ×'.$qty;
            } elseif ($before->get($name) !== $qty) {
                $changes[] = $name.' ×'.$before->get($name).' → ×'.$qty;
            }
        }

        foreach ($before as $name => $qty) {
            if (! $after->has($name)) {
                $changes[] = 'removed '.$name;
            }
        }

        return $changes;
    }

    private function comparable(string $key, mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($key === 'expected_delivery_date') {
            if ($value instanceof CarbonInterface) {
                return $value->toDateString();
            }

            try {
                return filled($value) ? Carbon::parse((string) $value)->toDateString() : '';
            } catch (Throwable) {
                return (string) $value;
            }
        }

        if (in_array($key, ['from_location_id', 'to_location_id', 'customer_id'], true)) {
            return filled($value) ? (string) (int) $value : '';
        }

        return trim((string) $value);
    }

    private function display(string $key, mixed $value): string
    {
        $value = $value instanceof BackedEnum ? $value->value : $value;

        if (blank($value)) {
            return '—';
        }

        $text = match ($key) {
            'from_location_id', 'to_location_id' => Location::query()->whereKey($value)->value('name') ?? (string) $value,
            'customer_id' => Customer::query()->whereKey($value)->value('company_name') ?? (string) $value,
            'order_type' => OrderType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'service_type' => ServiceType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'payment_method' => PaymentMethod::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'drop_off_type' => DropOffType::tryFrom((string) $value)?->getLabel() ?? ucfirst((string) $value),
            'expected_delivery_date' => $this->comparable($key, $value),
            default => (string) $value,
        };

        return Str::limit($text, 40);
    }

    private function cancelRecord(Quotation $order, User $actor): void
    {
        $from = $order->status->value;
        $reason = 'Removed from order by '.$actor->name;

        $order->update([
            'status' => QuotationStatus::Cancelled,
            'closed_at' => now(),
            'closed_reason' => $reason,
        ]);

        QuotationStatusLog::query()->create([
            'quotation_id' => $order->id,
            'from_status' => $from,
            'to_status' => QuotationStatus::Cancelled->value,
            'user_id' => $actor->id,
            'remarks' => $reason,
        ]);
    }

    /**
     * Pair spec for CreateOrderFromEnquiry (same keys the Create order page passes through CreateAdminOrder).
     *
     * @return array<string, mixed>
     */
    private function pairSpec(array $pair, array $data): array
    {
        return [
            'consignor_name' => $this->clean($pair['consignor_name'] ?? null),
            'consignee_name' => $this->clean($pair['consignee_name'] ?? null),
            'consignee_brn' => $this->clean($pair['consignee_brn'] ?? null),
            'consignee_address' => $this->clean($pair['consignee_address'] ?? null),
            'drop_off_location' => $this->clean($pair['drop_off_location'] ?? null),
            'to_location_id' => $this->clean($pair['to_location_id'] ?? null),
            'from_location_id' => $this->clean($pair['from_location_id'] ?? null),
            'consignor_brn' => $this->clean($pair['consignor_brn'] ?? null),
            'customer_address' => $this->clean($pair['customer_address'] ?? null),
            'pickup_location' => $this->clean($pair['pickup_location'] ?? null),
            'drop_off_type' => $this->clean($pair['drop_off_type'] ?? null),
            'service_type' => $this->clean($data['service_type'] ?? null),
            'customer_do_number' => $this->clean($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $this->clean($pair['expected_delivery_date'] ?? null),
            'instructions' => $this->clean($pair['instructions'] ?? null),
            'items' => collect($pair['items'] ?? [])
                ->filter(fn ($item) => is_array($item) && filled($item['item_name'] ?? null))
                ->map(fn (array $item) => [
                    'item_name' => trim((string) $item['item_name']),
                    'uom' => $this->clean($item['uom'] ?? null),
                    'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                    'catalog_key' => $this->clean($item['catalog_key'] ?? null),
                    'line_type' => $this->clean($item['line_type'] ?? null),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Rewrites the enquiry's submitted order form (payload destinations / items) so it matches the
     * edited blocks. Locked records keep what the payload had. Each destination remembers its record.
     *
     * @param  array<int, array{record: ?Quotation, pair: ?array, old_index: ?int}>  $entries  keyed by block position
     */
    private function syncPayload(PortalEnquiry $enquiry, array $entries, array $data): void
    {
        ksort($entries);
        $payload = $enquiry->payload ?? [];
        $oldDestinations = array_values(array_filter($payload['destinations'] ?? [], 'is_array'));
        $destinations = [];
        $items = [];

        foreach (array_values($entries) as $index => $entry) {
            $record = $entry['record'];
            $pair = $entry['pair'];
            $oldIndex = $entry['old_index'];
            $base = $oldIndex !== null && isset($oldDestinations[$oldIndex]) ? $oldDestinations[$oldIndex] : [];
            $oldItems = $oldIndex !== null ? static::payloadItemsFor($enquiry, $oldIndex) : [];

            if ($pair === null) {
                $destination = $base !== [] ? $base : ($record ? $this->destinationFromRecord($record) : []);
                $ownItems = $oldItems !== [] || ! $record
                    ? $oldItems
                    : array_map(fn (array $item) => Arr::except($item, ['unit_price']), $this->itemsForRecord($record, null));
            } else {
                $destination = array_merge($base, $this->destinationFromPair($pair, $data, $base));
                $ownItems = collect($pair['items'] ?? [])
                    ->filter(fn ($item) => is_array($item) && filled($item['item_name'] ?? null))
                    ->map(function (array $item) use ($oldItems): array {
                        $name = trim((string) $item['item_name']);
                        $previous = collect($oldItems)->first(fn (array $old) => trim((string) ($old['item_name'] ?? '')) === $name) ?? [];

                        return array_merge(Arr::except($previous, ['destination_index']), [
                            'item_name' => $name,
                            'uom' => $this->clean($item['uom'] ?? null),
                            'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                            'catalog_key' => $this->clean($item['catalog_key'] ?? null),
                            'line_type' => $this->clean($item['line_type'] ?? null),
                        ]);
                    })
                    ->values()
                    ->all();
            }

            if ($record) {
                $destination['record_id'] = $record->rootId();
            }

            $destinations[] = $destination;

            foreach ($ownItems as $item) {
                $item['destination_index'] = $index;
                $items[] = $item;
            }
        }

        $payload['destinations'] = $destinations;
        $payload['items'] = $items;

        $enquiry->update(['payload' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $base  the destination as it was in the payload
     * @return array<string, mixed>
     */
    private function destinationFromPair(array $pair, array $data, array $base = []): array
    {
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $toName = $toLocationId ? Location::query()->whereKey($toLocationId)->value('name') : null;

        $destination = [
            'consignee_name' => $this->clean($pair['consignee_name'] ?? null),
            'address' => $this->clean($pair['consignee_address'] ?? null) ?? $this->clean($pair['drop_off_location'] ?? null),
            'drop_off_type' => $this->clean($pair['drop_off_type'] ?? null),
            'service_type' => $this->clean($data['service_type'] ?? null),
            'customer_do_number' => $this->clean($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $this->clean($pair['expected_delivery_date'] ?? null),
            // kept so the edit page shows the same values again
            'consignor_name' => $this->clean($pair['consignor_name'] ?? null),
            'from_location_id' => $this->clean($pair['from_location_id'] ?? null),
            'consignor_brn' => $this->clean($pair['consignor_brn'] ?? null),
            'customer_address' => $this->clean($pair['customer_address'] ?? null),
            'pickup_location' => $this->clean($pair['pickup_location'] ?? null),
            'to_location_id' => $toLocationId,
            'consignee_brn' => $this->clean($pair['consignee_brn'] ?? null),
            'consignee_address' => $this->clean($pair['consignee_address'] ?? null),
            'drop_off_location' => $this->clean($pair['drop_off_location'] ?? null),
            'instructions' => $this->clean($pair['instructions'] ?? null),
        ];

        // The price-list location travels as the city, so pricing resolves the same rates later
        // (a submitted city that already names that location, e.g. "Johor Bahru" for Johor, is kept)
        $baseCity = mb_strtolower(trim((string) ($base['city'] ?? '')));

        if ($toName && ($baseCity === '' || ! str_contains($baseCity, mb_strtolower($toName)))) {
            $destination['city'] = $toName;
        }

        return $destination;
    }

    /** @return array<string, mixed> */
    private function destinationFromRecord(Quotation $order): array
    {
        $type = collect($order->destination_types ?? [])->first()['drop_off_type'] ?? null;

        return array_filter([
            'consignee_name' => $order->consignee_name,
            'address' => $order->consignee_address ?: $order->drop_off_location,
            'city' => $order->to_location_id ? Location::query()->whereKey($order->to_location_id)->value('name') : null,
            'drop_off_type' => $type instanceof BackedEnum ? $type->value : $type,
            'service_type' => $order->service_type?->value,
            'customer_do_number' => $order->customer_do_number,
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Adds newly uploaded files to the enquiry (shown for every record of the order) or, without an
     * enquiry, to the record itself.
     *
     * @param  list<array<string, mixed>>  $files
     */
    private function appendAttachments(?PortalEnquiry $enquiry, ?Quotation $order, array $files): int
    {
        $files = array_values(array_filter($files, fn ($file) => is_array($file) && filled($file['path'] ?? null)));

        if ($files === []) {
            return 0;
        }

        if ($enquiry) {
            $enquiry->update(['attachments' => array_values(array_merge($enquiry->attachments ?? [], $files))]);
        } elseif ($order) {
            $order->update(['attachments' => array_values(array_merge($order->attachments ?? [], array_column($files, 'path')))]);
        }

        return count($files);
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}

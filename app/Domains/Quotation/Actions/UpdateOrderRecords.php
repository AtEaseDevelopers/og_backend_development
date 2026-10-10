<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Store;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\OrderFormOptions;
use App\Support\QuotationMatrix;
use App\Support\QuotationPricingLookup;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

    /**
     * Quotation columns edited per consignor & consignee block, with the label used in the change summary.
     * The billing address (customer_address) is one order-level field (see updateRecord); the consignee's
     * own address (consignee_address) and the company numbers (consignor_brn / consignee_brn) are no longer
     * entered and are left as they are on the record (the consignee address only while the drop-off location
     * is unchanged). Pickup / store (service_type) is set per block too.
     */
    private const PAIR_FIELDS = [
        'consignor_name' => 'consignor',
        'store_id' => 'store',
        'from_location_id' => 'from',
        'consignor_pic_name' => 'consignor PIC',
        'consignor_pic_phone' => 'consignor contact no.',
        'pickup_location' => 'pickup location',
        'consignee_name' => 'consignee',
        'to_location_id' => 'to',
        'consignee_pic_name' => 'consignee PIC',
        'consignee_pic_phone' => 'consignee contact no.',
        'drop_off_location' => 'drop-off location',
        'customer_do_number' => 'DO number',
        'expected_delivery_date' => 'expected delivery',
    ];

    /** Fields whose old / new values are written into the change summary (the others only say "updated"). */
    private const SHORT_FIELDS = ['consignor_name', 'store_id', 'from_location_id', 'consignor_pic_name', 'consignor_pic_phone', 'consignee_name', 'to_location_id', 'consignee_pic_name', 'consignee_pic_phone', 'customer_do_number', 'expected_delivery_date', 'customer_id', 'order_type', 'service_type', 'drop_off_type'];

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
        $query = static::recordsQuery($enquiry)->with(['destinations', 'lines', 'customer']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Query of the live order records of an enquiry (same rules as recordsFor, nothing eager loaded).
     *
     * @return Builder<Quotation>
     */
    public static function recordsQuery(PortalEnquiry $enquiry): Builder
    {
        return Quotation::query()
            ->where('portal_enquiry_id', $enquiry->id)
            ->whereNotIn('status', [QuotationStatus::Superseded->value, QuotationStatus::Cancelled->value])
            ->whereDoesntHave('newerVersion')
            ->orderBy('id');
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

    /**
     * The order's billing address as the Edit order page shows it: the first record's customer_address
     * (an order not priced yet: the one saved on its order form), else the customer's saved address.
     *
     * @param  Collection<int, Quotation>  $records
     */
    public static function billingAddressFor(?PortalEnquiry $enquiry, Collection $records): string
    {
        $first = $records->first();
        $stored = $first
            ? $first->customer_address
            : (collect($enquiry?->payload['destinations'] ?? [])->first(fn ($d) => is_array($d))['customer_address'] ?? null);
        $customer = $enquiry?->customer ?? $first?->customer;

        return trim((string) (filled($stored) ? $stored : ($customer?->address ?? '')));
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
     * Index of the enquiry payload destination that belongs to a record (null when none matches).
     *
     * @param  Collection<int, Quotation>  $records
     */
    public static function payloadIndexFor(PortalEnquiry $enquiry, Quotation $order, Collection $records): ?int
    {
        if (! $records->contains(fn (Quotation $q) => (int) $q->id === (int) $order->id)) {
            $records = $records->push($order);
        }

        return static::payloadIndexMap($enquiry, $records)[(int) $order->id] ?? null;
    }

    /**
     * Maps every record of the order to its own payload destination in one pass, so two records never
     * share a destination (e.g. two consignees both named "JB Warehouse"):
     * 1. the record id stored on an earlier edit;
     * 2. consignee name among destinations not used yet — ties broken by the destination city matching
     *    the record's TO location, then by the record's position;
     * 3. creation order (position) among destinations not used yet.
     *
     * @param  Collection<int, Quotation>  $records
     * @return array<int, int|null> record id → destination index
     */
    public static function payloadIndexMap(PortalEnquiry $enquiry, Collection $records): array
    {
        $destinations = array_values(array_filter($enquiry->payload['destinations'] ?? [], 'is_array'));
        $ordered = $records->sortBy(fn (Quotation $q) => $q->rootId())->values();
        $map = $ordered->mapWithKeys(fn (Quotation $q) => [(int) $q->id => null])->all();

        if ($destinations === []) {
            return $map;
        }

        $used = [];
        $norm = fn ($v) => mb_strtolower(trim((string) $v));
        // a closure with $used by reference (an arrow function would copy the still-empty array)
        $free = function (int $i) use (&$used, $destinations): bool {
            return ! isset($used[$i]) && ! isset($destinations[$i]['record_id']);
        };

        // 1. stored record id
        foreach ($ordered as $q) {
            foreach ($destinations as $i => $destination) {
                if (! isset($used[$i]) && (int) ($destination['record_id'] ?? 0) === $q->rootId()) {
                    $map[(int) $q->id] = $i;
                    $used[$i] = true;
                    break;
                }
            }
        }

        // 2. consignee name (ties: TO location ↔ destination city, then position)
        foreach ($ordered as $position => $q) {
            if ($map[(int) $q->id] !== null || $norm($q->consignee_name) === '') {
                continue;
            }

            $candidates = collect($destinations)
                ->filter(fn (array $d, int $i) => $free($i) && $norm($d['consignee_name'] ?? '') === $norm($q->consignee_name))
                ->keys();

            if ($candidates->isEmpty()) {
                continue;
            }

            $toName = $q->to_location_id
                ? ($q->relationLoaded('toLocation') ? $q->toLocation?->name : Location::query()->whereKey($q->to_location_id)->value('name'))
                : null;

            $pick = ($toName ? $candidates->first(fn (int $i) => $norm($destinations[$i]['city'] ?? '') === $norm($toName)) : null)
                ?? ($candidates->contains($position) ? $position : null)
                ?? $candidates->first();

            $map[(int) $q->id] = (int) $pick;
            $used[(int) $pick] = true;
        }

        // 3. creation order
        foreach ($ordered as $position => $q) {
            if ($map[(int) $q->id] === null && isset($destinations[$position]) && $free($position)) {
                $map[(int) $q->id] = $position;
                $used[$position] = true;
            }
        }

        return $map;
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
     * Products of a record as edit-form rows: its lines (existing unit price kept; a line without a price yet
     * has none) plus the products of its order form that never became a line (see payloadItemsWithoutLine).
     *
     * @param  Collection<int, Quotation>|null  $records
     * @return list<array{line_type: string, catalog_key: ?string, item_name: string, uom: ?string, quantity: int, unit_price: ?float}>
     */
    public function itemsForRecord(Quotation $order, ?PortalEnquiry $enquiry, ?Collection $records = null): array
    {
        $lines = static::productLines($order);
        $missing = collect($enquiry ? static::payloadItemsWithoutLine($order, $enquiry, $records) : [])
            ->map(fn (array $item) => $this->itemFromPayload($item))
            ->filter(fn (array $item) => $item['item_name'] !== '')
            ->values();

        if ($lines->isEmpty()) {
            return $missing->all();
        }

        if ($order->destinations->count() > 1) {
            $lines = $lines->unique('item_name')->values();
        }

        $items = $lines->map(function ($line) use ($order): array {
            $catalogKey = $this->lookup->resolveCatalogKey($line->item_name);
            $lineType = $this->lookup->inferLineType($catalogKey, $line->item_name);

            return [
                'line_type' => $lineType,
                'catalog_key' => $catalogKey,
                'item_name' => (string) $line->item_name,
                'uom' => $line->uom,
                'quantity' => max(1, (int) round((float) $line->quantity)),
                'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
                'attachments' => array_values(array_filter($order->item_attachments[(string) $line->item_name] ?? [], 'is_array')),
            ];
        })->values();

        return $items->concat($missing)->values()->all();
    }

    /**
     * Products on the order form (enquiry payload) of a record that have no line on it, raw payload items.
     *
     * Every product entered is kept as a line, priced or not (quotation_lines.unit_price NULL = no price yet).
     * Records saved before that lost the products that had no price (only priced products became lines), so
     * the order form stands in for them — mapped to the record by payloadIndexMap:
     * - a record without any product line: every product of its order-form destination;
     * - a first version whose lines were never rewritten since it was created (no pricing save / edit since):
     *   the products missing from its lines;
     * - products an earlier Edit order kept on the form as "unpriced".
     * A product removed later under Items & pricing (lines rewritten) is not brought back.
     *
     * @param  Collection<int, Quotation>|null  $records  live records of the order (loaded when not given)
     * @return list<array<string, mixed>>
     */
    public static function payloadItemsWithoutLine(Quotation $order, PortalEnquiry $enquiry, ?Collection $records = null): array
    {
        $index = static::payloadIndexFor($enquiry, $order, $records ?? static::recordsFor($enquiry));

        if ($index === null) {
            return [];
        }

        $lines = static::productLines($order);
        $names = $lines->map(fn ($line) => trim((string) $line->item_name))->all();
        $createdAt = $order->created_at;
        $untouched = $lines->isEmpty() || (
            $order->rootId() === (int) $order->id
            && $createdAt !== null
            && $lines->every(fn ($line) => $line->created_at !== null && $line->created_at->lte($createdAt->copy()->addSeconds(5)))
        );

        return collect(static::payloadItemsFor($enquiry, $index))
            ->filter(function (array $item) use ($names, $untouched): bool {
                $name = trim((string) ($item['item_name'] ?? ''));

                return $name !== '' && ! in_array($name, $names, true) && ($untouched || ! empty($item['unpriced']));
            })
            ->values()
            ->all();
    }

    /**
     * After the Items & pricing form is saved its lines are the record's products: products the order form
     * still flags "unpriced" (kept by an earlier Edit order) are no longer offered on top of them, so a product
     * removed there stays removed.
     */
    public static function settleUnpricedPayloadItems(Quotation $order): void
    {
        $enquiry = $order->portal_enquiry_id ? PortalEnquiry::query()->find($order->portal_enquiry_id) : null;

        if (! $enquiry) {
            return;
        }

        $index = static::payloadIndexFor($enquiry, $order, static::recordsFor($enquiry));
        $payload = $enquiry->payload ?? [];
        $count = count(array_filter($payload['destinations'] ?? [], 'is_array'));
        $changed = false;

        if ($index === null || ! is_array($payload['items'] ?? null)) {
            return;
        }

        foreach ($payload['items'] as $key => $item) {
            $target = is_array($item) && isset($item['destination_index']) && $item['destination_index'] !== '' ? (int) $item['destination_index'] : 0;

            if (is_array($item) && ! empty($item['unpriced']) && ($target === $index || $target >= $count)) {
                unset($payload['items'][$key]['unpriced']);
                $changed = true;
            }
        }

        if ($changed) {
            activity()->withoutLogs(fn () => $enquiry->update(['payload' => $payload]));
        }
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
            'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
            'unit_price' => null,
            'attachments' => array_values(array_filter($item['attachments'] ?? [], 'is_array')),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data  validated Edit order form: customer_id, customer_address (the order's billing address),
     *                                      received_through (optional), salesperson_id, order_type, service_type (the order form's:
     *                                      the first block's pickup / store), attachments, pairs[] (record_id, payload_index and the
     *                                      Create order pair keys — service_type, store_branch_id, PIC fields … — with items[]);
     *                                      known_record_ids (records the page was opened with — a live record outside it means the
     *                                      order changed since); header_changed (order_type / customer_address => whether the user
     *                                      changed it). The payment method is not edited (captured when the payment is recorded).
     * @return array{enquiry: ?PortalEnquiry, records: Collection<int, Quotation>, updated: list<Quotation>, created: list<Quotation>, cancelled: list<Quotation>, locked: list<Quotation>, unpriced: array<string, list<string>>}
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
        // A record created since the page was opened (another tab, "Provide pricing") is not on the page:
        // saving would cancel it as "removed" and duplicate it from the stale blocks.
        if (array_key_exists('known_record_ids', $data)) {
            $known = array_map('intval', (array) $data['known_record_ids']);

            if ($records->contains(fn (Quotation $q) => ! in_array((int) $q->id, $known, true))) {
                throw new InvalidArgumentException('This order changed since you opened it. Reload the page and try again.');
            }
        }

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
            ? static::payloadIndexMap($enquiry, $records)
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

            // a product without a price yet is kept as a line without a unit price (with or without an order form)
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

        $unpriced = [];

        if ($enquiry) {
            if ($cancelled !== [] && collect($cancelled)->contains(fn (Quotation $q) => (int) $q->id === (int) $enquiry->quotation_id)) {
                $enquiry->update(['quotation_id' => collect($entries)->sortKeys()->pluck('record')->filter()->first()?->id]);
            }

            $unpriced = $this->syncPayload($enquiry, $entries, $data);
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
            // products kept on the order form without a line because there is no price yet (per record number)
            'unpriced' => $ownerId ? $unpriced : [],
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
        $billingBefore = static::billingAddressFor($enquiry, collect());
        $changes = array_values($this->updateEnquiryHeader($enquiry, $data, true, $actor));
        $first = $pairs->first();

        $enquiry->update([
            'customer_do_number' => $this->clean($first['customer_do_number'] ?? null),
            'pickup_address' => $this->clean($first['pickup_location'] ?? null),
            // the expected delivery date is a free-text remark: only a real date fills the enquiry's date
            'preferred_delivery_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($first['expected_delivery_date'] ?? '')) ? $first['expected_delivery_date'] : $enquiry->preferred_delivery_date?->toDateString(),
            'special_requirements' => $pairs->map(fn (array $pair) => trim((string) ($pair['instructions'] ?? '')))->filter()->implode("\n") ?: null,
        ]);

        $entries = $pairs->map(fn (array $pair) => [
            'record' => null,
            'pair' => $pair,
            'old_index' => isset($pair['payload_index']) && $pair['payload_index'] !== '' && $pair['payload_index'] !== null ? (int) $pair['payload_index'] : null,
        ])->all();

        $this->syncPayload($enquiry, $entries, $data);
        $enquiry->refresh();

        // one entry for the order's billing address (saved on every destination of the order form)
        if (static::billingAddressFor($enquiry, collect()) !== $billingBefore) {
            $changes[] = 'billing address updated';
        }

        if ($before !== $this->formSnapshot($enquiry)) {
            $count = $pairs->count();
            $changes[] = 'consignor & consignee details and products updated ('.$count.' '.Str::plural('block', $count).')';
        }

        $attached = $this->appendAttachments($enquiry, null, $data['attachments'] ?? []);

        if ($attached > 0) {
            $changes[] = $attached.' '.Str::plural('attachment', $attached).' added';
        }

        // photos of the blocks are kept on their destination (syncPayload) until the records are created
        $photos = (int) $pairs->sum(fn (array $pair) => count(array_filter($pair['attachments'] ?? [], fn ($file) => is_array($file) && filled($file['path'] ?? null))));

        if ($photos > 0) {
            $changes[] = $photos.' '.Str::plural('photo', $photos).' added';
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
            'unpriced' => [],
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
        // portal destinations carry no DO number / delivery date / pickup-or-store of their own: they inherit the enquiry's
        $fallback = ['customer_do_number' => $enquiry->customer_do_number, 'expected_delivery_date' => $enquiry->preferred_delivery_date?->toDateString(), 'drop_off_type' => DropOffType::Other->value, 'service_type' => $enquiry->service_type?->value ?? ServiceType::Pickup->value];
        $keys = ['consignee_name', 'address', 'city', 'drop_off_type', 'customer_do_number', 'expected_delivery_date', 'service_type', 'store_branch_id', 'store_id', 'consignor_pic_name', 'consignor_pic_phone', 'consignee_pic_name', 'consignee_pic_phone', 'attention', 'customer_pic_phone'];

        return [
            collect($payload['destinations'] ?? [])->filter(fn ($d) => is_array($d))
                // a portal destination's consignee phone is what the page shows as the consignee contact number
                ->map(fn (array $d) => collect($keys)->mapWithKeys(fn (string $k) => [$k => trim((string) ($d[$k] ?? ($k === 'consignee_pic_phone' ? ($d['consignee_phone'] ?? null) : null) ?? $fallback[$k] ?? ''))])->all())
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
     * Customer, payment term and received through change only while every record is editable; the order
     * form's pickup / store (the first block's) always; the salesperson through AssignEnquirySalesperson (all
     * records follow). The payment method is not edited here (it is captured when the payment is recorded).
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
            $changes['service_type'] = 'pickup / store → '.$service->getLabel();
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

            // optional: a known channel is set, a blank one clears it (the order then shows as a plain admin entry);
            // a portal / salesperson-link origin shown on the page is not a channel and is left alone
            $received = (string) ($data['received_through'] ?? '');
            $current = (string) ($enquiry->received_through ?: ($enquiry->payload['received_through'] ?? ''));
            $known = $received !== '' && array_key_exists($received, CreateAdminOrder::RECEIVED_THROUGH);

            if (array_key_exists('received_through', $data) && ($known || $received === '') && ($received !== $current || $received !== (string) $enquiry->received_through)) {
                $payload = $enquiry->payload ?? [];
                $payload['received_through'] = $known ? $received : null;
                $updates['received_through'] = $known ? $received : null;
                $updates['payload'] = $payload;

                if (in_array($enquiry->source, [PortalEnquiry::SOURCE_ADMIN, PortalEnquiry::SOURCE_WALK_IN], true)) {
                    $updates['source'] = $received === 'walk_in' ? PortalEnquiry::SOURCE_WALK_IN : PortalEnquiry::SOURCE_ADMIN;
                }

                if ($received !== $current) {
                    $changes['received_through'] = $known ? 'received through → '.CreateAdminOrder::RECEIVED_THROUGH[$received] : 'received through cleared';
                }
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
     * Pickup / store (service_type, with the store branch) comes from the block. The payment term comes from
     * the order header only when the user changed it there (or the record has none yet), so a value set on one
     * record (e.g. "Change payment term") is kept. The payment method is left as it is (captured with the payment).
     *
     * Every product of the block becomes a line; one without a price yet (no price-list rate, no salesperson,
     * a lorry type) is a line without a unit price until it is priced under Items & pricing.
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
        $existingCity = trim((string) $order->destinations->sortBy('sequence')->first()?->city);
        $existingCity = $existingCity !== '' && mb_strtolower($existingCity) !== mb_strtolower(trim((string) $order->consignee_name)) ? $existingCity : null;
        $column = (string) ($toName ?: ($existingCity ?: ($consignee !== '' ? $consignee : 'Destination')));
        // pickup / store of this block; an older caller without one: the header value it changed, else the record's
        $headerService = ServiceType::tryFrom((string) ($data['service_type'] ?? ''))?->value;
        $serviceType = ServiceType::tryFrom((string) ($pair['service_type'] ?? ''))?->value
            ?? ($this->headerChanged($data, 'service_type')
                ? ($headerService ?? $order->service_type?->value)
                : ($order->service_type?->value ?? $headerService));
        $dropOffType = DropOffType::tryFrom((string) ($pair['drop_off_type'] ?? ''))?->value ?? DropOffType::Other->value;

        $fields = [];
        foreach (array_keys(self::PAIR_FIELDS) as $key) {
            $fields[$key] = $this->clean($pair[$key] ?? null);
        }

        // the consignee's own address (no longer on the page) belongs to the drop-off it came with: a changed
        // drop-off location clears it, so it cannot stand in for the new drop-off later (CSN fallback)
        if (array_key_exists('drop_off_location', $fields) && ! array_key_exists('consignee_address', $pair)
            && filled($order->consignee_address) && ! $this->sameText($fields['drop_off_location'], $order->drop_off_location)) {
            $fields['consignee_address'] = null;
        }

        $fields['from_location_id'] = filled($pair['from_location_id'] ?? null) ? (int) $pair['from_location_id'] : null;
        $fields['to_location_id'] = $toLocationId;
        // a consignee left blank stays blank (the price column / destination keeps its own name)
        $fields['consignee_name'] = $consignee !== '' ? $consignee : null;
        $fields['service_type'] = $serviceType;
        // the store (and its branch) belongs to a Store record only
        $fields['store_id'] = $serviceType === ServiceType::Store->value && filled($pair['store_id'] ?? null) ? (int) $pair['store_id'] : null;
        $fields['store_branch_id'] = $serviceType === ServiceType::Store->value && filled($pair['store_branch_id'] ?? null) ? (int) $pair['store_branch_id'] : null;
        $fields['destination_types'] = [['column' => $column, 'drop_off_type' => $dropOffType, 'service_type' => $serviceType]];
        $fields['notes'] = static::notesWithInstructions($order->notes, $pair['instructions'] ?? null);

        if ($headerEditable) {
            $type = OrderType::tryFrom((string) ($data['order_type'] ?? ''));
            $current = $order->orderType();
            $newCustomerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
            // a still-editable record (draft / negotiation) that moves to another customer takes the payment term
            // chosen for that customer (the page offers only that customer type's terms), whatever it had before
            $customerChanged = $newCustomerId && $newCustomerId !== (int) $order->customer_id;

            if ($customerChanged && ($type ?? $current) === OrderType::Term && ! Customer::query()->whereKey($newCustomerId)->value('is_credit')) {
                throw new InvalidArgumentException($order->number.': Credit / Term needs a credit customer. Choose Cash or COD for this customer.');
            }

            if ($type && $current === null) {
                $fields['order_type'] = $type->value;
            } elseif ($type && $current !== $type && ($customerChanged || $this->headerChanged($data, 'order_type'))) {
                // same customer: the rules of "Change payment term" (ChangeOrderType): any until the customer
                // confirms or a payment is recorded, fixed after that
                if (! $customerChanged && ! $order->canChangeOrderTypeTo($type)) {
                    throw new InvalidArgumentException(sprintf(
                        '%s: payment term %s cannot be changed to %s (allowed: %s).',
                        $order->number,
                        $current->getLabel(),
                        $type->getLabel(),
                        collect($order->allowedOrderTypes())->map->getLabel()->implode(', '),
                    ));
                }

                $fields['order_type'] = $type->value;
            }

            if (filled($data['customer_id'] ?? null) && (int) $data['customer_id'] !== (int) $order->customer_id) {
                $fields['customer_id'] = (int) $data['customer_id'];
                $consignor = OrderFormOptions::consignorStateForCustomer((string) $data['customer_id'], withPickupPreset: false);
                $fields += Arr::only($consignor, ['attention', 'terms_of_payment']);
            }

            // the order's billing address (one header field) lands on every record once the user changes it;
            // blank = the customer's saved address (documents fall back to it)
            if (array_key_exists('customer_address', $data) && $this->headerChanged($data, 'customer_address')) {
                $fields['customer_address'] = $this->clean($data['customer_address']);
            }

            // the customer's person in charge and contact number: the same, once the user changes them
            if (array_key_exists('customer_pic_name', $data) && $this->headerChanged($data, 'customer_pic_name')) {
                $fields['attention'] = $this->clean($data['customer_pic_name']);
            }

            if (array_key_exists('customer_pic_phone', $data) && $this->headerChanged($data, 'customer_pic_phone')) {
                $fields['customer_pic_phone'] = $this->clean($data['customer_pic_phone']);
            }
        }

        // photos uploaded for this block are added to the record's own files (none is removed)
        $newPhotos = array_values(array_diff(CreateOrderFromEnquiry::attachmentPaths($pair['attachments'] ?? []), CreateOrderFromEnquiry::attachmentPaths($order->attachments ?? [])));

        if ($newPhotos !== []) {
            $fields['attachments'] = array_values(array_merge(CreateOrderFromEnquiry::attachmentPaths($order->attachments ?? []), $newPhotos));
            $extraChanges[] = count($newPhotos).' '.Str::plural('photo', count($newPhotos)).' added';
        }

        // photos uploaded per product are added to the record's product photos (none is removed)
        $itemPhotos = CreateOrderFromEnquiry::itemAttachments($pair['items'] ?? []);

        if ($itemPhotos !== []) {
            $saved = is_array($order->item_attachments) ? $order->item_attachments : [];
            $added = 0;

            foreach ($itemPhotos as $name => $files) {
                $known = CreateOrderFromEnquiry::attachmentPaths($saved[$name] ?? []);
                $new = array_values(array_filter($files, fn (array $file) => ! in_array($file['path'], $known, true)));
                $added += count($new);
                $saved[$name] = array_values(array_merge(array_values(array_filter($saved[$name] ?? [], 'is_array')), $new));
            }

            if ($added > 0) {
                $fields['item_attachments'] = $saved;
                $extraChanges[] = $added.' product '.Str::plural('photo', $added).' added';
            }
        }

        $changes = array_merge($extraChanges, $this->fieldChanges($order, $fields, $dropOffType));

        // Products: keep the unit price already on this record (admin-negotiated prices are never lost);
        // a product new to the record takes the price-list rate, and only once a salesperson owns the order.
        $customerId = (int) ($fields['customer_id'] ?? $order->customer_id) ?: null;
        $productLines = static::productLines($order);
        $original = $productLines->keyBy('item_name');
        $rows = [];
        $newItems = [];

        foreach ($pair['items'] ?? [] as $item) {
            $name = trim((string) ($item['item_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $catalogKey = filled($item['catalog_key'] ?? null) ? (string) $item['catalog_key'] : $this->lookup->resolveCatalogKey($name);
            $lineType = filled($item['line_type'] ?? null) ? (string) $item['line_type'] : $this->lookup->inferLineType($catalogKey, $name);
            $existing = $original->get($name);
            // whole units (the page only lets UOM products change quantity; others keep the loaded one)
            $quantity = max(1, (int) round((float) ($item['quantity'] ?? 1)));

            if ($existing && $existing->unit_price !== null) {
                $price = (float) $existing->unit_price;
            } else {
                $price = $lineType === 'lorry' || ! $ownerId
                    ? null
                    : ($this->lookup->lookupForCustomer($customerId, $name, $column, (float) $quantity)['price'] ?? null);

                // no special / price-list rate: the price keyed in on the page
                if ($price === null && $ownerId && is_numeric($item['unit_price'] ?? null)) {
                    $price = (float) $item['unit_price'];
                }
            }

            // no price yet: still a line (without a unit price), priced later under Items & pricing
            $rows[] = [
                // QuotationMatrix keeps the quantity only for UOM rows
                'line_type' => $lineType !== 'uom' && $quantity !== 1 ? 'uom' : $lineType,
                'item_name' => $name,
                'catalog_key' => $catalogKey,
                'quantity' => $quantity,
                'prices' => [$column => $price !== null ? round($price, 2) : null],
            ];
            $newItems[] = ['item_name' => $name, 'quantity' => $quantity];
        }

        $changes = array_merge($changes, $this->itemChanges($oldItems, $newItems));

        // product|qty|price ("-" = no price yet) of the lines now and after the edit
        $priceKey = fn ($price) => $price !== null ? number_format((float) $price, 2, '.', '') : '-';
        $currentSignature = $productLines
            ->map(fn ($line) => $line->item_name.'|'.max(1, (int) round((float) $line->quantity)).'|'.$priceKey($line->unit_price))
            ->sort()->values()->all();
        $newSignature = collect($rows)
            ->map(fn (array $row) => $row['item_name'].'|'.$row['quantity'].'|'.$priceKey($row['prices'][$column]))
            ->sort()->values()->all();
        $destination = $order->destinations->first();
        $columnChanged = $order->destinations->count() !== 1 || trim((string) $destination?->consignee_name) !== $column;
        $linesChanged = $currentSignature !== $newSignature;

        if ($changes === [] && ! $linesChanged && ! $columnChanged) {
            return false;
        }

        if ($changes === []) {
            $changes[] = match (true) {
                ! $linesChanged => 'destination renamed to '.$column,
                // the same products: some got a price-list rate, or a product without a price became a line again
                collect($rows)->contains(fn (array $row) => $row['prices'][$column] !== null && ! in_array($row['item_name'].'|'.$row['quantity'].'|'.$priceKey($row['prices'][$column]), $currentSignature, true)) => 'product prices filled from the price list',
                default => 'products without a price kept on the record',
            };
        }

        $before = Arr::only($order->getAttributes(), array_keys($fields));
        $oldTotal = (float) $order->total_amount;

        // The change is recorded below in one readable entry instead of the automatic "updated" activity
        activity()->withoutLogs(fn () => $order->update($fields));

        if ($linesChanged || $columnChanged) {
            // Pickup / drop-off / other charges stay as they are
            $chargeRows = $order->lines
                ->filter(fn ($line) => in_array($line->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true))
                ->map(fn ($line) => [
                    'line_type' => (float) $line->quantity !== 1.0 ? 'uom' : 'item',
                    'item_name' => $line->item_name,
                    'catalog_key' => null,
                    'quantity' => (float) $line->quantity ?: 1,
                    'prices' => [$column => (float) $line->unit_price],
                ])
                ->values()
                ->all();
            $previousUom = $order->lines->filter(fn ($line) => filled($line->uom))->mapWithKeys(fn ($line) => [$line->item_name => $line->uom]);

            activity()->withoutLogs(fn () => $this->matrix->sync($order, [$column], array_merge($rows, $chargeRows)));

            // QuotationMatrix only knows the unit of UOM products; keep the unit other lines already had
            foreach ($order->lines()->whereNull('uom')->get() as $line) {
                if ($previousUom->has($line->item_name)) {
                    $line->update(['uom' => $previousUom->get($line->item_name)]);
                }
            }
        }

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

    /** Whether the user changed an order header field on the page (assumed changed when the page does not say). */
    private function headerChanged(array $data, string $key): bool
    {
        return ! is_array($data['header_changed'] ?? null) || ! empty($data['header_changed'][$key]);
    }

    /** @return list<string> */
    private function fieldChanges(Quotation $order, array $fields, string $dropOffType): array
    {
        $labels = self::PAIR_FIELDS + [
            'customer_id' => 'customer',
            'customer_address' => 'billing address',
            'attention' => 'customer PIC',
            'customer_pic_phone' => 'customer contact no.',
            'order_type' => 'payment term',
            'service_type' => 'pickup / store',
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


        if (in_array($key, ['from_location_id', 'to_location_id', 'customer_id', 'store_branch_id', 'store_id'], true)) {
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
            'store_branch_id' => Branch::query()->whereKey($value)->value('name') ?? (string) $value,
            'store_id' => Store::query()->whereKey($value)->value('name') ?? (string) $value,
            'order_type' => OrderType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'service_type' => ServiceType::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'drop_off_type' => DropOffType::tryFrom((string) $value)?->getLabel() ?? ucfirst((string) $value),
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
     * Explicit: a blank DO number / instructions stay blank instead of taking the order-level (block 1) values.
     *
     * @return array<string, mixed>
     */
    private function pairSpec(array $pair, array $data): array
    {
        return [
            'explicit' => true,
            'consignor_name' => $this->clean($pair['consignor_name'] ?? null),
            'consignee_name' => $this->clean($pair['consignee_name'] ?? null),
            'consignee_address' => $this->clean($pair['consignee_address'] ?? null),
            'drop_off_location' => $this->clean($pair['drop_off_location'] ?? null),
            'to_location_id' => $this->clean($pair['to_location_id'] ?? null),
            'from_location_id' => $this->clean($pair['from_location_id'] ?? null),
            // the order's billing address (blank: CreateOrderFromEnquiry falls back to the customer's saved address)
            'customer_address' => $this->billingAddress($pair, $data),
            // the customer's person in charge and contact number of the page (else the customer's default)
            ...$this->customerPic($data),
            'pickup_location' => $this->clean($pair['pickup_location'] ?? null),
            'drop_off_type' => $this->clean($pair['drop_off_type'] ?? null),
            // Pickup or Store (with the branch) per block; an older caller: the header value
            'service_type' => $this->clean($pair['service_type'] ?? null) ?? $this->clean($data['service_type'] ?? null),
            'store_branch_id' => $this->clean($pair['store_branch_id'] ?? null),
            'store_id' => $this->clean($pair['store_id'] ?? null),
            'consignor_pic_name' => $this->clean($pair['consignor_pic_name'] ?? null),
            'consignor_pic_phone' => $this->clean($pair['consignor_pic_phone'] ?? null),
            'consignee_pic_name' => $this->clean($pair['consignee_pic_name'] ?? null),
            'consignee_pic_phone' => $this->clean($pair['consignee_pic_phone'] ?? null),
            'customer_do_number' => $this->clean($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $this->clean($pair['expected_delivery_date'] ?? null),
            'instructions' => $this->clean($pair['instructions'] ?? null),
            // photos uploaded for the new block (its record's own files)
            'attachments' => array_values(array_filter($pair['attachments'] ?? [], 'is_array')),
            'items' => collect($pair['items'] ?? [])
                ->filter(fn ($item) => is_array($item) && filled($item['item_name'] ?? null))
                ->map(fn (array $item) => [
                    'item_name' => trim((string) $item['item_name']),
                    'uom' => $this->clean($item['uom'] ?? null),
                    'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                    'catalog_key' => $this->clean($item['catalog_key'] ?? null),
                    'line_type' => $this->clean($item['line_type'] ?? null),
                    'unit_price' => is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : null,
                    'attachments' => array_values(array_filter($item['attachments'] ?? [], 'is_array')),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Rewrites the enquiry's submitted order form (payload destinations / items) so it matches the
     * edited blocks. Locked records keep what the payload had. Each destination remembers its record.
     *
     * Every product of a record is a line (without a unit price while it has none); a product that still has no
     * line is flagged "unpriced" here. Photos uploaded for a block are added to its destination's attachments.
     *
     * @param  array<int, array{record: ?Quotation, pair: ?array, old_index: ?int}>  $entries  keyed by block position
     * @return array<string, list<string>> product names without a price yet per record number
     */
    private function syncPayload(PortalEnquiry $enquiry, array $entries, array $data): array
    {
        ksort($entries);
        $payload = $enquiry->payload ?? [];
        $oldDestinations = array_values(array_filter($payload['destinations'] ?? [], 'is_array'));
        $destinations = [];
        $items = [];
        $unpriced = [];

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
                $destination = array_merge($base, $this->destinationFromPair($pair, $data, $base, $record));

                // photos uploaded for the block are added to the ones it already had
                $known = CreateOrderFromEnquiry::attachmentPaths($base['attachments'] ?? []);
                $photos = array_values(array_filter($pair['attachments'] ?? [], fn ($file) => is_array($file) && filled($file['path'] ?? null) && ! in_array($file['path'], $known, true)));

                if ($photos !== []) {
                    $destination['attachments'] = array_values(array_merge(array_filter($base['attachments'] ?? [], 'is_array'), $photos));
                }

                $lines = $record ? $record->lines()->get(['item_name', 'unit_price']) : null;
                $lineNames = $lines?->pluck('item_name')->all();
                // products kept as a line without a price yet (reported like the ones without a line)
                $noPrice = $lines ? $lines->whereNull('unit_price')->pluck('item_name')->all() : [];
                $ownItems = collect($pair['items'] ?? [])
                    ->filter(fn ($item) => is_array($item) && filled($item['item_name'] ?? null))
                    ->map(function (array $item) use ($oldItems, $lineNames): array {
                        $name = trim((string) $item['item_name']);
                        $previous = collect($oldItems)->first(fn (array $old) => trim((string) ($old['item_name'] ?? '')) === $name) ?? [];
                        $row = array_merge(Arr::except($previous, ['destination_index', 'unpriced']), [
                            'item_name' => $name,
                            'uom' => $this->clean($item['uom'] ?? null),
                            'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                            'catalog_key' => $this->clean($item['catalog_key'] ?? null),
                            'line_type' => $this->clean($item['line_type'] ?? null),
                        ]);

                        // photos of the product: the ones it had plus the ones just uploaded
                        $known = CreateOrderFromEnquiry::attachmentPaths($previous['attachments'] ?? []);
                        $new = array_values(array_filter($item['attachments'] ?? [], fn ($file) => is_array($file) && filled($file['path'] ?? null) && ! in_array($file['path'], $known, true)));

                        if ($new !== []) {
                            $row['attachments'] = array_values(array_merge(array_values(array_filter($previous['attachments'] ?? [], 'is_array')), $new));
                        }

                        if ($lineNames !== null && ! in_array($name, $lineNames, true)) {
                            $row['unpriced'] = true;
                        }

                        return $row;
                    })
                    ->values()
                    ->all();

                $names = collect($ownItems)
                    ->filter(fn (array $item) => ! empty($item['unpriced']) || in_array($item['item_name'], $noPrice, true))
                    ->pluck('item_name')->unique()->values()->all();

                if ($record && $names !== []) {
                    $unpriced[(string) $record->number] = $names;
                }
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

        return $unpriced;
    }

    /**
     * @param  array<string, mixed>  $base  the destination as it was in the payload
     * @param  Quotation|null  $record  the block's order record (none while the order is not priced)
     * @return array<string, mixed>
     */
    private function destinationFromPair(array $pair, array $data, array $base = [], ?Quotation $record = null): array
    {
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $toName = $toLocationId ? Location::query()->whereKey($toLocationId)->value('name') : null;

        // The consignee's own address is no longer entered on the page: the one on file is kept (the record's; an
        // order form not priced yet: the one saved by an earlier edit, else the submitted address). Without one the
        // drop-off location stands in as the destination address.
        $consigneeAddress = match (true) {
            array_key_exists('consignee_address', $pair) => $this->clean($pair['consignee_address']),
            $record !== null => $this->clean($record->consignee_address),
            array_key_exists('consignee_address', $base) => $this->clean($base['consignee_address']),
            default => $this->clean($base['address'] ?? null),
        };

        // ...but only while the drop-off location is the one the page loaded (the order form's own, else the
        // submitted consignee / address line): a changed drop-off makes it stale and becomes the address instead
        if ($record === null && ! array_key_exists('consignee_address', $pair)
            && ! $this->sameText($pair['drop_off_location'] ?? null, CreateOrderFromEnquiry::dropOffLocationFor($base))) {
            $consigneeAddress = null;
        }

        // pickup / store of this block (an older caller: the header value); the store only on a Store block
        $serviceType = $this->clean($pair['service_type'] ?? null) ?? $this->clean($data['service_type'] ?? null);

        $destination = [
            'consignee_name' => $this->clean($pair['consignee_name'] ?? null),
            'address' => $consigneeAddress ?? $this->clean($pair['drop_off_location'] ?? null),
            'drop_off_type' => $this->clean($pair['drop_off_type'] ?? null),
            'service_type' => $serviceType,
            'customer_do_number' => $this->clean($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $this->clean($pair['expected_delivery_date'] ?? null),
            // kept so the edit page shows the same values again (company numbers are no longer entered:
            // an older destination keeps its own through the merge with $base)
            'consignor_name' => $this->clean($pair['consignor_name'] ?? null),
            'store_branch_id' => $serviceType === ServiceType::Store->value ? $this->clean($pair['store_branch_id'] ?? null) : null,
            'store_id' => $serviceType === ServiceType::Store->value ? $this->clean($pair['store_id'] ?? null) : null,
            'from_location_id' => $this->clean($pair['from_location_id'] ?? null),
            'consignor_pic_name' => $this->clean($pair['consignor_pic_name'] ?? null),
            'consignor_pic_phone' => $this->clean($pair['consignor_pic_phone'] ?? null),
            'customer_address' => $this->billingAddress($pair, $data),
            ...$this->customerPic($data),
            'pickup_location' => $this->clean($pair['pickup_location'] ?? null),
            'to_location_id' => $toLocationId,
            'consignee_pic_name' => $this->clean($pair['consignee_pic_name'] ?? null),
            'consignee_pic_phone' => $this->clean($pair['consignee_pic_phone'] ?? null),
            'consignee_address' => $consigneeAddress,
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
            'expected_delivery_date' => $order->expected_delivery_date,
            'store_branch_id' => $order->store_branch_id,
            'store_id' => $order->store_id,
            'consignor_pic_name' => $order->consignor_pic_name,
            'consignor_pic_phone' => $order->consignor_pic_phone,
            'consignee_pic_name' => $order->consignee_pic_name,
            'consignee_pic_phone' => $order->consignee_pic_phone,
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

    /**
     * The customer's person in charge and contact number of the Edit order page, for a new record or order-form
     * destination (nothing when the page did not send them: the customer's default applies).
     *
     * @return array<string, mixed>
     */
    private function customerPic(array $data): array
    {
        $pic = [];

        if (array_key_exists('customer_pic_name', $data)) {
            $pic['attention'] = $this->clean($data['customer_pic_name']);
        }

        if (array_key_exists('customer_pic_phone', $data)) {
            $pic['customer_pic_phone'] = $this->clean($data['customer_pic_phone']);
        }

        return $pic;
    }

    /** The order's billing address: the order-level value of the Edit order page, else a block's own (older callers). */
    private function billingAddress(array $pair, array $data): mixed
    {
        return array_key_exists('customer_address', $data)
            ? $this->clean($data['customer_address'])
            : $this->clean($pair['customer_address'] ?? null);
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    /** Same text once spacing and line breaks are ignored (a textarea may send other line endings); blank = null. */
    private function sameText(mixed $a, mixed $b): bool
    {
        $normalize = fn (mixed $value): string => trim((string) preg_replace('/\s+/u', ' ', is_scalar($value) ? (string) $value : ''));

        return $normalize($a) === $normalize($b);
    }
}

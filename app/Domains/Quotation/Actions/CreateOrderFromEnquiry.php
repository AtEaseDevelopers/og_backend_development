<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DocumentType;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Models\User;
use App\Services\DocumentNumberingService;
use App\Support\OrderFormOptions;
use App\Support\QuotationMatrix;
use App\Support\QuotationPricingLookup;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns an enquiry (customer portal submission or admin-assisted entry) into order records.
 *
 * One enquiry carries ONE order number; every consignor–consignee pair on it becomes a separate
 * quotation record (own quotation / proforma / invoice / CSN numbers) that shares the customer,
 * salesperson and order number. Prices are prefilled from the master price list where a
 * destination matches a price-list location.
 */
class CreateOrderFromEnquiry
{
    public const CHARGE_LINES = ['Pickup charge', 'Drop-off charge', 'Other charges'];

    public function __construct(
        private DocumentNumberingService $numbering,
        private QuotationMatrix $matrix,
        private QuotationPricingLookup $lookup,
    ) {}

    /**
     * Create one order record per consignor–consignee pair.
     *
     * A pair with 'explicit' => true (admin entry / Edit order block, or a destination saved by Edit order)
     * keeps a blank DO number and instructions blank instead of taking the enquiry-level values.
     *
     * @param  list<array<string, mixed>>|null  $pairs  explicit pair specs (admin entry); null = derive from the enquiry payload
     * @return Collection<int, Quotation>
     */
    public function execute(PortalEnquiry $enquiry, User $actor, ?array $pairs = null): Collection
    {
        $enquiry->loadMissing(['customer.pics', 'customer.addresses', 'branch', 'salesperson', 'quotations']);

        if (! $enquiry->customer_id) {
            throw new InvalidArgumentException('The enquiry has no customer; an order record needs a consignor.');
        }

        if (in_array($enquiry->status, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true)) {
            throw new InvalidArgumentException('This enquiry is '.$enquiry->status->getLabel().' and cannot be priced.');
        }

        // Pricing from the submitted form runs once: doing it again would duplicate every record
        if ($pairs === null && ($existing = UpdateOrderRecords::recordsQuery($enquiry)->pluck('number'))->isNotEmpty()) {
            throw new InvalidArgumentException('Pricing has already started for '.$enquiry->orderNumber().' ('.$existing->implode(', ').'). Open the order records, or use Edit order to add another consignor & consignee.');
        }

        $pairs ??= $this->pairsFromPayload($enquiry);

        if ($pairs === []) {
            throw new InvalidArgumentException('The enquiry has no destination to create an order for.');
        }

        $branch = Branch::query()->findOrFail($enquiry->branch_id);
        $consignor = $enquiry->customer_id ? OrderFormOptions::consignorStateForCustomer((string) $enquiry->customer_id, withPickupPreset: false) : [];
        $fillable = (new Quotation)->getFillable();

        return DB::transaction(function () use ($enquiry, $actor, $pairs, $branch, $consignor, $fillable): Collection {
            $created = collect();

            foreach ($pairs as $pair) {
                $column = $this->columnLabel($pair);
                $rows = $this->rows($enquiry, $pair, $column);
                $explicit = ! empty($pair['explicit']);
                // Pickup or Store: the store (and its branch) is kept only on a Store record
                $serviceType = ServiceType::tryFrom((string) ($pair['service_type'] ?? ''))?->value ?? $enquiry->service_type?->value;
                $isStore = $serviceType === ServiceType::Store->value;
                $storeId = $isStore && filled($pair['store_id'] ?? null) ? (int) $pair['store_id'] : null;
                $storeBranchId = $isStore && filled($pair['store_branch_id'] ?? null) ? (int) $pair['store_branch_id'] : null;

                $data = array_merge($consignor, [
                    'company_id' => $enquiry->company_id,
                    'branch_id' => $enquiry->branch_id,
                    'customer_id' => $enquiry->customer_id,
                    // a consignor left blank on the order stays blank; a pair without the field at all (older callers) is the customer
                    'consignor_name' => array_key_exists('consignor_name', $pair)
                        ? (filled($pair['consignor_name']) ? trim((string) $pair['consignor_name']) : null)
                        : $enquiry->customer?->company_name,
                    'portal_enquiry_id' => $enquiry->id,
                    'salesperson_id' => $enquiry->salesperson_id,
                    'salesperson_locked' => $enquiry->salesperson_id !== null,
                    'sa_location_id' => $enquiry->sa_location_id ?? $enquiry->salesperson?->sa_location_id,
                    'order_type' => $enquiry->order_type?->value ?? $enquiry->customer?->default_order_type,
                    'service_type' => $serviceType,
                    'store_branch_id' => $storeBranchId,
                    'store_id' => $storeId,
                    // the customer's person in charge and contact number of the order page (blank stays blank);
                    // a pair without them (portal / older callers): the customer's default
                    'attention' => array_key_exists('attention', $pair) ? (filled($pair['attention']) ? trim((string) $pair['attention']) : null) : ($consignor['attention'] ?? null),
                    'customer_pic_phone' => array_key_exists('customer_pic_phone', $pair) ? (filled($pair['customer_pic_phone']) ? trim((string) $pair['customer_pic_phone']) : null) : ($consignor['customer_pic_phone'] ?? null),
                    'consignor_pic_name' => $pair['consignor_pic_name'] ?? null,
                    'consignor_pic_phone' => $pair['consignor_pic_phone'] ?? null,
                    'consignee_pic_name' => $pair['consignee_pic_name'] ?? null,
                    'consignee_pic_phone' => $pair['consignee_pic_phone'] ?? null,
                    // admin entries leave it blank (captured when the payment is recorded); portal orders keep theirs
                    'payment_method' => $enquiry->payment_method,
                    'customer_do_number' => $explicit ? ($pair['customer_do_number'] ?? null) : ($pair['customer_do_number'] ?? $enquiry->customer_do_number),
                    // free text (a portal order: the date the customer asked for)
                    'expected_delivery_date' => $explicit ? ($pair['expected_delivery_date'] ?? null) : ($pair['expected_delivery_date'] ?? $enquiry->preferred_delivery_date?->format('d/m/Y')),
                    'from_location_id' => $pair['from_location_id'] ?? ($consignor['from_location_id'] ?? null),
                    'consignor_brn' => $pair['consignor_brn'] ?? ($consignor['consignor_brn'] ?? $enquiry->customer?->brn),
                    'customer_address' => $pair['customer_address'] ?? ($consignor['customer_address'] ?? $enquiry->customer?->address),
                    'pickup_location' => $pair['pickup_location'] ?? $enquiry->pickup_address,
                    // an explicit block (admin entry / Edit order) keeps a blank consignee blank: the price column (To
                    // location) is the destination's name, not the consignee's
                    'consignee_name' => $explicit
                        ? (filled($pair['consignee_name'] ?? null) ? trim((string) $pair['consignee_name']) : null)
                        : ($pair['consignee_name'] ?? $column),
                    'to_location_id' => $pair['to_location_id'] ?? null,
                    'consignee_brn' => $pair['consignee_brn'] ?? null,
                    'consignee_address' => $pair['consignee_address'] ?? null,
                    'drop_off_location' => $pair['drop_off_location'] ?? null,
                    'destination_types' => [[
                        'column' => $column,
                        'drop_off_type' => $pair['drop_off_type'] ?? null,
                        'service_type' => $serviceType,
                    ]],
                    // files of the whole order (enquiry) plus the photos of this consignor & consignee block
                    'attachments' => collect($enquiry->attachments ?? [])->pluck('path')
                        ->merge(static::attachmentPaths($pair['attachments'] ?? []))
                        ->filter()->unique()->values()->all(),
                    // photos of each product, by product name
                    'item_attachments' => static::itemAttachments($pair['items'] ?? []) ?: null,
                    'pricing_source' => 'portal',
                    'notes' => $this->notes($enquiry, $pair),
                    'title' => 'Quotation Of Transport Charges',
                    'issued_by_name' => $actor->name,
                    'terms_of_payment' => $consignor['terms_of_payment'] ?? '30 days',
                    'status' => QuotationStatus::Draft,
                    'is_active' => true,
                    'quoted_at' => now()->toDateString(),
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'created_by' => $actor->id,
                    'version' => 1,
                ]);

                $data['number'] = $this->numbering->next($branch, DocumentType::Quotation);

                /** @var Quotation $order */
                $order = Quotation::query()->create(Arr::only($data, $fillable));
                $order->forceFill(['root_quotation_id' => $order->id])->saveQuietly();

                $this->matrix->sync($order, [$column], $rows);

                QuotationStatusLog::query()->create([
                    'quotation_id' => $order->id,
                    'from_status' => null,
                    'to_status' => QuotationStatus::Draft->value,
                    'user_id' => $actor->id,
                    'remarks' => 'Order record created from '.$enquiry->orderNumber().' ('.($pair['consignee_name'] ?? $column).')',
                ]);

                $created->push($order->fresh(['destinations', 'lines']));
            }

            // The enquiry keeps pointing at its first order; the status becomes "quoted"
            $enquiry->update([
                'status' => PortalEnquiryStatus::Quoted->value,
                'quotation_id' => $enquiry->quotation_id ?: $created->first()?->id,
                'attended_by' => $enquiry->attended_by ?? $actor->id,
                'attended_at' => $enquiry->attended_at ?? now(),
            ]);

            return $created;
        });
    }

    /**
     * Portal submissions: one pair per destination in the payload, items grouped by destination_index.
     * A destination saved by the Edit order page (it carries 'instructions') keeps the values entered there:
     * consignor, pickup / store (and the store branch), from, billing address, pickup, persons in charge and
     * contact numbers, to, drop-off location, DO number and instructions.
     *
     * @return list<array<string, mixed>>
     */
    public function pairsFromPayload(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? [])->filter(fn ($destination) => is_array($destination))->values();
        $items = collect($payload['items'] ?? [])->filter(fn ($item) => is_array($item))->values();

        if ($destinations->isEmpty()) {
            return [];
        }

        return $destinations->map(function (array $destination, int $index) use ($items, $destinations, $enquiry): array {
            $own = $items->filter(function (array $item) use ($index, $destinations): bool {
                $target = isset($item['destination_index']) && $item['destination_index'] !== '' ? (int) $item['destination_index'] : 0;

                return $target === $index || $target >= $destinations->count();
            })->values();

            $edited = array_key_exists('instructions', $destination);
            $stored = fn (string $key) => filled($destination[$key] ?? null) ? $destination[$key] : null;
            $toLocationId = $stored('to_location_id') !== null ? (int) $destination['to_location_id'] : null;
            // the TO location chosen on Edit order wins; otherwise the price-list location is matched from the address
            $location = $toLocationId === null ? $this->matchLocation($destination) : null;

            return [
                'explicit' => $edited,
                // saved on the order form (blank stays blank); a portal destination has no consignor: the customer
                'consignor_name' => array_key_exists('consignor_name', $destination) ? $stored('consignor_name') : $enquiry->customer?->company_name,
                'from_location_id' => $stored('from_location_id'),
                'consignor_brn' => $stored('consignor_brn'),
                'customer_address' => $stored('customer_address'),
                'pickup_location' => $stored('pickup_location'),
                // Store block: the store the consignor brings the goods to (and its branch); person in charge on each side
                'store_branch_id' => $stored('store_branch_id'),
                'store_id' => $stored('store_id'),
                // the customer's person in charge and contact number saved on the order form (when it has them)
                ...array_intersect_key($destination, ['attention' => true, 'customer_pic_phone' => true]),
                'consignor_pic_name' => $stored('consignor_pic_name'),
                'consignor_pic_phone' => $stored('consignor_pic_phone'),
                'consignee_pic_name' => $stored('consignee_pic_name'),
                // a portal destination has the consignee's phone only (until Edit order saves the contact number)
                'consignee_pic_phone' => array_key_exists('consignee_pic_phone', $destination) ? $stored('consignee_pic_phone') : $stored('consignee_phone'),
                'consignee_name' => $destination['consignee_name'] ?? null,
                'consignee_phone' => $destination['consignee_phone'] ?? null,
                'consignee_brn' => $stored('consignee_brn'),
                'consignee_address' => array_key_exists('consignee_address', $destination) ? $stored('consignee_address') : ($destination['address'] ?? null),
                'drop_off_location' => static::dropOffLocationFor($destination),
                'to_location_id' => $toLocationId ?? $location?->id,
                'location_name' => $toLocationId === null ? $location?->name : null,
                'drop_off_type' => $destination['drop_off_type'] ?? null,
                'service_type' => $destination['service_type'] ?? null,
                'expected_delivery_date' => $destination['expected_delivery_date'] ?? null,
                'customer_do_number' => $destination['customer_do_number'] ?? null,
                'instructions' => $edited ? $stored('instructions') : null,
                // photos uploaded for this consignor & consignee block
                'attachments' => array_values(array_filter($destination['attachments'] ?? [], 'is_array')),
                'items' => $own->map(fn (array $item) => [
                    'item_name' => trim((string) ($item['item_name'] ?? '')),
                    'uom' => strtoupper(trim((string) ($item['uom'] ?? ''))),
                    'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                    'catalog_key' => null,
                    // photos uploaded for this product on the order form
                    'attachments' => array_values(array_filter($item['attachments'] ?? [], 'is_array')),
                ])->filter(fn (array $item) => $item['item_name'] !== '')->values()->all(),
            ];
        })->all();
    }

    /**
     * A payload destination's drop-off location: the one saved by the Edit order page, else (a portal
     * submission) consignee, address and postcode / city / state in one line.
     *
     * @param  array<string, mixed>  $destination
     */
    public static function dropOffLocationFor(array $destination): ?string
    {
        if (array_key_exists('drop_off_location', $destination)) {
            return filled($destination['drop_off_location']) ? (string) $destination['drop_off_location'] : null;
        }

        return collect([
            $destination['consignee_name'] ?? null,
            $destination['address'] ?? null,
            collect([$destination['postcode'] ?? null, $destination['city'] ?? null, $destination['state'] ?? null])->filter()->implode(', '),
        ])->filter()->implode(', ') ?: null;
    }

    /**
     * Stored paths of uploaded files: entries of an attachments list ({path, name, …} as saved on the enquiry /
     * order form, or a bare path as saved on an order record).
     *
     * @param  mixed  $files
     * @return list<string>
     */
    public static function attachmentPaths(mixed $files): array
    {
        return collect(is_array($files) ? $files : [])
            ->map(fn ($file) => is_array($file) ? ($file['path'] ?? null) : $file)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();
    }

    /**
     * Photos of a block's products by product name ({path, name, mime, …} each); a product listed twice keeps
     * the photos of both rows.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    public static function itemAttachments(array $items): array
    {
        $byName = [];

        foreach ($items as $item) {
            $name = is_array($item) ? trim((string) ($item['item_name'] ?? '')) : '';
            $files = is_array($item) ? array_values(array_filter($item['attachments'] ?? [], fn ($file) => is_array($file) && filled($file['path'] ?? null))) : [];

            if ($name !== '' && $files !== []) {
                $byName[$name] = array_values(array_merge($byName[$name] ?? [], $files));
            }
        }

        return $byName;
    }

    /** Matrix column: the price-list location name when known (so UOM tiers resolve), else the consignee. */
    private function columnLabel(array $pair): string
    {
        if (filled($pair['location_name'] ?? null)) {
            return (string) $pair['location_name'];
        }

        if (filled($pair['to_location_id'] ?? null)) {
            $name = Location::query()->whereKey($pair['to_location_id'])->value('name');

            if ($name) {
                return $name;
            }
        }

        return (string) ($pair['consignee_name'] ?: 'Destination');
    }

    /** @return list<array<string, mixed>> */
    private function rows(PortalEnquiry $enquiry, array $pair, string $column): array
    {
        $items = collect($pair['items'] ?? []);

        // nothing asked for: no line (the pricing form starts with an empty product row)
        if ($items->isEmpty()) {
            return [];
        }

        return $items->map(function (array $item) use ($enquiry, $column): array {
            $name = (string) $item['item_name'];
            $catalogKey = $item['catalog_key'] ?? ($this->lookup->resolveCatalogKey($name) ?: ($item['uom'] ?? null ? $this->lookup->resolveCatalogKey((string) $item['uom']) : null));
            $lineType = $item['line_type'] ?? $this->lookup->inferLineType($catalogKey, $name);
            $quantity = $lineType === 'uom' ? max(0.01, (float) ($item['quantity'] ?? 1)) : 1.0;
            // Prices come from the UOM price list, and only once a salesperson owns the order
            $price = $lineType === 'lorry' || ! $enquiry->salesperson_id
                ? null
                : ($this->lookup->lookupForCustomer($enquiry->customer_id ? (int) $enquiry->customer_id : null, $name, $column, $quantity)['price'] ?? null);

            if ($price === null && $enquiry->salesperson_id && filled($item['unit_price'] ?? null)) {
                $price = (float) $item['unit_price'];
            }

            return [
                'line_type' => $lineType,
                'item_name' => $name,
                'catalog_key' => $catalogKey,
                'quantity' => $quantity,
                'prices' => [$column => $price],
            ];
        })->values()->all();
    }

    private function notes(PortalEnquiry $enquiry, array $pair): ?string
    {
        // an explicit block has its own instructions (the enquiry's special requirements join every block's)
        $notes = collect([
            'Order '.$enquiry->orderNumber().' · enquiry '.$enquiry->reference_no.'.',
            filled($pair['instructions'] ?? null) ? 'Customer instructions: '.$pair['instructions'] : null,
            empty($pair['explicit']) && filled($enquiry->special_requirements) && blank($pair['instructions'] ?? null) ? 'Customer request: '.$enquiry->special_requirements : null,
        ])->filter()->implode("\n");

        return $notes !== '' ? $notes : null;
    }

    /** @param  array<string, mixed>  $destination */
    private function matchLocation(array $destination): ?Location
    {
        $candidates = collect([
            $destination['city'] ?? null,
            $destination['state'] ?? null,
            $destination['address'] ?? null,
            $destination['consignee_name'] ?? null,
        ])->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $locations = Location::query()->where('is_active', true)->whereHas('uomRateTiers')->get(['id', 'code', 'name']);

        foreach ($candidates as $candidate) {
            foreach ($locations as $location) {
                $name = mb_strtolower($location->name);

                if ($candidate === $name || str_contains($candidate, $name)) {
                    return $location;
                }
            }
        }

        return null;
    }
}

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

                $data = array_merge($consignor, [
                    'company_id' => $enquiry->company_id,
                    'branch_id' => $enquiry->branch_id,
                    'customer_id' => $enquiry->customer_id,
                    'consignor_name' => $pair['consignor_name'] ?? $enquiry->customer?->company_name,
                    'portal_enquiry_id' => $enquiry->id,
                    'salesperson_id' => $enquiry->salesperson_id,
                    'salesperson_locked' => $enquiry->salesperson_id !== null,
                    'sa_location_id' => $enquiry->sa_location_id ?? $enquiry->salesperson?->sa_location_id,
                    'order_type' => $enquiry->order_type?->value ?? $enquiry->customer?->default_order_type,
                    'service_type' => $pair['service_type'] ?? $enquiry->service_type?->value,
                    'payment_method' => $enquiry->payment_method,
                    'customer_do_number' => $pair['customer_do_number'] ?? $enquiry->customer_do_number,
                    'expected_delivery_date' => $pair['expected_delivery_date'] ?? $enquiry->preferred_delivery_date?->toDateString(),
                    'from_location_id' => $pair['from_location_id'] ?? ($consignor['from_location_id'] ?? null),
                    'consignor_brn' => $pair['consignor_brn'] ?? ($consignor['consignor_brn'] ?? $enquiry->customer?->brn),
                    'customer_address' => $pair['customer_address'] ?? ($consignor['customer_address'] ?? $enquiry->customer?->address),
                    'pickup_location' => $pair['pickup_location'] ?? $enquiry->pickup_address,
                    'consignee_name' => $pair['consignee_name'] ?? $column,
                    'to_location_id' => $pair['to_location_id'] ?? null,
                    'consignee_brn' => $pair['consignee_brn'] ?? null,
                    'consignee_address' => $pair['consignee_address'] ?? null,
                    'drop_off_location' => $pair['drop_off_location'] ?? null,
                    'destination_types' => [[
                        'column' => $column,
                        'drop_off_type' => $pair['drop_off_type'] ?? null,
                        'service_type' => $pair['service_type'] ?? $enquiry->service_type?->value,
                    ]],
                    'attachments' => collect($enquiry->attachments ?? [])->pluck('path')->filter()->values()->all(),
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
     *
     * @return list<array<string, mixed>>
     */
    public function pairsFromPayload(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? [])->values();
        $items = collect($payload['items'] ?? [])->values();

        if ($destinations->isEmpty()) {
            return [];
        }

        return $destinations->map(function (array $destination, int $index) use ($items, $destinations): array {
            $own = $items->filter(function (array $item) use ($index, $destinations): bool {
                $target = isset($item['destination_index']) && $item['destination_index'] !== '' ? (int) $item['destination_index'] : 0;

                return $target === $index || $target >= $destinations->count();
            })->values();

            $location = $this->matchLocation($destination);

            return [
                'consignee_name' => $destination['consignee_name'] ?? null,
                'consignee_phone' => $destination['consignee_phone'] ?? null,
                'consignee_address' => $destination['address'] ?? null,
                'drop_off_location' => collect([
                    $destination['consignee_name'] ?? null,
                    $destination['address'] ?? null,
                    collect([$destination['postcode'] ?? null, $destination['city'] ?? null, $destination['state'] ?? null])->filter()->implode(', '),
                ])->filter()->implode(', ') ?: null,
                'to_location_id' => $location?->id,
                'location_name' => $location?->name,
                'drop_off_type' => $destination['drop_off_type'] ?? null,
                'service_type' => $destination['service_type'] ?? null,
                'expected_delivery_date' => $destination['expected_delivery_date'] ?? null,
                'customer_do_number' => $destination['customer_do_number'] ?? null,
                'items' => $own->map(fn (array $item) => [
                    'item_name' => trim((string) ($item['item_name'] ?? '')),
                    'uom' => strtoupper(trim((string) ($item['uom'] ?? ''))),
                    'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
                    'catalog_key' => null,
                ])->filter(fn (array $item) => $item['item_name'] !== '')->values()->all(),
            ];
        })->all();
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

        if ($items->isEmpty()) {
            return [[
                'line_type' => 'item',
                'item_name' => 'Transport charges',
                'catalog_key' => null,
                'quantity' => 1,
                'prices' => [$column => null],
            ]];
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
        $notes = collect([
            'Order '.$enquiry->orderNumber().' · enquiry '.$enquiry->reference_no.'.',
            filled($pair['instructions'] ?? null) ? 'Customer instructions: '.$pair['instructions'] : null,
            filled($enquiry->special_requirements) && blank($pair['instructions'] ?? null) ? 'Customer request: '.$enquiry->special_requirements : null,
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

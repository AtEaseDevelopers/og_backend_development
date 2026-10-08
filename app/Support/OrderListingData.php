<?php

namespace App\Support;

use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\QuotationStatus;
use App\Filament\Pages\OrderDetail;
use App\Filament\Resources\ConsignmentNoteResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rows for the Order management list.
 *
 * An enquiry that has not been priced yet is one row. Once order records exist, every record
 * (one per consignor–consignee pair, sharing the enquiry's order number) is its own row with
 * its own quotation number, stage, payment state and next step.
 */
class OrderListingData
{
    public const STEPS = OrderStage::STEPS;

    /** Sortable table columns (header click). */
    public const SORTS = ['order', 'route', 'stage', 'payment', 'amount', 'next'];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, count: int, total: int, summary: array<string, int>, stage_counts: array<string, int>}
     */
    public function for(array $filters): array
    {
        // Plain collections: an empty Eloquent result stays an Eloquent collection, whose merge()
        // expects models and fails on these array rows ("getKey() on array").
        $rows = $this->enquiryRows($filters)->toBase()
            ->merge($this->orderRows($filters)->toBase())
            ->sortByDesc('sort_at')
            ->values();

        $summary = [
            'total' => $rows->where('is_closed', false)->count(),
            'needs_attention' => $rows->where('attention', true)->count(),
            'awaiting_customer' => $rows->filter(fn (array $r) => $r['stage']['key'] === 'awaiting_customer')->count(),
            'ready_to_bill' => $rows->where('ready_to_bill', true)->count(),
        ];

        $stage = (string) ($filters['stage'] ?? '');
        $card = (string) ($filters['card'] ?? '');

        // Every filter except the stage: the stage tags count within this set
        $matching = $rows->filter(function (array $row) use ($card, $filters): bool {
            $cardOk = match ($card) {
                'attention' => $row['attention'],
                'customer' => $row['stage']['key'] === 'awaiting_customer',
                'bill' => $row['ready_to_bill'],
                default => true,
            };

            return $cardOk && $this->passesMemoryFilters($row, $filters);
        });

        $byStage = $matching->countBy(fn (array $row) => $row['stage']['key']);
        $stageCounts = ['' => $matching->where('is_closed', false)->count()];

        foreach (array_keys(OrderStage::STAGES) as $key) {
            $stageCounts[$key] = (int) $byStage->get($key, 0);
        }

        // "All" ('') is every open order; closed ones sit under their own tag
        $filtered = $matching->filter(fn (array $row): bool => $stage === ''
            ? ! $row['is_closed']
            : $row['stage']['key'] === $stage)->values();

        $filtered = $this->sortRows($filtered, (string) ($filters['sort'] ?? ''), (string) ($filters['dir'] ?? 'asc'));

        return [
            'rows' => $filtered->all(),
            'count' => $filtered->count(),
            'total' => $rows->count(),
            'summary' => $summary,
            'stage_counts' => $stageCounts,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Enquiries not yet priced
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, array<string, mixed>> */
    private function enquiryRows(array $filters): Collection
    {
        // Filters that only make sense once an order record exists exclude enquiry rows
        foreach (['pricing_source', 'quotation_status', 'valid_from', 'valid_to', 'amount_min', 'amount_max'] as $key) {
            if (filled($filters[$key] ?? null)) {
                return collect();
            }
        }

        if (filled($filters['payment_status'] ?? null) && $filters['payment_status'] !== 'not_requested') {
            return collect();
        }

        $query = PortalEnquiry::query()
            ->with(['customer', 'branch', 'user', 'salesperson', 'saLocation', 'attendee'])
            ->whereDoesntHave('quotations');

        $this->scopeCompany($query);
        $this->applyCommonFilters($query, $filters, 'portal_enquiries');

        return $query->orderByDesc('created_at')->limit(300)->get()
            ->map(fn (PortalEnquiry $enquiry) => $this->enquiryRow($enquiry))
            ->toBase();
    }

    /** @return array<string, mixed> */
    private function enquiryRow(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? [])->values();
        $items = collect($payload['items'] ?? []);
        $stage = OrderStage::forEnquiry($enquiry);
        $payment = OrderStage::paymentForEnquiry($enquiry);
        $first = $destinations->first() ?? [];

        $to = (string) ($first['city'] ?? $first['consignee_name'] ?? $first['state'] ?? '—');
        if ($destinations->count() > 1) {
            $to .= ' +'.($destinations->count() - 1);
        }

        $url = OrderDetail::urlFor('enquiry', $enquiry->id);

        return [
            'kind' => 'enquiry',
            'id' => $enquiry->id,
            'enquiry_id' => $enquiry->id,
            'url' => $url,
            'order_number' => $enquiry->orderNumber(),
            'document_number' => null,
            'customer' => $enquiry->customer?->company_name ?? '—',
            'customer_id' => $enquiry->customer_id,
            'customer_type' => $this->customerType($enquiry->customer),
            'enquiry_ref' => $enquiry->reference_no,
            'order_type' => $enquiry->order_type?->getLabel(),
            'order_type_value' => $enquiry->order_type?->value,
            'source_line' => $enquiry->sourceLabel().' · '.($enquiry->user?->name ?? ($payload['entered_by'] ?? 'Customer')),
            'route_from' => $this->cityFromAddress($enquiry->pickup_address) ?? ($enquiry->branch?->name ?? 'Pickup'),
            'route_to' => $to,
            'salesperson' => $enquiry->salesperson?->name,
            'service_type' => $enquiry->service_type?->getLabel(),
            'items_summary' => $this->itemsSummary($items),
            'consignee' => $first['consignee_name'] ?? null,
            'stage' => $stage,
            'payment' => $payment,
            'amount' => 'Not priced',
            'amount_muted' => true,
            'total' => 0.0,
            'next_step' => $stage['next'],
            'next_url' => $url.($stage['key'] === 'quotation' ? '?tab=pricing' : ''),
            'is_closed' => $stage['closed'],
            'attention' => $stage['attention'],
            'ready_to_bill' => false,
            'sort_at' => $enquiry->created_at?->timestamp ?? 0,
            'created_at' => $enquiry->created_at,
            'valid_until' => null,
            'pricing_source' => null,
            'quotation_status' => null,
            'payment_method_value' => $enquiry->payment_method,
            'drop_off_types' => $destinations->pluck('drop_off_type')->filter()->unique()->values()->all(),
            'version' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Order records (latest versions)
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, array<string, mixed>> */
    private function orderRows(array $filters): Collection
    {
        $query = Quotation::query()
            ->with(['customer', 'branch', 'salesperson', 'saLocation', 'destinations', 'lines', 'proformaInvoice', 'portalEnquiry.user', 'paymentSubmissions', 'fromLocation', 'toLocation', 'creator'])
            ->withCount(['consignmentNotes', 'invoices'])
            ->where('status', '!=', QuotationStatus::Superseded->value);

        $this->scopeCompany($query);
        $this->applyCommonFilters($query, $filters, 'quotations');

        if (filled($filters['pricing_source'] ?? null)) {
            $query->where('pricing_source', $filters['pricing_source']);
        }

        if (filled($filters['quotation_status'] ?? null)) {
            $query->where('status', $filters['quotation_status']);
        }

        if (filled($filters['valid_from'] ?? null)) {
            $query->whereDate('valid_until', '>=', Carbon::parse((string) $filters['valid_from'])->toDateString());
        }

        if (filled($filters['valid_to'] ?? null)) {
            $query->whereDate('valid_until', '<=', Carbon::parse((string) $filters['valid_to'])->toDateString());
        }

        if (filled($filters['amount_min'] ?? null)) {
            $query->where('total_amount', '>=', (float) $filters['amount_min']);
        }

        if (filled($filters['amount_max'] ?? null)) {
            $query->where('total_amount', '<=', (float) $filters['amount_max']);
        }

        return $query->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (Quotation $quotation) => $this->orderRow($quotation))
            ->toBase();
    }

    /** @return array<string, mixed> */
    private function orderRow(Quotation $q): array
    {
        $stage = OrderStage::forOrder($q);
        $payment = OrderStage::paymentForOrder($q, $stage);
        $total = (float) $q->total_amount;
        $enquiry = $q->portalEnquiry;

        $firstDestination = $q->destinations->sortBy('sequence')->first();
        $to = $q->toLocation?->name ?? $firstDestination?->consignee_name ?? $q->consignee_name ?? '—';
        if ($q->destinations->count() > 1) {
            $to .= ' +'.($q->destinations->count() - 1);
        }

        $from = $q->fromLocation?->name
            ?? $this->cityFromAddress($q->pickup_location)
            ?? ($q->branch?->name ?? 'Pickup');

        $url = OrderDetail::urlFor('order', $q->id);
        $nextUrl = match ($stage['key']) {
            'csn' => ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $q->id]]]),
            'payment', 'billed' => $url.'?tab=payment',
            'quotation', 'pending_salesperson' => $url.'?tab=pricing',
            default => $url,
        };

        $sourceLine = $enquiry
            ? $enquiry->sourceLabel().' · '.($enquiry->user?->name ?? (($enquiry->payload['entered_by'] ?? null) ?: ($q->creator?->name ?? 'Admin')))
            : 'Admin entry · '.($q->creator?->name ?? 'Admin');

        return [
            'kind' => 'order',
            'id' => $q->id,
            'enquiry_id' => $q->portal_enquiry_id,
            'url' => $url,
            'order_number' => $q->orderNumber(),
            'document_number' => $q->number,
            'customer' => $q->customer?->company_name ?? '—',
            'customer_id' => $q->customer_id,
            'customer_type' => $this->customerType($q->customer),
            'enquiry_ref' => $enquiry?->reference_no,
            'order_type' => $q->orderType()?->getLabel(),
            'order_type_value' => $q->orderType()?->value,
            'source_line' => $sourceLine,
            'route_from' => $from,
            'route_to' => $to,
            'salesperson' => $q->salesperson?->name,
            'service_type' => $q->service_type?->getLabel(),
            'items_summary' => $this->linesSummary($q->lines),
            'consignee' => $q->consignee_name ?: $firstDestination?->consignee_name,
            'stage' => $stage,
            'payment' => $payment,
            'amount' => $total > 0 && $q->salesperson_id ? 'RM '.number_format($total, 2) : 'Not priced',
            'amount_muted' => $total <= 0 || ! $q->salesperson_id,
            'total' => $total,
            'next_step' => $stage['next'],
            'next_url' => $nextUrl,
            'is_closed' => $stage['closed'],
            'attention' => $stage['attention'],
            'ready_to_bill' => $stage['ready_to_bill'],
            'sort_at' => $q->created_at?->timestamp ?? 0,
            'created_at' => $q->created_at,
            'valid_until' => $q->valid_until,
            'pricing_source' => $q->pricing_source,
            'quotation_status' => $q->status->value,
            'payment_method_value' => $q->payment_method,
            'drop_off_types' => collect($q->destination_types ?? [])->pluck('drop_off_type')->filter()->unique()->values()->all(),
            'version' => $q->version,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    private function scopeCompany(Builder $query): void
    {
        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }
    }

    private function applyCommonFilters(Builder $query, array $filters, string $table): void
    {
        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($needle, $table): void {
                if ($table === 'quotations') {
                    $builder->where('number', 'like', '%'.$needle.'%')
                        ->orWhere('consignee_name', 'like', '%'.$needle.'%')
                        ->orWhereHas('portalEnquiry', fn (Builder $e) => $e->where('reference_no', 'like', '%'.$needle.'%')->orWhere('order_number', 'like', '%'.$needle.'%'));
                } else {
                    $builder->where('reference_no', 'like', '%'.$needle.'%')
                        ->orWhere('order_number', 'like', '%'.$needle.'%')
                        ->orWhere('pickup_address', 'like', '%'.$needle.'%');
                }

                $builder->orWhere('customer_do_number', 'like', '%'.$needle.'%')
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('company_name', 'like', '%'.$needle.'%')->orWhere('code', 'like', '%'.$needle.'%'));
            });
        }

        if (filled($filters['customer_id'] ?? null)) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (filled($filters['customer_type'] ?? null)) {
            $type = (string) $filters['customer_type'];
            $query->whereHas('customer', fn (Builder $customer) => $customer->ofCustomerType($type));
        }

        if (filled($filters['order_type'] ?? null)) {
            $query->where('order_type', $filters['order_type']);
        }

        if (filled($filters['service_type'] ?? null)) {
            $query->where('service_type', $filters['service_type']);
        }

        if (filled($filters['salesperson_id'] ?? null)) {
            $query->where('salesperson_id', $filters['salesperson_id']);
        }

        if (filled($filters['sa_location_id'] ?? null)) {
            $query->where('sa_location_id', $filters['sa_location_id']);
        }

        if (filled($filters['payment_method'] ?? null)) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (filled($filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', Carbon::parse((string) $filters['created_from'])->toDateString());
        }

        if (filled($filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', Carbon::parse((string) $filters['created_to'])->toDateString());
        }
    }

    /** @return array{key: string, label: string}|null */
    private function customerType(?Customer $customer): ?array
    {
        $type = $customer?->customerType();

        return $type ? ['key' => $type->value, 'label' => $type->shortLabel()] : null;
    }

    /** Filters that depend on computed values. */
    private function passesMemoryFilters(array $row, array $filters): bool
    {
        if (filled($filters['payment_status'] ?? null) && $row['payment']['key'] !== $filters['payment_status']) {
            return false;
        }

        if (filled($filters['drop_off_type'] ?? null) && ! in_array($filters['drop_off_type'], $row['drop_off_types'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Header sort. Without one the rows stay newest first; the sort is stable, so rows with
     * equal values keep that newest-first order in both directions.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, string $sort, string $dir): Collection
    {
        if (! in_array($sort, self::SORTS, true)) {
            return $rows;
        }

        $sign = $dir === 'desc' ? -1 : 1;
        // "Not priced" sorts as no amount, below every priced order
        $amount = fn (array $row): float => $row['amount_muted'] ? 0.0 : (float) $row['total'];

        return $rows->sort(fn (array $a, array $b): int => $sign * match ($sort) {
            'order' => strnatcasecmp((string) $a['order_number'], (string) $b['order_number'])
                ?: strnatcasecmp((string) $a['customer'], (string) $b['customer']),
            'route' => strnatcasecmp((string) $a['route_from'], (string) $b['route_from'])
                ?: strnatcasecmp((string) $a['route_to'], (string) $b['route_to']),
            'stage' => ((int) $a['stage']['step'] <=> (int) $b['stage']['step'])
                ?: strnatcasecmp((string) $a['stage']['label'], (string) $b['stage']['label']),
            'payment' => strnatcasecmp((string) $a['payment']['label'], (string) $b['payment']['label']),
            'amount' => $amount($a) <=> $amount($b),
            'next' => strnatcasecmp((string) $a['next_step'], (string) $b['next_step']),
        })->values();
    }

    /** @return array<string, string> */
    public static function customerTypeOptions(): array
    {
        return ['' => 'All'] + collect(OrderType::cases())->mapWithKeys(fn (OrderType $type) => [$type->value => $type->shortLabel()])->all();
    }

    /** @return array<string, string> */
    public static function pricingSourceOptions(): array
    {
        return [
            '' => 'All',
            'default' => 'Price list',
            'special' => 'Customer special',
            'previous' => 'Previous quotation',
            'manual' => 'Manual',
            'portal' => 'Portal (not priced)',
        ];
    }

    /** @return array<string, string> */
    public static function quotationStatusOptions(): array
    {
        return ['' => 'All'] + collect(QuotationStatus::cases())
            ->reject(fn (QuotationStatus $s) => $s === QuotationStatus::Superseded)
            ->mapWithKeys(fn (QuotationStatus $s) => [$s->value => $s->getLabel()])
            ->all();
    }

    /** @return array<string, string> */
    public static function dropOffTypeOptions(): array
    {
        return ['' => 'All'] + DropOffType::options();
    }

    /** @return array<string, string> */
    public static function paymentMethodOptions(): array
    {
        return ['' => 'All'] + PaymentMethod::options();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** "No. 8, Jalan X, 47100 Puchong, Selangor" → "Puchong"; "15 Jalan Ampang, 50450 Kuala Lumpur" → "Kuala Lumpur". */
    public function cityFromAddress(?string $address): ?string
    {
        if (blank($address)) {
            return null;
        }

        $parts = collect(preg_split('/[,\n]+/', (string) $address))->map(fn ($p) => trim($p))->filter()->values();

        if ($parts->isEmpty()) {
            return null;
        }

        // The town normally follows the 5-digit postcode
        foreach ($parts->reverse() as $part) {
            if (preg_match('/^\d{5}\s+(.+)$/u', $part, $m)) {
                return str(trim($m[1]))->limit(24)->toString();
            }
        }

        $states = ['johor', 'kedah', 'kelantan', 'melaka', 'malacca', 'negeri sembilan', 'pahang', 'perak', 'perlis', 'pulau pinang', 'penang', 'sabah', 'sarawak', 'selangor', 'terengganu', 'kuala lumpur', 'putrajaya', 'labuan', 'malaysia'];
        $candidates = $parts->reject(fn ($p) => preg_match('/^\d/', $p) || preg_match('/\b(jalan|jln|lorong|lot|no\.?|unit|blok|block|lebuh|persiaran|taman)\b/i', $p))->values();

        $town = $candidates->first(fn ($p) => ! in_array(mb_strtolower($p), $states, true)) ?? $candidates->last() ?? $parts->last();

        return $town !== null ? str((string) $town)->limit(24)->toString() : null;
    }

    /** @param  Collection<int, array<string, mixed>>  $items */
    private function itemsSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No items listed';
        }

        $first = $items->first();
        return trim(QuantityLabel::format($first['quantity'] ?? 1, $first['uom'] ?? null).' '.str((string) ($first['item_name'] ?? ''))->limit(22))
            .($items->count() > 1 ? ' · +'.($items->count() - 1).' more' : '');
    }

    private function linesSummary(Collection $lines): string
    {
        $distinct = $lines->reject(fn ($line) => in_array($line->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true))->unique('item_name');

        if ($distinct->isEmpty()) {
            return 'No items yet';
        }

        $first = $distinct->first();
        return trim(QuantityLabel::format($first->quantity, $first->uom).' '.str($first->item_name)->limit(22))
            .($distinct->count() > 1 ? ' · +'.($distinct->count() - 1).' more' : '');
    }
}

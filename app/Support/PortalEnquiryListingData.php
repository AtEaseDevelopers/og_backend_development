<?php

namespace App\Support;

use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\PortalEnquiryStatus;
use App\Filament\Pages\OrderDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PortalEnquiryListingData
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function for(array $filters): array
    {
        $rows = $this->query($filters)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PortalEnquiry $enquiry): array => $this->formatRow($enquiry));

        $pendingCount = $rows->filter(fn (array $row): bool => in_array($row['status'], [
            PortalEnquiryStatus::Pending->value,
            PortalEnquiryStatus::InReview->value,
        ], true))->count();

        return [
            'rows' => $rows->values()->all(),
            'count' => $rows->count(),
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * Status counts for the same search / date window, ignoring the status filter.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total: int, needs_attention: int, quoted: int, rejected: int}
     */
    public function summary(array $filters): array
    {
        $counts = $this->query(array_merge($filters, ['status' => 'all']))
            ->reorder()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($count, $status): array => [(string) $status => (int) $count]);

        $of = fn (PortalEnquiryStatus $status): int => $counts->get($status->value, 0);

        return [
            'total' => (int) $counts->sum(),
            'needs_attention' => $of(PortalEnquiryStatus::Pending) + $of(PortalEnquiryStatus::InReview),
            'quoted' => $of(PortalEnquiryStatus::Quoted),
            'rejected' => $of(PortalEnquiryStatus::Rejected),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(PortalEnquiry $enquiry): array
    {
        $enquiry->loadMissing(['customer', 'branch', 'user', 'quotation', 'quotations', 'salesperson', 'saLocation', 'locker', 'attendee']);
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? []);
        $items = collect($payload['items'] ?? []);
        $firstDestination = $destinations->first() ?? [];
        $review = $this->reviewStatusDisplay($enquiry->status);
        $viewer = auth()->user();
        $lockedByOther = $enquiry->isLockedByOther($viewer);

        $totalWeightKg = $items->sum(fn (array $item): float => (float) ($item['weight'] ?? 0));

        return [
            'id' => $enquiry->id,
            // --- section A / B ---
            'salesperson' => $enquiry->salesperson?->name,
            'salesperson_id' => $enquiry->salesperson_id,
            'salesperson_locked' => (bool) $enquiry->salesperson_locked,
            'sa_location' => $enquiry->saLocation?->label(),
            'source' => match ($enquiry->source) {
                PortalEnquiry::SOURCE_SALESPERSON_LINK => 'Salesperson link',
                PortalEnquiry::SOURCE_WALK_IN => 'Walk-in',
                PortalEnquiry::SOURCE_ADMIN => 'Admin entry',
                default => 'Customer portal',
            },
            'order_type' => $enquiry->order_type?->getLabel() ?? '—',
            'payment_method' => \App\Enums\PaymentMethod::tryFrom((string) $enquiry->payment_method)?->getLabel() ?? '—',
            'customer_do_number' => $enquiry->customer_do_number ?? '—',
            'attachments' => collect($enquiry->attachments ?? [])->map(fn (array $file) => [
                'name' => $file['name'] ?? basename((string) ($file['path'] ?? '')),
                'url' => isset($file['path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($file['path']) : null,
                'is_image' => str_starts_with((string) ($file['mime'] ?? ''), 'image/'),
            ])->values()->all(),
            'is_locked_by_other' => $lockedByOther,
            'locked_by' => $lockedByOther ? ($enquiry->locker?->name ?? 'another user') : null,
            'locked_since' => $lockedByOther ? $enquiry->locked_at?->format('H:i') : null,
            'attended_by' => $enquiry->attendee?->name,
            'orders' => $enquiry->quotations->map(fn ($q) => [
                'number' => $q->number,
                'version' => $q->version,
                'status' => $q->status->getLabel(),
                'total' => 'RM '.number_format((float) $q->total_amount, 2),
                'url' => OrderDetail::urlFor('order', (int) $q->getKey()),
            ])->values()->all(),
            'can_assign_salesperson' => ! $lockedByOther && ! $enquiry->salesperson_locked && in_array($this->statusValue($enquiry->status), [
                PortalEnquiryStatus::Pending->value,
                PortalEnquiryStatus::InReview->value,
                PortalEnquiryStatus::Quoted->value,
            ], true),
            'reference_no' => $enquiry->reference_no ?? '—',
            'status' => $this->statusValue($enquiry->status),
            'status_label' => $review['label'],
            'status_color' => $review['color'],
            'customer' => $enquiry->customer?->company_name ?? '—',
            'customer_code' => $enquiry->customer?->code,
            'branch' => $enquiry->branch?->code ?? '—',
            'branch_name' => strtoupper($enquiry->branch?->name ?? '—'),
            'submitted_by' => $enquiry->user?->name ?? '—',
            'submitted_at' => $enquiry->created_at?->format('d/m/Y H:i') ?? '—',
            'preferred_delivery_date' => $enquiry->preferred_delivery_date?->format('d/m/Y') ?? '—',
            'pickup_address' => $enquiry->pickup_address ?? '—',
            'pickup_maps_url' => $enquiry->pickup_maps_url,
            'pickup_datetime' => $this->formatRequestedDatetime($enquiry->preferred_delivery_date, '08:00 AM'),
            'pickup_contact' => $this->formatContact(
                $firstDestination['pickup_pic'] ?? $enquiry->user?->name,
                $firstDestination['pickup_phone'] ?? $enquiry->customer?->phone,
            ),
            'delivery_address' => $this->formatAddress($firstDestination) ?: '—',
            'delivery_label' => $this->destinationLabel($firstDestination, 0),
            'delivery_datetime' => $this->formatRequestedDatetime($enquiry->preferred_delivery_date, '04:00 PM'),
            'delivery_contact' => $this->formatContact(
                $firstDestination['consignee_pic'] ?? $firstDestination['consignee_name'] ?? null,
                $firstDestination['consignee_phone'] ?? null,
            ),
            'special_requirements' => $enquiry->special_requirements,
            'rejection_reason' => $payload['rejection_reason'] ?? null,
            'destinations' => $destinations->map(fn (array $destination, int $index): array => [
                'index' => $index + 1,
                'label' => $this->destinationLabel($destination, $index),
                'consignee_name' => $destination['consignee_name'] ?? '—',
                'address' => $this->formatAddress($destination),
                'postcode' => $destination['postcode'] ?? null,
                'state' => $destination['state'] ?? null,
                'google_maps_url' => $destination['google_maps_url'] ?? null,
            ])->values()->all(),
            'items' => $items->map(function (array $item, int $index) use ($destinations, $enquiry): array {
                $destinationIndex = isset($item['destination_index']) ? (int) $item['destination_index'] : null;
                $destination = $destinationIndex !== null
                    ? $destinations->get($destinationIndex)
                    : null;

                $special = $item['special_request']
                    ?? ($index === 0 ? $enquiry->special_requirements : null);

                return [
                    'index' => $index + 1,
                    'item_name' => $item['item_name'] ?? '—',
                    'packaging' => QuantityLabel::unit($item['uom'] ?? null) ?? 'UNIT',
                    'quantity' => $this->formatQuantity($item['quantity'] ?? null),
                    'weight' => filled($item['weight'] ?? null)
                        ? number_format((float) $item['weight'], 0).' KG'
                        : '—',
                    'dimensions' => $item['dimensions'] ?? '—',
                    'special_request' => filled($special) ? $special : null,
                    'destination' => $destination
                        ? $this->destinationLabel($destination, $destinationIndex)
                        : '—',
                ];
            })->values()->all(),
            'total_weight_kg' => number_format($totalWeightKg, 0).' KG',
            'route_summary' => $this->routeSummary($enquiry, $destinations),
            'pricing' => $this->pricingPanel($enquiry),
            'payment' => $this->paymentPanel($enquiry),
            'traceability' => $this->traceabilitySteps($enquiry),
            'notifications' => $this->notificationItems($enquiry),
            // pricing creates every order record once; afterwards the button opens Edit order (add / change blocks there)
            'has_order_records' => UpdateOrderRecords::recordsQuery($enquiry)->exists(),
            'can_create_quotation' => ! $lockedByOther && in_array($this->statusValue($enquiry->status), [
                PortalEnquiryStatus::Pending->value,
                PortalEnquiryStatus::InReview->value,
                PortalEnquiryStatus::Quoted->value,
            ], true),
            'can_approve' => ! $lockedByOther && in_array($this->statusValue($enquiry->status), [
                PortalEnquiryStatus::Pending->value,
                PortalEnquiryStatus::InReview->value,
            ], true),
            'can_reject' => ! $lockedByOther && in_array($this->statusValue($enquiry->status), [
                PortalEnquiryStatus::Pending->value,
                PortalEnquiryStatus::InReview->value,
            ], true),
            'quotation_number' => $enquiry->quotation?->number,
            'quotation_url' => $enquiry->quotation
                ? OrderDetail::urlFor('order', (int) $enquiry->quotation->getKey())
                : null,
            'csn_url' => ($order = $this->latestOrder($enquiry)) && $order->consignmentNotes()->exists()
                ? \App\Filament\Resources\ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $order->id]]])
                : null,
        ];
    }

    /** The newest version of the order created from this enquiry, if any. */
    private function latestOrder(PortalEnquiry $enquiry): ?\App\Domains\Quotation\Models\Quotation
    {
        $order = $enquiry->quotation;

        while ($order && $order->newerVersion) {
            $order = $order->newerVersion;
        }

        return $order;
    }

    /** @return array<string, string> */
    public function statusFilterOptions(): array
    {
        return [
            '' => 'All Pending',
            'all' => 'All Statuses',
            ...collect(PortalEnquiryStatus::cases())
                ->mapWithKeys(fn (PortalEnquiryStatus $status) => [$status->value => $this->reviewStatusDisplay($status)['label']])
                ->all(),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters): Builder
    {
        $query = PortalEnquiry::query()
            ->with(['customer', 'branch', 'user', 'quotation']);

        if ($companyId = CurrentCompany::id()) {
            $query->where(function (Builder $builder) use ($companyId): void {
                $builder->where('company_id', $companyId)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_id', $companyId));
            });
        }

        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($needle): void {
                $builder->where('reference_no', 'like', '%'.$needle.'%')
                    ->orWhere('pickup_address', 'like', '%'.$needle.'%')
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('company_name', 'like', '%'.$needle.'%')
                        ->orWhere('code', 'like', '%'.$needle.'%'));
            });
        }

        if (($filters['status'] ?? '') === '') {
            $query->whereIn('status', [
                PortalEnquiryStatus::Pending->value,
                PortalEnquiryStatus::InReview->value,
            ]);
        } elseif (($filters['status'] ?? null) === 'all') {
            // no status restriction
        } elseif (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', Carbon::parse((string) $filters['date_from'])->toDateString());
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', Carbon::parse((string) $filters['date_to'])->toDateString());
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function formatRow(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? []);
        $items = collect($payload['items'] ?? []);
        $review = $this->reviewStatusDisplay($enquiry->status);
        $status = $this->statusValue($enquiry->status);
        $quotation = $enquiry->quotation;

        return [
            'id' => $enquiry->id,
            'reference_no' => $enquiry->reference_no ?? '—',
            'customer' => $enquiry->customer?->company_name ?? '—',
            'customer_code' => $enquiry->customer?->code,
            'branch' => $enquiry->branch?->code ?? '—',
            'submitted_by' => $enquiry->user?->name ?? '—',
            'destination' => $this->destinationShort($destinations),
            'route' => $this->routeSummary($enquiry, $destinations),
            'items_summary' => $this->itemsSummary($items),
            'preferred_delivery_date' => $enquiry->preferred_delivery_date?->format('d/m/Y') ?? '—',
            'status' => $status,
            'status_label' => $review['label'],
            'status_color' => $review['color'],
            'stage_hint' => $this->stageHint($status, $quotation !== null),
            'payment_label' => $quotation ? ($quotation->status?->getLabel() ?? '—') : 'Not requested',
            'payment_hint' => $quotation ? 'Quotation '.$quotation->number : 'After confirmation',
            'amount' => $quotation ? 'RM '.number_format((float) $quotation->total_amount, 2) : 'Not priced',
            'quotation_number' => $quotation?->number,
            'quotation_url' => $quotation ? OrderDetail::urlFor('order', (int) $quotation->getKey()) : null,
            'next_step' => $this->nextStep($status, $quotation !== null),
            'submitted_at' => $enquiry->created_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $items */
    private function itemsSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No items listed';
        }

        $first = $items->first();
        $label = trim($this->formatQuantity($first['quantity'] ?? null).' '.(QuantityLabel::unit($first['uom'] ?? null) ?? ''));
        $more = $items->count() > 1 ? ' · +'.($items->count() - 1).' more' : '';

        return $label.$more;
    }

    private function stageHint(string $status, bool $hasQuotation): string
    {
        return match ($status) {
            PortalEnquiryStatus::Pending->value => 'New submission · Review required',
            PortalEnquiryStatus::InReview->value => $hasQuotation
                ? 'Quotation drafted · Awaiting customer'
                : 'Pricing in progress · Admin editing',
            PortalEnquiryStatus::Quoted->value => 'Quotation linked · Continue in quotation',
            PortalEnquiryStatus::Rejected->value => 'Rejected by admin',
            default => 'Cancelled by customer',
        };
    }

    private function nextStep(string $status, bool $hasQuotation): string
    {
        return match ($status) {
            PortalEnquiryStatus::Pending->value => 'Review submitted order',
            PortalEnquiryStatus::InReview->value => $hasQuotation ? 'View quotation' : 'Provide pricing',
            PortalEnquiryStatus::Quoted->value => 'View quotation',
            PortalEnquiryStatus::Rejected->value => 'View rejection',
            default => 'View order',
        };
    }

    /** @return array{label: string, color: string} */
    private function reviewStatusDisplay(PortalEnquiryStatus|string|null $status): array
    {
        return match ($this->statusValue($status)) {
            PortalEnquiryStatus::Pending->value => ['label' => 'PENDING REVIEW', 'color' => 'gray'],
            PortalEnquiryStatus::InReview->value => ['label' => 'PRICING REVIEW', 'color' => 'blue'],
            PortalEnquiryStatus::Quoted->value => ['label' => 'APPROVED', 'color' => 'approved'],
            PortalEnquiryStatus::Rejected->value => ['label' => 'REJECTED', 'color' => 'danger'],
            default => ['label' => 'CANCELLED', 'color' => 'gray'],
        };
    }

    /** @param  Collection<int, array<string, mixed>>  $destinations */
    private function destinationShort(Collection $destinations): string
    {
        if ($destinations->isEmpty()) {
            return '—';
        }

        $first = $this->destinationLabel($destinations->first(), 0);
        $suffix = $destinations->count() > 1 ? ', +'.($destinations->count() - 1) : '';

        return $first.$suffix;
    }

    /** @param  Collection<int, array<string, mixed>>  $destinations */
    private function routeSummary(PortalEnquiry $enquiry, Collection $destinations): string
    {
        $pickup = str($enquiry->pickup_address ?? 'Pickup')->limit(28)->toString();
        $drop = $destinations->isNotEmpty()
            ? $this->destinationLabel($destinations->first(), 0)
            : '—';

        if ($destinations->count() > 1) {
            $drop .= ' +'.($destinations->count() - 1);
        }

        return $pickup.' → '.$drop;
    }

    /** @return array<string, mixed> */
    private function pricingPanel(PortalEnquiry $enquiry): array
    {
        if ($enquiry->quotation) {
            return [
                'contract_ref' => $enquiry->quotation->number,
                'amount' => 'RM '.number_format((float) $enquiry->quotation->total_amount, 2),
                'verified' => true,
                'verified_label' => 'AUTO-VERIFIED',
                'note' => 'Linked quotation from portal enquiry.',
            ];
        }

        $contractRef = $enquiry->customer?->code
            ? 'C-'.now()->format('Y').'-'.strtoupper(str_replace('-', '', (string) $enquiry->customer->code))
            : 'Pending contract';

        return [
            'contract_ref' => $contractRef,
            'amount' => '—',
            'verified' => false,
            'verified_label' => 'PENDING REVIEW',
            'note' => 'Generate quotation to calculate applicable pricing.',
        ];
    }

    /** @return array<string, mixed> */
    private function paymentPanel(PortalEnquiry $enquiry): array
    {
        return [
            'uploaded' => false,
            'amount' => $enquiry->quotation
                ? 'RM '.number_format((float) $enquiry->quotation->total_amount, 2)
                : '—',
            'reference' => null,
            'date' => null,
        ];
    }

    /** @return list<array{key: string, label: string, active: bool, completed: bool}> */
    private function traceabilitySteps(PortalEnquiry $enquiry): array
    {
        $status = $this->statusValue($enquiry->status);
        $hasQuotation = $enquiry->quotation_id !== null;

        // Follow the order to its latest version so CSN / billing / DO reflect the live state.
        $order = $this->latestOrder($enquiry);

        $csns = $order ? $order->consignmentNotes()->with('deliveryOrder')->get() : collect();
        $invoice = $order?->invoices()->latest('id')->first();
        $deliveryOrders = $csns->map(fn ($csn) => $csn->deliveryOrder)->filter();

        return [
            ['key' => 'portal', 'label' => 'Portal Order', 'value' => $enquiry->reference_no, 'active' => ! $hasQuotation, 'completed' => true],
            ['key' => 'quotation', 'label' => 'Quotation', 'value' => $order?->number, 'active' => $hasQuotation && $status !== PortalEnquiryStatus::Quoted->value, 'completed' => $hasQuotation],
            ['key' => 'invoice', 'label' => 'Invoice / Cash Bill', 'value' => $invoice?->number, 'active' => $hasQuotation && ! $invoice && $csns->isEmpty(), 'completed' => (bool) $invoice],
            ['key' => 'csn', 'label' => 'CSN', 'value' => $csns->pluck('number')->implode(', ') ?: null, 'active' => $csns->isNotEmpty() && $deliveryOrders->isEmpty(), 'completed' => $csns->isNotEmpty()],
            ['key' => 'delivery', 'label' => 'Delivery Order', 'value' => $deliveryOrders->pluck('number')->implode(', ') ?: null, 'active' => $deliveryOrders->isNotEmpty(), 'completed' => $deliveryOrders->isNotEmpty()],
        ];
    }

    /** @return list<array{label: string, status: string}> */
    private function notificationItems(PortalEnquiry $enquiry): array
    {
        $hasQuotation = $enquiry->quotation_id !== null;
        $status = $this->statusValue($enquiry->status);

        return [
            [
                'label' => 'Quotation Completion',
                'status' => $hasQuotation ? 'Sent' : 'Pending',
            ],
            [
                'label' => 'Approval / Rejection',
                'status' => $status === PortalEnquiryStatus::Rejected->value
                    ? 'Rejected'
                    : ($status === PortalEnquiryStatus::Quoted->value ? 'Approved' : 'Pending'),
            ],
            [
                'label' => 'Amendment',
                'status' => 'Pending',
            ],
        ];
    }

    /** @param  array<string, mixed>  $destination */
    private function destinationLabel(array $destination, int $index): string
    {
        if (filled($destination['consignee_name'] ?? null)) {
            return (string) $destination['consignee_name'];
        }

        if (filled($destination['city'] ?? null)) {
            return (string) $destination['city'];
        }

        if (filled($destination['state'] ?? null)) {
            return (string) $destination['state'];
        }

        return 'Destination '.($index + 1);
    }

    /** @param  array<string, mixed>  $destination */
    private function formatAddress(array $destination): string
    {
        return collect([
            $destination['address'] ?? null,
            collect([
                $destination['postcode'] ?? null,
                $destination['state'] ?? null,
            ])->filter()->implode(', '),
        ])->filter()->implode("\n");
    }

    private function formatQuantity(mixed $quantity): string
    {
        if (! filled($quantity)) {
            return '—';
        }

        $value = (float) $quantity;

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function formatRequestedDatetime(?Carbon $date, string $time): string
    {
        if (! $date) {
            return '—';
        }

        return $date->format('d/m/Y').' ('.$time.')';
    }

    private function formatContact(?string $name, ?string $phone): string
    {
        $parts = collect([$name, $phone])->filter();

        return $parts->isNotEmpty() ? $parts->implode(' | ') : '—';
    }

    private function statusValue(PortalEnquiryStatus|string|null $status): string
    {
        if ($status instanceof PortalEnquiryStatus) {
            return $status->value;
        }

        return (string) $status;
    }
}

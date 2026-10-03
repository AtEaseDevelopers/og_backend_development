<?php

namespace App\Support;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\QuotationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified order list following the flow
 * Enquiry → Quotation → Confirmation → Proforma → Payment / Release → Invoice / Cash Bill → CSN.
 *
 * Enquiries that have not been turned into an order yet appear as "enquiry" rows; once an order
 * (quotation) exists the order row represents it, with its stage derived from the quotation status,
 * the proforma, the billing status and whether CSNs exist.
 */
class OrderListingData
{
    public const STEPS = ['Enquiry', 'Quotation', 'Confirmation', 'Proforma', 'Payment / Release', 'Invoice / Cash Bill', 'CSN'];

    /** @return array<string, string> */
    public static function stageOptions(): array
    {
        return [
            '' => 'All open',
            'all' => 'All (incl. closed)',
            'enquiry' => '1 · New enquiry',
            'quotation' => '2 · Preparing quotation',
            'awaiting_customer' => '2 · Awaiting customer',
            'confirmation' => '3 · Credit approval',
            'payment' => '4–5 · Payment / release',
            'billed' => '6 · Billing issued',
            'csn' => '7 · CSN created',
            'closed' => 'Rejected / closed',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, mixed>>, count: int, summary: array<string, int>}
     */
    public function for(array $filters): array
    {
        $enquiries = $this->enquiryRows($filters);
        $orders = $this->orderRows($filters);

        $rows = $enquiries->merge($orders)->sortByDesc('sort_at')->values();
        $summary = $this->summary($rows);
        $stage = (string) ($filters['stage'] ?? '');

        $rows = $rows->filter(function (array $row) use ($stage): bool {
            if ($stage === 'all') {
                return true;
            }

            if ($stage === '') {
                return ! $row['is_closed'];
            }

            return $row['stage_key'] === $stage;
        })->values();

        return ['rows' => $rows->all(), 'count' => $rows->count(), 'summary' => $summary];
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function summary(Collection $rows): array
    {
        return [
            'total' => $rows->where('is_closed', false)->count(),
            'needs_attention' => $rows->whereIn('stage_key', ['enquiry', 'quotation', 'confirmation'])->count(),
            'awaiting_customer' => $rows->where('stage_key', 'awaiting_customer')->count(),
            'ready_to_bill' => $rows->where('stage_key', 'payment')->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Enquiries not yet turned into an order
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, array<string, mixed>> */
    private function enquiryRows(array $filters): Collection
    {
        $query = PortalEnquiry::query()
            ->with(['customer', 'branch', 'user', 'salesperson', 'saLocation'])
            ->whereDoesntHave('quotations');

        $this->scopeCompany($query, 'company_id');
        $this->applyCommonFilters($query, $filters, 'portal_enquiries');

        if (filled($filters['order_type'] ?? null)) {
            $query->where('order_type', $filters['order_type']);
        }

        if (filled($filters['billing_status'] ?? null)) {
            return collect(); // enquiries have no billing yet
        }

        return $query->orderByDesc('created_at')->limit(300)->get()
            ->map(fn (PortalEnquiry $enquiry) => $this->enquiryRow($enquiry));
    }

    /** @return array<string, mixed> */
    private function enquiryRow(PortalEnquiry $enquiry): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? []);
        $items = collect($payload['items'] ?? []);
        $status = $enquiry->status instanceof PortalEnquiryStatus ? $enquiry->status : PortalEnquiryStatus::tryFrom((string) $enquiry->status);

        [$stageKey, $stageLabel, $stageColor, $hint, $isClosed] = match ($status) {
            PortalEnquiryStatus::InReview => ['quotation', 'Pricing review', 'blue', 'Approved for pricing · prepare the quotation', false],
            PortalEnquiryStatus::Rejected => ['closed', 'Rejected', 'danger', 'Rejected at review', true],
            PortalEnquiryStatus::Cancelled => ['closed', 'Cancelled', 'gray', 'Cancelled by customer', true],
            default => ['enquiry', 'New enquiry', 'gray', 'New submission · review required', false],
        };

        $pickup = str($enquiry->pickup_address ?? 'Pickup')->limit(28)->toString();
        $drop = $destinations->isNotEmpty() ? $this->destinationLabel($destinations->first(), 0) : '—';
        if ($destinations->count() > 1) {
            $drop .= ' +'.($destinations->count() - 1);
        }

        return [
            'kind' => 'enquiry',
            'id' => $enquiry->id,
            'enquiry_id' => $enquiry->id,
            'reference' => $enquiry->reference_no ?? '—',
            'customer' => $enquiry->customer?->company_name ?? '—',
            'meta' => trim(($enquiry->branch?->code ?? '').' · '.($enquiry->salesperson?->name ?? 'no salesperson').' · '.$enquiry->created_at?->format('d/m/Y H:i')),
            'route' => $pickup.' → '.$drop,
            'items_summary' => $this->itemsSummary($items).($enquiry->preferred_delivery_date ? ' · Delivery '.$enquiry->preferred_delivery_date->format('d/m/Y') : ''),
            'order_type' => $enquiry->order_type?->getLabel(),
            'service_type' => $enquiry->service_type?->getLabel(),
            'step' => $stageKey === 'quotation' ? 2 : 1,
            'stage_key' => $stageKey,
            'stage_label' => $stageLabel,
            'stage_color' => $stageColor,
            'stage_hint' => $hint,
            'payment_label' => 'Not requested',
            'payment_hint' => 'After confirmation',
            'amount' => 'Not priced',
            'amount_muted' => true,
            'next_step' => $isClosed ? 'View enquiry' : ($status === PortalEnquiryStatus::InReview ? 'Provide pricing' : 'Review submitted order'),
            'next_url' => null,
            'is_closed' => $isClosed,
            'sort_at' => $enquiry->created_at?->timestamp ?? 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Orders (latest quotation versions)
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, array<string, mixed>> */
    private function orderRows(array $filters): Collection
    {
        $query = Quotation::query()
            ->with(['customer', 'branch', 'salesperson', 'saLocation', 'destinations', 'lines', 'proformaInvoice', 'portalEnquiry'])
            ->withCount(['consignmentNotes', 'invoices'])
            ->where('status', '!=', QuotationStatus::Superseded->value);

        $this->scopeCompany($query, 'company_id');
        $this->applyCommonFilters($query, $filters, 'quotations');

        if (filled($filters['order_type'] ?? null)) {
            $query->where('order_type', $filters['order_type']);
        }

        if (filled($filters['billing_status'] ?? null)) {
            $query->where('billing_status', $filters['billing_status']);
        }

        return $query->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (Quotation $quotation) => $this->orderRow($quotation));
    }

    /** @return array<string, mixed> */
    private function orderRow(Quotation $q): array
    {
        $status = $q->status;
        $billing = $q->billingStatus();
        $hasProforma = $q->proformaInvoice !== null;
        $hasCsn = ($q->consignment_notes_count ?? 0) > 0;
        $isClosed = false;

        [$stageKey, $step, $stageLabel, $stageColor, $hint, $nextStep] = match (true) {
            $status === QuotationStatus::Converted || $hasCsn => ['csn', 7, 'CSN created', 'approved', 'Billing issued · continue in CSN', 'View CSN'],
            $status === QuotationStatus::Confirmed && $billing === BillingStatus::Generated => ['billed', 6, 'Billing issued', 'approved', 'Invoice / Cash Bill generated', 'View billing'],
            $status === QuotationStatus::Confirmed && $billing === BillingStatus::Failed => ['payment', 5, 'Billing failed', 'danger', 'Billing generation failed · retry', 'Retry billing'],
            $status === QuotationStatus::Confirmed => ['payment', 5, 'Awaiting payment / release', 'blue', $billing->getLabel(), 'Review payment'],
            $status === QuotationStatus::PendingApproval => ['confirmation', 3, 'Credit approval', 'blue', 'Customer confirmed · awaiting branch manager credit approval', 'Review credit approval'],
            $status === QuotationStatus::Accepted => [$hasProforma ? 'payment' : 'confirmation', $hasProforma ? 4 : 3, 'Customer confirmed', 'approved', $hasProforma ? 'Proforma issued' : 'Confirmation recorded', 'View order'],
            in_array($status, [QuotationStatus::Sent, QuotationStatus::PendingReview], true) => ['awaiting_customer', 2, 'Awaiting customer', 'blue', $status === QuotationStatus::PendingReview ? 'Pending customer review · no response yet' : 'Quotation sent · customer has not confirmed', 'View quotation'],
            $status === QuotationStatus::Negotiation => ['awaiting_customer', 2, 'Negotiation', 'blue', 'Customer asked for changes · revise quotation', 'Revise quotation'],
            $status === QuotationStatus::Draft => ['quotation', 2, 'Preparing quotation', 'gray', (float) $q->total_amount > 0 ? 'Priced · ready to send' : 'Pricing in progress', (float) $q->total_amount > 0 ? 'Send quotation' : 'Provide pricing'],
            default => ['closed', 2, $status->getLabel(), 'danger', $q->rejection_reason ?: $q->closed_reason ?: $status->getLabel(), 'View order'],
        };

        if ($stageKey === 'closed') {
            $isClosed = true;
        }

        $firstDestination = $q->destinations->sortBy('sequence')->first();
        $drop = $firstDestination?->consignee_name ?? $q->consignee_name ?? '—';
        if ($q->destinations->count() > 1) {
            $drop .= ' +'.($q->destinations->count() - 1);
        }
        $pickup = str($q->pickup_location ?: ($q->branch?->code ?? 'Pickup'))->limit(28)->toString();

        $paid = (float) $q->paid_amount;
        $total = (float) $q->total_amount;
        $paymentLabel = match (true) {
            $stageKey === 'enquiry', $status === QuotationStatus::Draft, in_array($stageKey, ['awaiting_customer'], true) => 'Not requested',
            $q->orderType() === OrderType::Cod => $q->cod_blocked ? 'COD blocked' : 'COD',
            $q->orderType() === OrderType::Term => $q->isReleased() ? 'Credit · released' : 'Credit',
            $paid + 0.005 >= $total && $total > 0 => 'Paid',
            $paid > 0 => 'Partial',
            default => 'Unpaid',
        };
        $paymentHint = match (true) {
            $paid > 0 => 'Paid RM '.number_format($paid, 2).($paid + 0.005 < $total ? ' · outstanding RM '.number_format($total - $paid, 2) : ''),
            $hasProforma => 'Proforma '.$q->proformaInvoice->number,
            default => 'After confirmation',
        };

        $nextUrl = match ($stageKey) {
            'csn' => ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $q->id]]]),
            default => QuotationResource::getUrl('view', ['record' => $q]),
        };

        return [
            'kind' => 'order',
            'id' => $q->id,
            'enquiry_id' => $q->portal_enquiry_id,
            'reference' => $q->number,
            'customer' => $q->customer?->company_name ?? '—',
            'meta' => trim(($q->branch?->code ?? '').' · '.($q->salesperson?->name ?? 'no salesperson').' · '.$q->created_at?->format('d/m/Y H:i')).($q->version > 1 ? ' · v'.$q->version : '').($q->customer_do_number ? ' · DO '.$q->customer_do_number : ''),
            'route' => $pickup.' → '.$drop,
            'items_summary' => $this->linesSummary($q->lines).($q->expected_delivery_date ? ' · Delivery '.$q->expected_delivery_date->format('d/m/Y') : ''),
            'order_type' => $q->orderType()?->getLabel(),
            'service_type' => $q->service_type?->getLabel(),
            'step' => $step,
            'stage_key' => $stageKey,
            'stage_label' => $stageLabel,
            'stage_color' => $stageColor,
            'stage_hint' => $hint,
            'payment_label' => $paymentLabel,
            'payment_hint' => $paymentHint,
            'amount' => $total > 0 ? 'RM '.number_format($total, 2) : 'Not priced',
            'amount_muted' => $total <= 0,
            'next_step' => $nextStep,
            'next_url' => $nextUrl,
            'is_closed' => $isClosed,
            'sort_at' => $q->created_at?->timestamp ?? 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function scopeCompany(Builder $query, string $column): void
    {
        if ($companyId = CurrentCompany::id()) {
            $query->where($column, $companyId);
        }
    }

    private function applyCommonFilters(Builder $query, array $filters, string $table): void
    {
        if (filled($filters['search'] ?? null)) {
            $needle = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($needle, $table): void {
                $builder->where($table === 'quotations' ? 'number' : 'reference_no', 'like', '%'.$needle.'%')
                    ->orWhere('customer_do_number', 'like', '%'.$needle.'%')
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('company_name', 'like', '%'.$needle.'%')->orWhere('code', 'like', '%'.$needle.'%'));

                if ($table === 'portal_enquiries') {
                    $builder->orWhere('pickup_address', 'like', '%'.$needle.'%');
                }
            });
        }

        if (filled($filters['salesperson_id'] ?? null)) {
            $query->where('salesperson_id', $filters['salesperson_id']);
        }

        if (filled($filters['sa_location_id'] ?? null)) {
            $query->where('sa_location_id', $filters['sa_location_id']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', Carbon::parse((string) $filters['date_from'])->toDateString());
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', Carbon::parse((string) $filters['date_to'])->toDateString());
        }
    }

    /** @param  Collection<int, array<string, mixed>>  $items */
    private function itemsSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No items listed';
        }

        $first = $items->first();
        $qty = rtrim(rtrim(number_format((float) ($first['quantity'] ?? 1), 3, '.', ''), '0'), '.');

        return trim($qty.' '.strtoupper((string) ($first['uom'] ?? 'UNIT'))).($items->count() > 1 ? ' · +'.($items->count() - 1).' more' : '');
    }

    private function linesSummary(Collection $lines): string
    {
        $distinct = $lines->unique('item_name');

        if ($distinct->isEmpty()) {
            return 'No items yet';
        }

        $first = $distinct->first();
        $qty = rtrim(rtrim(number_format((float) $first->quantity, 3, '.', ''), '0'), '.');

        return trim($qty.' '.strtoupper((string) ($first->uom ?: 'x')).' '.str($first->item_name)->limit(22)).($distinct->count() > 1 ? ' · +'.($distinct->count() - 1).' more' : '');
    }

    /** @param  array<string, mixed>  $destination */
    private function destinationLabel(array $destination, int $index): string
    {
        return (string) ($destination['consignee_name'] ?? $destination['city'] ?? $destination['state'] ?? 'Destination '.($index + 1));
    }
}

<?php

namespace App\Support;

use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Filament\Pages\EditOrder;
use App\Filament\Pages\OrderDetail;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\PaymentSubmissionResource;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * View model for the order detail page (header, 7-step progress, action banner and the
 * Overview / Items & pricing / Payment & billing / Documents / Activity tabs).
 *
 * Works for an enquiry that has not been priced yet ("customer-submitted order") and for an
 * order record (quotation) at any later stage.
 */
class OrderDetailData
{
    public function __construct(private QuotationPricingLookup $lookup) {}

    /** @return array<string, mixed>|null */
    public function for(string $type, int $id): ?array
    {
        $order = null;
        $enquiry = null;

        if ($type === 'order') {
            $order = Quotation::query()
                ->with(['customer', 'branch', 'salesperson', 'saLocation', 'creator', 'destinations', 'lines', 'proformaInvoice', 'invoices', 'payments', 'paymentSubmissions.submitter', 'consignmentNotes.deliveryOrder.lorry', 'portalEnquiry.user', 'portalEnquiry.salesperson', 'portalEnquiry.attendee', 'fromLocation', 'toLocation', 'releaser', 'refundNotes', 'notificationLogs', 'statusLogs.user', 'root'])
                ->find($id);

            if (! $order || ($companyId = CurrentCompany::id()) && (int) $order->company_id !== (int) $companyId) {
                return null;
            }

            $enquiry = $order->portalEnquiry;
        } else {
            $enquiry = PortalEnquiry::query()
                ->with(['customer', 'branch', 'user', 'salesperson', 'saLocation', 'locker', 'attendee', 'quotations'])
                ->find($id);

            if (! $enquiry || ($companyId = CurrentCompany::id()) && $enquiry->company_id && (int) $enquiry->company_id !== (int) $companyId) {
                return null;
            }
        }

        $stage = $order ? OrderStage::forOrder($order) : OrderStage::forEnquiry($enquiry);
        $orderType = $order?->orderType() ?? $enquiry?->order_type;
        $cashFlow = ! in_array($orderType, [OrderType::Term, OrderType::Cod], true);
        $payment = $order ? OrderStage::paymentForOrder($order, $stage) : OrderStage::paymentForEnquiry($enquiry);
        $siblings = $this->siblings($enquiry, $order);
        $viewer = auth()->user();
        $lockedByOther = $enquiry ? $enquiry->isLockedByOther($viewer) : false;

        return [
            'type' => $order ? 'order' : 'enquiry',
            'id' => $order?->id ?? $enquiry->id,
            'order' => $order,
            'enquiry' => $enquiry,
            'number' => $order ? $order->orderNumber() : $enquiry->orderNumber(),
            'document_number' => $order?->number,
            'version' => $order?->version,
            'customer' => $order?->customer?->company_name ?? $enquiry?->customer?->company_name ?? '—',
            'customer_id' => $order?->customer_id ?? $enquiry?->customer_id,
            'stage' => $stage,
            'payment' => $payment,
            'order_type' => $order?->orderType()?->getLabel() ?? $enquiry?->order_type?->getLabel(),
            'cash_flow' => $cashFlow,
            'show_payment' => $order && ($order->status->isConfirmedOrLater() || $order->paymentSubmissions->isNotEmpty()),
            'show_decision' => $order && in_array($order->status, [QuotationStatus::Sent, QuotationStatus::PendingReview], true) && $order->isLatestVersion(),
            'steps' => OrderStage::steps($stage, $order, $cashFlow),
            'banner' => $this->banner($stage, $payment, $order, $enquiry),
            'siblings' => $siblings,
            'lock' => ['locked_by_other' => $lockedByOther, 'locked_by' => $lockedByOther ? ($enquiry?->locker?->name ?? 'another user') : null],
            'overview' => $order ? $this->orderOverview($order, $enquiry, $stage, $payment, $siblings) : $this->enquiryOverview($enquiry, $stage, $payment),
            'pricing' => $this->pricingTab($order, $enquiry, $stage),
            'payment_tab' => $this->paymentTab($order, $enquiry, $stage, $payment),
            'documents' => $this->documents($order, $enquiry),
            'activity' => $this->activity($order, $enquiry),
            'can' => $this->permissions($order, $enquiry, $stage, $lockedByOther),
            'urls' => [
                'index' => \App\Filament\Pages\Orders::getUrl(),
                'enquiry' => $enquiry ? OrderDetail::urlFor('enquiry', $enquiry->id) : null,
                'csn' => $order && $order->consignmentNotes->isNotEmpty()
                    ? ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $order->id]]])
                    : null,
                'full_editor' => $this->editOrderUrl($order, $enquiry, $lockedByOther),
                'quotation_view' => $order ? $this->pdfUrl('quotations', 'quotation', $order) : null,
                'quotation_pdf' => $order ? $this->pdfUrl('quotations', 'quotation', $order) : null,
                'payment_review' => PaymentSubmissionResource::getUrl('index'),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed>|null */
    private function banner(array $stage, array $payment, ?Quotation $order, ?PortalEnquiry $enquiry): ?array
    {
        $total = (float) ($order?->total_amount ?? 0);

        return match ($stage['key']) {
            'enquiry' => $this->b('info', 'Review submitted order', 'Customer submitted the form. Admin must review the details and provide pricing.', 'Review submitted order →', 'scroll', 'og-admin-action'),
            'pending_salesperson' => $this->b('warning', 'Pending salesperson', 'No salesperson owns this order yet. Assign one to see product prices and continue with pricing.', 'Assign salesperson →', 'scroll', 'og-assign-salesperson'),
            'quotation' => $order?->status === QuotationStatus::Negotiation
                ? $this->b('warning', 'Customer rejected the quotation', 'Reason: '.($order->rejection_reason ?: 'not given').' · Edit the pricing and send it again.', 'Edit pricing →', 'tab', 'pricing')
                : ($order
                ? ($total > 0
                    ? $this->b('info', 'Send for confirmation', 'Pricing saved. Preview the quotation and send it so the customer can accept or reject.', 'Preview quotation →', 'method', 'openPreview')
                    : $this->b('info', 'Provide pricing', 'Admin is preparing charges. Customer has not confirmed the price.', 'Provide pricing →', 'tab', 'pricing'))
                : $this->b('info', 'Provide pricing', 'Create the order record for each consignee and enter the charges. Customer has not confirmed any price.', 'Provide pricing →', 'method', 'startPricing')),
            'awaiting_customer' => $order?->status === QuotationStatus::Negotiation
                ? $this->b('warning', 'Customer requested changes', 'Revise the quotation (new version) and send it again.', 'Revise quotation →', 'action', 'revise')
                : $this->b('customer', 'Awaiting customer confirmation', 'Quotation sent · customer has not confirmed. Record the confirmation if it arrives by WhatsApp or email.', 'Record customer confirmation', 'action', 'accept'),
            'confirmation' => $order?->status === QuotationStatus::PendingApproval
                ? $this->b('warning', 'Credit approval required', 'Customer confirmed. This term order awaits branch manager credit approval.', 'Review credit approval →', 'action', 'creditDecision')
                : $this->b('info', 'Customer confirmed', 'Confirmation recorded · the proforma invoice is being issued.', 'Open payment & billing →', 'tab', 'payment'),
            'payment' => match ($stage['color']) {
                'issue' => $order?->cod_blocked
                    ? $this->b('danger', 'COD order blocked', $stage['hint'], 'Unblock COD →', 'action', 'unblockCod')
                    : $this->b('danger', 'Billing generation failed', $order?->billing_error ?: $stage['hint'], 'Retry billing →', 'action', 'generateBilling'),
                'action' => $order?->paymentSubmissions->contains(fn ($s) => $s->status->isOpen())
                    ? $this->b('warning', 'Review payment', 'Payment proof submitted · verify it under Payment & billing or obtain Admin release to continue.', 'Review payment →', 'tab', 'payment')
                    : $this->b('warning', 'Review admin release', 'Resolve payment review or obtain Admin release to continue.', 'Review admin release →', 'action', 'release'),
                'released' => $this->b('success', 'Ready to bill', 'Admin released · issue the Invoice / Cash Bill to create the CSN.', 'Generate billing →', 'action', 'generateBilling'),
                default => $this->b('info', 'Awaiting customer payment', 'Proforma issued · waiting for payment proof. Submissions appear under Payment & billing.', 'Review payment →', 'tab', 'payment'),
            },
            'billed' => $this->b('success', 'Billing issued', 'Invoice / Cash Bill issued · create the CSN to continue to dispatch.', 'Create CSN →', 'action', 'generateBilling'),
            'csn' => $this->b('success', 'CSN created', 'Invoice issued · continue in CSN management.', 'View CSN →', 'url', $order ? ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $order->id]]]) : null),
            'closed' => $this->b('danger', $stage['label'], $stage['hint'], $order?->status === QuotationStatus::Closed ? 'Reopen case' : null, 'action', 'reopen'),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function b(string $tone, string $title, string $text, ?string $ctaLabel, string $ctaKind, ?string $ctaValue): array
    {
        return ['tone' => $tone, 'title' => $title, 'text' => $text, 'cta_label' => $ctaLabel, 'cta_kind' => $ctaKind, 'cta_value' => $ctaValue];
    }

    /** Other order records under the same order number. */
    private function siblings(?PortalEnquiry $enquiry, ?Quotation $order): array
    {
        if (! $enquiry) {
            return [];
        }

        return Quotation::query()
            ->where('portal_enquiry_id', $enquiry->id)
            ->where('status', '!=', QuotationStatus::Superseded->value)
            ->with(['destinations', 'toLocation'])
            ->orderBy('id')
            ->get()
            ->map(function (Quotation $q) use ($order) {
                $stage = OrderStage::forOrder($q);

                return [
                    'id' => $q->id,
                    'number' => $q->number,
                    'version' => $q->version,
                    'consignee' => $q->consignee_name ?: ($q->destinations->sortBy('sequence')->first()?->consignee_name ?? $q->toLocation?->name ?? '—'),
                    'to' => $q->toLocation?->name ?? $q->destinations->sortBy('sequence')->first()?->consignee_name,
                    'status' => $stage['label'],
                    'color' => $stage['color'],
                    'total' => (float) $q->total_amount > 0 ? 'RM '.number_format((float) $q->total_amount, 2) : 'Not priced',
                    'url' => OrderDetail::urlFor('order', $q->id),
                    'current' => $order && (int) $order->id === (int) $q->id,
                ];
            })
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Overview
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function enquiryOverview(PortalEnquiry $enquiry, array $stage, array $payment): array
    {
        $payload = $enquiry->payload ?? [];
        $destinations = collect($payload['destinations'] ?? [])->values();
        $items = collect($payload['items'] ?? [])->values();
        $enteredBy = $enquiry->user?->name ?? ($payload['entered_by'] ?? null) ?? ($enquiry->source === PortalEnquiry::SOURCE_ADMIN ? ($enquiry->attendee?->name ?? 'Admin') : 'Customer');

        return [
            'mode' => 'enquiry',
            'form' => [
                'source_label' => $enquiry->sourceLabel(),
                'submitted_by' => $enteredBy,
                'submitted_at' => $enquiry->created_at?->format('d M Y · H:i') ?? '—',
                'customer' => $enquiry->customer?->company_name ?? '—',
                'salesperson' => $enquiry->salesperson?->name,
                'enquiry_ref' => $enquiry->reference_no,
                'do_number' => $enquiry->customer_do_number ?: '—',
                'requested_delivery' => $enquiry->preferred_delivery_date?->format('Y-m-d') ?? '—',
                'order_type' => $enquiry->order_type?->getLabel() ?? '—',
                'service_type' => $enquiry->service_type?->getLabel(),
                'payment_method' => PaymentMethod::tryFrom((string) $enquiry->payment_method)?->getLabel(),
                'pickup' => ['title' => app(OrderListingData::class)->cityFromAddress($enquiry->pickup_address) ?? ($enquiry->branch?->name ?? 'Pickup'), 'sub' => $enquiry->pickup_address ?: '—'],
                'destinations' => $destinations->map(fn (array $d, int $i) => [
                    'title' => trim(($d['city'] ?? $d['consignee_name'] ?? 'Destination '.($i + 1)).(filled($d['drop_off_type'] ?? null) ? ' · '.(DropOffType::tryFrom((string) $d['drop_off_type'])?->getLabel() ?? ucfirst((string) $d['drop_off_type'])) : '')),
                    'sub' => collect([$d['consignee_name'] ?? null, $d['address'] ?? null, $d['postcode'] ?? null, $d['state'] ?? null])->filter()->implode(', ') ?: '—',
                ])->all(),
                'items' => $items->map(fn (array $item) => [
                    'name' => $item['item_name'] ?? '—',
                    'qty' => rtrim(rtrim(number_format((float) ($item['quantity'] ?? 1), 3, '.', ''), '0'), '.').' '.strtoupper((string) ($item['uom'] ?? '')),
                ])->all(),
                'instructions' => $enquiry->special_requirements,
                'attachments' => $this->attachments($enquiry->attachments ?? []),
            ],
            'admin_action' => [
                'status_label' => $stage['label'],
                'status_color' => $stage['color'],
                'text' => match ($stage['key']) {
                    'pending_salesperson' => 'Assign a salesperson so the order has an owner, then check the route, quantities and DO number and prepare the customer\'s price.',
                    'quotation' => 'Approved for pricing. Create the order record(s) and enter the charges.',
                    'closed' => $stage['hint'],
                    default => 'Check the route, quantities and DO number, then prepare the customer\'s price.',
                },
                'quoted_total' => 'Not priced',
                'confirmation' => 'Not requested',
                'note' => 'No Invoice / Cash Bill or CSN at this stage.',
            ],
            'ownership' => [
                'origin' => $enquiry->sourceLabel(),
                'entered_by' => $enteredBy,
                'editing' => $enquiry->isLockedByOther(auth()->user()) ? 'Locked by '.($enquiry->locker?->name ?? 'another user') : 'Available',
                'acceptance' => $enquiry->customer?->consentSkipsReconfirmation() ? 'Consent letter on file' : 'Separate confirmation required',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function orderOverview(Quotation $order, ?PortalEnquiry $enquiry, array $stage, array $payment, array $siblings): array
    {
        $total = (float) $order->total_amount;
        $paid = (float) $order->paid_amount;
        $destinations = $order->destinations->sortBy('sequence')->values();
        $types = collect($order->destination_types ?? [])->keyBy('column');

        $linked = [];
        $linked[] = ['title' => $order->number, 'sub' => 'Quotation · Version '.$order->version, 'status' => $order->sent_at ? 'Issued' : ($order->status === QuotationStatus::Draft ? 'Draft' : $order->status->getLabel()), 'color' => $order->sent_at ? 'progress' : 'gray', 'url' => $this->pdfUrl('quotations', 'quotation', $order)];
        $linked[] = $order->proformaInvoice
            ? ['title' => 'Proforma Invoice', 'sub' => $order->proformaInvoice->number, 'status' => 'Issued', 'color' => 'progress', 'url' => $this->pdfUrl('proforma-invoices', 'proformaInvoice', $order->proformaInvoice)]
            : ['title' => 'Proforma Invoice', 'sub' => 'Issued on customer confirmation', 'status' => 'Not issued', 'color' => 'gray', 'url' => null];

        if ($order->invoices->isEmpty()) {
            $linked[] = ['title' => 'Invoice / Cash Bill', 'sub' => 'Required before CSN creation', 'status' => 'Not issued', 'color' => 'gray', 'url' => null];
        } else {
            foreach ($order->invoices as $invoice) {
                $linked[] = ['title' => $invoice->isCashBill() ? 'Cash Bill' : 'Invoice', 'sub' => $invoice->number, 'status' => 'Issued', 'color' => 'done', 'url' => $this->pdfUrl('invoices', 'invoice', $invoice)];
            }
        }

        foreach ($order->consignmentNotes as $csn) {
            $linked[] = ['title' => 'CSN', 'sub' => $csn->number.($csn->deliveryOrder ? ' · DO '.$csn->deliveryOrder->number : ''), 'status' => $csn->status?->getLabel() ?? '—', 'color' => 'progress', 'url' => ConsignmentNoteResource::getUrl('view', ['record' => $csn])];
        }

        $note = match (true) {
            $order->consignmentNotes->isNotEmpty() => 'CSN created · continue in CSN management',
            $order->billingStatus() === BillingStatus::Generated => 'Billing issued · CSN pending',
            $order->isReleased() => 'Released by '.($order->releaser?->name ?? 'Admin').' on '.$order->released_at?->format('d/m/Y H:i'),
            default => 'CSN not created · Billing must be issued first',
        };

        return [
            'mode' => 'order',
            'customer_order' => [
                'customer' => $order->customer?->company_name ?? '—',
                'salesperson_sa' => trim(($order->salesperson?->name ?? 'Not assigned').' / '.($order->saLocation?->code ?? $order->branch?->code ?? '—')),
                'enquiry_ref' => $enquiry?->reference_no ?? ($order->orderNumber()),
                'enquiry_url' => $enquiry ? OrderDetail::urlFor('enquiry', $enquiry->id) : null,
                'do_number' => $order->customer_do_number ?: '—',
                'order_type' => $order->orderType()?->getLabel() ?? '—',
                'service_type' => $order->service_type?->getLabel(),
                'consent' => $order->customer?->consentSkipsReconfirmation() ? 'Consent letter on file' : 'Pricing reconfirmation required',
                'expected_delivery' => $order->expected_delivery_date?->format('d M Y'),
                'pickup' => ['title' => 'Pickup · '.($order->fromLocation?->name ?? app(OrderListingData::class)->cityFromAddress($order->pickup_location) ?? ($order->branch?->name ?? '—')), 'sub' => trim(($order->consignor_name ?: $order->customer?->company_name).' · '.($order->pickup_location ?: ($order->customer_address ?: '—')), ' ·')],
                'dropoffs' => $destinations->map(function ($d) use ($types, $order) {
                    $type = $types->get($d->consignee_name)['drop_off_type'] ?? null;

                    return [
                        'title' => 'Drop-off · '.($order->toLocation?->name ?? $d->city ?? $d->consignee_name).($order->consignee_name ? ' · '.$order->consignee_name : ''),
                        'sub' => trim(($type ? (DropOffType::tryFrom((string) $type)?->getLabel() ?? ucfirst((string) $type)).' · ' : '').($order->drop_off_location ?: ($d->address !== $d->consignee_name ? $d->address : ($order->consignee_name ?: $d->consignee_name)))),
                    ];
                })->all(),
            ],
            'linked' => $linked,
            'other_orders' => array_values(array_filter($siblings, fn ($s) => ! $s['current'])),
            'payment_card' => [
                'label' => $payment['label'],
                'color' => $payment['color'],
                'order_type' => $order->orderType()?->getLabel() ?? '—',
                'total' => 'RM '.number_format($total, 2),
                'paid' => 'RM '.number_format($paid, 2),
                'outstanding' => 'RM '.number_format(max(0, $total - $paid), 2),
                'note' => $note,
            ],
            'ownership' => [
                'handled_by' => $order->salesperson?->name ?? 'Not assigned',
                'editing' => $order->status->isEditable() ? 'Available' : 'Locked (version '.$order->version.' · '.$order->status->getLabel().')',
                'created' => $order->created_at?->format('Y-m-d') ?? '—',
                'last_activity' => ($order->statusLogs->sortByDesc('created_at')->first()?->created_at ?? $order->updated_at)?->format('d M Y · H:i') ?? '—',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Items & pricing
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function pricingTab(?Quotation $order, ?PortalEnquiry $enquiry, array $stage): array
    {
        if (! $order) {
            $payload = $enquiry?->payload ?? [];
            $destinations = collect($payload['destinations'] ?? []);

            return [
                'mode' => 'start',
                'destinations_count' => max(1, $destinations->count()),
                'can_start' => $enquiry && $enquiry->salesperson_id && ! in_array($stage['key'], ['closed'], true),
                'why_not' => ! $enquiry?->salesperson_id ? 'Assign a salesperson before pricing.' : ($stage['key'] === 'closed' ? 'This enquiry is closed.' : null),
            ];
        }

        $editable = $order->status->isEditable() && $order->isLatestVersion();
        $columns = $order->destinations->sortBy('sequence')->pluck('consignee_name')->values()->all();
        $charges = $order->lines->filter(fn ($l) => in_array($l->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true));
        $itemLines = $order->lines->reject(fn ($l) => in_array($l->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true));
        $destinationById = $order->destinations->keyBy('id');

        $rows = $itemLines->map(function ($line) use ($destinationById, $order) {
            $destination = $destinationById->get($line->quotation_destination_id);
            $column = $destination?->consignee_name ?? '—';
            $tier = $this->lookup->matchedUomTier($line->item_name, $column, (float) $line->quantity);

            return [
                'item' => $line->item_name,
                'sub' => null,
                'qty_range' => rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.').' unit(s)',
                'range' => $tier ? 'Range: '.($tier->max_qty ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0) : number_format((float) $tier->min_qty, 0).'+') : null,
                'route' => ($order->fromLocation?->name ?? app(OrderListingData::class)->cityFromAddress($order->pickup_location) ?? $order->branch?->name ?? 'Pickup').' → '.$column,
                'unit' => 'RM '.number_format((float) $line->unit_price, 2),
                'charge' => 'RM '.number_format((float) $line->line_total, 2),
            ];
        })->values()->all();

        foreach ($charges as $charge) {
            $rows[] = ['item' => $charge->item_name, 'sub' => 'Additional charge', 'qty_range' => '1', 'range' => null, 'route' => '—', 'unit' => 'RM '.number_format((float) $charge->unit_price, 2), 'charge' => 'RM '.number_format((float) $charge->line_total, 2)];
        }

        $badge = match ($order->pricing_source) {
            'special' => ['Customer Special', 'customer'],
            'previous' => ['Previous quotation', 'progress'],
            'manual' => ['Manual pricing', 'action'],
            default => ['Price list', 'progress'],
        };

        $basis = [
            ['label' => 'Pricing source', 'value' => $badge[0]],
            ['label' => 'Override reason', 'value' => $order->pricing_override_reason ?: 'None · rates match the price list'],
            ['label' => 'Version', 'value' => 'Version '.$order->version.($order->revisionOf ? ' · revised from '.$order->revisionOf->number : '')],
            ['label' => 'Valid until', 'value' => $order->valid_until?->format('d M Y') ?? '—'],
        ];

        return [
            'mode' => $editable ? 'edit' : 'view',
            'editable' => $editable,
            'table' => [
                'badge' => $badge[0],
                'badge_color' => $badge[1],
                'rows' => $rows,
                'total' => 'RM '.number_format((float) $order->total_amount, 2),
                'basis' => $basis,
            ],
            'before' => [
                'do_number' => $order->customer_do_number ?: '—',
                'salesperson' => $order->salesperson?->name ?? 'Not assigned',
                'consent' => $order->customer?->consentSkipsReconfirmation() ? 'Consent letter on file' : 'Pricing reconfirmation required',
                'quotation' => $order->number,
                'version' => (string) $order->version,
                'payment' => $order->proformaInvoice ? 'Proforma '.$order->proformaInvoice->number : 'Not requested',
                'billing' => $order->invoices->isNotEmpty() ? $order->invoices->pluck('number')->implode(', ') : 'Not created',
            ],
            'preview' => [
                'number' => $order->number,
                'version' => $order->version,
                'status' => $order->status->getLabel(),
                'customer' => $order->customer?->company_name ?? '—',
                'route' => ($order->fromLocation?->name ?? app(OrderListingData::class)->cityFromAddress($order->pickup_location) ?? $order->branch?->name ?? 'Pickup').' → '.implode(', ', $columns),
                'items' => $itemLines->unique('item_name')->map(fn ($l) => $l->item_name.' × '.rtrim(rtrim(number_format((float) $l->quantity, 3, '.', ''), '0'), '.'))->implode('; ') ?: '—',
                'order_type' => $order->orderType()?->getLabel() ?? '—',
                'total' => 'RM '.number_format((float) $order->total_amount, 2),
                'remarks' => $order->notes,
            ],
            'columns' => $columns,
        ];
    }

    /**
     * Initial state for the Admin pricing form.
     *
     * @return array<string, mixed>
     */
    public function pricingState(Quotation $order): array
    {
        $order->loadMissing(['destinations', 'lines', 'customer']);
        $columns = $order->destinations->sortBy('sequence')->pluck('consignee_name')->values()->all();
        $destinationById = $order->destinations->keyBy('id');
        $customerId = $order->customer_id ? (int) $order->customer_id : null;

        $rows = [];
        foreach ($order->lines as $line) {
            if (in_array($line->item_name, CreateOrderFromEnquiry::CHARGE_LINES, true)) {
                continue;
            }

            $column = $destinationById->get($line->quotation_destination_id)?->consignee_name;
            $key = $line->item_name;

            if (! isset($rows[$key])) {
                $catalogKey = $this->lookup->resolveCatalogKey($line->item_name);
                $lineType = $this->lookup->inferLineType($catalogKey, $line->item_name);
                $rows[$key] = [
                    'line_type' => $lineType,
                    'catalog_key' => $catalogKey,
                    'item_name' => $line->item_name,
                    'uom' => $line->uom,
                    'quantity' => (float) $line->quantity,
                    'prices' => array_fill_keys($columns, null),
                    'list' => [],
                ];
            }

            if ($column !== null) {
                $rows[$key]['prices'][$column] = (float) $line->unit_price;
            }
        }

        $rows = array_values($rows);

        foreach ($rows as &$row) {
            $row['list'] = $this->listPrices($customerId, $row['item_name'], $columns, (float) $row['quantity']);
        }
        unset($row);

        $charge = fn (string $name) => (float) ($order->lines->firstWhere('item_name', $name)?->unit_price ?? 0);

        return [
            'reference' => match ($order->pricing_source) {
                'special' => 'customer_special',
                'manual' => 'manual',
                default => 'price_list',
            },
            'columns' => $columns,
            'rows' => $rows,
            'pickup_charge' => $charge('Pickup charge') ?: null,
            'drop_off_charge' => $charge('Drop-off charge') ?: null,
            'other_charges' => $charge('Other charges') ?: null,
            'remarks' => $order->notes,
            'override_reason' => $order->pricing_override_reason,
        ];
    }

    /**
     * Standard (price-list / special / previous) rate per column with a tier label.
     *
     * @param  list<string>  $columns
     * @return array<string, array{price: ?float, source: ?string, tier: ?string}>
     */
    public function listPrices(?int $customerId, string $itemName, array $columns, float $quantity): array
    {
        $out = [];

        foreach ($columns as $column) {
            $resolved = $this->lookup->lookupForCustomer($customerId, $itemName, $column, max(0.01, $quantity));
            $tier = $this->lookup->matchedUomTier($itemName, $column, max(0.01, $quantity));

            $out[$column] = [
                'price' => $resolved['price'] !== null ? (float) $resolved['price'] : null,
                'source' => $resolved['source'],
                'tier' => $tier ? 'Tier '.($tier->max_qty ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0) : number_format((float) $tier->min_qty, 0).'+') : null,
            ];
        }

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | Payment & billing
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function paymentTab(?Quotation $order, ?PortalEnquiry $enquiry, array $stage, array $payment): array
    {
        if (! $order) {
            return ['mode' => 'none', 'text' => 'Payment is requested after the customer confirms the quotation. No proforma, payment or billing exists at this stage.'];
        }

        if (in_array($order->orderType(), [OrderType::Term, OrderType::Cod], true)) {
            $invoice = $order->invoices->sortByDesc('id')->first();

            return [
                'mode' => 'non_cash',
                'text' => $order->orderType() === OrderType::Term
                    ? 'Credit term order · no payment is collected at this stage. Once the customer confirms (and credit approval passes) the invoice is issued and the CSN is created.'
                    : 'COD order · payment is collected on delivery. Once the customer confirms the invoice is issued and the CSN is created, unless Admin blocks the order.',
                'invoice' => $invoice?->number,
                'csn' => $order->consignmentNotes->pluck('number')->implode(', ') ?: null,
                'cod_blocked' => (bool) $order->cod_blocked,
            ];
        }

        $total = (float) $order->total_amount;
        $paid = (float) $order->paid_amount;
        $method = PaymentMethod::tryFrom((string) $order->payment_method);
        $type = $order->orderType();
        $open = $order->paymentSubmissions->filter(fn ($s) => $s->status->isOpen());

        $reviewStatus = match (true) {
            $order->consignmentNotes->isNotEmpty() || $order->billingStatus() === BillingStatus::Generated => 'Billing issued',
            $order->isReleased() => 'Admin released',
            $open->isNotEmpty() => 'Verification pending',
            $type === OrderType::Term => 'Admin release required',
            $type === OrderType::Cod => $order->cod_blocked ? 'COD blocked' : 'Collect on delivery',
            $paid > 0 && $paid + 0.005 < $total => 'Admin release required',
            $paid + 0.005 >= $total && $total > 0 => 'Paid in full',
            $order->status === QuotationStatus::Confirmed => 'Awaiting payment proof',
            default => 'Not requested',
        };

        $note = match (true) {
            $order->billingStatus() === BillingStatus::Failed => 'Billing generation failed: '.($order->billing_error ?: 'see activity'),
            $order->status === QuotationStatus::Confirmed && $paid + 0.005 < $total && $type === OrderType::Cash && ! $order->isReleased() => 'Outstanding balance requires Admin approval before billing and CSN creation.',
            $type === OrderType::Term && ! $order->isReleased() && $order->status === QuotationStatus::Confirmed => 'Credit order · Admin release issues the invoice and creates the CSN.',
            default => null,
        };

        $invoice = $order->invoices->sortByDesc('id')->first();

        return [
            'mode' => 'order',
            'review' => [
                'label' => $payment['label'],
                'color' => $payment['color'],
                'total' => 'RM '.number_format($total, 2),
                'paid' => 'RM '.number_format($paid, 2),
                'outstanding' => 'RM '.number_format(max(0, $total - $paid), 2),
                'method' => $method?->getLabel() ?? '—',
                'order_type' => $type?->getLabel() ?? '—',
                'review_status' => $reviewStatus,
                'approval_levels' => $method?->requiresTwoApprovals() ? 'Two-level (verify + approve)' : 'Standard review',
                'note' => $note,
                'proforma' => $order->proformaInvoice?->number,
            ],
            'submissions' => $order->paymentSubmissions->sortByDesc('id')->map(fn (PaymentSubmission $s) => [
                'id' => $s->id,
                'date' => $s->payment_date?->format('d/m/Y') ?? $s->created_at?->format('d/m/Y'),
                'amount' => 'RM '.number_format((float) $s->amount, 2),
                'method' => $s->method()?->getLabel() ?? '—',
                'reference' => $s->reference ?: '—',
                'status' => $s->status->getLabel(),
                'color' => match ($s->status) {
                    PaymentSubmissionStatus::Approved => 'done',
                    PaymentSubmissionStatus::Verified => 'released',
                    PaymentSubmissionStatus::Rejected, PaymentSubmissionStatus::Cancelled => 'issue',
                    default => 'action',
                },
                'receipt_url' => $s->receipt_path ? Storage::disk('public')->url($s->receipt_path) : null,
                'remarks' => $s->rejection_reason ?: $s->remarks,
                'can_verify' => $s->status === PaymentSubmissionStatus::Submitted && ($s->method()?->requiresTwoApprovals() ?? false),
                'can_approve' => in_array($s->status, [PaymentSubmissionStatus::Submitted, PaymentSubmissionStatus::Verified], true),
                'can_reject' => $s->status->isOpen(),
            ])->values()->all(),
            'billing' => [
                'billing_type' => match ($type) {
                    OrderType::Cash => 'Cash Bill',
                    OrderType::Cod => 'Invoice (COD)',
                    OrderType::Term => 'Invoice (credit term)',
                    default => 'Invoice',
                },
                'invoice' => $invoice ? $invoice->number.' · '.ucfirst((string) $invoice->status) : 'Not issued',
                'dispatch_release' => $order->isReleased() ? 'Released '.$order->released_at?->format('d/m/Y') : ($order->consignmentNotes->isNotEmpty() ? 'CSN created' : 'Not released'),
                'eod' => $order->consignmentNotes->contains(fn ($c) => (string) ($c->status?->value ?? '') === 'delivered') ? 'Done' : 'Pending',
                'autocount' => $invoice ? ($invoice->autocount_sync_status ? ucfirst(str_replace('_', ' ', (string) $invoice->autocount_sync_status)) : 'Waiting for completed EOD') : 'Not applicable yet',
                'refund' => $order->refundNotes->isNotEmpty() ? $order->refundNotes->pluck('number')->implode(', ') : 'Not applicable',
                'note' => 'Term may change to Cash or COD. COD may change to Cash. Cash remains Cash.',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Documents & activity
    |--------------------------------------------------------------------------
    */

    /** @return list<array<string, mixed>> */
    private function documents(?Quotation $order, ?PortalEnquiry $enquiry): array
    {
        $docs = [];

        if ($order) {
            $docs[] = ['title' => 'Quotation', 'sub' => $order->number.' · Version '.$order->version, 'status' => $order->sent_at ? 'Issued' : 'Draft', 'color' => $order->sent_at ? 'progress' : 'gray', 'url' => $this->pdfUrl('quotations', 'quotation', $order), 'action' => 'Preview'];
            $docs[] = $order->proformaInvoice
                ? ['title' => 'Proforma Invoice', 'sub' => $order->proformaInvoice->number, 'status' => 'Issued', 'color' => 'progress', 'url' => $this->pdfUrl('proforma-invoices', 'proformaInvoice', $order->proformaInvoice), 'action' => 'Preview']
                : ['title' => 'Proforma Invoice', 'sub' => 'Issued when the customer confirms', 'status' => 'Not issued', 'color' => 'gray', 'url' => null, 'action' => null];

            if ($order->invoices->isEmpty()) {
                $docs[] = ['title' => 'Invoice / Cash Bill', 'sub' => 'Not issued', 'status' => 'Not issued', 'color' => 'gray', 'url' => null, 'action' => null];
            }

            foreach ($order->invoices as $invoice) {
                $docs[] = ['title' => $invoice->isCashBill() ? 'Cash Bill' : 'Invoice', 'sub' => $invoice->number, 'status' => 'Issued', 'color' => 'done', 'url' => $this->pdfUrl('invoices', 'invoice', $invoice), 'action' => 'Preview'];
            }

            foreach ($order->consignmentNotes as $csn) {
                $docs[] = ['title' => 'CSN', 'sub' => $csn->number, 'status' => $csn->status?->getLabel() ?? '—', 'color' => 'progress', 'url' => $this->pdfUrl('consignment-notes', 'consignmentNote', $csn), 'action' => 'Preview'];

                if ($csn->deliveryOrder) {
                    $doStatus = $csn->deliveryOrder->status;
                    $docs[] = ['title' => 'DO', 'sub' => $csn->deliveryOrder->number.($csn->deliveryOrder->lorry ? ' · '.$csn->deliveryOrder->lorry->registration_no : ''), 'status' => $doStatus instanceof \BackedEnum ? (method_exists($doStatus, 'getLabel') ? $doStatus->getLabel() : $doStatus->value) : ucfirst((string) $doStatus), 'color' => 'progress', 'url' => $this->pdfUrl('delivery-orders', 'deliveryOrder', $csn->deliveryOrder), 'action' => 'Preview'];
                }
            }
        }

        foreach ($this->attachments($enquiry?->attachments ?? []) as $file) {
            $docs[] = ['title' => 'Attachment', 'sub' => $file['name'], 'status' => 'Customer upload', 'color' => 'gray', 'url' => $file['url'], 'action' => 'Open'];
        }

        if ($order) {
            foreach (collect($order->attachments ?? [])->filter() as $path) {
                if ($enquiry && collect($enquiry->attachments ?? [])->pluck('path')->contains($path)) {
                    continue;
                }
                $docs[] = ['title' => 'Attachment', 'sub' => basename((string) $path), 'status' => 'Order upload', 'color' => 'gray', 'url' => Storage::disk('public')->url($path), 'action' => 'Open'];
            }
        }

        return $docs;
    }

    /** @return array<string, mixed> */
    private function activity(?Quotation $order, ?PortalEnquiry $enquiry): array
    {
        $items = collect();
        $seq = 0;
        $push = function ($at, string $title, string $sub, string $kind = 'info') use ($items, &$seq): void {
            if ($at) {
                $items->push(['at' => $at, 'title' => $title, 'sub' => $sub, 'kind' => $kind, 'seq' => $seq++]);
            }
        };

        if ($enquiry) {
            $push($enquiry->created_at, 'Order form received · '.$enquiry->orderNumber(), $enquiry->sourceLabel().' · '.($enquiry->user?->name ?? (($enquiry->payload['entered_by'] ?? null) ?: 'System')), 'start');

            Activity::query()->where('subject_type', $enquiry->getMorphClass())->where('subject_id', $enquiry->id)->with('causer')->get()
                ->reject(fn (Activity $a) => in_array($a->description, ['created', 'updated'], true))
                ->each(fn (Activity $a) => $push($a->created_at, $a->description, $a->causer?->name ?? 'System'));
        }

        if ($order) {
            $push($order->created_at, 'Order record created · '.$order->number.' (version '.$order->version.')', $order->creator?->name ?? 'System', 'start');

            foreach ($order->statusLogs as $log) {
                // invoices and CSNs get their own entries below
                if (str_starts_with((string) $log->remarks, 'Billing issued:') || str_starts_with((string) $log->remarks, 'CSN created:')) {
                    continue;
                }

                $title = $log->remarks ?: ('Status changed to '.(QuotationStatus::tryFrom((string) $log->to_status)?->getLabel() ?? $log->to_status));
                $kind = match (true) {
                    str_contains(strtolower($title), 'rejected') => 'issue',
                    str_contains(strtolower($title), 'price offered'), str_contains(strtolower($title), 'pricing saved') => 'price',
                    default => 'info',
                };
                $push($log->created_at, $title, $log->user?->name ?? 'System', $kind);
            }

            foreach ($order->notificationLogs as $log) {
                $push($log->created_at, ucfirst(str_replace('_', ' ', (string) $log->event)).' · '.ucfirst((string) $log->channel).' ('.$log->status.')', ($log->recipient_name ?: $log->recipient_contact ?: 'Customer').($log->whatsapp_url ? ' · WhatsApp link ready' : ''), 'message');
            }

            if ($order->proformaInvoice) {
                $push($order->proformaInvoice->issued_at ?? $order->proformaInvoice->created_at, 'Proforma invoice issued · '.$order->proformaInvoice->number, 'RM '.number_format((float) $order->proformaInvoice->total_amount, 2), 'doc');
            }

            foreach ($order->paymentSubmissions as $sub) {
                $push($sub->created_at, 'Payment recorded · RM '.number_format((float) $sub->amount, 2), ($sub->method()?->getLabel() ?? '').' · '.($sub->reference ?: 'no reference').' · '.($sub->submitter?->name ?? ucfirst((string) $sub->submitted_channel)), 'payment');

                if ($sub->level1_at && $sub->level2_at === null) {
                    $push($sub->level1_at, 'Payment verified (level 1) · RM '.number_format((float) $sub->amount, 2), 'Finance', 'payment');
                }

                if ($sub->level2_at) {
                    $push($sub->level2_at, $sub->status === PaymentSubmissionStatus::Rejected ? 'Payment rejected · '.($sub->rejection_reason ?: '') : 'Payment approved · RM '.number_format((float) $sub->amount, 2), 'Finance', $sub->status === PaymentSubmissionStatus::Rejected ? 'issue' : 'payment');
                }
            }

            foreach ($order->invoices as $invoice) {
                $push($invoice->created_at, ($invoice->isCashBill() ? 'Cash bill' : 'Invoice').' issued · '.$invoice->number, 'RM '.number_format((float) $invoice->total_amount, 2), 'doc');

                if ($invoice->sent_at) {
                    $push($invoice->sent_at, ($invoice->isCashBill() ? 'Cash bill' : 'Invoice').' sent to customer · '.$invoice->number, 'Email', 'message');
                }
            }

            foreach ($order->consignmentNotes as $csn) {
                $push($csn->created_at, 'CSN created · '.$csn->number, $csn->status?->getLabel() ?? '', 'doc');

                if ($csn->claimed_at) {
                    $push($csn->claimed_at, 'CSN claimed · '.$csn->number, ucfirst((string) ($csn->claim_channel ?? 'admin')), 'info');
                }

                if ($csn->deliveryOrder) {
                    $push($csn->deliveryOrder->created_at, 'Assigned to lorry '.($csn->deliveryOrder->lorry?->registration_no ?? '').' · DO '.$csn->deliveryOrder->number, 'CSN '.$csn->number, 'doc');
                }
            }

            Activity::query()->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->where('description', 'updated')->with('causer')->get()
                ->each(function (Activity $a) use ($push): void {
                    $changed = collect(array_keys((array) ($a->properties['attributes'] ?? [])))
                        ->reject(fn (string $k) => in_array($k, [
                            'status', 'updated_at', 'locked_by', 'locked_at', 'lock_heartbeat_at', 'billing_status', 'billing_error', 'billed_at',
                            'paid_amount', 'sent_at', 'confirmed_at', 'converted_at', 'root_quotation_id', 'subtotal', 'total_amount', 'tax_amount',
                            'pricing_source', 'price_overrides', 'pricing_override_reason', 'cod_blocked', 'cod_block_reason', 'cod_blocked_by', 'cod_blocked_at',
                            'rejection_reason', 'rejection_category', 'released_at', 'released_by', 'release_reason', 'release_outstanding',
                            'accepted_version', 'confirmation_channel', 'confirmed_by_name', 'consent_evidence', 'pending_review_since',
                            'salesperson_id', 'sa_location_id', 'salesperson_locked', 'closed_at', 'closed_reason', 'pricing_reconfirmation_required',
                        ], true))
                        ->map(fn (string $k) => str_replace('_', ' ', $k))
                        ->values();

                    if ($changed->isNotEmpty()) {
                        $push($a->created_at, 'Order details updated · '.$changed->implode(', '), $a->causer?->name ?? 'System', 'info');
                    }
                });
        }

        // Newest first; events logged in the same second keep their logical order (later step on top)
        $sorted = $items
            ->sort(fn ($a, $b) => [$b['at']->getTimestamp(), $b['seq']] <=> [$a['at']->getTimestamp(), $a['seq']])
            ->values()
            ->map(fn ($i) => ['title' => $i['title'], 'date' => $i['at']->format('d M Y · H:i'), 'day' => $i['at']->format('Y-m-d'), 'by' => $i['sub'], 'kind' => $i['kind']])
            ->all();

        return [
            'items' => $sorted,
            'offers' => $order ? $this->offers($order) : [],
            'meta' => [
                'email_control' => SystemSettings::bool(SystemSettings::EMAILS_ENABLED) ? 'Enabled by Admin' : 'Disabled by Admin',
                'notification_history' => $order ? $order->notificationLogs->count().' message(s) logged' : 'No messages yet',
                'customer_confirmation' => $order?->confirmation_channel
                    ? ucfirst((string) $order->confirmation_channel).' · '.$order->confirmed_at?->format('d M Y H:i')
                    : (($order?->customer ?? $enquiry?->customer)?->consentSkipsReconfirmation() ? 'Consent letter on file' : 'Pricing reconfirmation required'),
                'ownership_check' => 'One salesperson per enquiry'.(($order?->salesperson_locked ?? $enquiry?->salesperson_locked) ? ' · locked' : ''),
            ],
        ];
    }

    /**
     * Every price offered to (and accepted / rejected by) the customer, newest first.
     *
     * @return list<array{at: string, text: string, by: string}>
     */
    private function offers(Quotation $order): array
    {
        return $order->statusLogs
            ->filter(fn ($log) => str_contains((string) $log->remarks, 'price offered') || str_contains((string) $log->remarks, 'Customer rejected quotation'))
            ->sortByDesc('created_at')
            ->map(fn ($log) => ['at' => $log->created_at?->format('d M Y · H:i') ?? '', 'text' => (string) $log->remarks, 'by' => $log->user?->name ?? 'System'])
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions / helpers
    |--------------------------------------------------------------------------
    */

    /** @return array<string, bool> */
    private function permissions(?Quotation $order, ?PortalEnquiry $enquiry, array $stage, bool $lockedByOther): array
    {
        $user = auth()->user();
        $isManager = (bool) ($user?->is_hq || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'finance']));
        $enquiryStatus = $enquiry?->status instanceof PortalEnquiryStatus ? $enquiry->status : null;
        $status = $order?->status;
        $latest = $order?->isLatestVersion() ?? false;

        return [
            'approve_enquiry' => ! $order && $enquiry && ! $lockedByOther && $enquiryStatus === PortalEnquiryStatus::Pending,
            'reject_enquiry' => ! $order && $enquiry && ! $lockedByOther && in_array($enquiryStatus, [PortalEnquiryStatus::Pending, PortalEnquiryStatus::InReview], true),
            'assign_salesperson' => $enquiry && ! $lockedByOther && ! $enquiry->salesperson_locked && ! in_array($enquiryStatus, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true),
            'start_pricing' => ! $order && $enquiry && ! $lockedByOther && $enquiry->salesperson_id && in_array($enquiryStatus, [PortalEnquiryStatus::Pending, PortalEnquiryStatus::InReview, PortalEnquiryStatus::Quoted], true),
            'edit_pricing' => $order && $latest && $status->isEditable(),
            'send' => $order && $latest && in_array($status, [QuotationStatus::Draft, QuotationStatus::Negotiation, QuotationStatus::Sent, QuotationStatus::PendingReview], true) && (float) $order->total_amount > 0,
            'accept' => $order && $latest && ($status->isCustomerActionable() || $status === QuotationStatus::Draft) && (float) $order->total_amount > 0,
            'reject' => $order && $latest && ($status->isCustomerActionable() || ($status === QuotationStatus::Draft && (float) $order->total_amount > 0)),
            'revise' => $order && $latest && ! in_array($status, [QuotationStatus::Converted, QuotationStatus::Superseded], true) && (! $status->isConfirmedOrLater() || $user?->isSuperadmin()),
            'change_type' => $order && $status !== QuotationStatus::Converted && ! $status->isTerminal(),
            'release' => $order && $status === QuotationStatus::Confirmed && ! $order->isReleased() && $order->billingStatus() !== BillingStatus::Generated && $isManager,
            'block_cod' => $order && $order->orderType() === OrderType::Cod && ! $order->cod_blocked && $status !== QuotationStatus::Converted,
            'unblock_cod' => $order && $order->orderType() === OrderType::Cod && $order->cod_blocked,
            'generate_billing' => $order && $status === QuotationStatus::Confirmed && ($order->billingStatus() !== BillingStatus::Generated || $order->consignmentNotes->isEmpty()),
            'reopen' => $order && $status === QuotationStatus::Closed,
            'credit_decision' => $order && $status === QuotationStatus::PendingApproval && $isManager && CreditApprovalRequest::query()->where('quotation_id', $order->id)->where('status', 'pending')->exists(),
            'review_payments' => $order && $isManager,
            'send_invoice' => $order && $order->invoices->isNotEmpty(),
        ];
    }

    /**
     * "Edit order" page (the Create order layout in edit mode) while the order still has a record that
     * can be edited; an enquiry that is not priced yet can always be edited unless it was rejected or cancelled.
     */
    private function editOrderUrl(?Quotation $order, ?PortalEnquiry $enquiry, bool $lockedByOther): ?string
    {
        if ($lockedByOther || (! $order && ! $enquiry)) {
            return null;
        }

        $type = $order ? 'order' : 'enquiry';
        $id = (int) ($order?->id ?? $enquiry->id);

        if ($enquiry) {
            if (in_array($enquiry->status, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true)) {
                return null;
            }

            $records = UpdateOrderRecords::recordsFor($enquiry);

            return $records->isEmpty() || $records->contains(fn (Quotation $q) => UpdateOrderRecords::isEditable($q))
                ? EditOrder::urlFor($type, $id)
                : null;
        }

        return UpdateOrderRecords::isEditable($order) ? EditOrder::urlFor($type, $id) : null;
    }

    /** @return list<array{name: string, url: ?string, is_image: bool}> */
    private function attachments(array $files): array
    {
        return collect($files)->map(fn (array $file) => [
            'name' => $file['name'] ?? basename((string) ($file['path'] ?? '')),
            'url' => isset($file['path']) ? Storage::disk('public')->url($file['path']) : null,
            'is_image' => str_starts_with((string) ($file['mime'] ?? ''), 'image/'),
        ])->values()->all();
    }

    private function pdfUrl(string $route, string $param, $record): ?string
    {
        try {
            return route('filament.admin.'.$route.'.pdf', ['tenant' => Filament::getTenant(), $param => $record]);
        } catch (\Throwable) {
            return null;
        }
    }
}

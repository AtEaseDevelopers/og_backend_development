<?php

namespace App\Support;

use App\Domains\Billing\Actions\EditRecordedPayment;
use App\Domains\Billing\Actions\RefreshOrderPaidAmount;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Domains\Quotation\Actions\CreateOrderFromEnquiry;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\BillingStatus;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceType;
use App\Filament\Pages\EditOrder;
use App\Filament\Pages\OrderDetail;
use App\Filament\Resources\ConsignmentNoteResource;
use App\Filament\Resources\PaymentSubmissionResource;
use App\Filament\Resources\RefundNoteResource;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * View model for the order detail page (header, 7-step progress, action banner and the
 * Overview / Items & pricing / Activity tabs; the overview carries the Payment summary and Linked records cards).
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
                ->with(['customer', 'branch', 'salesperson', 'saLocation', 'creator', 'destinations', 'lines', 'proformaInvoice', 'invoices', 'payments', 'paymentSubmissions.submitter', 'consignmentNotes.deliveryOrder.lorry', 'portalEnquiry.user', 'portalEnquiry.salesperson', 'portalEnquiry.attendee', 'fromLocation', 'toLocation', 'storeBranch', 'releaser', 'refundNotes', 'notificationLogs', 'statusLogs.user', 'root'])
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
        // enquiry view of an order that already has records: pricing has started and is not offered again
        $firstRecordId = ! $order && $enquiry ? UpdateOrderRecords::recordsQuery($enquiry)->value('id') : null;
        $firstRecordId = $firstRecordId !== null ? (int) $firstRecordId : null;

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
            'banner' => $this->banner($stage, $payment, $order, $enquiry, $firstRecordId),
            'siblings' => $siblings,
            'lock' => ['locked_by_other' => $lockedByOther, 'locked_by' => $lockedByOther ? ($enquiry?->locker?->name ?? 'another user') : null],
            'overview' => $order ? $this->orderOverview($order, $enquiry, $stage, $payment, $siblings) : $this->enquiryOverview($enquiry, $stage, $payment),
            'pricing' => $this->pricingTab($order, $enquiry, $stage, $firstRecordId),
            'payment_summary' => $order ? $this->paymentSummary($order, $payment) : null,
            'activity' => $this->activity($order, $enquiry),
            'can' => $this->permissions($order, $enquiry, $stage, $lockedByOther, $firstRecordId),
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
    private function banner(array $stage, array $payment, ?Quotation $order, ?PortalEnquiry $enquiry, ?int $firstRecordId = null): ?array
    {
        $total = (float) ($order?->total_amount ?? 0);

        // the enquiry of an order that already has records: continue on the records instead of pricing again
        if (! $order && $firstRecordId && in_array($stage['key'], ['enquiry', 'quotation'], true)) {
            return $this->b('info', 'Order records created', 'Pricing has started for this order. Continue on its order records.', 'Open order →', 'url', OrderDetail::urlFor('order', $firstRecordId));
        }

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
                : $this->b('info', 'Customer confirmed', 'Confirmation recorded · the proforma invoice is being issued.', 'Open payment summary →', 'tab', 'payment'),
            'payment' => match ($stage['color']) {
                'issue' => $order?->cod_blocked
                    ? $this->b('danger', 'COD order blocked', $stage['hint'], 'Unblock COD →', 'action', 'unblockCod')
                    : $this->b('danger', 'Billing generation failed', $order?->billing_error ?: $stage['hint'], 'Retry billing →', 'action', 'generateBilling'),
                'action' => $order?->paymentSubmissions->contains(fn ($s) => $s->status->isOpen())
                    ? $this->b('warning', 'Review payment', 'Payment proof submitted · verify it under Payment summary or obtain Admin release to continue.', 'Review payment →', 'tab', 'payment')
                    : $this->b('warning', 'Review admin release', 'Resolve payment review or obtain Admin release to continue.', 'Review admin release →', 'action', 'release'),
                'released' => $this->b('success', 'Ready to bill', 'Admin released · issue the Invoice / Cash Bill to create the CSN.', 'Generate billing →', 'action', 'generateBilling'),
                default => $this->b('info', 'Awaiting customer payment', 'Proforma issued · waiting for payment proof. Submissions appear under Payment summary.', 'Review payment →', 'tab', 'payment'),
            },
            'billed' => $this->b('success', 'Billing issued', 'Invoice / Cash Bill issued · create the CSN to continue to dispatch.', 'Create CSN →', 'action', 'generateBilling'),
            'csn' => $this->b('success', 'CSN created', 'Invoice issued · continue in CSN management.', 'View CSN →', 'url', $order ? ConsignmentNoteResource::getUrl('index', ['tableFilters' => ['quotation_id' => ['value' => $order->id]]]) : null),
            'closed' => $order?->status === QuotationStatus::Superseded
                ? $this->oldVersionBanner($order)
                : $this->b('danger', $stage['label'], $stage['hint'], $order?->status === QuotationStatus::Closed ? 'Reopen case' : null, 'action', 'reopen'),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    /** An older version of an order (replaced by a revision): read-only, with a link to the latest version. */
    private function oldVersionBanner(Quotation $order): array
    {
        $rootId = $order->rootId();
        $latest = Quotation::query()
            ->where(fn ($q) => $q->where('root_quotation_id', $rootId)->orWhere('id', $rootId))
            ->orderByDesc('version')
            ->first();
        $reason = $order->statusLogs
            ->filter(fn ($log) => $log->to_status === QuotationStatus::Superseded->value)
            ->sortByDesc('id')
            ->first()?->remarks;
        $reason = $reason ? trim((string) preg_replace('/^(Superseded|Replaced) by version \d+( — )?/u', '', $reason)) : '';

        return $this->b(
            'info',
            'Old version'.($latest && $latest->id !== $order->id ? ' · replaced by version '.$latest->version : ''),
            'This version is kept for reference only and can no longer be edited.'.($reason !== '' ? ' Reason: '.$reason : ''),
            $latest && $latest->id !== $order->id ? 'Open latest version →' : null,
            'url',
            $latest && $latest->id !== $order->id ? $this->resourceUrl(fn () => OrderDetail::urlFor('order', $latest->id)) : null,
        );
    }

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
                'received_through' => $this->receivedThrough($enquiry),
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
                    'qty' => QuantityLabel::format($item['quantity'] ?? 1, $item['uom'] ?? null),
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

    /**
     * How the order reached us: the channel picked on an admin-entered order (phone call, WhatsApp, …),
     * otherwise where the form came from (customer portal, salesperson link, walk-in).
     */
    private function receivedThrough(?PortalEnquiry $enquiry): string
    {
        if (! $enquiry) {
            return 'Admin entry';
        }

        $channel = (string) ($enquiry->received_through ?: ($enquiry->payload['received_through'] ?? ''));

        return CreateAdminOrder::RECEIVED_THROUGH[$channel]
            ?? ($enquiry->source === PortalEnquiry::SOURCE_ADMIN ? '—' : $enquiry->sourceLabel());
    }

    /** "Ahmad · 012-345 6789" — a person in charge and contact number (null when neither is entered). */
    private function contactLine(?string $name, ?string $phone): ?string
    {
        $line = collect([trim((string) $name), trim((string) $phone)])->filter()->implode(' · ');

        return $line !== '' ? $line : null;
    }

    /** @return array<string, mixed> */
    private function orderOverview(Quotation $order, ?PortalEnquiry $enquiry, array $stage, array $payment, array $siblings): array
    {
        $destinations = $order->destinations->sortBy('sequence')->values();
        $types = collect($order->destination_types ?? [])->keyBy('column');

        return [
            'mode' => 'order',
            'customer_order' => [
                'customer' => $order->customer?->company_name ?? '—',
                'salesperson_sa' => trim(($order->salesperson?->name ?? 'Not assigned').' / '.($order->saLocation?->code ?? $order->branch?->code ?? '—')),
                'enquiry_ref' => $enquiry?->reference_no ?? ($order->orderNumber()),
                'enquiry_url' => $enquiry ? OrderDetail::urlFor('enquiry', $enquiry->id) : null,
                'received_through' => $this->receivedThrough($enquiry),
                'do_number' => $order->customer_do_number ?: '—',
                'order_type' => $order->orderType()?->getLabel() ?? '—',
                'service_type' => $order->service_type?->getLabel(),
                'consent' => $order->customer?->consentSkipsReconfirmation() ? 'Consent letter on file' : 'Pricing reconfirmation required',
                'expected_delivery' => $order->expected_delivery_date?->format('d M Y'),
                // the consignor is picked up or brings the goods to an O&G store; a person in charge on each side
                'consignor_mode' => match ($order->service_type) {
                    ServiceType::Store => 'Store · '.($order->storeBranch?->name ?? 'not selected'),
                    ServiceType::Pickup => 'Pickup',
                    default => null,
                },
                'consignor_pic' => $this->contactLine($order->consignor_pic_name, $order->consignor_pic_phone),
                'consignee_pic' => $this->contactLine($order->consignee_pic_name, $order->consignee_pic_phone),
                'pickup' => [
                    'title' => $order->service_type === ServiceType::Store
                        ? 'Store · '.($order->storeBranch?->name ?? $order->fromLocation?->name ?? '—')
                        : 'Pickup · '.($order->fromLocation?->name ?? app(OrderListingData::class)->cityFromAddress($order->pickup_location) ?? ($order->branch?->name ?? '—')),
                    'sub' => trim(($order->consignor_name ?: ($order->portal_enquiry_id ? '—' : $order->customer?->company_name)).' · '.($order->pickup_location ?: ($order->customer_address ?: '—')), ' ·'),
                ],
                'dropoffs' => $destinations->map(function ($d) use ($types, $order) {
                    $type = $types->get($d->consignee_name)['drop_off_type'] ?? null;

                    return [
                        'title' => 'Drop-off · '.($order->toLocation?->name ?? $d->city ?? $d->consignee_name).($order->consignee_name ? ' · '.$order->consignee_name : ''),
                        'sub' => trim(($type ? (DropOffType::tryFrom((string) $type)?->getLabel() ?? ucfirst((string) $type)).' · ' : '').($order->drop_off_location ?: ($d->address !== $d->consignee_name ? $d->address : ($order->consignee_name ?: $d->consignee_name)))),
                    ];
                })->all(),
            ],
            'linked' => $this->linkedRecords($order),
            'attachments' => $this->orderAttachments($order, $enquiry),
            'other_orders' => array_values(array_filter($siblings, fn ($s) => ! $s['current'])),
            'ownership' => [
                'handled_by' => $order->salesperson?->name ?? 'Not assigned',
                'editing' => $order->status->isEditable() ? 'Available' : 'Locked (version '.$order->version.' · '.$order->status->getLabel().')',
                'created' => $order->created_at?->format('Y-m-d') ?? '—',
                'last_activity' => ($order->statusLogs->sortByDesc('created_at')->first()?->created_at ?? $order->updated_at)?->format('d M Y · H:i') ?? '—',
            ],
        ];
    }

    /**
     * Linked records card: every document of the order (what the former Documents tab listed) —
     * quotation, proforma, invoice / cash bill (+ AutoCount sync), CSN and DO (page + PDF) and refund notes.
     *
     * @return list<array{title: string, sub: string, status: string, color: string, url: ?string, links: list<array{label: string, url: string}>}>
     */
    private function linkedRecords(Quotation $order): array
    {
        $item = fn (string $title, string $sub, string $status, string $color, ?string $url, array $links = []) => [
            'title' => $title, 'sub' => $sub, 'status' => $status, 'color' => $color, 'url' => $url,
            'links' => array_values(array_filter($links, fn (array $l) => filled($l['url']))),
        ];
        $sync = fn ($doc) => 'AutoCount: '.ucfirst(str_replace('_', ' ', (string) ($doc->autocount_sync_status ?: 'not_synced')));

        $linked = [];
        $linked[] = $item($order->number, 'Quotation · Version '.$order->version, $order->sent_at ? 'Issued' : ($order->status === QuotationStatus::Draft ? 'Draft' : (string) $order->status->getLabel()), $order->sent_at ? 'progress' : 'gray', $this->pdfUrl('quotations', 'quotation', $order));
        $linked[] = $order->proformaInvoice
            ? $item('Proforma Invoice', $order->proformaInvoice->number, 'Issued', 'progress', $this->pdfUrl('proforma-invoices', 'proformaInvoice', $order->proformaInvoice))
            : $item('Proforma Invoice', 'Issued on customer confirmation', 'Not issued', 'gray', null);

        if ($order->invoices->isEmpty()) {
            $linked[] = $item('Invoice / Cash Bill', 'Required before CSN creation', 'Not issued', 'gray', null);
        }

        foreach ($order->invoices as $invoice) {
            $linked[] = $item(
                $invoice->isCashBill() ? 'Cash Bill' : 'Invoice',
                $invoice->number.' · RM '.number_format((float) $invoice->total_amount, 2).' · '.$sync($invoice),
                'Issued',
                'done',
                $this->pdfUrl('invoices', 'invoice', $invoice),
            );
        }

        foreach ($order->consignmentNotes as $csn) {
            $linked[] = $item('CSN', $csn->number, $csn->status?->getLabel() ?? '—', 'progress', ConsignmentNoteResource::getUrl('view', ['record' => $csn]), [
                ['label' => 'PDF', 'url' => $this->pdfUrl('consignment-notes', 'consignmentNote', $csn)],
            ]);

            if ($do = $csn->deliveryOrder) {
                $doStatus = $do->status;
                $linked[] = $item(
                    'DO',
                    $do->number.($do->lorry ? ' · '.$do->lorry->registration_no : '').' · CSN '.$csn->number,
                    $doStatus instanceof \BackedEnum ? (method_exists($doStatus, 'getLabel') ? (string) $doStatus->getLabel() : (string) $doStatus->value) : ucfirst((string) $doStatus),
                    'progress',
                    $this->pdfUrl('delivery-orders', 'deliveryOrder', $do),
                );
            }
        }

        foreach ($order->refundNotes as $refund) {
            $linked[] = $item('Refund Note', $refund->number.' · RM '.number_format((float) $refund->amount, 2).' · '.$sync($refund), ucfirst((string) $refund->status), 'action', $this->resourceUrl(fn () => RefundNoteResource::getUrl('index')));
        }

        // earlier versions of this record (kept as superseded when it was revised / rejected and re-priced)
        foreach ($this->earlierVersions($order) as $version) {
            $linked[] = $item(
                $version->number,
                'Earlier version '.$version->version.' · RM '.number_format((float) $version->total_amount, 2),
                (string) $version->status->getLabel(),
                'gray',
                $this->resourceUrl(fn () => OrderDetail::urlFor('order', $version->id)),
                [['label' => 'PDF', 'url' => $this->pdfUrl('quotations', 'quotation', $version)]],
            );
        }

        return $linked;
    }

    /** @return \Illuminate\Support\Collection<int, Quotation> older versions of the order, newest first */
    private function earlierVersions(Quotation $order): \Illuminate\Support\Collection
    {
        $rootId = $order->rootId();

        return Quotation::query()
            ->where(fn ($q) => $q->where('root_quotation_id', $rootId)->orWhere('id', $rootId))
            ->where('id', '!=', $order->id)
            ->where('version', '<', (int) $order->version)
            ->with('statusLogs.user', 'creator')
            ->orderByDesc('version')
            ->get();
    }

    /**
     * Files uploaded with the order (customer form uploads and order uploads), shown under Linked records.
     *
     * @return list<array{name: string, url: ?string, is_image: bool, source: string}>
     */
    private function orderAttachments(Quotation $order, ?PortalEnquiry $enquiry): array
    {
        $files = collect($this->attachments($enquiry?->attachments ?? []))->map(fn (array $f) => $f + ['source' => 'Customer upload']);
        $enquiryPaths = collect($enquiry?->attachments ?? [])->pluck('path')->filter()->all();

        foreach (collect($order->attachments ?? [])->filter() as $path) {
            $path = is_array($path) ? (string) ($path['path'] ?? '') : (string) $path;

            if ($path === '' || in_array($path, $enquiryPaths, true)) {
                continue;
            }

            $files->push(['name' => basename($path), 'url' => Storage::disk('public')->url($path), 'is_image' => $this->isImagePath($path), 'source' => 'Order upload']);
        }

        return $files->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Items & pricing
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function pricingTab(?Quotation $order, ?PortalEnquiry $enquiry, array $stage, ?int $firstRecordId = null): array
    {
        if (! $order) {
            $payload = $enquiry?->payload ?? [];
            $destinations = collect($payload['destinations'] ?? []);

            return [
                'mode' => 'start',
                'destinations_count' => max(1, $destinations->count()),
                'can_start' => $enquiry && $enquiry->salesperson_id && ! $firstRecordId && ! in_array($stage['key'], ['closed'], true),
                'why_not' => match (true) {
                    (bool) $firstRecordId => 'Pricing has already started: the order records exist. Price them on the order page.',
                    ! $enquiry?->salesperson_id => 'Assign a salesperson before pricing.',
                    $stage['key'] === 'closed' => 'This enquiry is closed.',
                    default => null,
                },
                'records_url' => $firstRecordId ? OrderDetail::urlFor('order', $firstRecordId) : null,
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

            // a product kept on the order without a price yet shows no amount
            $priced = $line->unit_price !== null;

            return [
                'item' => $line->item_name,
                'sub' => $priced ? null : 'No price yet',
                'qty_range' => rtrim(rtrim(number_format((float) $line->quantity, 3, '.', ''), '0'), '.').' unit(s)',
                'range' => $tier ? 'Range: '.($tier->max_qty ? number_format((float) $tier->min_qty, 0).'–'.number_format((float) $tier->max_qty, 0) : number_format((float) $tier->min_qty, 0).'+') : null,
                'route' => ($order->fromLocation?->name ?? app(OrderListingData::class)->cityFromAddress($order->pickup_location) ?? $order->branch?->name ?? 'Pickup').' → '.$column,
                'unit' => $priced ? 'RM '.number_format((float) $line->unit_price, 2) : '—',
                'charge' => $priced ? 'RM '.number_format((float) $line->line_total, 2) : '—',
            ];
        })->values()->all();

        // products of the order form that never became a line on this record (saved before products without a
        // price were kept): listed with the form's products while it can still be priced
        $formEnquiry = $enquiry ?? $order->portalEnquiry;
        $missing = $editable && $formEnquiry
            ? collect(UpdateOrderRecords::payloadItemsWithoutLine($order, $formEnquiry))
            : collect();

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
                'items' => $itemLines->unique('item_name')->map(fn ($l) => $l->item_name.' × '.rtrim(rtrim(number_format((float) $l->quantity, 3, '.', ''), '0'), '.'))
                    ->concat($missing->map(fn (array $item) => trim((string) ($item['item_name'] ?? '')).' × '.max(1, (int) round((float) ($item['quantity'] ?? 1)))))
                    ->implode('; ') ?: '—',
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
                // a product kept without a price yet: empty price box
                $rows[$key]['prices'][$column] = $line->unit_price !== null ? (float) $line->unit_price : null;
            }
        }

        // products of the order form that never became a line (saved before products without a price were kept,
        // e.g. one with no price-list rate): shown with an empty price so they can be priced
        if ($order->portal_enquiry_id && ($enquiry = $order->portalEnquiry)) {
            $updater = app(UpdateOrderRecords::class);

            foreach (UpdateOrderRecords::payloadItemsWithoutLine($order, $enquiry) as $payloadItem) {
                $item = $updater->itemFromPayload($payloadItem);

                if ($item['item_name'] === '' || isset($rows[$item['item_name']])) {
                    continue;
                }

                $rows[$item['item_name']] = [
                    'line_type' => $item['line_type'],
                    'catalog_key' => $item['catalog_key'],
                    'item_name' => $item['item_name'],
                    'uom' => $item['uom'],
                    'quantity' => (float) $item['quantity'],
                    'prices' => array_fill_keys($columns, null),
                    'list' => [],
                ];
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
    | Payment summary (overview card)
    |--------------------------------------------------------------------------
    */

    /**
     * Payment summary card: status, Total / Paid / Outstanding, the payment history (each submission and each payment
     * recorded without one, with its uploaded slip / receipt), add / edit rights and the admin release state.
     *
     * @return array<string, mixed>
     */
    private function paymentSummary(Quotation $order, array $payment): array
    {
        $order->loadMissing(['paymentSubmissions.payment.receipt', 'paymentSubmissions.payment.cashBill', 'paymentSubmissions.level2Approver', 'payments.receiver', 'payments.receipt', 'payments.cashBill']);

        $total = (float) $order->total_amount;
        // live sum of completed payments: quotations.paid_amount was not refreshed for payments recorded outside the
        // submission flow (driver COD collection, CSN "Collect Payment") before RecordPayment started doing so
        $paid = round((float) $order->payments->where('status', 'completed')->sum('amount'), 2);
        // admin-entered orders no longer carry a payment method: it is the one of the latest payment recorded
        $method = PaymentMethod::tryFrom((string) $order->payment_method)
            ?? $order->paymentSubmissions->sortByDesc('id')->first()?->method();
        $type = $order->orderType();
        $open = $order->paymentSubmissions->filter(fn ($s) => $s->status->isOpen());
        $user = auth()->user();
        $canEdit = EditRecordedPayment::allows($user) && $order->isLatestVersion();
        $billed = $order->billingStatus() === BillingStatus::Generated;
        // credit term orders are released automatically on confirmation (ProceedNonCashOrderToCsn): not an Admin release
        $autoReleased = $order->isReleased() && $type === OrderType::Term
            && ($order->released_by === null || str_starts_with((string) $order->release_reason, 'Credit term order · released automatically'));
        $adminReleased = $order->isReleased() && ! $autoReleased;
        $codPending = RefreshOrderPaidAmount::codCollectionPending($order);

        $reviewStatus = match (true) {
            $order->consignmentNotes->isNotEmpty() || $order->billingStatus() === BillingStatus::Generated => 'Billing issued',
            $autoReleased => 'Released on confirmation',
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

        $statusNote = match (true) {
            $order->consignmentNotes->isNotEmpty() => 'CSN created · continue in CSN management',
            $billed => 'Billing issued · CSN pending',
            $autoReleased => 'Credit term · released for invoice and CSN on confirmation',
            $order->isReleased() => 'Released · billing can be issued',
            default => 'CSN not created · Billing must be issued first',
        };

        // last editor of each payment entry (EditRecordedPayment writes a payment_edit status log), shown next to
        // "Approved by" so an edited amount is not read as the approver's
        $editedSub = [];
        $editedPay = [];

        foreach ($order->statusLogs->sortBy('id') as $log) {
            $edit = $log->meta['payment_edit'] ?? null;

            if (! is_array($edit)) {
                continue;
            }

            $editor = $edit['edited_by']['name'] ?? $log->user?->name ?? 'Unknown user';

            if (! empty($edit['payment_submission_id'])) {
                $editedSub[(int) $edit['payment_submission_id']] = $editor;
            }

            if (! empty($edit['payment_id'])) {
                $editedPay[(int) $edit['payment_id']] = $editor;
            }
        }

        $entries = $order->paymentSubmissions->map(function (PaymentSubmission $s) use ($canEdit, $editedSub, $editedPay) {
            $p = $s->payment;

            return [
                'key' => 'sub-'.$s->id,
                'kind' => 'submission',
                'id' => $s->id,
                'sort' => $s->created_at?->getTimestamp() ?? 0,
                'date' => $s->payment_date?->format('d/m/Y') ?? $s->created_at?->format('d/m/Y'),
                'amount' => 'RM '.number_format((float) $s->amount, 2),
                'method' => $s->method()?->getLabel() ?? '—',
                'reference' => $s->reference,
                'status' => (string) $s->status->getLabel(),
                'color' => match ($s->status) {
                    PaymentSubmissionStatus::Approved => 'done',
                    PaymentSubmissionStatus::Verified => 'released',
                    PaymentSubmissionStatus::Rejected, PaymentSubmissionStatus::Cancelled => 'issue',
                    default => 'action',
                },
                'recorded_by' => $s->submitter?->name ?? ($s->submitted_channel === 'portal' ? 'Customer (portal)' : ucfirst((string) ($s->submitted_channel ?: 'system'))),
                'approved_by' => $s->status === PaymentSubmissionStatus::Approved ? $s->level2Approver?->name : null,
                'edited_by' => $editedSub[$s->id] ?? ($p ? ($editedPay[$p->id] ?? null) : null),
                'remarks' => $s->rejection_reason ?: $s->remarks,
                'documents' => $this->paymentDocuments($p),
                'attachments' => $this->fileLinks(array_values(array_unique([...$s->files(), ...($p?->files() ?? [])]))),
                'can_verify' => $s->status === PaymentSubmissionStatus::Submitted && ($s->method()?->requiresTwoApprovals() ?? false),
                'can_approve' => in_array($s->status, [PaymentSubmissionStatus::Submitted, PaymentSubmissionStatus::Verified], true),
                'can_reject' => $s->status->isOpen(),
                'can_edit' => $canEdit && in_array($s->status, [PaymentSubmissionStatus::Submitted, PaymentSubmissionStatus::Verified, PaymentSubmissionStatus::Approved], true),
            ];
        });

        // payments recorded without a submission (COD collection on delivery, CSN / counter payments)
        $direct = $order->payments->whereNull('payment_submission_id')->map(fn (Payment $p) => [
            'key' => 'pay-'.$p->id,
            'kind' => 'payment',
            'id' => $p->id,
            'sort' => $p->created_at?->getTimestamp() ?? 0,
            'date' => $p->created_at?->format('d/m/Y'),
            'amount' => 'RM '.number_format((float) $p->amount, 2),
            'method' => PaymentMethod::tryFrom((string) $p->method)?->getLabel() ?? ucfirst(str_replace('_', ' ', (string) $p->method)),
            'reference' => $p->reference,
            'status' => ucfirst(str_replace('_', ' ', (string) $p->status)),
            'color' => $p->status === 'completed' ? 'done' : 'gray',
            'recorded_by' => $p->receiver?->name ?? 'System',
            'approved_by' => null,
            'edited_by' => $editedPay[$p->id] ?? null,
            'remarks' => $p->remarks,
            'documents' => $this->paymentDocuments($p),
            'attachments' => $this->fileLinks($p->files()),
            'can_verify' => false,
            'can_approve' => false,
            'can_reject' => false,
            'can_edit' => $canEdit && $p->status === 'completed',
        ]);

        return [
            'label' => $payment['label'],
            'color' => $payment['color'],
            'order_type' => $type?->getLabel() ?? '—',
            'cash_flow' => ! in_array($type, [OrderType::Term, OrderType::Cod], true),
            'total' => 'RM '.number_format($total, 2),
            'paid' => 'RM '.number_format($paid, 2),
            'outstanding' => 'RM '.number_format(max(0, $total - $paid), 2),
            'outstanding_value' => round(max(0, $total - $paid), 2),
            'method' => $method?->getLabel(),
            'review_status' => $reviewStatus,
            'approval_levels' => $method ? ($method->requiresTwoApprovals() ? 'Two-level (verify + approve)' : 'Single approval') : null,
            'proforma' => $order->proformaInvoice?->number,
            'note' => $note,
            'term_text' => match ($type) {
                OrderType::Term => 'Credit term order · no payment is collected here. Once the customer confirms (and credit approval passes) the CSN is created; Admin generates the invoice when ready (e.g. once the CSN is returned) and payments are recorded against the invoice.',
                OrderType::Cod => $order->cod_blocked
                    ? 'COD order blocked by Admin'.($order->cod_block_reason ? ': '.$order->cod_block_reason : '').'.'
                    : 'COD order · the CSN is created once the customer confirms (unless Admin blocks the order). Record payments here or let the driver collect on delivery; the COD invoice is issued once the order is fully paid.',
                default => null,
            },
            'cod_blocked' => (bool) $order->cod_blocked,
            'status_note' => $statusNote,
            'release' => [
                // only a real Admin release: a credit term order released automatically on confirmation shows no callout
                'released' => $adminReleased,
                'by' => $order->releaser?->name ?? 'Admin',
                'at' => $order->released_at?->format('d M Y · H:i'),
                'reason' => $order->release_reason,
                // a cash order still short of payment needs an Admin release before billing and the CSN
                'required' => ! $order->isReleased() && ! $billed && $order->status === QuotationStatus::Confirmed
                    && $type === OrderType::Cash && $paid + 0.005 < $total,
                // ...as does a credit term order that was not released on confirmation (term orders are normally released automatically)
                'applies' => ! $order->isReleased() && ! $billed && $order->status === QuotationStatus::Confirmed
                    && ($type === OrderType::Term || (in_array($type, [OrderType::Cash, null], true) && $paid + 0.005 < $total)),
            ],
            'entries' => $entries->concat($direct)->sortByDesc('sort')->values()->all(),
            // payments can be added once the customer has confirmed, also after billing and CSN (SubmitPaymentEvidence);
            // cash and COD only: a credit term order is paid against its invoice (Invoices / AR)
            'can_add' => $total > 0 && $type !== OrderType::Term && in_array($order->status, [QuotationStatus::Accepted, QuotationStatus::PendingApproval, QuotationStatus::Confirmed, QuotationStatus::Converted], true),
            'cod_pending' => $codPending && $total > 0 && in_array($order->status, [QuotationStatus::Accepted, QuotationStatus::PendingApproval, QuotationStatus::Confirmed, QuotationStatus::Converted], true),
            'can_edit' => $canEdit,
            'billed' => $billed,
        ];
    }

    /** "Receipt OR-0001 · Cash Bill CB-0001" — documents issued for a recorded payment. */
    private function paymentDocuments(?Payment $payment): ?string
    {
        if (! $payment) {
            return null;
        }

        $docs = collect([
            $payment->receipt ? 'Receipt '.$payment->receipt->number : null,
            $payment->cashBill ? 'Cash Bill '.$payment->cashBill->number : null,
        ])->filter();

        return $docs->isNotEmpty() ? $docs->implode(' · ') : null;
    }

    /**
     * Links to uploaded files on the public disk (payment slips / receipts), one per distinct path.
     *
     * @param  list<?string>  $paths
     * @return list<array{name: string, url: string, is_image: bool, ext: string, missing: bool}>
     */
    private function fileLinks(array $paths): array
    {
        return collect($paths)
            ->filter(fn ($p) => filled($p))
            ->unique()
            ->map(fn (string $path) => [
                'name' => basename($path),
                'url' => Storage::disk('public')->url($path),
                'is_image' => $this->isImagePath($path),
                'ext' => strtoupper(pathinfo($path, PATHINFO_EXTENSION) ?: 'file'),
                'missing' => ! Storage::disk('public')->exists($path),
            ])
            ->values()
            ->all();
    }

    private function isImagePath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /** A Filament resource URL, or null when the resource / page is not available in this panel. */
    private function resourceUrl(\Closure $url): ?string
    {
        try {
            return $url();
        } catch (\Throwable) {
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Activity
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    /**
     * A "price offered" log (sent / accepted) reads as one long sentence; split it into a short title plus the
     * offered total and its product lines (from the log meta) so the timeline can show them as a small table.
     *
     * @return array{0: string, 1: array{total?: string, lines?: list<array{item: string, qty: string, unit: string, amount: string, destination: ?string}>}}
     */
    private function offerEntry(QuotationStatusLog $log, string $title): array
    {
        $meta = is_array($log->meta) ? $log->meta : [];
        // older logs used "Superseded by version N"
        $title = (string) preg_replace('/^Superseded by version/u', 'Replaced by version', $title);

        if (empty($meta['offer_event']) || ! str_contains($title, ' · price offered ')) {
            return [$title, []];
        }

        $short = strstr($title, ' · price offered ', true) ?: $title;
        // "via email, whatsapp" -> "via Email, WhatsApp"
        $short = preg_replace_callback('/ via (.+?)( \(|$)/u', fn ($m) => ' via '.collect(explode(',', $m[1]))
            ->map(fn ($c) => match (strtolower(trim($c))) { 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'portal' => 'Customer portal', default => ucfirst(trim($c)) })
            ->implode(', ').$m[2], $short) ?? $short;

        $money = fn ($v) => 'RM '.number_format((float) $v, 2);

        return [$short, [
            'total' => isset($meta['total']) ? $money($meta['total']) : null,
            'lines' => collect($meta['lines'] ?? [])->filter(fn ($l) => is_array($l))->map(fn (array $l) => [
                'item' => (string) ($l['item'] ?? ''),
                'qty' => (string) ($l['qty'] ?? ''),
                'unit' => $money($l['unit'] ?? 0),
                'amount' => $money($l['amount'] ?? 0),
                'destination' => filled($l['destination'] ?? null) ? (string) $l['destination'] : null,
            ])->values()->all(),
        ]];
    }

    private function activity(?Quotation $order, ?PortalEnquiry $enquiry): array
    {
        $items = collect();
        $seq = 0;
        $push = function ($at, string $title, string $sub, string $kind = 'info', array $extra = []) use ($items, &$seq): void {
            if ($at) {
                $items->push(['at' => $at, 'title' => $title, 'sub' => $sub, 'kind' => $kind, 'seq' => $seq++] + $extra);
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
                [$title, $extra] = $this->offerEntry($log, $title);
                $push($log->created_at, $title, $log->user?->name ?? 'System', $kind, $extra);
            }

            foreach ($this->earlierVersions($order) as $version) {
                $push($version->created_at, 'V'.$version->version.' · Order record created · '.$version->number, $version->creator?->name ?? 'System', 'start');

                foreach ($version->statusLogs as $log) {
                    $title = $log->remarks ?: ('Status changed to '.(QuotationStatus::tryFrom((string) $log->to_status)?->getLabel() ?? $log->to_status));
                    [$title, $extra] = $this->offerEntry($log, $title);
                    $push($log->created_at, 'V'.$version->version.' · '.$title, $log->user?->name ?? 'System', str_contains(strtolower($title), 'rejected') ? 'issue' : 'info', $extra);
                }
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
            ->map(fn ($i) => ['title' => $i['title'], 'date' => $i['at']->format('d M Y · H:i'), 'day' => $i['at']->format('Y-m-d'), 'by' => $i['sub'], 'kind' => $i['kind'], 'total' => $i['total'] ?? null, 'lines' => $i['lines'] ?? []])
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
    private function permissions(?Quotation $order, ?PortalEnquiry $enquiry, array $stage, bool $lockedByOther, ?int $firstRecordId = null): array
    {
        $user = auth()->user();
        $isManager = (bool) ($user?->is_hq || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'finance']));
        $enquiryStatus = $enquiry?->status instanceof PortalEnquiryStatus ? $enquiry->status : null;
        $status = $order?->status;
        $latest = $order?->isLatestVersion() ?? false;

        $can = [
            'approve_enquiry' => ! $order && $enquiry && ! $lockedByOther && $enquiryStatus === PortalEnquiryStatus::Pending,
            'reject_enquiry' => ! $order && $enquiry && ! $lockedByOther && in_array($enquiryStatus, [PortalEnquiryStatus::Pending, PortalEnquiryStatus::InReview], true),
            'assign_salesperson' => $enquiry && ! $lockedByOther && ! $enquiry->salesperson_locked && ! in_array($enquiryStatus, [PortalEnquiryStatus::Rejected, PortalEnquiryStatus::Cancelled], true),
            'start_pricing' => ! $order && $enquiry && ! $lockedByOther && ! $firstRecordId && $enquiry->salesperson_id && in_array($enquiryStatus, [PortalEnquiryStatus::Pending, PortalEnquiryStatus::InReview, PortalEnquiryStatus::Quoted], true),
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
            'send_invoice' => (bool) $order,
            // credit term: Admin generates the invoice whenever ready, once the CSN exists
            'issue_invoice' => $order && $order->orderType() === OrderType::Term
                && $order->consignmentNotes->contains(fn ($csn) => $csn->status?->value !== 'cancelled')
                && ! $order->invoices->contains(fn ($i) => $i->type === 'term' && $i->status !== 'cancelled'),
        ];

        // an old version (replaced by a newer one) is view only: nothing can be assigned, priced, sent or paid on it
        return $order && ! $latest ? array_map(fn () => false, $can) : $can;
    }

    /**
     * "Edit order" page (the Create order layout in edit mode) while the order still has a record that
     * can be edited; an enquiry that is not priced yet can always be edited unless it was rejected or cancelled.
     */
    private function editOrderUrl(?Quotation $order, ?PortalEnquiry $enquiry, bool $lockedByOther): ?string
    {
        // an old version is view only (edit the latest version instead)
        if ($lockedByOther || (! $order && ! $enquiry) || ($order && ! $order->isLatestVersion())) {
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
            'is_image' => str_starts_with((string) ($file['mime'] ?? ''), 'image/') || $this->isImagePath((string) ($file['path'] ?? '')),
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

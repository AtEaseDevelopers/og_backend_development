<?php

namespace App\Support;

use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Enums\PortalEnquiryStatus;
use App\Enums\QuotationStatus;

/**
 * Single source of truth for where an order sits in the flow
 * Enquiry → Quotation → Confirmation → Proforma → Payment / Release → CSN,
 * and for the stage tags that filter the Orders workspace.
 *
 * No "Pending salesperson" or "Invoice issued" stage (user, 19 Oct 2026): an order without a salesperson is a New
 * enquiry, one with a salesperson is Preparing quotation, and a released order goes straight to CSN created.
 */
class OrderStage
{
    public const STEPS = ['Enquiry', 'Quotation', 'Pending payment', 'Payment verification', 'Pending item', 'CSN'];

    /**
     * Stage keys (as returned in forEnquiry / forOrder 'key') → tag label and colour, in flow order.
     * The Orders list shows one clickable tag per stage, after an "All" tag for every open order.
     *
     * @var array<string, array{label: string, color: string}>
     */
    public const STAGES = [
        'enquiry' => ['label' => 'New enquiry', 'color' => 'progress'],
        'quotation' => ['label' => 'Preparing quotation', 'color' => 'progress'],
        'awaiting_customer' => ['label' => 'Awaiting customer', 'color' => 'customer'],
        // the flow (user, 27 Oct 2026): Awaiting customer → Pending payment → Payment verification → Pending item → CSN created
        'confirmation' => ['label' => 'Pending payment', 'color' => 'customer'],
        'payment' => ['label' => 'Payment verification', 'color' => 'action'],
        'item' => ['label' => 'Pending item', 'color' => 'action'],
        'csn' => ['label' => 'CSN created', 'color' => 'done'],
        // apart (user, 26 Oct 2026): a rejected order can still be edited and sent again; a closed one is finished
        'rejected' => ['label' => 'Rejected', 'color' => 'issue'],
        'closed' => ['label' => 'Closed', 'color' => 'muted'],
    ];

    /**
     * @return array{key: string, step: int, label: string, color: string, hint: string, next: string, closed: bool, attention: bool, ready_to_bill: bool}
     */
    public static function forEnquiry(PortalEnquiry $enquiry): array
    {
        $status = $enquiry->status instanceof PortalEnquiryStatus
            ? $enquiry->status
            : PortalEnquiryStatus::tryFrom((string) $enquiry->status);

        $stage = match (true) {
            $status === PortalEnquiryStatus::Rejected => ['rejected', 1, 'Rejected', 'issue', 'Rejected at review', 'View enquiry', true],
            $status === PortalEnquiryStatus::Cancelled => ['closed', 1, 'Cancelled', 'issue', 'Cancelled by customer', 'View enquiry', true],
            ! $enquiry->salesperson_id => ['enquiry', 1, 'New enquiry', 'progress', 'No salesperson assigned · Assign one to continue', 'Assign salesperson', false],
            default => ['quotation', 2, 'Preparing quotation', 'progress', 'Salesperson assigned · Prepare the order', 'Provide pricing', false],
        };

        return [
            'key' => $stage[0],
            'step' => $stage[1],
            'label' => $stage[2],
            'color' => $stage[3],
            'hint' => $stage[4],
            'next' => $stage[5],
            'closed' => $stage[6],
            'attention' => ! $stage[6],
            'ready_to_bill' => false,
        ];
    }

    /**
     * @return array{key: string, step: int, label: string, color: string, hint: string, next: string, closed: bool, attention: bool, ready_to_bill: bool}
     */
    public static function forOrder(Quotation $q): array
    {
        $status = $q->status;
        $billing = $q->billingStatus();
        $hasProforma = $q->relationLoaded('proformaInvoice') ? $q->proformaInvoice !== null : $q->proformaInvoice()->exists();
        $hasCsn = isset($q->consignment_notes_count)
            ? (int) $q->consignment_notes_count > 0
            : $q->consignmentNotes()->exists();
        $paid = (float) $q->paid_amount;
        $total = (float) $q->total_amount;
        $submissions = $q->relationLoaded('paymentSubmissions') ? $q->paymentSubmissions : $q->paymentSubmissions()->get();
        $pending = $submissions->filter(fn ($s) => $s->status instanceof PaymentSubmissionStatus && $s->status->isOpen())->count();
        $latest = $submissions->sortByDesc('id')->first();
        $rejected = $latest && $latest->status === PaymentSubmissionStatus::Rejected;
        $type = $q->orderType();

        $stage = match (true) {
            $status === QuotationStatus::Converted || $hasCsn => ['csn', 6, 'CSN created', 'done', 'Released · Continue in CSN management', 'View CSN', false],
            // a Store order paid / released: its CSN waits for the goods at the store (user, 21 Oct 2026)
            $status === QuotationStatus::Confirmed && $billing === BillingStatus::Generated && $q->awaitsItem() => ['item', 5, 'Pending item', 'action', 'Paid · Waiting for the goods at the store · Confirm when they are in', 'Ready for CSN', false],
            // billing issued but its CSN not created (a release creates both, so only after an error)
            $status === QuotationStatus::Confirmed && $billing === BillingStatus::Generated => ['csn', 6, 'CSN pending', 'action', 'Released · The CSN was not created · Create it to continue', 'Create CSN', false],
            $status === QuotationStatus::Confirmed && $billing === BillingStatus::Failed => ['payment', 4, 'Billing failed', 'issue', 'Billing generation failed · Retry', 'Retry billing', false],
            $status === QuotationStatus::Confirmed && $q->cod_blocked => ['payment', 4, 'COD blocked', 'issue', $q->cod_block_reason ?: 'COD order blocked by admin', 'Review COD block', false],
            $status === QuotationStatus::Confirmed && $q->isReleased() => ['payment', 4, 'Ready to bill', 'released', 'Admin released · Billing not issued', 'Review billing', false],
            $status === QuotationStatus::Confirmed && $rejected => ['payment', 4, 'Payment review', 'action', 'Receipt mismatch · Customer correction needed', 'View rejection', false],
            $status === QuotationStatus::Confirmed && $pending > 0 => ['payment', 4, 'Payment review', 'action', 'Payment proof submitted · Verify and approve', 'Review payment', false],
            $status === QuotationStatus::Confirmed && $type === OrderType::Term => ['payment', 4, 'Admin release required', 'action', 'Credit order · Release to issue invoice and CSN', 'Review admin release', false],
            $status === QuotationStatus::Confirmed && $type === OrderType::Cod => ['payment', 4, 'Ready to bill', 'released', 'COD order · CSN created, invoice once fully paid', 'Review billing', false],
            // a Cash order still owed: pending payment (Admin release is for Credit / Term orders only)
            // waiting for the customer's payment: the Pending payment stage (user, 27 Oct 2026)
            $status === QuotationStatus::Confirmed && $paid > 0 && $paid + 0.005 < $total => ['confirmation', 3, 'Pending payment', 'customer', 'Partial payment · Outstanding RM '.number_format($total - $paid, 2).' · Admin may release to CSN', 'Review payment', false],
            $status === QuotationStatus::Confirmed => ['confirmation', 3, 'Pending payment', 'customer', 'Proforma issued · Waiting for customer payment', 'Review payment', false],
            $status === QuotationStatus::PendingApproval => ['confirmation', 3, 'Credit approval', 'action', 'Customer confirmed · Awaiting branch manager credit approval', 'Review credit approval', false],
            $status === QuotationStatus::Accepted => ['confirmation', 3, 'Pending payment', 'customer', $hasProforma ? 'Customer confirmed · Proforma issued · Waiting for payment' : 'Customer confirmed · Proforma being issued', 'Review payment', false],
            $status === QuotationStatus::PendingReview => ['awaiting_customer', 3, 'Awaiting customer', 'customer', 'Pending customer review · No response yet', 'View quotation', false],
            $status === QuotationStatus::Sent => ['awaiting_customer', 3, 'Awaiting customer', 'customer', 'Quotation sent · Customer has not confirmed', 'View quotation', false],
            $status === QuotationStatus::Negotiation => ['quotation', 3, 'Customer rejected · revising', 'action', 'Customer rejected the price · Edit and send again', 'Edit pricing', false],
            $status === QuotationStatus::Draft && ! $q->salesperson_id => ['enquiry', 1, 'New enquiry', 'progress', 'No salesperson assigned · Assign one to continue', 'Assign salesperson', false],
            $status === QuotationStatus::Draft => ['quotation', 2, 'Preparing quotation', 'progress', $total > 0 ? 'Priced · Ready to send for confirmation' : 'Pricing in progress · Salesperson editing', $total > 0 ? 'Send quotation' : 'Provide pricing', false],
            $status === QuotationStatus::Superseded => ['closed', 2, 'Old version', 'gray', 'Replaced by a newer version · kept for reference', 'View order', true],
            // the customer rejected (portal or recorded by staff): edit and send a new version, or close it
            $status === QuotationStatus::Rejected => ['rejected', 3, 'Customer rejected', 'issue', 'Rejected'.($q->rejection_reason ? ' · '.$q->rejection_reason : '').' · Edit and send a new version, or close', 'Edit & send new version', false],
            default => ['closed', 2, $status->getLabel() ?? 'Closed', 'issue', $q->rejection_reason ?: $q->closed_reason ?: ($status->getLabel() ?? 'Closed'), 'View order', true],
        };

        $key = $stage[0];
        $color = $stage[3];

        return [
            'key' => $key,
            'step' => $stage[1],
            'label' => $stage[2],
            'color' => $color,
            'hint' => $stage[4],
            'next' => $stage[5],
            'closed' => $stage[6],
            'attention' => in_array($key, ['enquiry', 'quotation'], true) || (in_array($key, ['confirmation', 'payment', 'item', 'csn'], true) && in_array($color, ['action', 'issue'], true)),
            'ready_to_bill' => $key === 'payment' && $color === 'released',
        ];
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array{key: string, label: string, color: string, method: ?string, hint: string}
     */
    public static function paymentForOrder(Quotation $q, array $stage): array
    {
        $paid = (float) $q->paid_amount;
        $total = (float) $q->total_amount;
        $method = PaymentMethod::tryFrom((string) $q->payment_method)?->getLabel();
        $type = $q->orderType();
        $hasProforma = $q->relationLoaded('proformaInvoice') ? $q->proformaInvoice !== null : $q->proformaInvoice()->exists();
        $submissions = $q->relationLoaded('paymentSubmissions') ? $q->paymentSubmissions : $q->paymentSubmissions()->get();
        $latest = $submissions->sortByDesc('id')->first();
        $outstanding = max(0, $total - $paid);

        if ($paid + 0.005 >= $total && $total > 0 && $paid > 0) {
            return ['key' => 'paid', 'label' => 'Paid', 'color' => 'done', 'method' => $method, 'hint' => 'Paid RM '.number_format($paid, 2)];
        }

        if (in_array($stage['key'], ['quotation', 'awaiting_customer', 'closed'], true) && $paid <= 0) {
            return ['key' => 'not_requested', 'label' => 'Not requested', 'color' => 'progress', 'method' => $method, 'hint' => 'After confirmation'];
        }

        if ($type === OrderType::Cod) {
            return $q->cod_blocked
                ? ['key' => 'cod_blocked', 'label' => 'COD blocked', 'color' => 'issue', 'method' => 'Cash on delivery', 'hint' => $q->cod_block_reason ?: 'Blocked by admin']
                : ['key' => 'cod', 'label' => 'COD Pending', 'color' => 'action', 'method' => 'Cash on delivery', 'hint' => 'Collected on delivery'];
        }

        if ($type === OrderType::Term) {
            return $q->isReleased()
                ? ['key' => 'credit', 'label' => 'Credit · released', 'color' => 'released', 'method' => $method ?? 'Credit term', 'hint' => 'Released '.$q->released_at?->format('d/m/Y')]
                : ['key' => 'credit', 'label' => 'Credit', 'color' => 'released', 'method' => $method ?? 'Credit term', 'hint' => 'Invoice generated by Admin after the CSN'];
        }

        if ($latest && $latest->status === PaymentSubmissionStatus::Rejected && $paid + 0.005 < $total) {
            return ['key' => 'rejected', 'label' => 'Payment rejected', 'color' => 'issue', 'method' => $latest->method()?->getLabel() ?? $method, 'hint' => $latest->rejection_reason ?: 'Receipt mismatch'];
        }

        if ($paid > 0) {
            return ['key' => 'partial', 'label' => 'Partial', 'color' => 'action', 'method' => $method, 'hint' => 'Outstanding RM '.number_format($outstanding, 2)];
        }

        return ['key' => 'unpaid', 'label' => 'Unpaid', 'color' => 'action', 'method' => $method, 'hint' => $hasProforma ? 'Proforma '.$q->proformaInvoice?->number : 'Awaiting payment'];
    }

    /** @return array{key: string, label: string, color: string, method: ?string, hint: string} */
    public static function paymentForEnquiry(PortalEnquiry $enquiry): array
    {
        return [
            'key' => 'not_requested',
            'label' => 'Not requested',
            'color' => 'progress',
            'method' => PaymentMethod::tryFrom((string) $enquiry->payment_method)?->getLabel(),
            'hint' => 'After confirmation',
        ];
    }

    /** @return array<string, string> */
    public static function paymentOptions(): array
    {
        return [
            '' => 'All',
            'not_requested' => 'Not requested',
            'unpaid' => 'Unpaid',
            'partial' => 'Partial',
            'paid' => 'Paid',
            'rejected' => 'Payment rejected',
            'credit' => 'Credit',
            'cod' => 'COD',
            'cod_blocked' => 'COD blocked',
        ];
    }

    /**
     * Seven-step progress for the detail page header.
     *
     * @param  array<string, mixed>  $stage
     * @return list<array{n: int, label: string, state: string}>
     */
    public static function steps(array $stage, ?Quotation $order = null, bool $cashFlow = true): array
    {
        $current = (int) $stage['step'];
        $closed = (bool) $stage['closed'];
        $steps = [];

        foreach (self::STEPS as $index => $label) {
            $n = $index + 1;

            // Non-cash orders (credit term / COD) go from confirmation straight to the CSN
            // (Payment verification and Pending item are the cash flow's steps)
            if (! $cashFlow && in_array($n, [4, 5], true)) {
                continue;
            }
            $state = match (true) {
                $closed && $n === $current => 'issue',
                $n < $current => 'done',
                $n === $current => 'current',
                default => 'todo',
            };

            // Proforma / billing / CSN are facts, not just positions in the flow
            if ($order) {
                if ($n === 4 && (float) $order->paid_amount + 0.005 >= (float) $order->total_amount && (float) $order->total_amount > 0) {
                    $state = $n === $current ? 'current' : 'done';
                }
                if ($n === 6 && $order->billingStatus() === BillingStatus::Generated) {
                    $state = 'done';
                }
                if ($n === 5 && $stage['key'] === 'item') {
                    $state = 'done';
                }
                if ($n === 7 && $stage['key'] === 'csn') {
                    $state = 'done';
                }
            }

            $steps[] = ['n' => $n, 'label' => $label, 'state' => $state];
        }

        return $steps;
    }
}

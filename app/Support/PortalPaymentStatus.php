<?php

namespace App\Support;

use App\Domains\Billing\Models\OnlinePayment;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;

/**
 * Section P: the payment status a customer sees for an order: Pending, Paid, Failed, Counter payment or
 * Credit (with its Branch Manager approval state). Load paymentSubmissions, onlinePayments and
 * creditApprovalRequests first when listing many orders.
 */
class PortalPaymentStatus
{
    /** @return array{label: string, tone: string} */
    public static function for(Quotation $quotation): array
    {
        $type = $quotation->orderType();

        if ($type === OrderType::Term) {
            $credit = $quotation->creditApprovalRequests->sortByDesc('id')->first();

            return match ($credit?->status) {
                'pending' => ['label' => 'Credit · awaiting approval', 'tone' => 'amber'],
                'rejected' => ['label' => 'Credit · rejected', 'tone' => 'red'],
                default => ['label' => 'Credit', 'tone' => 'blue'],
            };
        }

        if ((float) $quotation->total_amount > 0 && $quotation->isFullyPaid()) {
            return ['label' => 'Paid', 'tone' => 'green'];
        }

        $submissions = $quotation->paymentSubmissions->sortByDesc('id');
        $open = $submissions->first(fn ($s) => $s->isOpen());

        if ($open) {
            return $open->method() === PaymentMethod::Counter
                ? ['label' => 'Counter payment', 'tone' => 'blue']
                : ['label' => 'Pending verification', 'tone' => 'amber'];
        }

        $lastOnline = $quotation->onlinePayments->sortByDesc('id')->first();
        $lastSubmission = $submissions->first();

        if (($lastOnline && $lastOnline->status === OnlinePayment::STATUS_FAILED && (! $lastSubmission || $lastSubmission->created_at < $lastOnline->created_at))
            || ($lastSubmission && $lastSubmission->status === PaymentSubmissionStatus::Rejected)) {
            return ['label' => 'Failed', 'tone' => 'red'];
        }

        if ($type === OrderType::Cod) {
            return ['label' => 'Cash on delivery', 'tone' => 'stone'];
        }

        if ((float) $quotation->paid_amount > 0) {
            return ['label' => 'Partially paid', 'tone' => 'amber'];
        }

        return ['label' => 'Pending', 'tone' => 'stone'];
    }
}

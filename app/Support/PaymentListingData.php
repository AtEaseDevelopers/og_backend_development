<?php

namespace App\Support;

use App\Domains\Billing\Models\Payment;
use App\Enums\CsnBillingType;

class PaymentListingData
{
    public static function paymentNumber(Payment $payment): string
    {
        $date = $payment->created_at ?? now();

        return sprintf(
            'PMT-%s-%04d',
            $date->format('ym'),
            $payment->id,
        );
    }

    public static function typeLabel(Payment $payment): string
    {
        if ($payment->invoice_id) {
            return 'Term Billing';
        }

        $billingType = $payment->consignmentNote?->billing_type;

        return match (true) {
            $billingType === CsnBillingType::CashBill => 'Cash Bill',
            $billingType === CsnBillingType::Cod, $payment->method === 'cod' => 'COD',
            $billingType === CsnBillingType::Term => 'Term Billing',
            default => 'Cash Bill',
        };
    }

    public static function typeKey(Payment $payment): string
    {
        if ($payment->invoice_id) {
            return 'term';
        }

        $billingType = $payment->consignmentNote?->billing_type;

        return match (true) {
            $billingType === CsnBillingType::CashBill => 'cash_bill',
            $billingType === CsnBillingType::Cod, $payment->method === 'cod' => 'cod',
            $billingType === CsnBillingType::Term => 'term',
            default => 'cash_bill',
        };
    }

    public static function statusLabel(Payment $payment): string
    {
        return match ($payment->status) {
            'completed' => 'DONE',
            'pending' => 'PENDING',
            'failed', 'cancelled' => 'FAILED',
            default => strtoupper(str_replace('_', ' ', (string) $payment->status)),
        };
    }

    public static function statusColor(Payment $payment): string
    {
        return match ($payment->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function typeFilterOptions(): array
    {
        return [
            'cash_bill' => 'Cash Bill',
            'cod' => 'COD',
            'term' => 'Term Billing',
        ];
    }

    /** @return array<string, string> */
    public static function statusFilterOptions(): array
    {
        return [
            'completed' => 'Done',
            'pending' => 'Pending',
            'failed' => 'Failed',
        ];
    }
}

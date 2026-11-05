<?php

namespace App\Support;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\MasterData\Models\Customer;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Carbon;

/**
 * Statement of account of one customer for a period: opening balance, invoices (debit) and completed payments
 * (credit) with a running balance, and the aging of what is still unpaid at the end of the period.
 */
class StatementData
{
    public const AGING_BUCKETS = [
        'current' => 'Current',
        '1_30' => '1–30 days',
        '31_60' => '31–60 days',
        '61_90' => '61–90 days',
        '90_plus' => 'Over 90 days',
    ];

    /** @return array{from: Carbon, to: Carbon, opening: float, rows: list<array<string, mixed>>, closing: float, outstanding: float, aging: array<string, float>, open_invoices: list<array<string, mixed>>} */
    public function build(Customer $customer, ?string $from = null, ?string $to = null): array
    {
        $to = Carbon::parse($to ?? now()->toDateString())->endOfDay();
        $from = $from ? Carbon::parse($from)->startOfDay() : null;

        $invoices = Invoice::query()
            ->with('payments')
            ->where('customer_id', $customer->id)
            ->whereDate('invoice_date', '<=', $to)
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereNot('status', InvoiceStatus::Draft->value)
            ->orderBy('invoice_date')
            ->get();

        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->where('created_at', '<=', $to)
            ->orderBy('created_at')
            ->get();

        $opening = 0.0;
        $rows = collect();

        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->total_amount;

            if ($from && $invoice->invoice_date < $from) {
                $opening += $amount;

                continue;
            }

            $rows->push([
                'date' => $invoice->invoice_date?->toDateString(),
                'type' => $invoice->isCashBill() ? 'Cash bill' : 'Invoice',
                'reference' => $invoice->number,
                'due_date' => $invoice->due_date?->toDateString(),
                'debit' => $amount,
                'credit' => 0.0,
            ]);
        }

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;

            if ($from && $payment->created_at < $from) {
                $opening -= $amount;

                continue;
            }

            $rows->push([
                'date' => $payment->created_at?->toDateString(),
                'type' => 'Payment',
                'reference' => $payment->reference ?: 'Payment #'.$payment->id,
                'due_date' => null,
                'debit' => 0.0,
                'credit' => $amount,
            ]);
        }

        $running = round($opening, 2);
        $rows = $rows->sortBy('date')->values()->map(function (array $row) use (&$running) {
            $running = round($running + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $running;

            return $row;
        });

        $aging = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
        $openInvoices = [];

        foreach ($invoices as $invoice) {
            $paid = (float) $invoice->payments->where('status', 'completed')->where('created_at', '<=', $to)->sum('amount');
            $balance = round((float) $invoice->total_amount - $paid, 2);

            if ($balance <= 0 || $invoice->status === InvoiceStatus::Paid->value) {
                continue;
            }

            $due = $invoice->due_date ?? $invoice->invoice_date;
            $days = $due ? (int) $due->copy()->startOfDay()->diffInDays($to->copy()->startOfDay(), false) : 0;
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => '90_plus',
            };
            $aging[$bucket] += $balance;
            $openInvoices[] = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->invoice_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => max(0, $days),
                'balance' => $balance,
            ];
        }

        return [
            'from' => $from ?? ($rows->isNotEmpty() ? Carbon::parse($rows->first()['date']) : $to->copy()->startOfMonth()),
            'to' => $to,
            'opening' => round($opening, 2),
            'rows' => $rows->all(),
            'closing' => $running,
            'outstanding' => round(array_sum($aging), 2),
            'aging' => array_map(fn ($v) => round($v, 2), $aging),
            'open_invoices' => $openInvoices,
        ];
    }
}

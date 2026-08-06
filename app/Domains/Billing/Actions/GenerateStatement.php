<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Statement;
use App\Domains\MasterData\Models\Customer;
use App\Enums\InvoiceStatus;
use Illuminate\Support\Carbon;

class GenerateStatement
{
    public function execute(Customer $customer, int $branchId, ?string $asOf = null): Statement
    {
        $statementDate = Carbon::parse($asOf ?? now()->toDateString());

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('source_branch_id', $branchId)
            ->whereDate('invoice_date', '<=', $statementDate)
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->orderBy('invoice_date')
            ->get();

        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('source_branch_id', $branchId)
            ->whereDate('created_at', '<=', $statementDate)
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->get();

        $transactions = collect();
        foreach ($invoices as $invoice) {
            $transactions->push([
                'date' => $invoice->invoice_date?->toDateString(),
                'type' => 'invoice',
                'reference' => $invoice->number,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0,
                'due_date' => $invoice->due_date?->toDateString(),
            ]);
        }
        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->created_at?->toDateString(),
                'type' => 'payment',
                'reference' => $payment->reference ?? ('PAY-'.$payment->id),
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'due_date' => null,
            ]);
        }

        $transactions = $transactions->sortBy('date')->values();
        $running = 0.0;
        $rows = $transactions->map(function (array $row) use (&$running) {
            $running += $row['debit'] - $row['credit'];
            $row['balance'] = round($running, 2);

            return $row;
        });

        $aging = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '90_plus' => 0,
        ];

        foreach ($invoices->whereIn('status', [
            InvoiceStatus::Outstanding->value,
            InvoiceStatus::Confirmed->value,
            InvoiceStatus::PartiallyPaid->value,
        ]) as $invoice) {
            $due = $invoice->due_date ?? $invoice->invoice_date;
            $days = $due ? $due->diffInDays($statementDate, false) : 0;
            $amount = (float) $invoice->total_amount;
            if ($days <= 0) {
                $aging['current'] += $amount;
            } elseif ($days <= 30) {
                $aging['1_30'] += $amount;
            } elseif ($days <= 60) {
                $aging['31_60'] += $amount;
            } elseif ($days <= 90) {
                $aging['61_90'] += $amount;
            } else {
                $aging['90_plus'] += $amount;
            }
        }

        return Statement::query()->create([
            'source_branch_id' => $branchId,
            'customer_id' => $customer->id,
            'statement_date' => $statementDate->toDateString(),
            'from_date' => $invoices->min('invoice_date')?->toDateString(),
            'to_date' => $statementDate->toDateString(),
            'opening_balance' => 0,
            'outstanding_balance' => round($running, 2),
            'payload' => [
                'transactions' => $rows->all(),
                'aging' => $aging,
            ],
        ]);
    }
}

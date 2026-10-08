<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentVoucher;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * COD Reconciliation: Admin verifies one COD collection recorded by the driver on delivery, entering the
 * amount actually handed over. A shortage is kept on the payment (and a payment voucher for the driver);
 * the order's paid amount is refreshed, which issues the COD invoice once the order is fully paid and
 * every collection is verified.
 */
class VerifyCodCollection
{
    public function __construct(private DocumentNumberingService $numbering) {}

    /** A driver's COD collection that Admin has not verified yet. */
    public static function isPending(Payment $payment): bool
    {
        return $payment->method === 'cod' && in_array($payment->reconciliation_status, [null, 'pending'], true);
    }

    public function execute(Payment $payment, float $received, User $actor, ?string $remarks = null): Payment
    {
        if (! self::isPending($payment)) {
            throw new InvalidArgumentException('This COD collection is already verified.');
        }

        $received = round($received, 2);

        if ($received < 0) {
            throw new InvalidArgumentException('The amount received cannot be negative.');
        }

        $payment = DB::transaction(function () use ($payment, $received, $actor, $remarks) {
            $recorded = round((float) $payment->amount, 2);
            $shortage = max(0, round($recorded - $received, 2));

            $payment->update([
                'amount' => $received,
                'shortage_amount' => $shortage,
                'reconciliation_status' => 'reconciled',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'remarks' => trim(($payment->remarks ? $payment->remarks.' | ' : '').'COD verified by '.$actor->name
                    .($received !== $recorded ? ' · driver recorded RM '.number_format($recorded, 2).', received RM '.number_format($received, 2) : '')
                    .($remarks ? ' · '.$remarks : '')),
            ]);

            if ($payment->consignment_note_id) {
                ConsignmentNote::query()->whereKey($payment->consignment_note_id)->update(['payment_status' => PaymentStatus::CodReconciled->value]);
            }

            if ($shortage > 0 && $payment->driver_id) {
                PaymentVoucher::query()->create([
                    'number' => 'PV-'.$this->numbering->next($payment->source_branch_id, DocumentType::Receipt),
                    'source_branch_id' => $payment->source_branch_id,
                    'driver_id' => $payment->driver_id,
                    'amount' => $shortage,
                    'reason' => 'COD shortage / employee recoverable',
                    'status' => 'issued',
                ]);
            }

            DB::table('cod_reconciliations')->insert([
                'source_branch_id' => $payment->source_branch_id,
                'driver_id' => $payment->driver_id,
                'reconciliation_date' => now()->toDateString(),
                'expected_amount' => $recorded,
                'returned_amount' => $received,
                'shortage_amount' => $shortage,
                'status' => 'closed',
                'reconciled_by' => $actor->id,
                'remarks' => $remarks,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($payment->quotation_id && ($order = Quotation::query()->find($payment->quotation_id))) {
                QuotationStatusLog::query()->create([
                    'quotation_id' => $order->id,
                    'from_status' => $order->status->value,
                    'to_status' => $order->status->value,
                    'user_id' => $actor->id,
                    'remarks' => 'COD collection verified · RM '.number_format($received, 2)
                        .($shortage > 0 ? ' (shortage RM '.number_format($shortage, 2).')' : ''),
                ]);
            }

            return $payment->fresh();
        });

        // paid amount, and the COD invoice once fully paid and verified
        if ($payment->quotation_id && ($order = Quotation::query()->find($payment->quotation_id))) {
            app(RefreshOrderPaidAmount::class)->execute($order);
        }

        return $payment;
    }
}

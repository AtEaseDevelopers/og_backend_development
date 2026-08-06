<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentVoucher;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Driver;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconcileCodCollections
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(Driver $driver, int $branchId, float $returnedAmount, User $actor, ?string $remarks = null): array
    {
        return DB::transaction(function () use ($driver, $branchId, $returnedAmount, $actor, $remarks) {
            $payments = Payment::query()
                ->where('driver_id', $driver->id)
                ->where('source_branch_id', $branchId)
                ->where('method', 'cod')
                ->where(function ($q) {
                    $q->whereNull('reconciliation_status')
                        ->orWhere('reconciliation_status', 'pending');
                })
                ->get();

            if ($payments->isEmpty()) {
                throw new InvalidArgumentException('No pending COD collections for this driver.');
            }

            $expected = (float) $payments->sum('amount');
            $shortage = round($expected - $returnedAmount, 2);

            foreach ($payments as $payment) {
                $payment->update([
                    'reconciliation_status' => 'reconciled',
                    'shortage_amount' => $shortage > 0 ? round($shortage * ($payment->amount / max($expected, 0.01)), 2) : 0,
                    'remarks' => trim(($payment->remarks ? $payment->remarks.' | ' : '').($remarks ?? 'COD reconciled')),
                ]);

                if ($payment->consignment_note_id) {
                    ConsignmentNote::query()->whereKey($payment->consignment_note_id)->update([
                        'payment_status' => PaymentStatus::CodReconciled->value,
                    ]);
                }
            }

            $voucher = null;
            if ($shortage > 0) {
                $voucher = PaymentVoucher::query()->create([
                    'number' => 'PV-'.$this->numbering->next($branchId, DocumentType::Receipt),
                    'source_branch_id' => $branchId,
                    'driver_id' => $driver->id,
                    'amount' => $shortage,
                    'reason' => 'COD shortage / employee recoverable',
                    'status' => 'issued',
                ]);
            }

            DB::table('cod_reconciliations')->insert([
                'source_branch_id' => $branchId,
                'driver_id' => $driver->id,
                'reconciliation_date' => now()->toDateString(),
                'expected_amount' => $expected,
                'returned_amount' => $returnedAmount,
                'shortage_amount' => max($shortage, 0),
                'status' => 'closed',
                'reconciled_by' => $actor->id,
                'remarks' => $remarks,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'expected' => $expected,
                'returned' => $returnedAmount,
                'shortage' => max($shortage, 0),
                'payments' => $payments->count(),
                'voucher' => $voucher,
            ];
        });
    }
}

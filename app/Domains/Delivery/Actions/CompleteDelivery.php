<?php

namespace App\Domains\Delivery\Actions;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Delivery\Models\ProofOfDelivery;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Driver;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\JobSheetStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteDelivery
{
    public function __construct(
        private RecordPayment $recordPayment,
        private FlagMissingCsns $pendingReturns,
    ) {}

    public function execute(DeliveryOrder $do, Driver $driver, array $data, ?User $actor = null): ProofOfDelivery
    {
        if ($do->status === DeliveryOrderStatus::Delivered) {
            throw new InvalidArgumentException('Delivery order already delivered.');
        }

        if (! empty($data['client_uuid'])) {
            $existing = ProofOfDelivery::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($do, $driver, $data, $actor) {
            $csn = $do->consignmentNote;

            if ($csn?->billing_type === CsnBillingType::Cod) {
                $collected = (float) ($data['cod_amount_collected'] ?? $csn->total_amount);
                if ($collected + 0.0001 < (float) $csn->total_amount) {
                    throw new InvalidArgumentException('Driver must collect the full COD amount unless an authorized adjustment is recorded.');
                }
            }

            $pod = ProofOfDelivery::query()->create([
                'delivery_order_id' => $do->id,
                'driver_id' => $driver->id,
                'recipient_name' => $data['recipient_name'] ?? null,
                'signature_path' => $data['signature_path'] ?? null,
                'photo_paths' => $data['photo_paths'] ?? null,
                'pod_document_path' => $data['pod_document_path'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'cod_amount_collected' => $data['cod_amount_collected'] ?? null,
                'cod_payment_method' => $data['cod_payment_method'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
                'delivered_at' => $data['delivered_at'] ?? now(),
                'synced_at' => now(),
            ]);

            $do->update([
                'status' => DeliveryOrderStatus::Delivered,
                'delivered_at' => $pod->delivered_at,
            ]);

            if ($csn) {
                $csn->update([
                    'status' => CsnStatus::Delivered,
                    'return_status' => $csn->return_status === 'returned' ? 'returned' : 'pending_return',
                ]);
                $this->pendingReturns->ensurePendingReturn($csn->fresh());
            }

            if ($csn?->billing_type === CsnBillingType::Cod) {
                $paymentActor = $actor ?? $driver->user ?? User::query()->where('driver_id', $driver->id)->first();
                if ($paymentActor) {
                    $this->recordPayment->execute([
                        'source_branch_id' => $csn->source_branch_id,
                        'customer_id' => $csn->customer_id,
                        'consignment_note_id' => $csn->id,
                        'delivery_order_id' => $do->id,
                        'driver_id' => $driver->id,
                        'method' => 'cod',
                        'amount' => (float) ($data['cod_amount_collected'] ?? $csn->total_amount),
                        'expected_amount' => $csn->total_amount,
                        'reference' => $data['cod_payment_method'] ?? 'COD collection',
                        'receipt_type' => 'cod',
                        'reconciliation_status' => 'pending',
                    ], $paymentActor);
                }
            }

            $jobSheet = $do->jobSheet;
            if ($jobSheet) {
                $pending = $jobSheet->deliveryOrders()
                    ->whereNotIn('status', [
                        DeliveryOrderStatus::Delivered->value,
                        DeliveryOrderStatus::Failed->value,
                        DeliveryOrderStatus::Cancelled->value,
                    ])
                    ->exists();

                if (! $pending) {
                    $jobSheet->update(['status' => JobSheetStatus::Completed]);
                }
            }

            return $pod;
        });
    }
}

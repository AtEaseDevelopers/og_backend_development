<?php

namespace App\Domains\Integration\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\PaymentVoucher;
use App\Domains\Billing\Models\Receipt;
use App\Domains\Commission\Models\CommissionPurchaseOrder;
use App\Domains\Integration\Models\SyncLog;
use App\Domains\Integration\Services\AutoCountClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncDocumentToAutoCount
{
    public function __construct(private AutoCountClient $client) {}

    public function execute(string $documentType, Model $document, ?User $actor = null, bool $forceRetry = false): SyncLog
    {
        $map = [
            'sales_invoice' => Invoice::class,
            'ar_receipt' => Receipt::class,
            'payment_voucher' => PaymentVoucher::class,
            'commission_po' => CommissionPurchaseOrder::class,
            'commission_pi' => CommissionPurchaseOrder::class,
        ];

        if (! isset($map[$documentType]) || ! $document instanceof $map[$documentType]) {
            throw new InvalidArgumentException('Unsupported AutoCount document type: '.$documentType);
        }

        if (! $forceRetry && ($document->autocount_sync_status ?? null) === 'synced') {
            $existing = SyncLog::query()
                ->where('integration', 'autocount')
                ->where('document_type', $documentType)
                ->where('document_id', $document->getKey())
                ->where('status', 'synced')
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $branchId = $document->source_branch_id ?? null;
        $payload = $this->buildPayload($documentType, $document);

        return DB::transaction(function () use ($documentType, $document, $actor, $branchId, $payload, $forceRetry) {
            $previous = SyncLog::query()
                ->where('integration', 'autocount')
                ->where('document_type', $documentType)
                ->where('document_id', $document->getKey())
                ->latest('id')
                ->first();

            $result = $this->client->push($documentType, $payload);

            $log = SyncLog::query()->create([
                'source_branch_id' => $branchId,
                'integration' => 'autocount',
                'document_type' => $documentType,
                'document_id' => $document->getKey(),
                'external_ref' => $result['external_ref'],
                'status' => $result['ok'] ? 'synced' : 'failed',
                'retry_count' => ($previous?->retry_count ?? 0) + ($forceRetry || $previous ? 1 : 0),
                'error_message' => $result['ok'] ? null : $result['message'],
                'payload' => $result['raw'],
                'synced_by' => $actor?->id,
                'synced_at' => $result['ok'] ? now() : null,
            ]);

            $document->forceFill([
                'autocount_sync_status' => $result['ok'] ? 'synced' : 'failed',
            ])->save();

            return $log;
        });
    }

    /** @return array<string, mixed> */
    private function buildPayload(string $type, Model $document): array
    {
        return match ($type) {
            'sales_invoice' => [
                'number' => $document->number,
                'customer_id' => $document->customer_id,
                'branch_id' => $document->source_branch_id,
                'total' => (float) $document->total_amount,
                'invoice_date' => optional($document->invoice_date)?->toDateString(),
            ],
            'ar_receipt' => [
                'number' => $document->number,
                'amount' => (float) $document->amount,
                'branch_id' => $document->source_branch_id,
                'type' => $document->type,
            ],
            'payment_voucher' => [
                'number' => $document->number,
                'amount' => (float) $document->amount,
                'driver_id' => $document->driver_id,
                'branch_id' => $document->source_branch_id,
            ],
            'commission_po', 'commission_pi' => [
                'po_number' => $document->po_number,
                'pi_number' => $document->pi_number,
                'amount' => (float) $document->amount,
                'driver_id' => $document->driver_id,
                'branch_id' => $document->source_branch_id,
            ],
            default => $document->toArray(),
        };
    }
}

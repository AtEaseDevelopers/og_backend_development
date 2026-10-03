<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Actions\ClaimCsn;
use App\Domains\MasterData\Models\Lorry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Scan-to-claim for drivers (section H / J). */
class CsnController extends Controller
{
    /** Preview a scanned CSN before claiming. */
    public function show(Request $request, string $qrToken): JsonResponse
    {
        $csn = ConsignmentNote::query()
            ->with(['claimer', 'deliveryOrder.lorry', 'deliveryOrder.driver', 'sourceBranch'])
            ->where('qr_token', $qrToken)
            ->first();

        if (! $csn) {
            return response()->json(['message' => 'No CSN matches the scanned code.'], 404);
        }

        return response()->json(['data' => $this->transform($csn)]);
    }

    public function claim(Request $request, ClaimCsn $claim): JsonResponse
    {
        $data = $request->validate([
            'qr_token' => ['required', 'string'],
            'lorry_id' => ['nullable', 'exists:lorries,id'],
            'operating_date' => ['nullable', 'date'],
        ]);

        $lorry = isset($data['lorry_id']) ? Lorry::query()->find($data['lorry_id']) : null;

        try {
            $csn = $claim->executeByQrToken($data['qr_token'], $request->user(), $lorry, $data['operating_date'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'CSN '.$csn->number.' claimed.', 'data' => $this->transform($csn->load(['claimer', 'deliveryOrder.lorry', 'deliveryOrder.driver', 'sourceBranch']))]);
    }

    private function transform(ConsignmentNote $csn): array
    {
        return [
            'id' => $csn->id,
            'number' => $csn->number,
            'status' => $csn->status?->value,
            'status_label' => $csn->status?->getLabel(),
            'claimable' => $csn->isClaimable(),
            'transfer_claim_pending' => (bool) $csn->transfer_claim_pending,
            'claimed_by' => $csn->claimer?->name,
            'claimed_at' => $csn->claimed_at,
            'source_branch' => $csn->sourceBranch?->code,
            'sa_prefix' => $csn->sa_prefix,
            'order_type' => $csn->order_type?->value,
            'customer_name' => $csn->customer_name,
            'customer_phone' => $csn->customer_phone,
            'customer_do_number' => $csn->customer_do_number,
            'consignee_name' => $csn->consignee_name,
            'delivery_address' => $csn->delivery_address,
            'service_type' => $csn->service_type?->value,
            'drop_off_type' => $csn->drop_off_type,
            'delivery_order' => $csn->deliveryOrder ? [
                'id' => $csn->deliveryOrder->id,
                'number' => $csn->deliveryOrder->number,
                'status' => $csn->deliveryOrder->status?->value,
                'lorry' => $csn->deliveryOrder->lorry?->registration_no,
                'driver' => $csn->deliveryOrder->driver?->name,
                'job_sheet_id' => $csn->deliveryOrder->job_sheet_id,
            ] : null,
        ];
    }
}

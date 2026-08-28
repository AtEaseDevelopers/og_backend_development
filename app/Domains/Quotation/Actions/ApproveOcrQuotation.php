<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\OcrUpload;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveOcrQuotation
{
    public function __construct(private CreateQuotation $createQuotation) {}

    public function execute(OcrUpload $upload, array $corrections, User $actor): OcrUpload
    {
        if (! in_array($upload->status, ['pending_review', 'draft', 'rejected'], true)) {
            throw new InvalidArgumentException('Only pending OCR uploads can be approved.');
        }

        $data = array_merge($upload->extracted_data ?? [], $corrections);

        return DB::transaction(function () use ($upload, $data, $actor, $corrections) {
            $branchId = $upload->branch_id ?? $actor->defaultBranch()?->id;
            if (! $branchId) {
                throw new InvalidArgumentException('Branch is required to create quotation.');
            }

            $customerId = $upload->customer_id;
            if (! $customerId) {
                $customer = Customer::query()->firstOrCreate(
                    ['company_name' => $data['customer_name'] ?? 'OCR Customer'],
                    [
                        'branch_id' => $branchId,
                        'code' => 'OCR-'.now()->format('ymdHis'),
                        'status' => 'active',
                        'is_credit' => false,
                    ]
                );
                $customerId = $customer->id;
                $upload->update(['customer_id' => $customerId]);
            }

            $lines = $this->normalizeLines($data);

            $quotation = $this->createQuotation->execute([
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'salesperson_id' => $actor->id,
                'status' => QuotationStatus::Draft->value,
                'pricing_source' => 'ocr',
                'notes' => $data['notes'] ?? 'Created from OCR upload #'.$upload->id,
                'destinations' => [[
                    'consignee_name' => $data['consignee_name'] ?? 'Consignee',
                    'consignee_phone' => $data['consignee_phone'] ?? null,
                    'address' => $data['delivery_address'] ?? '',
                    'postcode' => $data['delivery_postcode'] ?? null,
                    'state' => $data['delivery_state'] ?? null,
                    'city' => $data['delivery_city'] ?? null,
                ]],
                'lines' => $lines,
            ], $actor);

            // Link first destination to line
            $dest = $quotation->destinations()->first();
            if ($dest) {
                $quotation->lines()->update(['quotation_destination_id' => $dest->id]);
            }

            $upload->update([
                'extracted_data' => array_merge($upload->extracted_data ?? [], $corrections),
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $corrections['review_notes'] ?? 'Approved and quotation created',
                'quotation_id' => $quotation->id,
            ]);

            return $upload->fresh(['quotation', 'customer', 'reviewer']);
        });
    }

    public function reject(OcrUpload $upload, string $reason, User $actor): OcrUpload
    {
        $upload->update([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        return $upload->fresh();
    }

    /** @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(array $data): array
    {
        if (! empty($data['lines']) && is_array($data['lines'])) {
            return collect($data['lines'])
                ->map(function (array $line): array {
                    $qty = (float) ($line['quantity'] ?? 1);
                    $unit = (float) ($line['unit_price'] ?? $line['rate'] ?? 0);

                    return [
                        'item_name' => $line['item_name'] ?? $line['description'] ?? 'General Goods',
                        'uom' => $line['uom'] ?? 'UNIT',
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => (float) ($line['line_total'] ?? round($qty * $unit, 2)),
                    ];
                })
                ->values()
                ->all();
        }

        $qty = (float) ($data['quantity'] ?? 1);
        $unit = (float) ($data['unit_price'] ?? 0);

        return [[
            'item_name' => $data['item_name'] ?? 'General Goods',
            'uom' => $data['uom'] ?? 'UNIT',
            'quantity' => $qty,
            'unit_price' => $unit,
            'line_total' => (float) ($data['line_total'] ?? round($qty * $unit, 2)),
        ]];
    }
}

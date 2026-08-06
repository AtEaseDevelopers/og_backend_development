<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;

class CreateQuotation
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(array $data, User $actor): Quotation
    {
        return DB::transaction(function () use ($data, $actor) {
            $branch = Branch::query()->findOrFail($data['branch_id']);

            $destinations = $data['destinations'] ?? [];
            $lines = $data['lines'] ?? [];
            unset($data['destinations'], $data['lines']);

            $subtotal = collect($lines)->sum(fn ($line) => (float) ($line['line_total'] ?? 0));

            $quotation = Quotation::query()->create([
                ...$data,
                'number' => $this->numbering->next($branch, DocumentType::Quotation),
                'status' => $data['status'] ?? QuotationStatus::Draft->value,
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + (float) ($data['tax_amount'] ?? 0),
                'created_by' => $actor->id,
            ]);

            foreach ($destinations as $index => $destination) {
                $quotation->destinations()->create([
                    ...$destination,
                    'sequence' => $destination['sequence'] ?? ($index + 1),
                ]);
            }

            foreach ($lines as $line) {
                $quotation->lines()->create($line);
            }

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => null,
                'to_status' => $quotation->status->value,
                'user_id' => $actor->id,
                'remarks' => 'Quotation created',
            ]);

            return $quotation->load(['destinations', 'lines', 'customer', 'branch']);
        });
    }
}

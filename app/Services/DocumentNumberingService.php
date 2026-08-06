<?php

namespace App\Services;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\DocumentNumberSequence;
use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;

class DocumentNumberingService
{
    public function next(Branch|int $branch, DocumentType $type): string
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $branchCode = $branch instanceof Branch
            ? $branch->code
            : Branch::query()->whereKey($branchId)->value('code');

        $period = now()->format('Ym');

        return DB::transaction(function () use ($branchId, $branchCode, $type, $period) {
            $sequence = DocumentNumberSequence::query()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'document_type' => $type->value,
                    'period' => $period,
                ],
                ['last_number' => 0]
            );

            $sequence->last_number++;
            $sequence->save();

            return sprintf(
                '%s-%s-%s-%04d',
                $branchCode,
                $type->value,
                $period,
                $sequence->last_number
            );
        });
    }
}

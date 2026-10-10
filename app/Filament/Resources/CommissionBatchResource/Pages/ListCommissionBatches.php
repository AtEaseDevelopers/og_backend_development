<?php

namespace App\Filament\Resources\CommissionBatchResource\Pages;

use App\Filament\Resources\CommissionBatchResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListCommissionBatches extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = CommissionBatchResource::class;
}

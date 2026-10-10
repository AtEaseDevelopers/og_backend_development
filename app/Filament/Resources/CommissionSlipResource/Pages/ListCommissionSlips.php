<?php

namespace App\Filament\Resources\CommissionSlipResource\Pages;

use App\Filament\Resources\CommissionSlipResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListCommissionSlips extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = CommissionSlipResource::class;
}

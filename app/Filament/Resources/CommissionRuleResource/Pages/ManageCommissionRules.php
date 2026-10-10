<?php

namespace App\Filament\Resources\CommissionRuleResource\Pages;

use App\Filament\Resources\CommissionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ManageCommissionRules extends ManageRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = CommissionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

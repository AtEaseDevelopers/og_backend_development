<?php

namespace App\Filament\Resources\CommissionRuleResource\Pages;

use App\Filament\Resources\CommissionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCommissionRules extends ManageRecords
{
    protected static string $resource = CommissionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

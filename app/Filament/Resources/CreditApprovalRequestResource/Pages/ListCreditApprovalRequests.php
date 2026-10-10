<?php

namespace App\Filament\Resources\CreditApprovalRequestResource\Pages;

use App\Filament\Resources\CreditApprovalRequestResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListCreditApprovalRequests extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = CreditApprovalRequestResource::class;
}

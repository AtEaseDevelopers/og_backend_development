<?php

namespace App\Filament\Resources\EinvoiceSubmissionResource\Pages;

use App\Filament\Resources\EinvoiceSubmissionResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListEinvoiceSubmissions extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = EinvoiceSubmissionResource::class;
}

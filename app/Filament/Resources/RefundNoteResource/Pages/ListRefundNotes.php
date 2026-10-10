<?php

namespace App\Filament\Resources\RefundNoteResource\Pages;

use App\Filament\Resources\RefundNoteResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListRefundNotes extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = RefundNoteResource::class;
}

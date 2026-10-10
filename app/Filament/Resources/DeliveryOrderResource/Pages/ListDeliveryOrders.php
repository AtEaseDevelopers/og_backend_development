<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListDeliveryOrders extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = DeliveryOrderResource::class;
}

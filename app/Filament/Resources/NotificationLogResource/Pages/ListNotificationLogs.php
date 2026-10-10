<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Concerns\HasExcelColumnFilters;

class ListNotificationLogs extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = NotificationLogResource::class;
}

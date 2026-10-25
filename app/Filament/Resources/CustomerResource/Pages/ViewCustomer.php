<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    // each card has its own Edit (user, 25 Oct 2026); the status (active / inactive) is changed in the Account card or
    // on the edit page, no separate button. An inactive customer is not offered on Create order, Create quotation or the
    // CSN form.
    protected function getHeaderActions(): array
    {
        return [];
    }
}

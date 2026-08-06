<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('cashBillCalculator')
                ->label('Cash Bill Calculator')
                ->url(fn () => \App\Filament\Pages\CashBillCalculator::getUrl()),
            Actions\Action::make('codReconcile')
                ->label('COD Reconciliation')
                ->url(fn () => \App\Filament\Pages\CodReconciliation::getUrl()),
        ];
    }
}

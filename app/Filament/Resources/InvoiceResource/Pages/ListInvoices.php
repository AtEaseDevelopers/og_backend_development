<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Domains\Billing\Actions\ConsolidateTermInvoices;
use App\Domains\MasterData\Models\Branch;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('consolidateTerm')
                ->label('Month-end Term Billing')
                ->icon('heroicon-o-calendar')
                ->form([
                    Forms\Components\TextInput::make('billing_month')
                        ->label('Billing month (YYYY-MM)')
                        ->default(now()->format('Y-m'))
                        ->required(),
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch (optional)')
                        ->options(Branch::query()->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $invoices = app(ConsolidateTermInvoices::class)->execute(
                        $data['billing_month'],
                        $data['branch_id'] ?? null
                    );
                    Notification::make()
                        ->title('Generated '.$invoices->count().' term invoice(s)')
                        ->success()
                        ->send();
                }),
        ];
    }
}

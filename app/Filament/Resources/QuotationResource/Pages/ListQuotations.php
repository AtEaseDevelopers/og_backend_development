<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Filament\Widgets\QuotationStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    public function getHeading(): string
    {
        return 'Quotation Management';
    }

    public function getSubheading(): ?string
    {
        return 'Manage and track all logistics quotations across branches.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QuotationStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Quotation')
                ->icon('heroicon-o-plus'),
        ];
    }
}

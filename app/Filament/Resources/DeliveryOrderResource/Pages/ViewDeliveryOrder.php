<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Filament\Pages\DeliveryMonitoring;
use App\Filament\Resources\DeliveryOrderResource;
use App\Support\DeliveryTaskViewData;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliveryOrder extends ViewRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    public function getTitle(): string
    {
        return 'Delivery Task Detail & Proof Monitoring';
    }

    public function getSubheading(): ?string
    {
        return 'Review delivery task details, proof of delivery, and operational timestamps.';
    }

    protected function getHeaderActions(): array
    {
        /** @var DeliveryOrder $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('previewPdf')
                ->label('Preview PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => DeliveryOrderResource::pdfUrl($record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $record->consignmentNote()->exists()),
            Actions\Action::make('backToMonitoring')
                ->label('Back to Delivery Monitoring')
                ->color('gray')
                ->url(fn (): string => Filament::getTenant()
                    ? DeliveryMonitoring::getUrl([], true, null, Filament::getTenant())
                    : DeliveryMonitoring::getUrl()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('delivery_task_view')
                    ->hiddenLabel()
                    ->view('filament.infolists.delivery-task-view')
                    ->viewData(fn (DeliveryOrder $record): array => app(DeliveryTaskViewData::class)->for($record)),
            ])
            ->columns(1);
    }
}

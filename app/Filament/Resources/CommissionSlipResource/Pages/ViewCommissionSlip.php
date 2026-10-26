<?php

namespace App\Filament\Resources\CommissionSlipResource\Pages;

use App\Filament\Resources\CommissionSlipResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionSlip extends ViewRecord
{
    protected static string $resource = CommissionSlipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the PO in the client's layout (user, 27 Oct 2026)
            Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-text')->url(fn () => CommissionSlipResource::pdfUrl($this->getRecord()))->openUrlInNewTab(),
        ];
    }
}

<?php

namespace App\Filament\Resources\JobSheetResource\Pages;

use App\Domains\Dispatch\Models\JobSheet;
use App\Enums\JobSheetStatus;
use App\Filament\Resources\JobSheetResource;
use App\Support\JobSheetViewData;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewJobSheet extends ViewRecord
{
    protected static string $resource = JobSheetResource::class;

    public ?int $driverId = null;

    public ?int $lorryId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var JobSheet $jobSheet */
        $jobSheet = $this->getRecord();
        $this->driverId = $jobSheet->driver_id;
        $this->lorryId = $jobSheet->lorry_id;
    }

    public function getTitle(): string
    {
        /** @var JobSheet $record */
        $record = $this->getRecord();

        return 'Job Sheet Details — '.$record->number;
    }

    public function saveDraft(): void
    {
        /** @var JobSheet $record */
        $record = $this->getRecord();

        if ($record->status !== JobSheetStatus::Draft) {
            Notification::make()
                ->title('Only draft job sheets can be saved.')
                ->warning()
                ->send();

            return;
        }

        $record->update([
            'driver_id' => $this->driverId,
            'lorry_id' => $this->lorryId,
        ]);

        $this->refreshRecord();

        Notification::make()
            ->title('Draft saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        /** @var JobSheet $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('backToListing')
                ->label('Back to Listing')
                ->color('gray')
                ->url(JobSheetResource::getUrl('index')),
            Actions\Action::make('auditLog')
                ->label('Audit Log')
                ->color('gray')
                ->url(fn (): string => JobSheetResource::getUrl('audit-log', ['record' => $record])),
            Actions\Action::make('saveDraft')
                ->label('Save Draft')
                ->visible(fn (): bool => $record->status === JobSheetStatus::Draft)
                ->action(fn () => $this->saveDraft()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('job_sheet_view')
                    ->hiddenLabel()
                    ->view('filament.infolists.job-sheet-view')
                    ->viewData(fn (JobSheet $record): array => app(JobSheetViewData::class)->for($record)),
            ])
            ->columns(1);
    }
}

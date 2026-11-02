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

        // a transfer job sheet picked up here: the whole sheet goes to the lorry (client's BA, 3 Nov 2026)
        if ($record->isUnassigned() && $this->lorryId) {
            try {
                $legs = app(\App\Domains\Dispatch\Actions\TransferJobSheets::class)->assign($record, \App\Domains\MasterData\Models\Lorry::query()->findOrFail($this->lorryId), $this->driverId);
                Notification::make()->title($record->number.' assigned · '.$legs.' leg(s) on the lorry')->success()->send();
            } catch (\Throwable $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
            }

            $this->refreshRecord();

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
            // the Job Sheet print (client's layout): Self + one page per job sheet code (user, 27 Oct 2026)
            Actions\Action::make('pdf')
                ->label('Print job sheet')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('filament.admin.job-sheets.pdf', ['tenant' => \Filament\Facades\Filament::getTenant(), 'jobSheet' => $record->id]))
                ->openUrlInNewTab(),
            Actions\Action::make('backToListing')
                ->label('Back to Listing')
                ->color('gray')
                ->url(JobSheetResource::getUrl('index')),
            Actions\Action::make('auditLog')
                ->label('Audit Log')
                ->color('gray')
                ->url(fn (): string => JobSheetResource::getUrl('audit-log', ['record' => $record])),
            // a transfer job sheet waiting to be picked up (client's BA, 3 Nov 2026)
            Actions\Action::make('assignJobSheet')
                ->label('Assign job sheet')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (): bool => $record->isUnassigned())
                ->modalHeading(fn () => 'Assign '.$record->number.' to a lorry')
                ->modalDescription('Every leg on this transfer job sheet goes to the lorry and its driver.')
                ->form([
                    \Filament\Forms\Components\Select::make('lorry_id')->label('Lorry')->options(fn () => \App\Filament\Resources\ConsignmentNoteResource::lorryOptions())->required()->searchable(),
                    \Filament\Forms\Components\Select::make('driver_id')->label('Driver')->options(fn () => \App\Domains\MasterData\Models\Driver::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable()->helperText('Blank: the lorry\'s default driver.'),
                ])
                ->action(function (array $data) use ($record): void {
                    try {
                        $legs = app(\App\Domains\Dispatch\Actions\TransferJobSheets::class)->assign($record, \App\Domains\MasterData\Models\Lorry::query()->findOrFail($data['lorry_id']), filled($data['driver_id'] ?? null) ? (int) $data['driver_id'] : null);
                        Notification::make()->title($record->number.' assigned · '.$legs.' leg(s) on the lorry')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }

                    $this->refreshRecord();
                }),
            Actions\Action::make('saveDraft')
                ->label('Save Draft')
                ->visible(fn (): bool => $record->status === JobSheetStatus::Draft && ! $record->isUnassigned())
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

<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\DriverResource;
use App\Services\Imports\DriverListImporter;
use App\Support\CurrentCompany;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListDrivers extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the client's Lorry & Driver Details workbook (user, 21 Oct 2026): drivers, lorry company / group, lorry
            // and attendant; the lorries are kept in step
            Actions\Action::make('importDrivers')
                ->label('Import driver list')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import driver list')
                ->modalDescription('Upload the Lorry & Driver Details workbook. Each row becomes a driver of this branch (a driver already here, same IC or name, is updated) with its lorry company, group, lorry and attendant; the lorry is added to Master Data → Lorries with this driver.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel workbook')
                        ->required()
                        ->disk('local')
                        ->directory('driver-imports')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240),
                ])
                ->modalSubmitActionLabel('Import')
                ->action(function (array $data): void {
                    $disk = Storage::disk('local');

                    try {
                        $result = app(DriverListImporter::class)->importFile($disk->path($data['file']), CurrentCompany::id(), CurrentCompany::branchId());

                        Notification::make()
                            ->title('Driver list imported')
                            ->body($result['created'].' driver(s) added, '.$result['updated'].' updated, '.$result['lorries'].' lorr'.($result['lorries'] === 1 ? 'y' : 'ies').' linked'.($result['skipped'] !== [] ? "\n".implode("\n", $result['skipped']) : ''))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('The driver list was not imported')->body($e->getMessage())->danger()->send();
                    } finally {
                        $disk->delete($data['file']);
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Filament\Concerns\HasExcelColumnFilters;
use App\Filament\Resources\UomResource;
use App\Services\Imports\ClientPriceListImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListUoms extends ListRecords
{
    use HasExcelColumnFilters;

    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the client's "O&G Customer Pricing Setup Template" workbooks: the default price file and/or the special
            // price file (one sheet per customer); importing a file again replaces the prices it holds
            Actions\Action::make('importPriceList')
                ->label('Import price list')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                // one import for every category (it fills UOM, Items and Lorries)
                ->visible(fn () => static::getResource() === UomResource::class && UomResource::canCreate())
                ->modalHeading('Import price list')
                ->modalDescription('Upload the O&G Customer Pricing Setup workbook(s): the Default Price file and/or the Special Price file. The default prices of the file\'s company, and the special prices of each customer in it, are replaced.')
                ->modalSubmitActionLabel('Import')
                ->form([
                    Forms\Components\FileUpload::make('files')
                        ->label('Excel workbook(s)')
                        ->multiple()
                        ->required()
                        ->disk('local')
                        ->directory('price-list-imports')
                        ->storeFileNamesIn('file_names')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel.sheet.macroEnabled.12',
                        ])
                        ->maxSize(20480),
                ])
                ->action(function (array $data): void {
                    $importer = app(ClientPriceListImporter::class);
                    $disk = Storage::disk('local');

                    foreach ((array) $data['files'] as $file) {
                        $name = $data['file_names'][$file] ?? basename($file);

                        try {
                            $lines = ClientPriceListImporter::summaryLines($importer->importFile($disk->path($file), $name));

                            Notification::make()
                                ->title($name.' imported')
                                ->body(implode("\n", array_slice($lines, 0, 12)).(count($lines) > 12 ? "\n… and ".(count($lines) - 12).' more note(s)' : ''))
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($name.' was not imported')->body($e->getMessage())->danger()->persistent()->send();
                        } finally {
                            $disk->delete($file);
                        }
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}

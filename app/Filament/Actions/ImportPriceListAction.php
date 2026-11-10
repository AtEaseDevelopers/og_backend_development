<?php

namespace App\Filament\Actions;

use App\Services\Imports\ClientPriceListImporter;
use Filament\Actions\Action;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * "Import price list" (the client's O&G Customer Pricing Setup workbooks): the files are read as soon as they are
 * uploaded and a preview (companies, customers, rows, warnings) appears in the modal; then the user chooses whether
 * the file **replaces** the current price list or only **adds / updates** the prices it holds (user, 2 Nov 2026).
 * Used on the product pages (Master Data) and on the customer page (Special price).
 */
class ImportPriceListAction
{
    /** parsed previews of this request, by file path (the preview and the mode's visibility share them) */
    private static array $previews = [];

    public static function make(string $name = 'importPriceList'): Action
    {
        return Action::make($name)
            ->label('Import price list')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Import price list')
            ->modalDescription('Upload the O&G Customer Pricing Setup workbook(s): the Default Price file and/or the Special Price file (one sheet per customer). The file is checked first; then choose whether it replaces the current prices or only adds to them.')
            ->modalSubmitActionLabel('Import')
            ->modalWidth('2xl')
            // the file is checked as soon as it is uploaded (user, 2 Nov 2026): the preview and the choice appear in the
            // modal, and the Import button only when every file can be read
            ->modalSubmitAction(fn (StaticAction $action, $livewire) => $action->hidden(! static::allParsed(static::uploaded(static::modalFiles($livewire)))))
            ->form([
                Forms\Components\FileUpload::make('files')
                    ->label('Excel workbook(s)')
                    ->multiple()
                    ->required()
                    ->live()
                    ->disk('local')
                    ->directory('price-list-imports')
                    ->storeFileNamesIn('file_names')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel.sheet.macroEnabled.12',
                    ])
                    ->maxSize(20480),
                Forms\Components\Placeholder::make('preview')
                    ->label('What the file holds')
                    ->visible(fn (Get $get) => static::uploaded($get('files')) !== [])
                    ->content(fn (Get $get) => new HtmlString(static::previewHtml(static::uploaded($get('files'))))),
                Forms\Components\Radio::make('mode')
                    ->label('How to import')
                    ->options([
                        'add' => 'Add the missing prices only',
                        'merge' => 'Add or update these prices only',
                        'replace' => 'Replace the current price list',
                    ])
                    ->descriptions([
                        'add' => 'Nothing existing is changed or removed. A price the system already has is kept and the row in the file is ignored.',
                        'merge' => 'A price the system already has is overwritten by the price in the file. Nothing else is removed.',
                        'replace' => 'The prices of the customers in the file (and the default prices, when the file has default rows) are removed first.',
                    ])
                    ->default('add')
                    ->required()
                    ->visible(fn (Get $get) => static::allParsed(static::uploaded($get('files')))),
            ])
            ->action(function (array $data): void {
                $importer = app(ClientPriceListImporter::class);
                $disk = Storage::disk('local');
                $mode = $data['mode'] ?? 'add';
                $replace = $mode === 'replace';
                $addOnly = $mode === 'add';

                if (! static::allParsed(static::uploaded($data['files'] ?? []))) {
                    Notification::make()->title('A file cannot be read')->body('Fix or remove it, then import again.')->danger()->send();

                    foreach ((array) ($data['files'] ?? []) as $file) {
                        $disk->delete($file);
                    }

                    return;
                }

                foreach ((array) $data['files'] as $file) {
                    $name = $data['file_names'][$file] ?? basename($file);

                    try {
                        $lines = ClientPriceListImporter::summaryLines($importer->importFile($disk->path($file), $name, $replace, $addOnly));

                        Notification::make()
                            ->title($name.match (true) {
                                $addOnly => ' imported · missing prices added',
                                $replace => ' imported · price list replaced',
                                default => ' imported · prices added / updated',
                            })
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
            });
    }

    /** The files of the open modal from its Livewire state (the modal footer has no form access). */
    private static function modalFiles(mixed $livewire): mixed
    {
        $data = is_object($livewire) && property_exists($livewire, 'mountedActionsData') ? (array) $livewire->mountedActionsData : [];
        $last = $data === [] ? [] : (array) end($data);

        return $last['files'] ?? null;
    }

    /**
     * The files in the modal as [path => name]: uploaded ones (still temporary) and any already stored.
     *
     * @return array<string, string>
     */
    private static function uploaded(mixed $files): array
    {
        $out = [];

        // the modal's state holds the uploads under their ids (sometimes one level deeper): every file found counts
        $walk = function (mixed $value) use (&$walk, &$out): void {
            if ($value instanceof TemporaryUploadedFile) {
                $out[$value->getRealPath()] = $value->getClientOriginalName();
            } elseif (is_string($value) && $value !== '' && Storage::disk('local')->exists($value)) {
                $out[Storage::disk('local')->path($value)] = basename($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };
        $walk($files);

        return $out;
    }

    /**
     * The file read without writing: ok with its counts, or the error.
     *
     * @return array{ok: bool, error?: string, data?: array<string, mixed>}
     */
    private static function preview(string $path, string $name): array
    {
        return static::$previews[$path] ??= (function () use ($path, $name) {
            try {
                return ['ok' => true, 'data' => app(ClientPriceListImporter::class)->parse($path, $name)];
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        })();
    }

    /** @param  array<string, string>  $files */
    private static function allParsed(array $files): bool
    {
        if ($files === []) {
            return false;
        }

        foreach ($files as $path => $name) {
            if (! static::preview($path, $name)['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, string>  $files */
    private static function previewHtml(array $files): string
    {
        $blocks = [];

        foreach ($files as $path => $name) {
            $preview = static::preview($path, $name);

            if (! $preview['ok']) {
                $blocks[] = '<div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300"><b>'.e($name).'</b> cannot be imported: '.e($preview['error']).'</div>';

                continue;
            }

            $data = $preview['data'];
            $customers = $data['customers'];
            $default = collect($data['rates'])->where('customer_code', null)->count();
            $special = count($data['rates']) - $default;
            $lines = [
                e($data['rows'].' price row(s) read · '.count($data['products']).' product(s) · '.count($data['locations']).' destination(s)'),
                e(($default > 0 ? $default.' default price row(s) for '.implode(', ', $data['branches']) : 'no default prices')
                    .' · '.($special > 0 ? $special.' special price row(s) for '.count($customers).' customer(s)' : 'no special prices')),
            ];

            if ($customers !== []) {
                $list = collect($customers)->map(fn ($n, $code) => str_starts_with((string) $code, 'NEW:') ? 'new customer · '.$n : $code.' '.$n)->values();
                $lines[] = e('Customers: '.$list->take(8)->implode('; ').($list->count() > 8 ? ' … and '.($list->count() - 8).' more' : ''));
            }

            foreach (array_slice($data['warnings'], 0, 8) as $warning) {
                $lines[] = '<span class="text-warning-600 dark:text-warning-400">⚠ '.e($warning).'</span>';
            }

            if (count($data['warnings']) > 8) {
                $lines[] = e('… and '.(count($data['warnings']) - 8).' more warning(s); they are listed again after the import');
            }

            $blocks[] = '<div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10"><div class="font-semibold">'.e($name).'</div>'
                .implode('', array_map(fn (string $line) => '<div class="mt-1 text-gray-600 dark:text-gray-300">'.$line.'</div>', $lines)).'</div>';
        }

        return '<div class="space-y-2">'.implode('', $blocks).'</div>';
    }
}

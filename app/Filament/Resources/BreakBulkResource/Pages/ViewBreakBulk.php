<?php

namespace App\Filament\Resources\BreakBulkResource\Pages;

use App\Domains\Delivery\Actions\AssignBreakBulkContinuation;
use App\Domains\Delivery\Models\BreakBulk;
use App\Filament\Resources\BreakBulkResource;
use App\Support\BreakBulkViewData;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewBreakBulk extends ViewRecord
{
    protected static string $resource = BreakBulkResource::class;

    public ?int $replacementDriverId = null;

    public ?int $replacementLorryId = null;

    public ?string $auditSearch = null;

    public string $auditSortColumn = 'sort_at';

    public string $auditSortDirection = 'desc';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var BreakBulk $breakBulk */
        $breakBulk = $this->getRecord();
        $this->replacementDriverId = $breakBulk->replacement_driver_id;
        $this->replacementLorryId = $breakBulk->replacement_lorry_id;
    }

    public function getTitle(): string
    {
        return 'Breakbulk Reassignment';
    }

    public function sortAuditColumn(string $column): void
    {
        if ($this->auditSortColumn === $column) {
            $this->auditSortDirection = $this->auditSortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->auditSortColumn = $column;
        $this->auditSortDirection = 'asc';
    }

    public function saveReassignment(): void
    {
        /** @var BreakBulk $record */
        $record = $this->getRecord();

        if ($record->status !== 'active') {
            Notification::make()
                ->title('Only pending break-bulk records can be saved.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->replacementDriverId && ! $this->replacementLorryId) {
            Notification::make()
                ->title('Select a replacement driver or lorry.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(AssignBreakBulkContinuation::class)->execute($record, [
                'replacement_driver_id' => $this->replacementDriverId,
                'replacement_lorry_id' => $this->replacementLorryId,
                'operating_date' => now()->toDateString(),
            ], auth()->user());

            $this->refreshRecord();

            /** @var BreakBulk $breakBulk */
            $breakBulk = $this->getRecord();
            $this->replacementDriverId = $breakBulk->replacement_driver_id;
            $this->replacementLorryId = $breakBulk->replacement_lorry_id;

            Notification::make()
                ->title('Reassignment saved')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<string, mixed> */
    protected function getBreakBulkViewData(): array
    {
        $data = app(BreakBulkViewData::class)->for($this->getRecord());
        $data['audit_rows'] = $this->filteredAuditRows($data['audit_rows'] ?? []);
        $data['audit_sort_column'] = $this->auditSortColumn;
        $data['audit_sort_direction'] = $this->auditSortDirection;
        $data['audit_search'] = $this->auditSearch;
        $data['reassignment']['replacement_driver_id'] = $this->replacementDriverId;
        $data['reassignment']['replacement_lorry_id'] = $this->replacementLorryId;

        return $data;
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filteredAuditRows(array $rows): array
    {
        $collection = collect($rows);

        if (filled($this->auditSearch)) {
            $needle = strtolower(trim((string) $this->auditSearch));

            $collection = $collection->filter(function (array $row) use ($needle): bool {
                $haystack = strtolower(implode(' ', [
                    (string) ($row['date_time'] ?? ''),
                    (string) ($row['reassignment_type'] ?? ''),
                    (string) ($row['original_driver'] ?? ''),
                    (string) ($row['replacement_driver'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                    (string) ($row['user'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            });
        }

        $column = $this->auditSortColumn;
        $descending = $this->auditSortDirection === 'desc';

        $sorted = $collection->sortBy(function (array $row) use ($column): int|string {
            if (in_array($column, ['sort_at', 'date_time'], true)) {
                return (int) ($row['sort_at'] ?? 0);
            }

            return strtolower((string) ($row[$column] ?? ''));
        }, SORT_REGULAR, $descending);

        return $sorted->values()->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToListing')
                ->label('Back to Break Bulk Listing')
                ->color('gray')
                ->url(BreakBulkResource::getUrl('index')),
            Actions\Action::make('saveReassignment')
                ->label('Save')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->action(fn () => $this->saveReassignment()),
            Actions\Action::make('handover')
                ->label('Update Handover')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->form([
                    Forms\Components\Select::make('handover_status')
                        ->options([
                            'pending' => 'Pending',
                            'released' => 'Released',
                            'collected' => 'Collected',
                            'completed' => 'Completed',
                        ])
                        ->default(fn (): string => $this->record->handover_status ?? 'pending')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(AssignBreakBulkContinuation::class)->updateHandover($this->record, $data['handover_status']);
                    $this->refreshRecord();
                    Notification::make()->title('Handover updated')->success()->send();
                }),
            Actions\Action::make('revoke')
                ->label('Revoke')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->form([Forms\Components\Textarea::make('reason')->required()])
                ->action(function (array $data): void {
                    app(AssignBreakBulkContinuation::class)->revoke($this->record, $data['reason']);
                    $this->refreshRecord();
                    Notification::make()->title('Break-Bulk revoked')->warning()->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('break_bulk_view')
                    ->hiddenLabel()
                    ->view('filament.infolists.break-bulk-view')
                    ->viewData(fn (BreakBulk $record): array => $this->getBreakBulkViewData()),
            ])
            ->columns(1);
    }
}

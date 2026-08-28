<?php

namespace App\Filament\Resources\FailedDeliveryResource\Pages;

use App\Domains\Delivery\Actions\ReassignFailedDelivery;
use App\Domains\Delivery\Models\FailedDelivery;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\FailedDeliveryResource;
use App\Support\FailedDeliveryViewData;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewFailedDelivery extends ViewRecord
{
    protected static string $resource = FailedDeliveryResource::class;

    public string $reassignmentOption = 'standard';

    public ?int $replacementDriverId = null;

    public ?int $replacementLorryId = null;

    public ?string $auditSearch = null;

    public string $auditSortColumn = 'sort_at';

    public string $auditSortDirection = 'desc';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var FailedDelivery $failed */
        $failed = $this->getRecord();
        $this->reassignmentOption = $failed->reassignment_option ?? 'standard';
        $this->replacementDriverId = $failed->replacementDeliveryOrder?->driver_id;
        $this->replacementLorryId = $failed->replacementDeliveryOrder?->lorry_id;
    }

    public function getTitle(): string
    {
        return 'Failed Delivery Reassignment';
    }

    public function getSubheading(): ?string
    {
        return 'Review the failed delivery task and prepare reassignment to a replacement driver.';
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
        /** @var FailedDelivery $record */
        $record = $this->getRecord();

        if (filled($record->replacement_do_id)) {
            Notification::make()
                ->title('This failed delivery was already reassigned.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->replacementLorryId) {
            Notification::make()
                ->title('Select a replacement lorry.')
                ->warning()
                ->send();

            return;
        }

        try {
            $lorry = Lorry::query()->findOrFail($this->replacementLorryId);

            app(ReassignFailedDelivery::class)->execute(
                $record,
                $this->reassignmentOption,
                $lorry,
                auth()->user(),
                now()->toDateString(),
                $this->replacementDriverId,
            );

            $this->refreshRecord();

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
    protected function getFailedDeliveryViewData(): array
    {
        $data = app(FailedDeliveryViewData::class)->for($this->getRecord());
        $data['audit_rows'] = $this->filteredAuditRows($data['audit_rows'] ?? []);
        $data['audit_sort_column'] = $this->auditSortColumn;
        $data['audit_sort_direction'] = $this->auditSortDirection;
        $data['audit_search'] = $this->auditSearch;
        $data['assignment']['reassignment_option'] = $this->reassignmentOption;
        $data['assignment']['replacement_driver_id'] = $this->replacementDriverId;
        $data['assignment']['replacement_lorry_id'] = $this->replacementLorryId;

        $selectedDriverName = collect($data['driver_options'] ?? [])->get($this->replacementDriverId);
        $data['selected_replacement_driver_name'] = $selectedDriverName;

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
            Actions\Action::make('backToReview')
                ->label('Back to Failed Delivery Review')
                ->color('gray')
                ->url(fn (): string => Filament::getTenant()
                    ? FailedDeliveryResource::getUrl('index', [], true, null, Filament::getTenant())
                    : FailedDeliveryResource::getUrl('index')),
            Actions\Action::make('saveReassignment')
                ->label('Save')
                ->visible(fn (): bool => blank($this->record->replacement_do_id))
                ->action(fn () => $this->saveReassignment()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('failed_delivery_view')
                    ->hiddenLabel()
                    ->view('filament.infolists.failed-delivery-view')
                    ->viewData(fn (FailedDelivery $record): array => $this->getFailedDeliveryViewData()),
            ])
            ->columns(1);
    }
}

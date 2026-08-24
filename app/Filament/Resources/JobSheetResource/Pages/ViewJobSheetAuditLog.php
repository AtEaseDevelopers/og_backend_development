<?php

namespace App\Filament\Resources\JobSheetResource\Pages;

use App\Domains\Dispatch\Models\JobSheet;
use App\Filament\Resources\JobSheetResource;
use App\Support\JobSheetAuditLogData;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJobSheetAuditLog extends ViewRecord
{
    protected static string $resource = JobSheetResource::class;

    protected static string $view = 'filament.resources.job-sheet-resource.pages.view-audit-log';

    public ?string $auditSearch = null;

    public string $auditSortColumn = 'sort_at';

    public string $auditSortDirection = 'desc';

    public function getTitle(): string
    {
        return 'Job Sheet Audit Log';
    }

    protected function getHeaderActions(): array
    {
        return [];
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

    /** @return array<string, mixed> */
    protected function getAuditLogViewData(): array
    {
        $data = app(JobSheetAuditLogData::class)->for($this->getRecord());
        $data['audit_rows'] = $this->filteredAuditRows($data['audit_rows'] ?? []);
        $data['audit_sort_column'] = $this->auditSortColumn;
        $data['audit_sort_direction'] = $this->auditSortDirection;
        $data['audit_search'] = $this->auditSearch;

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
                    (string) ($row['date'] ?? ''),
                    (string) ($row['time'] ?? ''),
                    (string) ($row['user'] ?? ''),
                    (string) ($row['change_type'] ?? ''),
                    (string) ($row['original_value'] ?? ''),
                    (string) ($row['new_value'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            });
        }

        $column = $this->auditSortColumn;
        $descending = $this->auditSortDirection === 'desc';

        $sorted = $collection->sortBy(function (array $row) use ($column): int|string {
            if (in_array($column, ['sort_at', 'date', 'time'], true)) {
                return (int) ($row['sort_at'] ?? 0);
            }

            return strtolower((string) ($row[$column] ?? ''));
        }, SORT_REGULAR, $descending);

        return $sorted->values()->all();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\ViewEntry::make('job_sheet_audit_log')
                    ->hiddenLabel()
                    ->view('filament.infolists.job-sheet-audit-log')
                    ->viewData(fn (JobSheet $record): array => $this->getAuditLogViewData()),
            ])
            ->columns(1);
    }
}

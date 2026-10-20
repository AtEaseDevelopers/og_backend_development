<?php

namespace App\Filament\Resources\CommissionBatchResource\Pages;

use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\Dispatch\Models\JobSheet;
use App\Filament\Resources\CommissionBatchResource;
use App\Filament\Resources\CommissionBatchResource\Pages\Concerns\EditsCommissionLines;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * A job sheet's commission in a batch (user, 21 Oct 2026): its CSNs, each one's commission editable; Save generates
 * the driver's PO.
 */
class JobSheetCommission extends Page
{
    use EditsCommissionLines;
    use InteractsWithRecord;

    protected static string $resource = CommissionBatchResource::class;

    protected static string $view = 'filament.resources.commission-batch-resource.pages.job-sheet-commission';

    public int $jobSheetId;

    public function mount(int|string $record, int|string $jobSheet): void
    {
        $this->record = $this->resolveRecord($record);
        $this->jobSheetId = (int) $jobSheet;
        $this->fillAmounts();
    }

    public function getBatch(): CommissionBatch
    {
        return $this->getRecord();
    }

    public function jobSheet(): ?JobSheet
    {
        return JobSheet::query()->with(['driver:id,name', 'lorry:id,registration_no'])->find($this->jobSheetId);
    }

    protected function linesQuery(): Builder
    {
        return CommissionLineItem::query()
            ->whereHas('deliveryOrder', fn (Builder $q) => $q->where('job_sheet_id', $this->jobSheetId))
            ->whereHas('slip', fn (Builder $q) => $q->where('commission_batch_id', $this->getRecord()->id));
    }

    public function getTitle(): string
    {
        return 'Job sheet '.($this->jobSheet()?->number ?? '—');
    }

    public function getBreadcrumbs(): array
    {
        $sheet = $this->jobSheet();

        return array_filter([
            CommissionBatchResource::getUrl('index') => 'Commission Batches',
            CommissionBatchResource::getUrl('view', ['record' => $this->getRecord()]) => $this->getRecord()->number,
            ($sheet?->lorry_id ? CommissionBatchResource::getUrl('lorry', ['record' => $this->getRecord(), 'lorry' => $sheet->lorry_id]) : null) => $sheet?->lorry?->registration_no ? 'Lorry '.$sheet->lorry->registration_no : null,
            'Job sheet '.($sheet?->number ?? ''),
        ]);
    }
}

<?php

namespace App\Filament\Resources\CommissionBatchResource\Pages;

use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionLineItem;
use App\Domains\MasterData\Models\Lorry;
use App\Filament\Resources\CommissionBatchResource;
use App\Filament\Resources\CommissionBatchResource\Pages\Concerns\EditsCommissionLines;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

/**
 * A lorry's commission in a batch (user, 21 Oct 2026): its job sheets and drivers, each CSN's commission editable;
 * Save generates the drivers' POs. A job sheet opens its own page.
 */
class LorryCommission extends Page
{
    use EditsCommissionLines;
    use InteractsWithRecord;

    protected static string $resource = CommissionBatchResource::class;

    protected static string $view = 'filament.resources.commission-batch-resource.pages.lorry-commission';

    public int $lorryId;

    public function mount(int|string $record, int|string $lorry): void
    {
        $this->record = $this->resolveRecord($record);
        $this->lorryId = (int) $lorry;
        $this->fillAmounts();
    }

    public function getBatch(): CommissionBatch
    {
        return $this->getRecord();
    }

    public function lorry(): ?Lorry
    {
        return Lorry::query()->with('branch:id,name')->find($this->lorryId);
    }

    protected function linesQuery(): Builder
    {
        return CommissionLineItem::query()
            ->where('lorry_id', $this->lorryId)
            ->whereHas('slip', fn (Builder $q) => $q->where('commission_batch_id', $this->getRecord()->id));
    }

    public function getTitle(): string
    {
        return 'Lorry '.($this->lorry()?->registration_no ?? '—').' · '.$this->getRecord()->month;
    }

    public function getBreadcrumbs(): array
    {
        return [
            CommissionBatchResource::getUrl('index') => 'Commission Batches',
            CommissionBatchResource::getUrl('view', ['record' => $this->getRecord()]) => $this->getRecord()->number,
            'Lorry '.($this->lorry()?->registration_no ?? ''),
        ];
    }
}

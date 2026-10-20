<?php

namespace App\Filament\Resources\CommissionBatchResource\Pages\Concerns;

use App\Domains\Commission\Actions\SaveCommissionLines;
use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionLineItem;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The commission lines of a batch's lorry or job sheet page (user, 21 Oct 2026): one row per CSN leg and driver, its
 * amount editable by admin (per CSN); Save keeps the amounts and generates the drivers' POs.
 */
trait EditsCommissionLines
{
    /** Line id → RM, as entered. @var array<string, string> */
    public array $amounts = [];

    abstract protected function linesQuery(): Builder;

    abstract public function getBatch(): CommissionBatch;

    /** @return Collection<int, CommissionLineItem> */
    public function lines(): Collection
    {
        return $this->linesQuery()
            ->with([
                'driver:id,name',
                'lorry:id,registration_no',
                'deliveryOrder:id,number,job_sheet_id,consignment_note_id,parent_do_id,subsheet_id',
                'deliveryOrder.jobSheet:id,number,operating_date,driver_id,lorry_id',
                'consignmentNote:id,number,customer_id,customer_name,consignee_name,do_number,issued_at,total_amount,from_location_id,to_location_id',
                'consignmentNote.customer:id,company_name',
                'consignmentNote.fromLocation:id,name',
                'consignmentNote.toLocation:id,name',
            ])
            ->orderBy('delivery_order_id')
            ->get();
    }

    protected function fillAmounts(): void
    {
        $this->amounts = $this->lines()->mapWithKeys(fn (CommissionLineItem $line) => [(string) $line->id => number_format((float) $line->amount, 2, '.', '')])->all();
    }

    public function editable(): bool
    {
        return $this->getBatch()->status === 'draft';
    }

    public function save(): void
    {
        try {
            $slips = app(SaveCommissionLines::class)->execute($this->getBatch(), $this->amounts);
            $this->fillAmounts();

            Notification::make()
                ->title('Commission saved')
                ->body($slips->isNotEmpty()
                    ? 'Purchase Order (PO) generated: '.$slips->map(fn ($slip) => ($slip->lorry?->registration_no ? 'lorry '.$slip->lorry->registration_no : ($slip->driver?->name ?? 'driver')).' RM '.number_format((float) $slip->final_amount, 2).' ('.$slip->driverBreakdownText().')')->implode('; ')
                    : 'No CSN line to save.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** The amount now entered for a line (RM), for the totals. */
    public function enteredAmount(CommissionLineItem $line): float
    {
        $value = $this->amounts[(string) $line->id] ?? null;

        return is_numeric($value) ? round((float) $value, 2) : (float) $line->amount;
    }
}

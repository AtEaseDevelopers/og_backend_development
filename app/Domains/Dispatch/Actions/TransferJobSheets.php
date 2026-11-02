<?php

namespace App\Domains\Dispatch\Actions;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Lorry;
use App\Enums\JobSheetStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transfer job sheets (client's BA, 3 Nov 2026): a CSN assigned to lorry A sits on lorry A's own (main) job sheet.
 * Each subsheet of that CSN with a transfer code (J, O, …) does not go on a lorry by itself: it is put on lorry A's
 * transfer job sheet for that code and day — opened by the system as lorry A's job number + the code
 * (JS-KL-261000019J), with no lorry and no driver — together with every other code-J subsheet from lorry A's CSNs.
 * Lorry B's code-J subsheets make lorry B's own code-J job sheet. A transfer job sheet waits until someone picks it
 * up: assigning it to a lorry gives the whole sheet (every leg on it) to that lorry and driver.
 */
class TransferJobSheets
{
    public const KIND_MAIN = 'main';

    public const KIND_TRANSFER = 'transfer';

    public function __construct(private CreateSubsheet $subsheets) {}

    /**
     * Puts a subsheet with a transfer code on its main lorry's transfer job sheet for that code (opened if none),
     * once the CSN is on a main lorry. Returns the job sheet, or null when there is nothing to do yet.
     */
    public function place(Subsheet $subsheet): ?JobSheet
    {
        $code = strtoupper(trim((string) $subsheet->transfer_code));

        if ($code === '' || $subsheet->delivery_order_id !== null || $subsheet->job_sheet_id !== null) {
            return null;
        }

        $csn = $subsheet->consignmentNote()->with('deliveryOrder.jobSheet')->first();
        $main = $csn?->deliveryOrder?->jobSheet;

        if (! $main || $main->isTransfer()) {
            return null;
        }

        return DB::transaction(function () use ($subsheet, $main, $code) {
            $sheet = $this->forMain($main, $code);
            $subsheet->forceFill(['job_sheet_id' => $sheet->id])->save();

            return $sheet;
        });
    }

    /** The subsheets of a CSN still waiting for a job sheet (created before the CSN was on a lorry), placed now. */
    public function placePending(ConsignmentNote $csn): int
    {
        $placed = 0;

        foreach ($csn->subsheets()->whereNull('delivery_order_id')->whereNull('job_sheet_id')->whereNotNull('transfer_code')->get() as $subsheet) {
            if ($this->place($subsheet)) {
                $placed++;
            }
        }

        return $placed;
    }

    /** Lorry A's open transfer job sheet for a code on the main sheet's day, opened when there is none. */
    public function forMain(JobSheet $main, string $code): JobSheet
    {
        $code = strtoupper(trim($code));
        $open = JobSheet::query()
            ->where('kind', self::KIND_TRANSFER)
            ->where('parent_job_sheet_id', $main->id)
            ->where('transfer_code', $code)
            ->where('status', '!=', JobSheetStatus::Completed->value)
            ->orderByDesc('id')
            ->first();

        if ($open) {
            return $open;
        }

        // lorry A's job number + the code; a second sheet of the same code on the same day (the first completed) -2
        $base = $main->number.$code;
        $number = $base;
        $n = 1;

        while (JobSheet::query()->where('number', $number)->exists()) {
            $number = $base.'-'.(++$n);
        }

        return JobSheet::query()->create([
            'number' => $number,
            'kind' => self::KIND_TRANSFER,
            'parent_job_sheet_id' => $main->id,
            'transfer_code' => $code,
            'company_id' => $main->company_id,
            'operating_branch_id' => $main->operating_branch_id,
            'lorry_id' => null,
            'driver_id' => null,
            'operating_date' => $main->operating_date,
            'trip_no' => 0,
            'status' => JobSheetStatus::Draft,
            'is_shared_dispatch' => false,
        ]);
    }

    /**
     * Someone picks the transfer job sheet up: the whole sheet goes to the lorry (and driver: the lorry's default
     * driver unless given) — every leg on it gets its delivery order and task on this sheet.
     *
     * @return int the legs put on the lorry
     */
    public function assign(JobSheet $sheet, Lorry $lorry, ?int $driverId = null): int
    {
        if (! $sheet->isTransfer()) {
            throw new InvalidArgumentException($sheet->number.' is not a transfer job sheet.');
        }

        if ($sheet->status === JobSheetStatus::Completed) {
            throw new InvalidArgumentException($sheet->number.' is completed.');
        }

        if (! $lorry->is_active) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' is not active.');
        }

        $parent = $sheet->parent;

        if ($parent && (int) $parent->lorry_id === (int) $lorry->id) {
            throw new InvalidArgumentException('Lorry '.$lorry->registration_no.' is the main lorry of these CSNs.');
        }

        return DB::transaction(function () use ($sheet, $lorry, $driverId) {
            $lorry->loadMissing(['branch', 'defaultDriver']);
            $driverId = $driverId ?: $lorry->default_driver_id;
            $date = $sheet->operating_date?->toDateString() ?? now()->toDateString();

            if ((int) $sheet->lorry_id !== (int) $lorry->id) {
                $tripNo = (int) JobSheet::query()->where('lorry_id', $lorry->id)->whereDate('operating_date', $date)->max('trip_no') + 1;
                $sheet->forceFill([
                    'lorry_id' => $lorry->id,
                    'driver_id' => $driverId,
                    'trip_no' => $tripNo,
                    'is_shared_dispatch' => (int) $sheet->operating_branch_id !== (int) $lorry->branch_id,
                ])->save();
            } elseif ($driverId && (int) $sheet->driver_id !== (int) $driverId) {
                $sheet->forceFill(['driver_id' => $driverId])->save();
            }

            $legs = 0;

            foreach ($sheet->subsheets()->whereNull('delivery_order_id')->get() as $subsheet) {
                $this->subsheets->assignToLorry($subsheet, $lorry, ['sub_driver_id' => $driverId, 'operating_date' => $date], $sheet);
                $legs++;
            }

            return $legs;
        });
    }
}

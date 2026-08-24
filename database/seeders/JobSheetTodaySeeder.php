<?php

namespace Database\Seeders;

use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\JobSheetStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Throwable;

class JobSheetTodaySeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'manager.kl@og.local')->first()
            ?? User::query()->where('email', 'admin@og.local')->firstOrFail();

        $convert = app(ConvertQuotationToCsns::class);
        $assign = app(AssignCsnToLorry::class);
        $today = now()->toDateString();

        $quotations = Quotation::query()
            ->whereIn('status', [QuotationStatus::Confirmed, QuotationStatus::Accepted])
            ->whereDoesntHave('consignmentNotes')
            ->with(['customer', 'branch'])
            ->orderBy('id')
            ->get();

        $assignments = 0;

        foreach ($quotations as $quotation) {
            $billingType = match (true) {
                (bool) $quotation->customer?->is_credit => 'term',
                default => 'cod',
            };

            try {
                $csns = $convert->execute($quotation, $actor, $billingType);
            } catch (Throwable $exception) {
                $this->command?->warn("Skipped {$quotation->number}: {$exception->getMessage()}");

                continue;
            }

            $lorries = Lorry::query()
                ->where('company_id', $quotation->company_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($lorries->isEmpty()) {
                $this->command?->warn("No lorries for quotation {$quotation->number}.");

                continue;
            }

            foreach ($csns->values() as $index => $csn) {
                $lorry = $lorries[$index % $lorries->count()];

                try {
                    $deliveryOrder = $assign->execute($csn, $lorry, $today);
                } catch (Throwable $exception) {
                    $this->command?->warn("Skipped assign {$csn->number}: {$exception->getMessage()}");

                    continue;
                }

                if ($index % 2 === 1) {
                    $deliveryOrder->jobSheet?->update([
                        'status' => JobSheetStatus::InTransit,
                        'checked_in_at' => now()->subHours(2),
                    ]);
                }

                $assignments++;
            }
        }

        if ($assignments === 0) {
            JobSheet::query()
                ->orderByDesc('id')
                ->limit(8)
                ->update(['operating_date' => $today]);

            $this->command?->info('No new assignments — moved recent job sheets to today\'s operating date.');
        } else {
            $this->command?->info("Assigned {$assignments} CSN(s) to job sheets for {$today}.");
        }

        $todayCount = JobSheet::query()->whereDate('operating_date', $today)->count();
        $this->command?->info("Job sheets on {$today}: {$todayCount}");
    }
}

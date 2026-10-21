<?php

namespace Tests\Feature;

use App\Domains\Commission\Actions\GenerateCommissionBatch;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A returned CSN pays its driver even while its delivery order is still "assigned" (no driver app yet), and an order
 * with no driver pays its lorry's default driver.
 */
class CommissionBatchReturnedCsnTest extends TestCase
{
    use RefreshDatabase;

    public function test_returned_csn_pays_even_when_its_order_is_still_assigned(): void
    {
        $this->seed();

        $admin = User::query()->firstOrFail();
        $branch = Branch::query()->where('code', 'KL')->firstOrFail();

        // a KL order still "assigned", with no driver of its own; its lorry has a default driver
        $do = DeliveryOrder::query()->where('status', 'assigned')
            ->whereHas('consignmentNote', fn ($q) => $q->where('source_branch_id', $branch->id))
            ->whereHas('lorry', fn ($q) => $q->whereNotNull('default_driver_id'))
            ->firstOrFail();
        $do->update(['driver_id' => null]);
        $csn = ConsignmentNote::query()->findOrFail($do->consignment_note_id);

        app(RecordReturnedCsn::class)->execute($csn, ['returned_at' => now()], $admin);

        $batch = app(GenerateCommissionBatch::class)->execute($branch, now()->format('Y-m'), $admin);

        $line = $batch->slips->flatMap->lines->firstWhere('consignment_note_id', $csn->id);
        $this->assertNotNull($line, 'the returned CSN has a commission line');
        $this->assertSame((int) $do->lorry->default_driver_id, (int) $line->driver_id);
        $this->assertGreaterThan(0, (float) $line->amount);
    }
}

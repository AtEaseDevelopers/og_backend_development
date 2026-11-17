<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Drivers keyed in twice are merged into the oldest one, and what pointed at the extra follows (client, 17 Nov 2026). */
class MergeDuplicateDriversTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicates_are_listed_then_merged_and_namesakes_with_other_ics_are_kept(): void
    {
        $this->seed();
        Driver::query()->update(['is_active' => false]); // the seed's own drivers are not part of this
        $before = Driver::query()->count();

        $keep = Driver::query()->create(['name' => 'ABDUL MUIZ B.MD NOR', 'lorry_number' => 'JCM 6456']);
        $extra = Driver::query()->create(['name' => 'Abdul Muiz B.Md Nor ', 'phone' => '019678987', 'ic_number' => '900101-01-1234']);
        $sameIc = Driver::query()->create(['name' => 'Muiz', 'ic_number' => '900101011234']);
        // two people of one name
        $a = Driver::query()->create(['name' => 'Ali', 'ic_number' => '111111-11-1111']);
        $b = Driver::query()->create(['name' => 'Ali', 'ic_number' => '222222-22-2222']);
        $unsure = Driver::query()->create(['name' => 'Ali']);

        $login = User::query()->whereNull('driver_id')->firstOrFail();
        $login->forceFill(['driver_id' => $extra->id])->save();
        $lorryId = DB::table('lorries')->value('id');
        DB::table('lorries')->where('id', $lorryId)->update(['default_driver_id' => $sameIc->id]);

        // the list only
        $this->artisan('og:merge-duplicate-drivers')->assertSuccessful();
        $this->assertSame($before + 6, Driver::query()->count());

        $this->artisan('og:merge-duplicate-drivers', ['--apply' => true])->assertSuccessful();

        $this->assertSame($before + 4, Driver::query()->count());
        $this->assertNull(Driver::query()->find($extra->id));
        $this->assertNull(Driver::query()->find($sameIc->id));
        foreach ([$a, $b, $unsure] as $kept) {
            $this->assertNotNull(Driver::query()->find($kept->id));
        }
        // the kept driver took what it lacked, and the login and the lorry now point at it
        $keep->refresh();
        $this->assertSame('019678987', $keep->phone);
        $this->assertSame('900101-01-1234', $keep->ic_number);
        $this->assertSame('JCM 6456', $keep->lorry_number);
        $this->assertSame($keep->id, (int) $login->fresh()->driver_id);
        $this->assertSame($keep->id, (int) DB::table('lorries')->where('id', $lorryId)->value('default_driver_id'));
    }
}

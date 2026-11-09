<?php

use App\Domains\MasterData\Models\Location;
use Illuminate\Database\Migrations\Migration;

/**
 * "Klang Valley" (user, 10 Nov 2026): the KL branch's pick-up area, as the price list's Pickup Location; on the
 * Create order page it is the "From" of a KL order instead of the areas inside it (OrderFormOptions).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Location::query()->where('name', 'Klang Valley')->exists()) {
            Location::query()->create([
                'code' => 'KLANG_VALLEY',
                'name' => 'Klang Valley',
                'type' => 'pickup',
                'state' => 'Selangor',
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Location::query()->where('code', 'KLANG_VALLEY')->where('type', 'pickup')->delete();
    }
};

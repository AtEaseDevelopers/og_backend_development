<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Consignor / Consignee (client, 16 Nov 2026): no "Used as" any more — every saved record is offered both as a
// consignor and as a consignee, so the existing ones are all set to "both".
return new class extends Migration
{
    public function up(): void
    {
        DB::table('consignor_consignees')->where('type', '!=', 'both')->orWhereNull('type')->update(['type' => 'both']);
    }

    public function down(): void
    {
        //
    }
};

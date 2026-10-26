<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The client's CSN number format XX-YYMM##### (user, 26 Oct 2026): a branch's CSN code is now the 2-letter location
 * code (KL, SA, MK, KG, PK, PP, PA, JB). The old prefixes that clearly match are changed; others stay for the user
 * to set in Master Data → Branches. Existing CSNs keep their numbers.
 */
return new class extends Migration
{
    private const CODES = ['KLS' => 'KL', 'JBS' => 'JB', 'KLGS' => 'KG'];

    public function up(): void
    {
        foreach (self::CODES as $old => $new) {
            DB::table('sa_locations')->where('csn_prefix', $old)->update(['csn_prefix' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::CODES as $old => $new) {
            DB::table('sa_locations')->where('csn_prefix', $new)->update(['csn_prefix' => $old, 'updated_at' => now()]);
        }
    }
};

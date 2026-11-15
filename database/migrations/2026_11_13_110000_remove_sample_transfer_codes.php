<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Master Data -> Transfer Codes (user, 13 Nov 2026): the three sample codes of the first set-up are removed; the
// client's own codes (A, C, E, J ...) stay. A subsheet that used one keeps the code as text; a CSN that pointed at
// one is unlinked by the database (transfer_code_id is set to null), and the codes' staff links go with them.
return new class extends Migration
{
    private const CODES = ['TRF-KL-JB', 'PSI-JB-IN', 'TRF-KL-PG'];

    public function up(): void
    {
        DB::table('transfer_codes')->whereIn('code', self::CODES)->delete();
    }

    public function down(): void
    {
        // the sample codes are not put back
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The quotation letterhead (user, 26 Oct 2026, as the client's quotation): fax and SST no. on each company
 * (Master Data → Company), and KL's address, telephone, fax, email and SST no. from the client's quotation, where
 * still empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'fax')) {
                $table->string('fax', 50)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('branches', 'sst_no')) {
                $table->string('sst_no', 50)->nullable()->after('company_no');
            }
        });

        $kl = DB::table('branches')->where('code', 'KL')->first();

        if (! $kl) {
            return;
        }

        $fill = array_filter([
            'address' => blank($kl->address) ? 'NO. 20 & 22, JALAN 13/108C, TAMAN SG BESI, 57100 KUALA LUMPUR.' : null,
            'phone' => blank($kl->phone) ? '603-7987 0088' : null,
            'fax' => '603-7981 0092',
            'email' => blank($kl->email) ? 'ogkl@ogtransport.com' : null,
            'sst_no' => 'W10-2403-32000531',
        ]);

        DB::table('branches')->where('id', $kl->id)->update($fill + ['updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['fax', 'sst_no']);
        });
    }
};

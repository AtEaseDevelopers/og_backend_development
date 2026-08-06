<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('special_requirements');
        });
    }

    public function down(): void
    {
        Schema::table('portal_enquiries', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};

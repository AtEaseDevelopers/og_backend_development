<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer who registers without a branch (user, 20 Nov 2026): the salesperson's link fills it only when the
 * salesperson has one branch; left blank, staff fill it on Approvals -> Portal Registrations when approving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // left nullable: customers waiting for a branch would be lost
    }
};

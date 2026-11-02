<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lorry company may belong to a lorry group (user, 3 Nov 2026): a driver picked under that company takes the group.
 * Lorry groups themselves are a name only now (their code / contact / email / address columns stay, unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lorry_companies', fn (Blueprint $table) => $table->foreignId('lorry_group_id')->nullable()->after('company_id')->constrained('lorry_groups')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('lorry_companies', fn (Blueprint $table) => $table->dropConstrainedForeignId('lorry_group_id'));
    }
};

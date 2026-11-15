<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Master Data -> Roles & Permission (user, 13 Nov 2026): the actions of App\Support\Permissions become the roles'
// permissions, each role starting with exactly what it could do before; and a table for one user's own overrides
// (allow or deny an action whatever their role says).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_permission_overrides')) {
            Schema::create('user_permission_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('permission', 100);
                $table->boolean('allowed');
                $table->timestamps();
                $table->unique(['user_id', 'permission']);
            });
        }

        \App\Support\Permissions::syncDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};

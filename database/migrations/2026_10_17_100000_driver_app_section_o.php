<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section O (driver mobile app): consignment barcode validation before delivery, pickup / handover proof of
 * additional tasks, and offline-safe Break-Bulk requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_scan_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->json('expected')->nullable();
            $table->json('scanned')->nullable();
            $table->json('missing')->nullable();
            $table->json('extra')->nullable();
            $table->json('unauthorized')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->boolean('continued_incomplete')->default(false);
            $table->text('remarks')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_task_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subsheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('photo_paths')->nullable();
            $table->string('handover_to')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('break_bulks', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('longitude');
            $table->timestamp('reported_at')->nullable()->after('client_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('break_bulks', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['client_uuid', 'reported_at']);
        });

        Schema::dropIfExists('driver_task_proofs');
        Schema::dropIfExists('driver_scan_validations');
    }
};

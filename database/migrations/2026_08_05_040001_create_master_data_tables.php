<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('company_name');
            $table->string('company_no')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('code', 50)->nullable();
            $table->string('company_name');
            $table->string('brn')->nullable()->index();
            $table->string('tin')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->unsignedInteger('credit_term_days')->default(0);
            $table->string('status')->default('active');
            $table->boolean('portal_approved')->default(false);
            $table->json('payment_methods')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('delivery'); // pickup|delivery
            $table->string('label')->nullable();
            $table->text('address');
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('google_maps_url')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('item_name')->nullable();
            $table->string('uom')->nullable();
            $table->string('route')->nullable();
            $table->string('destination')->nullable();
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->decimal('base_price', 15, 2)->default(0);
            $table->decimal('unit_rate', 15, 2)->nullable();
            $table->decimal('min_charge', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('ic_number')->nullable();
            $table->string('type')->default('internal'); // internal|external|subcontractor
            $table->foreignId('subcontractor_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lorries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('registration_no')->unique();
            $table->string('type')->nullable();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->foreignId('default_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subcontractors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('default_uom')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('delivery');
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('postcode_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('postcode')->index();
            $table->string('state');
            $table->string('city')->nullable();
            $table->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transfer_code')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('destination_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('type')->default('outgoing'); // outgoing|incoming
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('document_type');
            $table->string('period', 6); // YYYYMM
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'document_type', 'period'], 'doc_seq_unique');
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lorry_type')->nullable();
            $table->string('route')->nullable();
            $table->string('split_type')->default('single'); // single|split_2|split_3|split_4
            $table->json('percentages')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lorry_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // service|permit|insurance|road_tax|oil|tyre|repair
            $table->date('service_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->unsignedInteger('next_service_mileage')->nullable();
            $table->date('next_service_date')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lorry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_sheet_id')->nullable();
            $table->timestamp('checked_in_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_check_ins');
        Schema::dropIfExists('vehicle_maintenance_records');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('document_number_sequences');
        Schema::dropIfExists('transfer_codes');
        Schema::dropIfExists('postcode_mappings');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('subcontractors');
        Schema::dropIfExists('lorries');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('customer_pricing');
        Schema::dropIfExists('customer_pics');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};

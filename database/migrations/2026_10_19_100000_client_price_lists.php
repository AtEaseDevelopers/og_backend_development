<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's price list workbooks (default price + customer special price, 19 Oct 2026): a UOM product is one
 * size / weight band of an item ("Carton · L+W+H 0–149 cm · up to 30 kg"); its rate tiers are per destination,
 * branch, drop-off type and customer (blank = default price), with the row's minimum charges and dates.
 * Chartered lorry rates get the same customer / branch / drop-off type / dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uoms', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('name');      // measurement | item
            $table->string('sub_category', 100)->nullable()->after('category');
            $table->string('base_item', 150)->nullable()->after('sub_category');
            foreach (['min_lwh', 'max_lwh', 'min_length', 'max_length', 'max_width', 'max_height', 'min_weight', 'max_weight'] as $column) {
                $table->decimal($column, 10, 2)->nullable();
            }
        });

        Schema::table('uom_rate_tiers', function (Blueprint $table) {
            // the lookup index first: MySQL needs an index on uom_id for its foreign key
            $table->index(['uom_id', 'location_id'], 'uom_rate_tiers_lookup');
        });

        Schema::table('uom_rate_tiers', function (Blueprint $table) {
            $table->dropUnique('uom_rate_tiers_unique');
        });

        Schema::table('uom_rate_tiers', function (Blueprint $table) {
            // blank = the default price; else the AutoCount debtor code of the customer it is special for
            $table->string('customer_code', 50)->nullable()->after('location_id');
            $table->foreignId('branch_id')->nullable()->after('customer_code')->constrained()->nullOnDelete();
            $table->string('drop_off_type', 30)->default('default')->after('branch_id');
            $table->string('pickup_location', 100)->nullable()->after('drop_off_type');
            $table->decimal('pickup_min_charge', 15, 2)->nullable()->after('price');
            $table->decimal('construction_min_charge', 15, 2)->nullable()->after('pickup_min_charge');
            $table->decimal('drop_off_min_charge', 15, 2)->nullable()->after('construction_min_charge');
            $table->date('effective_from')->nullable()->after('drop_off_min_charge');
            $table->date('expires_on')->nullable()->after('effective_from');
            $table->string('source', 150)->nullable()->after('expires_on');
        });

        Schema::table('chartered_lorries', function (Blueprint $table) {
            $table->decimal('max_weight', 10, 2)->nullable()->after('name');
        });

        Schema::table('chartered_lorry_rates', function (Blueprint $table) {
            // the lookup index first: MySQL needs an index on chartered_lorry_id for its foreign key
            $table->index(['chartered_lorry_id', 'location_id'], 'chartered_lorry_rates_lookup');
        });

        Schema::table('chartered_lorry_rates', function (Blueprint $table) {
            $table->dropUnique(['chartered_lorry_id', 'location_id']);
            $table->string('customer_code', 50)->nullable()->after('location_id');
            $table->foreignId('branch_id')->nullable()->after('customer_code')->constrained()->nullOnDelete();
            $table->string('drop_off_type', 30)->default('default')->after('branch_id');
            $table->string('pickup_location', 100)->nullable()->after('drop_off_type');
            $table->date('effective_from')->nullable()->after('price');
            $table->date('expires_on')->nullable()->after('effective_from');
            $table->string('source', 150)->nullable()->after('expires_on');
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            // an automatic minimum charge top-up line (pickup / construction site / drop-off minimum), not a product
            $table->string('charge_type', 30)->nullable()->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', fn (Blueprint $table) => $table->dropColumn('charge_type'));

        Schema::table('chartered_lorry_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['customer_code', 'drop_off_type', 'pickup_location', 'effective_from', 'expires_on', 'source']);
            $table->unique(['chartered_lorry_id', 'location_id']);
        });

        Schema::table('chartered_lorry_rates', fn (Blueprint $table) => $table->dropIndex('chartered_lorry_rates_lookup'));

        Schema::table('chartered_lorries', fn (Blueprint $table) => $table->dropColumn('max_weight'));

        Schema::table('uom_rate_tiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'customer_code', 'drop_off_type', 'pickup_location', 'pickup_min_charge', 'construction_min_charge',
                'drop_off_min_charge', 'effective_from', 'expires_on', 'source',
            ]);
            $table->unique(['uom_id', 'location_id', 'min_qty'], 'uom_rate_tiers_unique');
        });

        Schema::table('uom_rate_tiers', fn (Blueprint $table) => $table->dropIndex('uom_rate_tiers_lookup'));

        Schema::table('uoms', function (Blueprint $table) {
            $table->dropColumn(['category', 'sub_category', 'base_item', 'min_lwh', 'max_lwh', 'min_length', 'max_length', 'max_width', 'max_height', 'min_weight', 'max_weight']);
        });
    }
};

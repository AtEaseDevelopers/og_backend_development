<?php

use App\Domains\MasterData\Models\ConsignorConsignee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every customer is also a consignor / consignee (user, 25 Oct 2026): Master Data → Consignor / Consignee lists the
 * customers (their name, address, PIC and telephone, tagged to themselves) beside the ones created there, and Create
 * order offers both. A customer's row follows the customer and goes when the customer is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consignor_consignees', 'customer_id')) {
            Schema::table('consignor_consignees', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->unique()->after('company_id')->constrained()->cascadeOnDelete();
            });
        }

        $added = ConsignorConsignee::addMissingCustomers();

        echo "  {$added} customer".($added === 1 ? '' : 's')." added as consignor / consignee.\n";
    }

    public function down(): void
    {
        ConsignorConsignee::query()->whereNotNull('customer_id')->delete();

        Schema::table('consignor_consignees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};

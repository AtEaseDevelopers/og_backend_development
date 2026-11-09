<?php

use App\Domains\MasterData\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More than one PIC (user, 10 Nov 2026):
 *  - a customer's PICs are its list of contact persons (customer_pics: PIC name + contact number, the first is the
 *    default); the attention / contact person 3 – 4 and phone 1 – 4 columns are copied into that list for customers
 *    that have none yet, and a PIC may have a number only;
 *  - an order keeps several PICs joined ("A / B"), so its PIC contact numbers get room for more than one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_pics', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->string('customer_pic_phone')->nullable()->change();
            $table->string('consignor_pic_phone')->nullable()->change();
            $table->string('consignee_pic_phone')->nullable()->change();
        });

        Customer::query()->whereDoesntHave('pics')->orderBy('id')->each(fn (Customer $customer) => $customer->createPicsFromContactColumns());
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->string('customer_pic_phone', 50)->nullable()->change();
            $table->string('consignor_pic_phone', 50)->nullable()->change();
            $table->string('consignee_pic_phone', 50)->nullable()->change();
        });
    }
};

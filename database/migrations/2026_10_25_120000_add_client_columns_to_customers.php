<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns of the client's Customer Information workbook that had no customer field yet (user, 25 Oct 2026):
 * the display term as written ("30 DAYS"), the billing postcode, the second phone and mobile, contact persons 3 and 4,
 * GST no., the three own notes and MSIC codes 2 and 3. The delivery address stays a customer address (type delivery).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'display_term' => 50, 'postcode' => 20, 'phone_2' => 50, 'mobile' => 50,
        'contact_person_3' => 150, 'phone_3' => 50, 'contact_person_4' => 150, 'phone_4' => 50,
        'gst_no' => 50, 'msic_code_2' => 20, 'msic_code_3' => 20,
    ];

    private const NOTES = ['own_note', 'mailing_statement_remark', 'payment_note'];

    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('customers', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }

            foreach (self::NOTES as $column) {
                if (! Schema::hasColumn('customers', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([...array_keys(self::COLUMNS), ...self::NOTES]);
        });
    }
};

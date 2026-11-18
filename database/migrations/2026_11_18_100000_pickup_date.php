<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pickup date of an order record and of its CSN (client, 18 Nov 2026): CSN management lists the CSNs by the day
 * they are picked up, not the day they were created. Existing rows: the date the customer gave on the portal, else the
 * CSN date (a record without a CSN: the day after it was created).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->date('pickup_date')->nullable()->after('expected_delivery_date'));
        Schema::table('consignment_notes', fn (Blueprint $table) => $table->date('pickup_date')->nullable()->after('issued_at')->index());

        DB::table('consignment_notes')->update(['pickup_date' => DB::raw('COALESCE(issued_at, DATE(created_at))')]);

        // the pickup date asked for on the portal order form
        DB::table('portal_enquiries')->where('payload', 'like', '%pickup_date%')->orderBy('id')->select(['id', 'payload'])
            ->chunkById(200, function ($enquiries): void {
                foreach ($enquiries as $enquiry) {
                    $date = data_get(json_decode((string) $enquiry->payload, true), 'pickup_date');

                    try {
                        $date = filled($date) ? Carbon::parse((string) $date)->toDateString() : null;
                    } catch (Throwable) {
                        $date = null;
                    }

                    if (! $date) {
                        continue;
                    }

                    $ids = DB::table('quotations')->where('portal_enquiry_id', $enquiry->id)->pluck('id');
                    DB::table('quotations')->whereIn('id', $ids)->update(['pickup_date' => $date]);
                    DB::table('consignment_notes')->whereIn('quotation_id', $ids)->update(['pickup_date' => $date]);
                }
            });

        DB::table('quotations')->whereNull('pickup_date')->orderBy('id')->select(['id', 'created_at'])
            ->chunkById(500, function ($orders): void {
                $csn = DB::table('consignment_notes')->whereIn('quotation_id', $orders->pluck('id'))
                    ->selectRaw('quotation_id, MIN(pickup_date) as day')->groupBy('quotation_id')->pluck('day', 'quotation_id');

                foreach ($orders as $order) {
                    $day = $csn[$order->id] ?? Carbon::parse($order->created_at ?? now())->addDay()->toDateString();
                    DB::table('quotations')->where('id', $order->id)->update(['pickup_date' => substr((string) $day, 0, 10)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('consignment_notes', fn (Blueprint $table) => $table->dropColumn('pickup_date'));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('pickup_date'));
    }
};

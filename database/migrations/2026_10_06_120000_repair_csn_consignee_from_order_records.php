<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair CSNs created from single-destination order records: they copied the price-list
 * column label (e.g. "Johor") as consignee/address and left From/To empty. Only rows that
 * still hold that placeholder are touched; anything edited by hand is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('consignment_notes as c')
            ->join('quotations as q', 'q.id', '=', 'c.quotation_id')
            ->join('quotation_destinations as d', 'd.id', '=', 'c.quotation_destination_id')
            ->leftJoin('locations as tl', 'tl.id', '=', 'q.to_location_id')
            ->leftJoin('customers as cu', 'cu.id', '=', 'q.customer_id')
            ->whereColumn('c.consignee_name', 'd.consignee_name')
            ->whereNotNull('q.consignee_name')
            ->where('q.consignee_name', '!=', '')
            ->whereRaw('(select count(*) from quotation_destinations qd where qd.quotation_id = q.id) = 1')
            ->get([
                'c.id', 'c.delivery_address', 'c.from_location_id', 'c.to_location_id', 'c.consignor_name',
                'd.consignee_name as label', 'd.address as label_address',
                'q.consignee_name', 'q.consignee_address', 'q.drop_off_location', 'q.pickup_location',
                'q.customer_address', 'q.from_location_id as q_from', 'q.to_location_id as q_to', 'q.consignor_name as q_consignor',
                'tl.name as to_name', 'cu.company_name', 'cu.address as customer_address_master',
            ]);

        foreach ($rows as $row) {
            $update = ['consignee_name' => $row->consignee_name];

            $labelOnlyAddress = $row->delivery_address === null
                || trim((string) $row->delivery_address) === ''
                || trim((string) $row->delivery_address) === trim((string) $row->label)
                || trim((string) $row->delivery_address) === trim((string) $row->label_address);

            $realAddress = $row->drop_off_location ?: $row->consignee_address;

            if ($labelOnlyAddress && filled($realAddress)) {
                $update['delivery_address'] = $realAddress; // column is NOT NULL: only replace with a real address
            }

            if ($row->to_name) {
                $update['delivery_city'] = $row->to_name;
            }

            if (! $row->from_location_id && $row->q_from) {
                $update['from_location_id'] = $row->q_from;
            }

            if (! $row->to_location_id && $row->q_to) {
                $update['to_location_id'] = $row->q_to;
            }

            if (blank($row->consignor_name)) {
                $update['consignor_name'] = $row->q_consignor ?: $row->company_name;
                $update['consignor_address'] = $row->pickup_location ?: ($row->customer_address ?: $row->customer_address_master);
            }

            DB::table('consignment_notes')->where('id', $row->id)->update($update);
        }
    }

    public function down(): void
    {
        // data repair only
    }
};

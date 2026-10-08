<?php

namespace Tests\Unit;

use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Actions\UpdateOrderRecords;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Domains\Quotation\Models\Quotation;
use Tests\TestCase;

/**
 * UpdateOrderRecords::payloadIndexMap must give every record its own payload destination, also when
 * two consignees share the same name and TO location (in-memory models only, no database).
 */
class PayloadIndexMapTest extends TestCase
{
    public function test_two_records_with_the_same_consignee_and_to_location_get_different_destinations(): void
    {
        $enquiry = $this->enquiry([
            ['consignee_name' => 'JB Warehouse', 'city' => 'Johor'],
            ['consignee_name' => 'JB Warehouse', 'city' => 'Johor'],
        ]);

        $records = collect([
            $this->record(11, 'JB Warehouse', 'Johor'),
            $this->record(12, 'JB Warehouse', 'Johor'),
        ]);

        $this->assertSame([11 => 0, 12 => 1], UpdateOrderRecords::payloadIndexMap($enquiry, $records));
        $this->assertSame(1, UpdateOrderRecords::payloadIndexFor($enquiry, $records[1], $records));
    }

    public function test_a_destination_claimed_by_consignee_name_is_not_taken_again_by_position(): void
    {
        $enquiry = $this->enquiry([
            ['consignee_name' => 'Kedai B', 'city' => 'Melaka'],
            ['consignee_name' => 'Kedai A', 'city' => 'Johor'],
        ]);

        // record 22 takes destination 0 by name; record 21 (no name match) must not take it again by position
        $records = collect([
            $this->record(21, 'Someone else', null),
            $this->record(22, 'Kedai B', 'Melaka'),
        ]);

        $this->assertSame([21 => null, 22 => 0], UpdateOrderRecords::payloadIndexMap($enquiry, $records));
    }

    public function test_a_stored_record_id_wins_over_the_consignee_name(): void
    {
        $enquiry = $this->enquiry([
            ['consignee_name' => 'JB Warehouse', 'city' => 'Johor'],
            ['consignee_name' => 'JB Warehouse', 'city' => 'Johor', 'record_id' => 31],
        ]);

        $records = collect([
            $this->record(31, 'JB Warehouse', 'Johor'),
            $this->record(32, 'JB Warehouse', 'Johor'),
        ]);

        $this->assertSame([31 => 1, 32 => 0], UpdateOrderRecords::payloadIndexMap($enquiry, $records));
    }

    /** @param  list<array<string, mixed>>  $destinations */
    private function enquiry(array $destinations): PortalEnquiry
    {
        $enquiry = new PortalEnquiry;
        $enquiry->payload = ['destinations' => $destinations, 'items' => []];

        return $enquiry;
    }

    private function record(int $id, string $consignee, ?string $toLocation): Quotation
    {
        $record = (new Quotation)->forceFill([
            'id' => $id,
            'root_quotation_id' => $id,
            'consignee_name' => $consignee,
            'to_location_id' => $toLocation ? $id + 1000 : null,
        ]);

        $record->setRelation('toLocation', $toLocation ? (new Location)->forceFill(['name' => $toLocation]) : null);

        return $record;
    }
}

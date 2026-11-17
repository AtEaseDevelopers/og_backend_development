<?php

namespace Tests\Unit;

use App\Support\OrderStops;
use PHPUnit\Framework\TestCase;

/**
 * Multiple consignees (client, 17 Nov 2026): the record's own consignee is Consignee 1 and is saved as the first
 * drop-off stop with the record's DO number; the form's rows are Consignee 2, 3 …; a pick-up address has no DO number.
 */
class OrderStopsConsigneeTest extends TestCase
{
    private function pair(): array
    {
        return [
            'consignee_name' => 'Tebrau Builders', 'consignee_pic_name' => 'Mr. Lee', 'consignee_pic_phone' => '07-355 1288',
            'drop_off_location' => 'Jalan Tebrau, Johor Bahru', 'customer_do_number' => 'DO-1, DO-2',
            'multi_dropoff' => true,
            'dropoff_stops' => [
                ['name' => 'Perai DIY', 'pic_name' => 'Ms. Nurul', 'pic_phone' => '04-390 7722', 'location' => 'Perai', 'do_number' => 'DO-3'],
                ['name' => 'Mega Jaya', 'location' => 'Cheras', 'do_number' => '-'],
            ],
        ];
    }

    public function test_consignee_one_is_saved_first_with_the_records_do_numbers(): void
    {
        $stops = OrderStops::merge($this->pair());

        $this->assertCount(3, $stops);
        $this->assertSame(['Tebrau Builders', 'Perai DIY', 'Mega Jaya'], array_column($stops, 'name'));
        // several numbers with a comma and "-" are kept as typed
        $this->assertSame(['DO-1, DO-2', 'DO-3', '-'], OrderStops::doNumbers($stops));
        $this->assertTrue($stops[0]['main']);
        $this->assertArrayNotHasKey('main', $stops[1]);
    }

    public function test_the_form_gets_consignee_two_onwards_back(): void
    {
        $stops = OrderStops::merge($this->pair());
        $main = ['name' => 'Tebrau Builders', 'location' => 'Jalan Tebrau, Johor Bahru'];

        // documents: every consignee
        $this->assertCount(3, OrderStops::split($stops)['dropoff_stops']);
        // the order form: Consignee 1 is the record's own fields
        $form = OrderStops::split($stops, $main);
        $this->assertTrue($form['multi_dropoff']);
        $this->assertSame(['Perai DIY', 'Mega Jaya'], array_column($form['dropoff_stops'], 'name'));

        // a record saved before the change whose first stop repeats the record's consignee
        $old = [['type' => 'dropoff', 'name' => 'tebrau builders', 'location' => 'Jalan Tebrau, Johor Bahru', 'do_number' => 'DO-1'], ['type' => 'dropoff', 'name' => 'Perai DIY', 'location' => 'Perai', 'do_number' => 'DO-3']];
        $this->assertSame(['Perai DIY'], array_column(OrderStops::split($old, $main)['dropoff_stops'], 'name'));
    }

    public function test_one_consignee_and_pick_up_addresses_save_no_consignee_stop(): void
    {
        // Multiple consignees ticked but no row filled in: a plain record
        $this->assertSame([], OrderStops::merge(['multi_dropoff' => true, 'dropoff_stops' => [OrderStops::blank()]] + $this->pair()));

        // several pick-up addresses: addresses only
        $stops = OrderStops::merge(['multi_dropoff' => false, 'multi_pickup' => true, 'pickup_stops' => [['location' => 'Jalan Klang Lama'], ['location' => 'Puchong']]] + $this->pair());
        $this->assertSame(['pickup', 'pickup'], array_column($stops, 'type'));
        $this->assertSame([], OrderStops::doNumbers($stops));
    }
}

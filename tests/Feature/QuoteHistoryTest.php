<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quote;
use App\Models\User;
use App\Support\QuoteChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An edited quotation keeps its number, and each save that changes something is kept in its history: the fields
 * changed, from which value to which value (user, 16 Nov 2026).
 */
class QuoteHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_edited_quotation_keeps_its_number_and_records_what_changed(): void
    {
        $this->seed();

        $branch = Branch::query()->where('code', 'KL')->firstOrFail();
        $customer = Customer::query()->whereNotNull('code')->where('code', '!=', '')->firstOrFail();
        $other = Customer::query()->whereNotNull('code')->where('code', '!=', '')->whereKeyNot($customer->id)->firstOrFail();
        $johor = Location::query()->firstOrCreate(['name' => 'Johor'], ['type' => 'delivery', 'is_active' => true]);
        $melaka = Location::query()->firstOrCreate(['name' => 'Melaka'], ['type' => 'delivery', 'is_active' => true]);
        $admin = User::query()->where('is_hq', true)->firstOrFail();

        $pairs = fn (int $toId, string $price) => [[
            'from_location_id' => $johor->id,
            'to_location_id' => $toId,
            'drop_off_type' => 'default',
            'items' => [[
                'item_name' => 'Steel Drum',
                'quantity' => 1,
                'manual_price' => $price,
                'special' => ['range_a_from' => '1', 'range_a_to' => '9', 'range_a_price' => $price, 'range_b_from' => '10', 'range_b_to' => null, 'range_b_price' => '40', 'pickup_min_charge' => '250'],
            ]],
        ]];

        // numbered when first saved, from its customer and month
        $quote = Quote::query()->create([
            'company_id' => $customer->company_id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'quote_date' => '2026-10-21', 'status' => Quote::PENDING, 'type' => Quote::TYPE_CUSTOMER,
            'form' => ['customer_address' => 'Old address', 'customer_pic_name' => 'Sherly', 'customer_pic_phone' => '0126484345'],
            'total_amount' => 45, 'pairs' => $pairs($johor->id, '45'),
        ]);
        $number = $quote->number;
        $this->assertStringStartsWith('QKL/'.strtoupper($customer->code).'/2610', $number);

        // an edit that changes nothing leaves no history
        $before = QuoteChangeLog::snapshot($quote);
        $quote->save();
        QuoteChangeLog::record($quote->fresh(), $before, $admin->id);
        $this->assertCount(0, QuoteChangeLog::history($quote->id));

        // edited: another date (another month), another customer, address, drop-off location and price
        $before = QuoteChangeLog::snapshot($quote);
        $quote->fill([
            'quote_date' => '2026-11-05',
            'customer_id' => $other->id,
            'form' => ['customer_address' => 'New address', 'customer_pic_name' => 'Sherly', 'customer_pic_phone' => '0126484345'],
            'pairs' => $pairs($melaka->id, '50'),
            'total_amount' => 50,
        ])->save();
        QuoteChangeLog::record($quote->fresh(), $before, $admin->id);

        // the number is the one it was given, with nothing added behind it
        $this->assertSame($number, $quote->fresh()->number);

        $history = QuoteChangeLog::history($quote->id);
        $this->assertCount(1, $history);
        $this->assertSame($admin->name, $history[0]['by']);
        $changes = collect($history[0]['changes'])->keyBy('field');

        $fromTo = fn (string $field) => [$changes[$field]['from'] ?? null, $changes[$field]['to'] ?? null];
        $this->assertSame(['21/10/2026', '05/11/2026'], $fromTo('Quotation date'));
        $this->assertSame([$customer->company_name, $other->company_name], $fromTo('Customer / lead'));
        $this->assertSame(['Old address', 'New address'], $fromTo('Billing address'));
        $this->assertSame(['Johor', 'Melaka'], $fromTo('Product 1 · Drop-off location'));
        $this->assertSame(['1–9: 45.00 · 10+: 40.00', '1–9: 50.00 · 10+: 40.00'], $fromTo('Product 1 · Price'));
        $this->assertSame(['45.00', '50.00'], $fromTo('Total'));
        // what did not change is not listed
        $this->assertFalse($changes->has('PIC name'));
        $this->assertFalse($changes->has('Product 1 · Pickup min charge'));

        // a second product row added shows as its fields appearing
        $before = QuoteChangeLog::snapshot($quote->fresh());
        $two = $pairs($melaka->id, '50');
        $two[0]['items'][] = ['item_name' => 'Pallet', 'quantity' => 1, 'manual_price' => '80', 'special' => []];
        $quote->fill(['pairs' => $two])->save();
        QuoteChangeLog::record($quote->fresh(), $before, $admin->id);
        $latest = collect(QuoteChangeLog::history($quote->id)[0]['changes'])->keyBy('field');
        $this->assertSame(['', 'Pallet'], [$latest['Product 2 · Product']['from'], $latest['Product 2 · Product']['to']]);
        $this->assertSame($number, $quote->fresh()->number);

        // the window lists them
        $html = view('filament.pages.partials.quote-history', ['history' => QuoteChangeLog::history($quote->id)])->render();
        $this->assertStringContainsString('Billing address', $html);
        $this->assertStringContainsString('Old address', $html);
        $this->assertStringContainsString('New address', $html);
    }
}

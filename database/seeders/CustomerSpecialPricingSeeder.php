<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPricing;
use Illuminate\Database\Seeder;

class CustomerSpecialPricingSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::query()
            ->where('company_name', 'Demo Trading Sdn Bhd')
            ->first();

        if (! $customer) {
            $this->command?->warn('Demo Trading customer not found — skipping special pricing seed.');

            return;
        }

        $rows = [
            [
                'item_name' => 'CTN (Below 30kg)',
                'uom' => 'CTN',
                'route' => 'KL → Seremban',
                'destination' => 'Seremban',
                'unit_rate' => 18.50,
                'min_charge' => 50.00,
            ],
            [
                'item_name' => 'CTN (Below 30kg)',
                'uom' => 'CTN',
                'route' => 'KL → Melaka',
                'destination' => 'Melaka',
                'unit_rate' => 22.00,
                'min_charge' => 55.00,
            ],
            [
                'item_name' => 'CTN (Below 30kg)',
                'uom' => 'CTN',
                'route' => 'KL → Johor',
                'destination' => 'Johor',
                'unit_rate' => 28.00,
                'min_charge' => 60.00,
            ],
            [
                'item_name' => '20 FT LORRY (10 TON)',
                'uom' => 'Trip',
                'route' => 'KL → Seremban',
                'destination' => 'Seremban',
                'unit_rate' => 480.00,
                'min_charge' => null,
            ],
            [
                'item_name' => 'PLT <(1.2x1.2x1.2)M',
                'uom' => 'PLT',
                'route' => 'All routes',
                'destination' => null,
                'unit_rate' => 35.00,
                'min_charge' => 70.00,
            ],
        ];

        foreach ($rows as $row) {
            CustomerPricing::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'item_name' => $row['item_name'],
                    'destination' => $row['destination'],
                ],
                [
                    ...$row,
                    'base_price' => $row['unit_rate'],
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Seeded '.count($rows).' special prices for Demo Trading Sdn Bhd.');
    }
}

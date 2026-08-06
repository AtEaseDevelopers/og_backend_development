<?php

namespace Database\Seeders;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Actions\CompleteDelivery;
use App\Domains\Delivery\Actions\RecordReturnedCsn;
use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\Quotation\Models\QuotationLine;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoOperationsSeeder extends Seeder
{
    /** @var array<string, array{city:string,state:string,postcode:string,prefix:string,plates:array<int,string>}> */
    private array $branchMeta = [
        'KL' => [
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'postcode' => '50450',
            'prefix' => 'W',
            'plates' => ['WXY1234', 'WKL8821', 'WKL3300'],
        ],
        'JB' => [
            'city' => 'Johor Bahru',
            'state' => 'Johor',
            'postcode' => '80000',
            'prefix' => 'J',
            'plates' => ['JBB5678', 'JBB9012', 'JHR4455'],
        ],
        'KLG' => [
            'city' => 'Klang',
            'state' => 'Selangor',
            'postcode' => '41000',
            'prefix' => 'B',
            'plates' => ['BKL7788', 'BKL2299', 'BKG6611'],
        ],
        'PG' => [
            'city' => 'George Town',
            'state' => 'Penang',
            'postcode' => '10050',
            'prefix' => 'P',
            'plates' => ['PNG1122', 'PNG3344', 'PNG5566'],
        ],
    ];

    public function run(): void
    {
        $branches = Branch::query()->orderBy('id')->get()->keyBy('code');
        $hq = User::query()->where('email', 'admin@og.local')->first();
        $numbering = app(DocumentNumberingService::class);

        $fleet = [];

        foreach ($branches as $code => $branch) {
            $meta = $this->branchMeta[$code];
            $slug = strtolower($code);
            $company = Company::query()->firstOrCreate(
                ['code' => $branch->code],
                [
                    'branch_id' => $branch->id,
                    'name' => $branch->company_name,
                    'brn' => $branch->company_no ?: "{$branch->code}-BRN",
                    'is_active' => true,
                ]
            );

            $manager = User::query()->firstOrCreate(
                ['email' => "manager.{$slug}@og.local"],
                [
                    'name' => "{$code} Branch Manager",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            if (! $manager->hasRole('branch_manager')) {
                $manager->assignRole('branch_manager');
            }
            if (! $manager->branches()->where('branches.id', $branch->id)->exists()) {
                $manager->branches()->attach($branch->id, ['is_default' => true]);
            }
            if (! $manager->companies()->where('companies.id', $company->id)->exists()) {
                $manager->companies()->attach($company->id, ['is_default' => true]);
            }

            $counter = User::query()->firstOrCreate(
                ['email' => "counter.{$slug}@og.local"],
                [
                    'name' => "{$code} Counter",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            if (! $counter->hasRole('counter')) {
                $counter->assignRole('counter');
            }
            if (! $counter->branches()->where('branches.id', $branch->id)->exists()) {
                $counter->branches()->attach($branch->id, ['is_default' => true]);
            }
            if (! $counter->companies()->where('companies.id', $company->id)->exists()) {
                $counter->companies()->attach($company->id, ['is_default' => true]);
            }

            $finance = User::query()->firstOrCreate(
                ['email' => "finance.{$slug}@og.local"],
                [
                    'name' => "{$code} Finance",
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            if (! $finance->hasRole('finance')) {
                $finance->assignRole('finance');
            }
            if (! $finance->branches()->where('branches.id', $branch->id)->exists()) {
                $finance->branches()->attach($branch->id, ['is_default' => true]);
            }
            if (! $finance->companies()->where('companies.id', $company->id)->exists()) {
                $finance->companies()->attach($company->id, ['is_default' => true]);
            }

            $drivers = collect();
            $lorries = collect();

            foreach ([1, 2, 3] as $i) {
                $driver = Driver::query()->firstOrCreate(
                    ['code' => sprintf('DRV-%s-%03d', $code, $i)],
                    [
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'name' => "{$code} Driver {$i}",
                        'phone' => '01'.random_int(20000000, 99999999),
                        'type' => $i === 3 ? 'subcontractor' : 'internal',
                        'is_active' => true,
                    ]
                );
                $drivers->push($driver);

                if ($i === 1) {
                    $driverUser = User::query()->firstOrCreate(
                        ['email' => "driver.{$slug}@og.local"],
                        [
                            'name' => $driver->name,
                            'password' => Hash::make('password'),
                            'is_active' => true,
                            'driver_id' => $driver->id,
                            'phone' => $driver->phone,
                        ]
                    );
                    if (! $driverUser->hasRole('driver')) {
                        $driverUser->assignRole('driver');
                    }
                    if (! $driverUser->branches()->where('branches.id', $branch->id)->exists()) {
                        $driverUser->branches()->attach($branch->id, ['is_default' => true]);
                    }
                    if (! $driverUser->companies()->where('companies.id', $company->id)->exists()) {
                        $driverUser->companies()->attach($company->id, ['is_default' => true]);
                    }
                    if (! $driverUser->driver_id) {
                        $driverUser->update(['driver_id' => $driver->id]);
                    }
                }

                $plate = $meta['plates'][$i - 1];
                $lorry = Lorry::query()->firstOrCreate(
                    ['registration_no' => $plate],
                    [
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'type' => $i === 2 ? '5-ton' : '10-ton',
                        'capacity' => $i === 2 ? 5000 : 10000,
                        'default_driver_id' => $driver->id,
                        'status' => 'available',
                        'is_active' => true,
                    ]
                );
                if (! $lorry->default_driver_id) {
                    $lorry->update(['default_driver_id' => $driver->id]);
                }
                $lorries->push($lorry);

                VehicleMaintenanceRecord::query()->firstOrCreate(
                    [
                        'lorry_id' => $lorry->id,
                        'type' => $i === 1 ? 'insurance' : ($i === 2 ? 'road_tax' : 'service'),
                    ],
                    [
                        'service_date' => now()->subMonths(6 + $i),
                        'expiry_date' => now()->addDays(10 + ($i * 12)),
                        'next_service_date' => now()->addDays(5 + ($i * 8)),
                        'mileage' => 40000 + ($i * 5000),
                        'next_service_mileage' => 50000 + ($i * 5000),
                        'cost' => 800 + ($i * 400),
                        'notes' => "Seeded {$code} {$lorry->registration_no} maintenance",
                        'status' => 'active',
                    ]
                );
            }

            $creditCustomer = Customer::query()->firstOrCreate(
                ['code' => "CUST-{$code}-001"],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'company_name' => "{$code} Trading Sdn Bhd",
                    'brn' => '2019'.str_pad((string) $branch->id, 8, '0', STR_PAD_LEFT),
                    'tin' => 'C'.str_pad((string) (1000000000 + $branch->id), 10, '0', STR_PAD_LEFT),
                    'email' => "accounts.{$slug}@demo.local",
                    'phone' => '03'.random_int(20000000, 99999999),
                    'address' => "1 Jalan Industri, {$meta['postcode']} {$meta['city']}",
                    'is_credit' => true,
                    'credit_limit' => 80000,
                    'credit_term_days' => 30,
                    'status' => 'active',
                    'portal_approved' => true,
                    'einvoice_buyer_name' => "{$code} Trading Sdn Bhd",
                    'einvoice_tin' => 'C'.str_pad((string) (1000000000 + $branch->id), 10, '0', STR_PAD_LEFT),
                    'einvoice_id_type' => 'BRN',
                    'einvoice_id_value' => '2019'.str_pad((string) $branch->id, 8, '0', STR_PAD_LEFT),
                ]
            );

            $cashCustomer = Customer::query()->firstOrCreate(
                ['code' => "CUST-{$code}-002"],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'company_name' => "{$code} Cash Mart",
                    'brn' => '2020'.str_pad((string) $branch->id, 8, '0', STR_PAD_LEFT),
                    'tin' => 'C'.str_pad((string) (2000000000 + $branch->id), 10, '0', STR_PAD_LEFT),
                    'email' => "cash.{$slug}@demo.local",
                    'phone' => '01'.random_int(20000000, 99999999),
                    'address' => "22 Lebuh Dagang, {$meta['postcode']} {$meta['city']}",
                    'is_credit' => false,
                    'credit_limit' => 0,
                    'credit_term_days' => 0,
                    'status' => 'active',
                    'portal_approved' => false,
                ]
            );

            foreach ([$creditCustomer, $cashCustomer] as $idx => $customer) {
                CustomerAddress::query()->firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'label' => "{$code} Main Depot",
                    ],
                    [
                        'type' => 'delivery',
                        'address' => "88 Depot Road, {$meta['postcode']} {$meta['city']}",
                        'postcode' => $meta['postcode'],
                        'state' => $meta['state'],
                        'city' => $meta['city'],
                        'is_default' => true,
                    ]
                );
            }

            $fleet[$code] = [
                'branch' => $branch,
                'manager' => $manager,
                'drivers' => $drivers,
                'lorries' => $lorries,
                'credit_customer' => $creditCustomer,
                'cash_customer' => $cashCustomer,
                'meta' => $meta,
            ];
        }

        // Cross-branch destinations for quotations
        $destinationsByBranch = [
            'KL' => [
                ['name' => 'JB Warehouse', 'city' => 'Johor Bahru', 'state' => 'Johor', 'postcode' => '80000', 'address' => '88 Persiaran Tebrau, 80000 Johor Bahru'],
                ['name' => 'Penang Store', 'city' => 'George Town', 'state' => 'Penang', 'postcode' => '10050', 'address' => '5 Jalan Sultan Ahmad Shah, 10050 George Town'],
            ],
            'JB' => [
                ['name' => 'KL DC', 'city' => 'Kuala Lumpur', 'state' => 'Wilayah Persekutuan', 'postcode' => '50450', 'address' => '12 Jalan Ampang, 50450 Kuala Lumpur'],
                ['name' => 'Klang Yard', 'city' => 'Klang', 'state' => 'Selangor', 'postcode' => '41000', 'address' => '9 Jalan Kapar, 41000 Klang'],
            ],
            'KLG' => [
                ['name' => 'KL West Hub', 'city' => 'Kuala Lumpur', 'state' => 'Wilayah Persekutuan', 'postcode' => '52100', 'address' => '3 Jalan Kepong, 52100 Kuala Lumpur'],
                ['name' => 'JB South Gate', 'city' => 'Johor Bahru', 'state' => 'Johor', 'postcode' => '81200', 'address' => '15 Jalan Tampoi, 81200 Johor Bahru'],
            ],
            'PG' => [
                ['name' => 'KL North', 'city' => 'Kuala Lumpur', 'state' => 'Wilayah Persekutuan', 'postcode' => '51200', 'address' => '77 Jalan Ipoh, 51200 Kuala Lumpur'],
                ['name' => 'Klang Port', 'city' => 'Port Klang', 'state' => 'Selangor', 'postcode' => '42000', 'address' => '1 Persiaran Pelabuhan, 42000 Port Klang'],
            ],
        ];

        $convert = app(ConvertQuotationToCsns::class);
        $assign = app(AssignCsnToLorry::class);
        $complete = app(CompleteDelivery::class);
        $returnCsn = app(RecordReturnedCsn::class);

        $stats = [
            'quotations' => 0,
            'csns' => 0,
            'delivered' => 0,
            'in_transit' => 0,
            'unassigned' => 0,
        ];

        foreach ($fleet as $code => $ctx) {
            $branch = $ctx['branch'];
            $manager = $ctx['manager'];
            $destDefs = $destinationsByBranch[$code];

            // 1) Confirmed term quotation → convert → assign → deliver (+ return one)
            $q1 = $this->makeQuotation(
                $numbering,
                $branch,
                $ctx['credit_customer'],
                $manager,
                $destDefs,
                "{$code} term multi-drop demo",
                [900, 750]
            );
            $stats['quotations']++;

            $csns = $convert->execute($q1, $manager, 'term');
            $stats['csns'] += $csns->count();

            foreach ($csns->values() as $index => $csn) {
                $lorry = $ctx['lorries'][$index % $ctx['lorries']->count()];
                $do = $assign->execute($csn, $lorry, now()->toDateString());

                if ($index === 0) {
                    $complete->execute(
                        $do,
                        $lorry->defaultDriver ?? $ctx['drivers']->first(),
                        [
                            'recipient_name' => 'Receiver '.$code,
                            'latitude' => 3.1 + ($branch->id * 0.01),
                            'longitude' => 101.6 + ($branch->id * 0.01),
                            'client_uuid' => (string) Str::uuid(),
                        ],
                        $manager
                    );
                    $stats['delivered']++;

                    try {
                        $returnCsn->execute($csn->fresh(), [
                            'is_signed' => true,
                            'is_stamped' => true,
                            'returned_by_driver_id' => $do->driver_id,
                        ], $manager);
                    } catch (\Throwable) {
                        // already returned in prior seed runs
                    }
                } else {
                    $do->jobSheet?->update([
                        'status' => 'in_transit',
                        'checked_in_at' => now()->subHours(2),
                    ]);
                    $do->update(['status' => 'in_transit']);
                    $stats['in_transit']++;
                }
            }

            // 2) COD quotation → convert → assign (leave open / in transit)
            $q2 = $this->makeQuotation(
                $numbering,
                $branch,
                $ctx['cash_customer'],
                $manager,
                [$destDefs[0]],
                "{$code} COD same-day",
                [1200]
            );
            $stats['quotations']++;
            $codCsns = $convert->execute($q2, $manager, 'cod');
            $stats['csns'] += $codCsns->count();
            foreach ($codCsns as $csn) {
                $lorry = $ctx['lorries'][1] ?? $ctx['lorries']->first();
                $assign->execute($csn, $lorry, now()->toDateString());
                $stats['in_transit']++;
            }

            // 3) Extra confirmed quotation left unconverted for UI demos
            $this->makeQuotation(
                $numbering,
                $branch,
                $ctx['credit_customer'],
                $manager,
                [$destDefs[1] ?? $destDefs[0]],
                "{$code} ready to convert",
                [560]
            );
            $stats['quotations']++;
            $stats['unassigned']++;

            // 4) Shared-dispatch style: one KL CSN executed by another branch lorry (only from KL)
            if ($code === 'KL' && isset($fleet['JB'])) {
                $sharedQuote = $this->makeQuotation(
                    $numbering,
                    $branch,
                    $ctx['credit_customer'],
                    $manager,
                    [[
                        'name' => 'JB Shared Drop',
                        'city' => 'Johor Bahru',
                        'state' => 'Johor',
                        'postcode' => '80000',
                        'address' => '99 Jalan Sutera, 80000 Johor Bahru',
                    ]],
                    'KL source / JB lorry shared dispatch',
                    [1500]
                );
                $stats['quotations']++;
                $sharedCsns = $convert->execute($sharedQuote, $manager, 'term');
                $stats['csns'] += $sharedCsns->count();
                $jbLorry = $fleet['JB']['lorries']->first();
                $assign->execute($sharedCsns->first(), $jbLorry, now()->toDateString());
                $stats['in_transit']++;
            }
        }

        // Keep classic Demo Trading portal customer linked if present
        $demo = Customer::query()->where('code', 'CUST-001')->first();
        if ($demo && $hq) {
            // already seeded in DatabaseSeeder
        }

        $this->command?->info('Rich demo operations seeded for all companies.');
        $this->command?->table(
            ['Metric', 'Count'],
            [
                ['Quotations', $stats['quotations']],
                ['CSNs created', $stats['csns']],
                ['Delivered DOs', $stats['delivered']],
                ['Active/in-transit assigns', $stats['in_transit']],
                ['Unconverted quotes', $stats['unassigned']],
                ['Drivers', Driver::count()],
                ['Lorries', Lorry::count()],
                ['Customers', Customer::count()],
            ]
        );
    }

    private function makeQuotation(
        DocumentNumberingService $numbering,
        Branch $branch,
        Customer $customer,
        User $manager,
        array $destDefs,
        string $notes,
        array $lineTotals,
    ): Quotation {
        $subtotal = array_sum($lineTotals);

        $quotation = Quotation::query()->create([
            'number' => $numbering->next($branch, DocumentType::Quotation),
            'company_id' => $branch->companies()->first()?->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'salesperson_id' => $manager->id,
            'status' => QuotationStatus::Confirmed,
            'pricing_source' => 'manual',
            'valid_until' => now()->addDays(14),
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'notes' => $notes,
            'confirmed_at' => now(),
            'created_by' => $manager->id,
        ]);

        foreach ($destDefs as $i => $dest) {
            $destination = QuotationDestination::query()->create([
                'quotation_id' => $quotation->id,
                'sequence' => $i + 1,
                'consignee_name' => $dest['name'],
                'consignee_pic' => 'PIC '.($i + 1),
                'consignee_phone' => '019'.random_int(1000000, 9999999),
                'address' => $dest['address'],
                'postcode' => $dest['postcode'],
                'state' => $dest['state'],
                'city' => $dest['city'],
            ]);

            $lineTotal = $lineTotals[$i] ?? $lineTotals[0];
            $qty = max(1, (int) round($lineTotal / 100));
            $unit = round($lineTotal / $qty, 2);

            QuotationLine::query()->create([
                'quotation_id' => $quotation->id,
                'quotation_destination_id' => $destination->id,
                'item_name' => 'General Goods',
                'uom' => 'CTN',
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
            ]);
        }

        return $quotation->fresh(['destinations', 'lines']);
    }
}

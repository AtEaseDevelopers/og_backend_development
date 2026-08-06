<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\ItemCategory;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\MasterData\Models\CommissionRule;
use App\Domains\MasterData\Models\TransferCode;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\VehicleMaintenanceRecord;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\Quotation\Models\QuotationLine;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'hq_admin', 'branch_manager', 'counter', 'finance',
            'dispatcher', 'salesperson', 'storekeeper', 'driver', 'customer',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        $branches = collect([
            [
                'code' => 'KL',
                'name' => 'KL Branch',
                'company_name' => 'O & G Transport (KL) Sdn. Bhd.',
                'company_no' => '200401017561 (656064-D)',
            ],
            [
                'code' => 'JB',
                'name' => 'JB Branch',
                'company_name' => 'O & G Transport (JB) Sdn. Bhd.',
                'company_no' => '201001000360 (884930-H)',
            ],
            [
                'code' => 'KLG',
                'name' => 'Klang Branch',
                'company_name' => 'O & G Transport (Klang) Sdn. Bhd.',
                'company_no' => '201401034372 (1110470-U)',
            ],
            [
                'code' => 'PG',
                'name' => 'Penang–Selangor Branch',
                'company_name' => 'Syarikat Pengangkutan (Penang – Selangor) Sdn. Bhd.',
                'company_no' => '199701036168 (451668-P)',
            ],
        ])->map(fn ($data) => Branch::query()->create($data + ['is_active' => true]));

        $companies = $branches->mapWithKeys(function (Branch $branch) {
            $company = Company::query()->create([
                'branch_id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->company_name,
                'brn' => $branch->company_no,
                'is_active' => true,
            ]);

            return [$branch->code => $company];
        });

        $kl = $branches->firstWhere('code', 'KL');
        $klCompany = $companies['KL'];

        $hq = User::query()->create([
            'name' => 'HQ Admin',
            'email' => 'admin@og.local',
            'password' => Hash::make('password'),
            'is_hq' => true,
            'is_active' => true,
        ]);
        $hq->assignRole('hq_admin');
        $hq->branches()->attach($branches->pluck('id'), ['is_default' => true]);
        $hq->companies()->attach($companies->pluck('id'), ['is_default' => true]);

        // One account that can switch across all company branches
        $ops = User::query()->create([
            'name' => 'Multi-Branch Ops',
            'email' => 'ops@og.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $ops->assignRole('branch_manager');
        foreach ($branches as $index => $branch) {
            $ops->branches()->attach($branch->id, ['is_default' => $index === 0]);
            $ops->companies()->attach($companies[$branch->code]->id, ['is_default' => $index === 0]);
        }

        $manager = User::query()->create([
            'name' => 'KL Branch Manager',
            'email' => 'manager.kl@og.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $manager->assignRole('branch_manager');
        $manager->branches()->attach($kl->id, ['is_default' => true]);
        $manager->companies()->attach($klCompany->id, ['is_default' => true]);

        $counter = User::query()->create([
            'name' => 'KL Counter',
            'email' => 'counter.kl@og.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $counter->assignRole('counter');
        $counter->branches()->attach($kl->id, ['is_default' => true]);
        $counter->companies()->attach($klCompany->id, ['is_default' => true]);

        $finance = User::query()->create([
            'name' => 'KL Finance',
            'email' => 'finance.kl@og.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $finance->assignRole('finance');
        $finance->branches()->attach($kl->id, ['is_default' => true]);
        $finance->companies()->attach($klCompany->id, ['is_default' => true]);

        $driver = Driver::query()->create([
            'company_id' => $klCompany->id,
            'branch_id' => $kl->id,
            'code' => 'DRV-KL-001',
            'name' => 'Ahmad Driver',
            'phone' => '0123456789',
            'type' => 'internal',
            'is_active' => true,
        ]);

        $driverUser = User::query()->create([
            'name' => 'Ahmad Driver',
            'email' => 'driver.kl@og.local',
            'password' => Hash::make('password'),
            'is_active' => true,
            'driver_id' => $driver->id,
            'phone' => '0123456789',
        ]);
        $driverUser->assignRole('driver');
        $driverUser->branches()->attach($kl->id, ['is_default' => true]);
        $driverUser->companies()->attach($klCompany->id, ['is_default' => true]);

        $lorry = Lorry::query()->create([
            'company_id' => $klCompany->id,
            'branch_id' => $kl->id,
            'registration_no' => 'WXY1234',
            'type' => '10-ton',
            'capacity' => 10000,
            'default_driver_id' => $driver->id,
            'status' => 'available',
            'is_active' => true,
        ]);

        $jb = $branches->firstWhere('code', 'JB');
        $jbCompany = $companies['JB'];
        $jbDriver = Driver::query()->create([
            'company_id' => $jbCompany->id,
            'branch_id' => $jb->id,
            'code' => 'DRV-JB-001',
            'name' => 'JB Driver',
            'phone' => '0198765432',
            'type' => 'internal',
            'is_active' => true,
        ]);
        $jbLorry = Lorry::query()->create([
            'company_id' => $jbCompany->id,
            'branch_id' => $jb->id,
            'registration_no' => 'JBB5678',
            'type' => '10-ton',
            'capacity' => 10000,
            'default_driver_id' => $jbDriver->id,
            'status' => 'available',
            'is_active' => true,
        ]);

        VehicleMaintenanceRecord::query()->create([
            'lorry_id' => $lorry->id,
            'type' => 'insurance',
            'service_date' => now()->subMonths(10),
            'expiry_date' => now()->addDays(20),
            'next_service_date' => now()->addDays(15),
            'cost' => 2500,
            'notes' => 'Demo insurance due soon',
            'status' => 'active',
        ]);

        CommissionRule::query()->create([
            'name' => 'Default single driver 10%',
            'split_type' => 'single',
            'rate_percent' => 10,
            'percentages' => ['shares' => [100]],
            'is_active' => true,
        ]);
        CommissionRule::query()->create([
            'name' => '10-ton split 2',
            'lorry_type' => '10-ton',
            'split_type' => 'split_2',
            'rate_percent' => 10,
            'percentages' => ['shares' => [60, 40]],
            'is_active' => true,
        ]);

        TransferCode::query()->insert([
            [
                'code' => 'TRF-KL-JB',
                'name' => 'KL → JB transfer',
                'destination_branch_id' => $jb->id,
                'type' => 'transfer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PSI-JB-IN',
                'name' => 'JB incoming PSI',
                'destination_branch_id' => $jb->id,
                'type' => 'incoming',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TRF-KL-PG',
                'name' => 'KL → Penang transfer',
                'destination_branch_id' => $branches->firstWhere('code', 'PG')->id,
                'type' => 'transfer',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Uom::query()->insert([
            ['code' => 'UNIT', 'name' => 'Unit', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KG', 'name' => 'Kilogram', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CTN', 'name' => 'Carton', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $category = ItemCategory::query()->create(['name' => 'General Cargo', 'is_active' => true]);
        Item::query()->create([
            'item_category_id' => $category->id,
            'code' => 'GEN-001',
            'name' => 'General Goods',
            'default_uom' => 'CTN',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $klCompany->id,
            'branch_id' => $kl->id,
            'code' => 'CUST-001',
            'company_name' => 'Demo Trading Sdn Bhd',
            'brn' => '201901234567',
            'tin' => 'C1234567890',
            'email' => 'customer@demo.local',
            'phone' => '0387654321',
            'address' => '12 Jalan Demo, 50450 Kuala Lumpur',
            'is_credit' => true,
            'credit_limit' => 50000,
            'credit_term_days' => 30,
            'status' => 'active',
            'portal_approved' => true,
        ]);

        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => 'delivery',
            'label' => 'JB Warehouse',
            'address' => '88 Persiaran Tebrau, 80000 Johor Bahru',
            'postcode' => '80000',
            'state' => 'Johor',
            'city' => 'Johor Bahru',
            'is_default' => true,
        ]);

        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => 'delivery',
            'label' => 'Penang Store',
            'address' => '5 Jalan Sultan Ahmad Shah, 10050 George Town',
            'postcode' => '10050',
            'state' => 'Penang',
            'city' => 'George Town',
            'is_default' => false,
        ]);

        $portalUser = User::query()->create([
            'name' => 'Demo Customer',
            'email' => 'portal@demo.local',
            'password' => Hash::make('password'),
            'is_active' => true,
            'customer_id' => $customer->id,
        ]);
        $portalUser->assignRole('customer');
        $portalUser->customers()->attach($customer->id, ['status' => 'approved']);

        $numbering = app(DocumentNumberingService::class);
        $quotation = Quotation::query()->create([
            'number' => $numbering->next($kl, DocumentType::Quotation),
            'company_id' => $klCompany->id,
            'branch_id' => $kl->id,
            'customer_id' => $customer->id,
            'salesperson_id' => $manager->id,
            'status' => QuotationStatus::Confirmed,
            'pricing_source' => 'manual',
            'valid_until' => now()->addDays(14),
            'subtotal' => 1500,
            'total_amount' => 1500,
            'notes' => 'Demo quotation with 2 destinations',
            'confirmed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $dest1 = QuotationDestination::query()->create([
            'quotation_id' => $quotation->id,
            'sequence' => 1,
            'consignee_name' => 'JB Warehouse',
            'consignee_pic' => 'Ali',
            'consignee_phone' => '0191111111',
            'address' => '88 Persiaran Tebrau, 80000 Johor Bahru',
            'postcode' => '80000',
            'state' => 'Johor',
            'city' => 'Johor Bahru',
        ]);

        $dest2 = QuotationDestination::query()->create([
            'quotation_id' => $quotation->id,
            'sequence' => 2,
            'consignee_name' => 'Penang Store',
            'consignee_pic' => 'Siti',
            'consignee_phone' => '0192222222',
            'address' => '5 Jalan Sultan Ahmad Shah, 10050 George Town',
            'postcode' => '10050',
            'state' => 'Penang',
            'city' => 'George Town',
        ]);

        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'quotation_destination_id' => $dest1->id,
            'item_name' => 'General Goods',
            'uom' => 'CTN',
            'quantity' => 10,
            'unit_price' => 80,
            'line_total' => 800,
        ]);

        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'quotation_destination_id' => $dest2->id,
            'item_name' => 'General Goods',
            'uom' => 'CTN',
            'quantity' => 7,
            'unit_price' => 100,
            'line_total' => 700,
        ]);

        // Rich multi-company fleet, customers, quotations, CSNs, deliveries
        $this->call(DemoOperationsSeeder::class);

        $this->command?->info('Seeded O&G Transport demo data.');
        $this->command?->table(
            ['Account', 'Email', 'Password'],
            [
                ['HQ Admin (all branches)', 'admin@og.local', 'password'],
                ['Multi-branch ops (pick KL/JB/KLG/PG)', 'ops@og.local', 'password'],
                ['KL-only Manager', 'manager.kl@og.local', 'password'],
                ['JB Manager', 'manager.jb@og.local', 'password'],
                ['KL Counter', 'counter.kl@og.local', 'password'],
                ['KL Finance', 'finance.kl@og.local', 'password'],
                ['KL Driver', 'driver.kl@og.local', 'password'],
                ['Portal Customer', 'portal@demo.local', 'password'],
            ]
        );
        $this->command?->info('Password for all demo accounts: password');
        $this->command?->info('Login → choose branch → choose/register company → open that company system.');
        $this->command?->info('Each seeded company has drivers, lorries, customers, quotations, CSNs, and mixed delivery statuses.');
        $this->command?->info("Legacy KL demo quotation still available: {$quotation->number} (confirmed, ready to convert)");
    }
}

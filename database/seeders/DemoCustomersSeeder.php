<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCustomersSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->whereIn('code', ['KL', 'JB', 'KLG', 'PG'])->get()->keyBy('code');

        $definitions = [
            [
                'branch' => 'KL',
                'code' => 'CUST-KL-010',
                'company_name' => 'Pacific Logistics Sdn Bhd',
                'brn' => '201801012345',
                'tin' => 'C19801012345',
                'email' => 'pacific@demo.local',
                'phone' => '0321234567',
                'address' => '15 Jalan Ampang, 50450 Kuala Lumpur',
                'is_credit' => true,
                'credit_limit' => 120000,
                'credit_term_days' => 30,
                'portal' => ['name' => 'Pacific Logistics', 'email' => 'pacific@demo.local'],
            ],
            [
                'branch' => 'KL',
                'code' => 'CUST-KL-011',
                'company_name' => 'Sunrise Retail Sdn Bhd',
                'brn' => '201901987654',
                'tin' => 'C19901987654',
                'email' => 'sunrise@demo.local',
                'phone' => '0376543210',
                'address' => '88 Jalan Tun Razak, 50400 Kuala Lumpur',
                'is_credit' => true,
                'credit_limit' => 45000,
                'credit_term_days' => 14,
                'portal' => ['name' => 'Sunrise Retail', 'email' => 'sunrise@demo.local'],
            ],
            [
                'branch' => 'KL',
                'code' => 'CUST-KL-012',
                'company_name' => 'Quick Send Trading',
                'brn' => '202001112233',
                'tin' => 'C20001112233',
                'email' => 'quicksend@demo.local',
                'phone' => '0112233445',
                'address' => 'Lot 7, Kawasan Perindustrian Bukit Raja, 40000 Shah Alam',
                'is_credit' => false,
                'credit_limit' => 0,
                'credit_term_days' => 0,
                'portal' => ['name' => 'Quick Send', 'email' => 'quicksend@demo.local'],
            ],
            [
                'branch' => 'JB',
                'code' => 'CUST-JB-010',
                'company_name' => 'Southern Freight Sdn Bhd',
                'brn' => '201701445566',
                'tin' => 'C19701445566',
                'email' => 'southfreight@demo.local',
                'phone' => '077123456',
                'address' => '12 Jalan Kempas, 81200 Johor Bahru',
                'is_credit' => true,
                'credit_limit' => 90000,
                'credit_term_days' => 30,
                'portal' => ['name' => 'Southern Freight', 'email' => 'southfreight@demo.local'],
            ],
            [
                'branch' => 'JB',
                'code' => 'CUST-JB-011',
                'company_name' => 'Tebrau Supplies Sdn Bhd',
                'brn' => '201802778899',
                'tin' => 'C19802778899',
                'email' => 'tebrau@demo.local',
                'phone' => '077998877',
                'address' => '45 Persiaran Tebrau, 80400 Johor Bahru',
                'is_credit' => false,
                'credit_limit' => 0,
                'credit_term_days' => 0,
                'portal' => null,
            ],
            [
                'branch' => 'KLG',
                'code' => 'CUST-KLG-010',
                'company_name' => 'Klang Port Services',
                'brn' => '201603334455',
                'tin' => 'C19603334455',
                'email' => 'klangport@demo.local',
                'phone' => '0333166888',
                'address' => 'Port Klang Free Zone, 42000 Pelabuhan Klang',
                'is_credit' => true,
                'credit_limit' => 65000,
                'credit_term_days' => 30,
                'portal' => ['name' => 'Klang Port Services', 'email' => 'klangport@demo.local'],
            ],
            [
                'branch' => 'PG',
                'code' => 'CUST-PG-010',
                'company_name' => 'Penang Electronics Sdn Bhd',
                'brn' => '201504556677',
                'tin' => 'C19504556677',
                'email' => 'penang.elec@demo.local',
                'phone' => '044567890',
                'address' => 'Bayan Lepas Industrial Park, 11900 Bayan Lepas',
                'is_credit' => true,
                'credit_limit' => 75000,
                'credit_term_days' => 45,
                'portal' => ['name' => 'Penang Electronics', 'email' => 'penang.elec@demo.local'],
            ],
        ];

        $created = 0;
        $portalUsers = 0;

        foreach ($definitions as $definition) {
            $branch = $branches->get($definition['branch']);

            if (! $branch) {
                continue;
            }

            $company = Company::query()
                ->where('branch_id', $branch->id)
                ->where('code', $branch->code)
                ->first();

            if (! $company) {
                continue;
            }

            $customer = Customer::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'company_name' => $definition['company_name'],
                    'brn' => $definition['brn'],
                    'tin' => $definition['tin'],
                    'email' => $definition['email'],
                    'phone' => $definition['phone'],
                    'address' => $definition['address'],
                    'debtor_type' => 'corporate',
                    'currency' => 'MYR',
                    'credit_term_days' => $definition['credit_term_days'],
                    'is_credit' => $definition['is_credit'],
                    'credit_limit' => $definition['credit_limit'],
                    'status' => 'active',
                    'portal_approved' => $definition['portal'] !== null,
                    'einvoice_buyer_name' => $definition['company_name'],
                    'einvoice_tin' => $definition['tin'],
                    'einvoice_id_type' => 'BRN',
                    'einvoice_id_value' => $definition['brn'],
                ],
            );

            CustomerAddress::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'label' => 'Main delivery',
                ],
                [
                    'type' => 'delivery',
                    'address' => $definition['address'],
                    'postcode' => match ($definition['branch']) {
                        'KL' => '50450',
                        'JB' => '81200',
                        'KLG' => '42000',
                        'PG' => '11900',
                        default => null,
                    },
                    'state' => match ($definition['branch']) {
                        'KL', 'KLG' => 'Selangor',
                        'JB' => 'Johor',
                        'PG' => 'Penang',
                        default => null,
                    },
                    'city' => match ($definition['branch']) {
                        'KL' => 'Kuala Lumpur',
                        'JB' => 'Johor Bahru',
                        'KLG' => 'Port Klang',
                        'PG' => 'Bayan Lepas',
                        default => null,
                    },
                    'is_default' => true,
                ],
            );

            $created++;

            if ($definition['portal'] === null) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $definition['portal']['email']],
                [
                    'name' => $definition['portal']['name'],
                    'password' => Hash::make('password'),
                    'phone' => $definition['phone'],
                    'customer_id' => $customer->id,
                    'is_active' => true,
                ],
            );

            if (! $user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            $user->customers()->syncWithoutDetaching([
                $customer->id => ['status' => 'approved'],
            ]);

            $portalUsers++;
        }

        // Multi-branch portal user linked to KL + JB customers
        $multiUser = User::query()->updateOrCreate(
            ['email' => 'multibranch@demo.local'],
            [
                'name' => 'Multi Branch Customer',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        if (! $multiUser->hasRole('customer')) {
            $multiUser->assignRole('customer');
        }

        foreach (['KL', 'JB'] as $branchCode) {
            $customer = Customer::query()->where('code', "CUST-{$branchCode}-010")->first();

            if ($customer) {
                $multiUser->customers()->syncWithoutDetaching([
                    $customer->id => ['status' => 'approved'],
                ]);
            }
        }

        if (! $multiUser->customer_id) {
            $multiUser->update([
                'customer_id' => Customer::query()->where('code', 'CUST-KL-010')->value('id'),
            ]);
        }

        $portalUsers++;

        $this->command?->info("Seeded {$created} customers with {$portalUsers} portal logins (password: password).");
        $this->command?->table(
            ['Portal login', 'Customer', 'Branch'],
            collect($definitions)
                ->filter(fn (array $row) => $row['portal'] !== null)
                ->map(fn (array $row) => [
                    $row['portal']['email'],
                    $row['company_name'],
                    $row['branch'],
                ])
                ->push(['multibranch@demo.local', 'Pacific + Southern Freight', 'KL + JB'])
                ->all(),
        );
    }
}

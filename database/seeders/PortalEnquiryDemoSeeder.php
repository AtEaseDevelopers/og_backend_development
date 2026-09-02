<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class PortalEnquiryDemoSeeder extends Seeder
{
    /**
     * Demo enquiries submitted by portal logins (see DemoCustomersSeeder).
     * Customer + submitter are always resolved from the portal user — same as EnquiryController.
     *
     * @var list<array<string, mixed>>
     */
    private array $enquiries = [
        [
            'reference_no' => 'ENQ-DEMO001',
            'portal_email' => 'pacific@demo.local',
            'status' => PortalEnquiryStatus::Pending,
            'pickup_maps_url' => 'https://maps.google.com/?q=Jalan+Ampang+Kuala+Lumpur',
            'preferred_delivery_date' => '+3 days',
            'special_requirements' => 'Need tailgate lorry. Delivery before 2pm.',
            'destinations' => [
                [
                    'consignee_name' => 'Mega Industrial Sdn Bhd',
                    'address' => 'Lot 12, Jalan Maju 3, Kajang Industrial Park',
                    'postcode' => '43000',
                    'state' => 'Selangor',
                    'city' => 'Kajang',
                    'consignee_pic' => 'Encik Azman',
                    'consignee_phone' => '+60 12-345 6789',
                    'pickup_pic' => 'Encik Hafiz',
                    'pickup_phone' => '+60 11-223 4455',
                ],
            ],
            'items' => [
                ['item_name' => 'Pallet - Industrial Parts', 'quantity' => 12, 'uom' => 'PLT', 'weight' => 850, 'dimensions' => '120 x 100 x 150 cm', 'special_request' => 'Requires side-curtain trailer', 'destination_index' => 0],
                ['item_name' => 'Carton - Spare Components', 'quantity' => 24, 'uom' => 'CTN', 'weight' => 320, 'dimensions' => '60 x 40 x 40 cm', 'destination_index' => 0],
            ],
        ],
        [
            'reference_no' => 'ENQ-DEMO002',
            'portal_email' => 'sunrise@demo.local',
            'status' => PortalEnquiryStatus::InReview,
            'pickup_maps_url' => null,
            'preferred_delivery_date' => '+5 days',
            'special_requirements' => 'Fragile goods — handle with care.',
            'destinations' => [
                [
                    'consignee_name' => 'Seremban Hypermarket',
                    'address' => 'Persiaran S2, Seremban 2',
                    'postcode' => '70300',
                    'state' => 'Negeri Sembilan',
                    'city' => 'Seremban',
                ],
                [
                    'consignee_name' => 'Melaka Retail Hub',
                    'address' => 'Jalan Merdeka, Bandar Hilir',
                    'postcode' => '75000',
                    'state' => 'Melaka',
                    'city' => 'Melaka',
                ],
            ],
            'items' => [
                ['item_name' => 'Display Rack', 'quantity' => 6, 'uom' => 'UNIT', 'weight' => 420, 'destination_index' => 0],
                ['item_name' => 'Carton - Consumer Goods', 'quantity' => 40, 'uom' => 'CTN', 'weight' => 560, 'destination_index' => 1],
            ],
        ],
        [
            'reference_no' => 'ENQ-DEMO003',
            'portal_email' => 'southfreight@demo.local',
            'status' => PortalEnquiryStatus::Pending,
            'pickup_maps_url' => 'https://maps.google.com/?q=Jalan+Kempas+Johor+Bahru',
            'preferred_delivery_date' => '+7 days',
            'special_requirements' => null,
            'destinations' => [
                [
                    'consignee_name' => 'Penang Electronics Warehouse',
                    'address' => 'Bayan Lepas Industrial Zone, Phase 3',
                    'postcode' => '11900',
                    'state' => 'Penang',
                    'city' => 'Bayan Lepas',
                ],
            ],
            'items' => [
                ['item_name' => 'Electronics - Server Racks', 'quantity' => 4, 'uom' => 'PLT', 'weight' => 960, 'destination_index' => 0],
            ],
        ],
        [
            'reference_no' => 'ENQ-DEMO004',
            'portal_email' => 'quicksend@demo.local',
            'status' => PortalEnquiryStatus::Rejected,
            'pickup_maps_url' => null,
            'preferred_delivery_date' => '+2 days',
            'special_requirements' => 'Urgent same-week delivery requested.',
            'rejection_reason' => 'Route capacity full for requested delivery week.',
            'destinations' => [
                [
                    'consignee_name' => 'Kuantan Trading',
                    'address' => 'Jalan Besar, Kuantan',
                    'postcode' => '25000',
                    'state' => 'Pahang',
                    'city' => 'Kuantan',
                ],
            ],
            'items' => [
                ['item_name' => 'Mixed cargo', 'quantity' => 8, 'uom' => 'PLT', 'weight' => 640, 'destination_index' => 0],
            ],
        ],
        [
            'reference_no' => 'ENQ-DEMO005',
            'portal_email' => 'multibranch@demo.local',
            'branch_code' => 'JB',
            'status' => PortalEnquiryStatus::Pending,
            'preferred_delivery_date' => '+4 days',
            'special_requirements' => 'Multi-branch portal account submitting under JB profile.',
            'destinations' => [
                [
                    'consignee_name' => 'Johor Distribution Centre',
                    'address' => 'Taman Perindustrian Kempas',
                    'postcode' => '81200',
                    'state' => 'Johor',
                    'city' => 'Johor Bahru',
                ],
            ],
            'items' => [
                ['item_name' => 'General cargo', 'quantity' => 10, 'uom' => 'CTN', 'weight' => 450, 'destination_index' => 0],
            ],
        ],
    ];

    public function run(): void
    {
        if (! User::query()->role('customer')->exists()) {
            $this->command?->warn('No portal users found — run DemoCustomersSeeder first.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->enquiries as $definition) {
            $portalUser = User::query()->where('email', $definition['portal_email'])->first();

            if (! $portalUser) {
                $this->command?->warn("Skipping {$definition['reference_no']}: portal user {$definition['portal_email']} not found.");
                $skipped++;

                continue;
            }

            $branch = isset($definition['branch_code'])
                ? Branch::query()->where('code', $definition['branch_code'])->first()
                : null;

            $customer = $this->resolveCustomerForPortalUser($portalUser, $branch);

            if (! $customer) {
                $this->command?->warn("Skipping {$definition['reference_no']}: no approved portal customer for {$definition['portal_email']}.");
                $skipped++;

                continue;
            }

            $branch ??= $customer->branch;

            if (! $branch) {
                $skipped++;

                continue;
            }

            $company = Company::query()->where('branch_id', $branch->id)->first();

            $payload = [
                'destinations' => $definition['destinations'],
                'items' => $definition['items'],
            ];

            if (filled($definition['rejection_reason'] ?? null)) {
                $payload['rejection_reason'] = $definition['rejection_reason'];
            }

            PortalEnquiry::query()->updateOrCreate(
                ['reference_no' => $definition['reference_no']],
                [
                    'company_id' => $company?->id ?? $customer->company_id,
                    'customer_id' => $customer->id,
                    'branch_id' => $branch->id,
                    'user_id' => $portalUser->id,
                    'pickup_address' => $definition['pickup_address'] ?? $customer->address,
                    'pickup_maps_url' => $definition['pickup_maps_url'] ?? null,
                    'preferred_delivery_date' => now()->addDays((int) preg_replace('/\D+/', '', (string) $definition['preferred_delivery_date']))->toDateString(),
                    'special_requirements' => $definition['special_requirements'] ?? null,
                    'status' => $definition['status']->value,
                    'payload' => $payload,
                    'quotation_id' => null,
                ],
            );

            $created++;
        }

        $this->command?->info("Seeded {$created} portal enquiries from portal customer accounts ({$skipped} skipped).");
    }

    private function resolveCustomerForPortalUser(User $portalUser, ?Branch $branch = null): ?Customer
    {
        if ($branch) {
            $linked = $portalUser->customers()
                ->wherePivot('status', 'approved')
                ->where('customers.branch_id', $branch->id)
                ->first();

            if ($linked) {
                return $linked;
            }
        }

        if ($portalUser->customer_id) {
            return Customer::query()->with('branch')->find($portalUser->customer_id);
        }

        $customerId = $portalUser->customers()
            ->wherePivot('status', 'approved')
            ->value('customers.id');

        return $customerId
            ? Customer::query()->with('branch')->find($customerId)
            : null;
    }
}

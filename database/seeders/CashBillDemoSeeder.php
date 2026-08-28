<?php

namespace Database\Seeders;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\Quotation\Models\QuotationLine;
use App\Enums\CsnBillingType;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;

class CashBillDemoSeeder extends Seeder
{
    /** @var array<string, list<array{amount: float, customer: string}>> */
    private array $outstandingBatch = [
        'KL' => [
            ['amount' => 120.00, 'customer' => 'Mega Industrial Sdn Bhd'],
            ['amount' => 80.00, 'customer' => 'TechTronix Sdn Bhd'],
        ],
        'PG' => [
            ['amount' => 150.00, 'customer' => 'Penang Retail Hub'],
            ['amount' => 95.00, 'customer' => 'Northern Logistics Sdn Bhd'],
        ],
        'JB' => [
            ['amount' => 210.00, 'customer' => 'JB Distribution Centre'],
            ['amount' => 130.00, 'customer' => 'Southern Freight Sdn Bhd'],
        ],
        'KLG' => [
            ['amount' => 175.00, 'customer' => 'Klang Warehouse'],
            ['amount' => 65.00, 'customer' => 'Port Klang Trading'],
        ],
    ];

    public function run(): void
    {
        $numbering = app(DocumentNumberingService::class);
        $convert = app(ConvertQuotationToCsns::class);
        $created = 0;

        foreach ($this->outstandingBatch as $code => $entries) {
            $branch = Branch::query()->where('code', $code)->first();

            if (! $branch) {
                continue;
            }

            $manager = User::query()
                ->where('email', 'manager.'.strtolower($code).'@og.local')
                ->first()
                ?? User::query()->where('email', 'admin@og.local')->first();

            if (! $manager) {
                continue;
            }

            $customer = Customer::query()
                ->where('code', "CUST-{$code}-002")
                ->first()
                ?? Customer::query()->where('branch_id', $branch->id)->first();

            if (! $customer) {
                continue;
            }

            foreach ($entries as $index => $entry) {
                $quotation = $this->makeCashBillQuotation(
                    $numbering,
                    $branch,
                    $customer,
                    $manager,
                    $code,
                    $entry['amount'],
                    $index + 1,
                    $entry['customer'],
                );

                $csns = $convert->execute($quotation, $manager, CsnBillingType::CashBill->value);
                $created += $csns->count();

                foreach ($csns as $csn) {
                    $csn->update(['customer_name' => $entry['customer']]);
                    $this->command?->info("Created {$csn->number} — {$entry['customer']} (RM ".number_format((float) $csn->total_amount, 2).')');
                }
            }
        }

        $outstanding = ConsignmentNote::query()
            ->where('billing_type', CsnBillingType::CashBill)
            ->whereNotIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::CodCollected->value])
            ->count();

        $this->command?->info("Cash bill demo seeding complete — {$created} CSN(s) created.");
        $this->command?->info("Outstanding Cash Bill CSNs in database: {$outstanding}");
        $this->command?->info('Open Billing → Cash Bill Calculator to collect payment.');
    }

    private function makeCashBillQuotation(
        DocumentNumberingService $numbering,
        Branch $branch,
        Customer $customer,
        User $manager,
        string $code,
        float $amount,
        int $sequence,
        ?string $consigneeName = null,
    ): Quotation {
        $companyId = $branch->companies()->first()?->id;

        $quotation = Quotation::query()->create([
            'number' => $numbering->next($branch, DocumentType::Quotation),
            'company_id' => $companyId,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'salesperson_id' => $manager->id,
            'status' => QuotationStatus::Confirmed,
            'pricing_source' => 'manual',
            'valid_until' => now()->addDays(7),
            'subtotal' => $amount,
            'total_amount' => $amount,
            'notes' => "{$code} cash bill demo {$sequence}",
            'confirmed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $destination = QuotationDestination::query()->create([
            'quotation_id' => $quotation->id,
            'sequence' => 1,
            'consignee_name' => $consigneeName ?? match ($code) {
                'PG' => 'Penang Retail Hub',
                'JB' => 'JB Distribution Centre',
                'KLG' => 'Klang Warehouse',
                default => 'Mega Industrial Sdn Bhd',
            },
            'consignee_pic' => 'Counter PIC',
            'consignee_phone' => '019'.random_int(1000000, 9999999),
            'address' => "Lot {$sequence}, Jalan Perindustrian, {$code}",
            'postcode' => '50000',
            'state' => 'Selangor',
            'city' => 'Kuala Lumpur',
        ]);

        QuotationLine::query()->create([
            'quotation_id' => $quotation->id,
            'quotation_destination_id' => $destination->id,
            'item_name' => 'General Goods',
            'uom' => 'CTN',
            'quantity' => 1,
            'unit_price' => $amount,
            'line_total' => $amount,
        ]);

        return $quotation->fresh(['destinations', 'lines']);
    }
}

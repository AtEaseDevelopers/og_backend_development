<?php

namespace Database\Seeders;

use App\Domains\Dispatch\Actions\AssignCsnToLorry;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use App\Domains\Quotation\Actions\ConvertQuotationToCsns;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\Quotation\Models\QuotationLine;
use App\Enums\CsnBillingType;
use App\Enums\DocumentType;
use App\Enums\JobSheetStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;

class CodListingDemoSeeder extends Seeder
{
    /** @var list<array{driver: string, lorry: string, amounts: list<float>, completed: bool}> */
    private array $rows = [
        ['driver' => 'Ahmad Faiz', 'lorry' => 'BBA4492', 'amounts' => [250, 250, 250, 250, 250], 'completed' => false],
        ['driver' => 'Lee Wei', 'lorry' => 'JQR1188', 'amounts' => [120, 110, 120], 'completed' => false],
        ['driver' => 'Muthu Kumar', 'lorry' => 'WWA9901', 'amounts' => [150, 100], 'completed' => true],
    ];

    public function run(): void
    {
        $branch = Branch::query()->where('code', 'KL')->first();

        if (! $branch) {
            $this->command?->warn('KL branch not found — skipping CodListingDemoSeeder.');

            return;
        }

        $companyId = $branch->companies()->first()?->id;
        $manager = User::query()->where('email', 'manager.kl@og.local')->first()
            ?? User::query()->where('email', 'admin@og.local')->first();

        if (! $manager) {
            return;
        }

        $customer = Customer::query()->where('code', 'CUST-KL-002')->first()
            ?? Customer::query()->where('branch_id', $branch->id)->first();

        if (! $customer) {
            return;
        }

        $numbering = app(DocumentNumberingService::class);
        $convert = app(ConvertQuotationToCsns::class);
        $assign = app(AssignCsnToLorry::class);
        $today = now()->toDateString();
        $created = 0;

        foreach ($this->rows as $row) {
            $driver = Driver::query()->firstOrCreate(
                ['code' => 'DRV-COD-'.strtoupper(str_replace(' ', '', $row['driver']))],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branch->id,
                    'name' => $row['driver'],
                    'phone' => '01'.random_int(20000000, 99999999),
                    'type' => 'internal',
                    'is_active' => true,
                ]
            );

            if ($driver->name !== $row['driver']) {
                $driver->update(['name' => $row['driver']]);
            }

            $lorry = Lorry::query()->firstOrCreate(
                ['registration_no' => $row['lorry']],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branch->id,
                    'type' => '5-ton',
                    'capacity' => 5000,
                    'default_driver_id' => $driver->id,
                    'status' => 'available',
                    'is_active' => true,
                ]
            );

            if ((int) $lorry->default_driver_id !== (int) $driver->id) {
                $lorry->update(['default_driver_id' => $driver->id]);
            }

            foreach ($row['amounts'] as $index => $amount) {
                $quotation = $this->makeCodQuotation(
                    $numbering,
                    $branch,
                    $customer,
                    $manager,
                    $amount,
                    $row['driver'],
                    $index + 1,
                );

                $csns = $convert->execute($quotation, $manager, CsnBillingType::Cod->value);

                foreach ($csns as $csn) {
                    if ($csn->deliveryOrder()->exists()) {
                        continue;
                    }

                    $assign->execute($csn, $lorry, $today, $driver->id);
                    $created++;

                    if ($row['completed']) {
                        $csn->update(['payment_status' => PaymentStatus::CodCollected->value]);
                    }
                }
            }

            $jobSheet = JobSheet::query()
                ->where('lorry_id', $lorry->id)
                ->whereDate('operating_date', $today)
                ->first();

            if ($jobSheet) {
                $jobSheet->update([
                    'driver_id' => $driver->id,
                    'status' => $row['completed'] ? JobSheetStatus::Completed : JobSheetStatus::InTransit,
                    'checked_in_at' => $row['completed'] ? now()->subHours(1) : now()->subHours(3),
                ]);

                $this->command?->info(sprintf(
                    '%s | %s | %s | %d deliveries | RM %s | %s',
                    $row['driver'],
                    $this->formatPlate($row['lorry']),
                    $jobSheet->number,
                    count($row['amounts']),
                    number_format(array_sum($row['amounts']), 2),
                    $row['completed'] ? 'COMPLETED' : 'IN PROGRESS',
                ));
            }
        }

        $this->command?->info("COD listing demo seeding complete — {$created} COD assignment(s) for {$today}.");
        $this->command?->info('Open Billing → COD Listing to review.');
    }

    private function makeCodQuotation(
        DocumentNumberingService $numbering,
        Branch $branch,
        Customer $customer,
        User $manager,
        float $amount,
        string $driverName,
        int $sequence,
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
            'notes' => "COD listing demo — {$driverName} #{$sequence}",
            'confirmed_at' => now(),
            'created_by' => $manager->id,
        ]);

        $destination = QuotationDestination::query()->create([
            'quotation_id' => $quotation->id,
            'sequence' => 1,
            'consignee_name' => 'COD Drop '.$sequence,
            'consignee_pic' => 'PIC',
            'consignee_phone' => '019'.random_int(1000000, 9999999),
            'address' => "Lot {$sequence}, Jalan COD, KL",
            'postcode' => '50450',
            'state' => 'Wilayah Persekutuan',
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

    private function formatPlate(string $registration): string
    {
        if (preg_match('/^([A-Z]+)(\d+)$/', strtoupper($registration), $matches)) {
            return $matches[1].' '.$matches[2];
        }

        return strtoupper($registration);
    }
}

<?php

namespace Database\Seeders;

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\Quotation\Models\QuotationLine;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;

class ImportedPricingQuotationSeeder extends Seeder
{
    public function run(): void
    {
        $numbering = app(DocumentNumberingService::class);

        $klCompany = Company::query()->where('code', 'KL')->firstOrFail();
        $branch = $klCompany->branch;
        $manager = User::query()->where('email', 'manager.kl@og.local')->firstOrFail();

        $customers = Customer::query()
            ->where('company_id', $klCompany->id)
            ->orderBy('id')
            ->get();

        $quotations = [
            $this->buildTransportChargesQuote($customers->first()),
            $this->buildCharteredLorryQuote($customers->skip(1)->first()),
            $this->buildEquipmentQuote($customers->skip(2)->first() ?? $customers->last()),
        ];

        foreach ($quotations as $definition) {
            if (! $definition['customer']) {
                continue;
            }

            $lines = $definition['lines'];
            $subtotal = collect($lines)->sum('line_total');

            $quotation = Quotation::query()->create([
                'number' => $numbering->next($branch, DocumentType::Quotation),
                'company_id' => $klCompany->id,
                'branch_id' => $branch->id,
                'customer_id' => $definition['customer']->id,
                'salesperson_id' => $manager->id,
                'status' => $definition['status'],
                'pricing_source' => 'default',
                'valid_until' => now()->addDays(30),
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'notes' => $definition['notes'],
                'confirmed_at' => $definition['status'] === QuotationStatus::Confirmed ? now() : null,
                'created_by' => $manager->id,
            ]);

            $destinationIds = [];

            foreach ($definition['destinations'] as $index => $destination) {
                $pricingLocation = $destination['pricing_location'];
                unset($destination['pricing_location']);

                $record = QuotationDestination::query()->create([
                    'quotation_id' => $quotation->id,
                    'sequence' => $index + 1,
                    ...$destination,
                ]);

                $destinationIds[$pricingLocation] = $record->id;
            }

            foreach ($lines as $line) {
                QuotationLine::query()->create([
                    'quotation_id' => $quotation->id,
                    'quotation_destination_id' => $destinationIds[$line['pricing_location']],
                    'item_name' => $line['item_name'],
                    'uom' => $line['uom'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'handling_notes' => $line['handling_notes'] ?? null,
                ]);
            }

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => null,
                'to_status' => $quotation->status->value,
                'user_id' => $manager->id,
                'remarks' => 'Seeded from imported master pricing',
            ]);

            $this->command?->info("Created {$quotation->number} — {$definition['customer']->company_name} (MYR ".number_format((float) $subtotal, 2).')');
        }
    }

    /** @return array{customer: ?Customer, status: QuotationStatus, notes: string, destinations: list<array<string, mixed>>, lines: list<array<string, mixed>>} */
    private function buildTransportChargesQuote(?Customer $customer): array
    {
        $ctn = Uom::query()->where('name', 'CTN (Below 30kg)')->firstOrFail();
        $plt = Uom::query()->where('name', 'PLT <(1.2x1.2x1.2)M')->firstOrFail();

        $destinations = [
            $this->destination('Seremban Depot', 'Seremban', 'Negeri Sembilan', '70300', 'Seremban'),
            $this->destination('Melaka Warehouse', 'Melaka', 'Melaka', '75450', 'Melaka'),
            $this->destination('Johor Hub', 'Johor Bahru', 'Johor', '80000', 'Johor'),
        ];

        $lines = [
            $this->uomLine($ctn, 'Seremban', 25),
            $this->uomLine($ctn, 'Melaka', 25),
            $this->uomLine($ctn, 'Johor', 25),
            $this->uomLine($plt, 'Seremban', 3),
            $this->uomLine($plt, 'Melaka', 2),
            $this->uomLine($plt, 'Johor', 4),
        ];

        return [
            'customer' => $customer,
            'status' => QuotationStatus::Confirmed,
            'notes' => 'Standard transport charges from imported UOM rate card (CTN & pallet tiers).',
            'destinations' => $destinations,
            'lines' => $lines,
        ];
    }

    /** @return array{customer: ?Customer, status: QuotationStatus, notes: string, destinations: list<array<string, mixed>>, lines: list<array<string, mixed>>} */
    private function buildCharteredLorryQuote(?Customer $customer): array
    {
        $tenTon = CharteredLorry::query()->where('name', '20 FT LORRY (10 TON)')->firstOrFail();
        $fifteenTon = CharteredLorry::query()->where('name', '30 FT LORRY (15 TON)')->firstOrFail();

        $destinations = [
            $this->destination('Seremban Plant', 'Seremban', 'Negeri Sembilan', '70300', 'Seremban'),
            $this->destination('Melaka Factory', 'Melaka', 'Melaka', '75450', 'Melaka'),
            $this->destination('JB Distribution', 'Johor Bahru', 'Johor', '80000', 'JB'),
        ];

        $lines = [
            $this->charteredLine($tenTon, 'Seremban', 1),
            $this->charteredLine($fifteenTon, 'Seremban', 1),
            $this->charteredLine($tenTon, 'Melaka', 1),
            $this->charteredLine($fifteenTon, 'Melaka', 1),
            $this->charteredLine($tenTon, 'JB', 1),
            $this->charteredLine($fifteenTon, 'JB', 1),
        ];

        return [
            'customer' => $customer,
            'status' => QuotationStatus::Sent,
            'notes' => 'Chartered lorry rates from imported lorry master (10T & 15T).',
            'destinations' => $destinations,
            'lines' => $lines,
        ];
    }

    /** @return array{customer: ?Customer, status: QuotationStatus, notes: string, destinations: list<array<string, mixed>>, lines: list<array<string, mixed>>} */
    private function buildEquipmentQuote(?Customer $customer): array
    {
        $ladder3 = Item::query()->where('name', 'HD - Platform Ladder 3 Steps')->firstOrFail();
        $ladder6 = Item::query()->where('name', 'HD - Platform Ladder 6 Steps')->firstOrFail();
        $fiberglass = Item::query()->where('name', 'Fiberglass Platform Trolly Ladder - 3 Steps With Yellow Safety Gate')->firstOrFail();

        $destinations = [
            $this->destination('Melaka Site', 'Melaka', 'Melaka', '75450', 'Melaka'),
            $this->destination('Johor Site', 'Pasir Gudang', 'Johor', '81700', 'Johor'),
        ];

        $lines = [
            $this->itemLine($ladder3, 'Melaka', 2),
            $this->itemLine($ladder6, 'Melaka', 1),
            $this->itemLine($fiberglass, 'Melaka', 1),
            $this->itemLine($ladder3, 'Johor', 3),
            $this->itemLine($ladder6, 'Johor', 2),
        ];

        return [
            'customer' => $customer,
            'status' => QuotationStatus::Draft,
            'notes' => 'Equipment delivery quote using imported transport item rates.',
            'destinations' => $destinations,
            'lines' => $lines,
        ];
    }

    /** @return array<string, mixed> */
    private function destination(
        string $consignee,
        string $city,
        string $state,
        string $postcode,
        string $pricingLocation,
    ): array {
        return [
            'consignee_name' => $consignee,
            'consignee_pic' => 'Site PIC',
            'consignee_phone' => '012-'.random_int(3000000, 9999999),
            'address' => "Lot ".random_int(1, 99).", Jalan Industri, {$postcode} {$city}, {$state}",
            'postcode' => $postcode,
            'state' => $state,
            'city' => $city,
            'pricing_location' => $pricingLocation,
        ];
    }

    /** @return array<string, mixed> */
    private function uomLine(Uom $uom, string $locationName, float $quantity): array
    {
        $unitPrice = $this->uomUnitPrice($uom, $locationName, $quantity);

        return [
            'pricing_location' => $locationName,
            'item_name' => $uom->name,
            'uom' => $uom->code,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function itemLine(Item $item, string $locationName, float $quantity): array
    {
        $unitPrice = $this->itemUnitPrice($item, $locationName);

        return [
            'pricing_location' => $locationName,
            'item_name' => $item->name,
            'uom' => 'UNIT',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function charteredLine(CharteredLorry $lorry, string $locationName, float $quantity): array
    {
        $unitPrice = $this->charteredUnitPrice($lorry, $locationName);

        return [
            'pricing_location' => $locationName,
            'item_name' => $lorry->name,
            'uom' => 'TRIP',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'handling_notes' => 'Chartered lorry',
        ];
    }

    private function uomUnitPrice(Uom $uom, string $locationName, float $quantity): float
    {
        $locationId = Location::query()->where('name', $locationName)->value('id');

        $tier = UomRateTier::query()
            ->where('uom_id', $uom->id)
            ->where('location_id', $locationId)
            ->where('min_qty', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
            })
            ->orderByDesc('min_qty')
            ->first();

        return (float) ($tier?->price ?? 0);
    }

    private function itemUnitPrice(Item $item, string $locationName): float
    {
        return (float) $item->rates()
            ->whereHas('location', fn ($query) => $query->where('name', $locationName))
            ->value('price') ?? 0;
    }

    private function charteredUnitPrice(CharteredLorry $lorry, string $locationName): float
    {
        return (float) $lorry->rates()
            ->whereHas('location', fn ($query) => $query->where('name', $locationName))
            ->value('price') ?? 0;
    }
}

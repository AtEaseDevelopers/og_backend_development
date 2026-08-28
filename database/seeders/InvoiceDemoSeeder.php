<?php

namespace Database\Seeders;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\InvoiceLine;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Location;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Services\DocumentNumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Invoice::query()->exists()) {
            $this->command?->info('Invoices already exist — skipping InvoiceDemoSeeder.');

            return;
        }

        $this->seedLocations();

        $invoicedCsnIds = InvoiceLine::query()
            ->whereNotNull('consignment_note_id')
            ->pluck('consignment_note_id');

        $csns = ConsignmentNote::query()
            ->with(['customer', 'sourceBranch', 'deliveryOrder'])
            ->where('billing_type', CsnBillingType::Term)
            ->where('status', CsnStatus::Delivered)
            ->when($invoicedCsnIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $invoicedCsnIds))
            ->orderBy('id')
            ->get();

        if ($csns->isEmpty()) {
            $this->command?->warn('No delivered term CSNs found. Run DemoOperationsSeeder first.');

            return;
        }

        $this->enrichCsnsForPdf($csns);

        $groups = $csns->groupBy(
            fn (ConsignmentNote $csn) => $csn->company_id.'-'.$csn->source_branch_id.'-'.$csn->customer_id
        );

        $numbering = app(DocumentNumberingService::class);
        $created = 0;

        DB::transaction(function () use ($groups, $numbering, &$created): void {
            foreach ($groups as $groupCsns) {
                /** @var ConsignmentNote $first */
                $first = $groupCsns->first();
                $customer = $first->customer;
                $branch = $first->sourceBranch;

                if (! $customer || ! $branch) {
                    continue;
                }

                $subtotal = (float) $groupCsns->sum('subtotal');
                $tax = (float) $groupCsns->sum('tax_amount');
                $rawTotal = $subtotal + $tax;
                $rounded = round($rawTotal, 2);
                $rounding = round($rounded - $rawTotal, 2);

                $dueDate = $customer->credit_term_days > 0
                    ? now()->addDays($customer->credit_term_days)->toDateString()
                    : now()->toDateString();

                $invoice = Invoice::query()->create([
                    'number' => $numbering->next($branch, DocumentType::Invoice),
                    'company_id' => $first->company_id,
                    'source_branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'type' => 'term',
                    'billing_month' => now()->format('Y-m'),
                    'status' => InvoiceStatus::Outstanding->value,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'rounding_amount' => $rounding,
                    'total_amount' => $rounded,
                    'invoice_date' => now()->toDateString(),
                    'due_date' => $dueDate,
                ]);

                foreach ($groupCsns as $csn) {
                    $invoice->lines()->create([
                        'consignment_note_id' => $csn->id,
                        'delivery_order_id' => $csn->deliveryOrder?->id,
                        'description' => 'CSN '.$csn->number.' — '.($csn->consignee_name ?: $csn->delivery_city ?: 'Delivery'),
                        'amount' => $csn->total_amount,
                    ]);
                }

                $created++;
                $this->command?->info("Created {$invoice->number} for {$customer->company_name} ({$groupCsns->count()} CSN line(s), RM ".number_format($rounded, 2).')');
            }
        });

        $this->command?->info("Invoice demo seeding complete — {$created} invoice(s) created.");
        $this->command?->info('Open Billing → Invoices and click PDF to preview.');
    }

    private function seedLocations(): void
    {
        $locations = [
            ['code' => 'SGR', 'name' => 'Selangor'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'JHR', 'name' => 'Johor'],
            ['code' => 'PNG', 'name' => 'Penang'],
            ['code' => 'KL', 'name' => 'Kuala Lumpur'],
        ];

        foreach ($locations as $location) {
            Location::query()->firstOrCreate(
                ['code' => $location['code']],
                ['name' => $location['name'], 'is_active' => true],
            );
        }
    }

    /** @param Collection<int, ConsignmentNote> $csns */
    private function enrichCsnsForPdf($csns): void
    {
        $selangor = Location::query()->where('code', 'SGR')->value('id');
        $singapore = Location::query()->where('code', 'SG')->value('id');
        $johor = Location::query()->where('code', 'JHR')->value('id');
        $penang = Location::query()->where('code', 'PNG')->value('id');
        $kl = Location::query()->where('code', 'KL')->value('id');

        $routes = [
            [$selangor, $singapore, 'INV802563', ['INV802564', 'INV802565']],
            [$selangor, $johor, 'INV802566', []],
            [$kl, $penang, 'INV802567', ['INV802568']],
            [$johor, $selangor, 'INV802569', []],
        ];

        foreach ($csns->values() as $index => $csn) {
            [$fromId, $toId, $reference, $otherDos] = $routes[$index % count($routes)];

            $csn->update([
                'from_location_id' => $fromId,
                'to_location_id' => $toId,
                'customer_reference' => $reference,
                'other_do_numbers' => $otherDos !== [] ? $otherDos : null,
                'issued_at' => $csn->issued_at ?: $csn->deliveryOrder?->delivered_at?->toDateString() ?: now()->subDays(3)->toDateString(),
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Domains\Billing\Actions\RecordPayment;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Enums\CsnBillingType;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'admin@og.local')->first()
            ?? User::query()->first();

        if (! $actor) {
            $this->command?->warn('No users found — skipping PaymentDemoSeeder.');

            return;
        }

        $recordPayment = app(RecordPayment::class);
        $created = 0;

        $cashBillCsns = ConsignmentNote::query()
            ->where('billing_type', CsnBillingType::CashBill)
            ->whereNotIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::CodCollected->value])
            ->whereDoesntHave('payments')
            ->with('sourceBranch')
            ->limit(2)
            ->get();

        foreach ($cashBillCsns as $csn) {
            if (Payment::query()->where('consignment_note_id', $csn->id)->exists()) {
                continue;
            }

            $recordPayment->execute([
                'source_branch_id' => $csn->source_branch_id,
                'consignment_note_id' => $csn->id,
                'customer_id' => $csn->customer_id,
                'amount' => $csn->total_amount,
                'method' => 'cash',
            ], $actor);

            $created++;
            $this->command?->info("Cash bill payment for {$csn->number}");
        }

        $invoice = Invoice::query()->with('customer', 'sourceBranch')->first();

        if ($invoice && ! Payment::query()->where('invoice_id', $invoice->id)->exists()) {
            $recordPayment->execute([
                'source_branch_id' => $invoice->source_branch_id,
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'amount' => min(300, (float) $invoice->total_amount),
                'method' => 'bank_transfer',
            ], $actor);

            $created++;
            $this->command?->info("Term billing payment for invoice {$invoice->number}");
        }

        $this->command?->info("Payment demo seeding complete — {$created} payment(s) created.");
        $this->command?->info('Open Billing → Payments & Receipts to review the listing.');
    }
}

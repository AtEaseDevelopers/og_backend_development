<?php

use App\Domains\Quotation\Actions\ReleaseCreditGate;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off fix: an order whose payment term was changed from Credit / Term to Cash / COD before the customer
 * confirmed still went to credit approval (the check looked at the customer, not the order's payment term) and
 * was stuck there (no payment, "Review credit approval"). Such orders are confirmed with their payment term and
 * the pending credit request is cancelled (see ReleaseCreditGate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Quotation::query()
            ->where('status', QuotationStatus::PendingApproval->value)
            ->get()
            ->each(function (Quotation $quotation): void {
                try {
                    app(ReleaseCreditGate::class)->execute($quotation, null, 'payment term is not Credit / Term (fix of 10 Oct 2026)');
                } catch (Throwable $e) {
                    report($e);
                }
            });
    }

    public function down(): void
    {
        // the released orders stay confirmed
    }
};

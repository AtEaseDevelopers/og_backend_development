<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;

/**
 * Section E: once the quotation is accepted, generate the Proforma Invoice on the same
 * main record (no separate Sales Order). Idempotent per order.
 */
class GenerateProformaForQuotation
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function execute(Quotation $quotation): ProformaInvoice
    {
        $existing = ProformaInvoice::query()->where('quotation_id', $quotation->id)->latest('id')->first();

        if ($existing) {
            return $existing;
        }

        $quotation->loadMissing(['branch', 'customer']);

        return DB::transaction(fn () => ProformaInvoice::query()->create([
            'number' => $this->numbering->next($quotation->branch, DocumentType::Proforma),
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'company_id' => $quotation->company_id,
            'source_branch_id' => $quotation->branch_id,
            'total_amount' => $quotation->total_amount,
            'paid_amount' => 0,
            'status' => 'issued',
            'issued_at' => now(),
            'payment_instructions' => $this->paymentInstructions(),
        ]));
    }

    public static function paymentInstructions(): string
    {
        $accounts = collect(config('og.invoice.bank_accounts', []))
            ->map(fn (array $account) => $account['bank'].' — '.$account['account'])
            ->implode("\n");

        return trim(
            "Please make payment to the account(s) below and upload your payment proof in the Customer Portal.\n"
            .($accounts !== '' ? $accounts : 'Bank details will be provided by our counter.')
        );
    }
}

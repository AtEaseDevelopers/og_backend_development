<?php

namespace App\Domains\Consignment\Actions;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCsn
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private GenerateProformaInvoice $proforma,
    ) {}

    public function execute(array $data, User $actor): ConsignmentNote
    {
        return DB::transaction(function () use ($data, $actor) {
            $branch = Branch::query()->findOrFail($data['source_branch_id']);
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $billingType = $data['billing_type'] ?? CsnBillingType::CashBill->value;
            $subtotal = collect($lines)->sum(fn ($line) => (float) ($line['line_total'] ?? 0));

            $csn = ConsignmentNote::query()->create([
                ...$data,
                'billing_type' => $billingType,
                'number' => $this->numbering->next($branch, DocumentType::Csn),
                'status' => $data['status'] ?? CsnStatus::Confirmed->value,
                'payment_status' => $data['payment_status'] ?? match ($billingType) {
                    CsnBillingType::Term->value => PaymentStatus::Credit->value,
                    CsnBillingType::Cod->value => PaymentStatus::CodPending->value,
                    default => PaymentStatus::Unpaid->value,
                },
                'customer_name' => $customer->company_name,
                'customer_brn' => $customer->brn,
                'customer_tin' => $customer->tin,
                'consignor_address' => $data['consignor_address'] ?? $customer->address,
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + (float) ($data['tax_amount'] ?? 0),
                'qr_token' => (string) Str::uuid(),
                'tracking_token' => Str::random(40),
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $csn->lines()->create($line);
            }

            if ($billingType === CsnBillingType::Cod->value) {
                $this->proforma->execute($csn);
            }

            return $csn->load(['lines', 'customer', 'sourceBranch', 'proformaInvoice']);
        });
    }
}

<?php

namespace App\Domains\Integration\Actions;

use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Domains\MasterData\Models\Customer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateEinvoiceBuyerInfo
{
    public function execute(EinvoiceSubmission $submission, array $buyer, bool $persistToCustomer = true): EinvoiceSubmission
    {
        if (blank($buyer['name'] ?? null)) {
            throw new InvalidArgumentException('Buyer name is required.');
        }

        return DB::transaction(function () use ($submission, $buyer, $persistToCustomer) {
            $submission->update([
                'buyer_info' => array_merge($submission->buyer_info ?? [], $buyer),
                'status' => in_array($submission->status, ['valid', 'failed'], true)
                    ? $submission->status
                    : 'ready',
            ]);

            if ($persistToCustomer) {
                $customer = $submission->invoice?->customer;
                if ($customer instanceof Customer) {
                    $customer->update([
                        'einvoice_buyer_name' => $buyer['name'] ?? $customer->einvoice_buyer_name,
                        'einvoice_tin' => $buyer['tin'] ?? $customer->einvoice_tin,
                        'einvoice_id_type' => $buyer['id_type'] ?? $customer->einvoice_id_type,
                        'einvoice_id_value' => $buyer['id_value'] ?? $customer->einvoice_id_value,
                        'einvoice_address' => $buyer['address'] ?? $customer->einvoice_address,
                        'tin' => $buyer['tin'] ?? $customer->tin,
                    ]);
                }
            }

            return $submission->fresh(['invoice.customer']);
        });
    }

    public function executeByToken(string $token, array $buyer): EinvoiceSubmission
    {
        $submission = EinvoiceSubmission::query()->where('buyer_token', $token)->firstOrFail();

        return $this->execute($submission, $buyer, true);
    }
}

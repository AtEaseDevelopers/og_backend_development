<?php

namespace App\Domains\Integration\Actions;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Domains\Integration\Models\SyncLog;
use App\Domains\Integration\Services\MyInvoisClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubmitEinvoice
{
    public function __construct(private MyInvoisClient $client) {}

    public function execute(Invoice $invoice, ?User $actor = null, string $mode = 'manual', ?array $buyerOverride = null): EinvoiceSubmission
    {
        $invoice->loadMissing('customer', 'sourceBranch');

        $buyer = $buyerOverride ?? $this->buyerFromCustomer($invoice);

        if (blank($buyer['tin'] ?? null) && blank($buyer['brn'] ?? null)) {
            throw new InvalidArgumentException('Buyer TIN/BRN is required before MyInvois submission.');
        }

        return DB::transaction(function () use ($invoice, $actor, $mode, $buyer) {
            $submission = EinvoiceSubmission::query()->firstOrNew(['invoice_id' => $invoice->id]);

            if ($submission->exists && $submission->status === 'valid' && $submission->uuid) {
                return $submission;
            }

            if (! $submission->buyer_token) {
                $submission->buyer_token = Str::random(40);
            }

            $submission->fill([
                'buyer_info' => $buyer,
                'submission_mode' => $mode,
                'status' => 'submitting',
            ])->save();

            $payload = [
                'invoice_number' => $invoice->number,
                'branch' => $invoice->sourceBranch?->code,
                'total' => (float) $invoice->total_amount,
                'buyer' => $buyer,
            ];

            $result = $this->client->submit($payload);

            $submission->update([
                'status' => $result['ok'] ? 'valid' : 'failed',
                'uuid' => $result['uuid'],
                'validated_pdf_path' => $result['pdf_path'],
                'response_payload' => $result['raw'],
                'retry_count' => (int) $submission->retry_count + 1,
                'submitted_at' => $result['ok'] ? now() : $submission->submitted_at,
                'email_sent_at' => $result['ok'] && $invoice->customer?->email ? now() : null,
            ]);

            SyncLog::query()->create([
                'source_branch_id' => $invoice->source_branch_id,
                'integration' => 'myinvois',
                'document_type' => 'invoice',
                'document_id' => $invoice->id,
                'external_ref' => $result['uuid'],
                'status' => $result['ok'] ? 'synced' : 'failed',
                'retry_count' => (int) $submission->retry_count,
                'error_message' => $result['ok'] ? null : $result['message'],
                'payload' => $result['raw'],
                'synced_by' => $actor?->id,
                'synced_at' => $result['ok'] ? now() : null,
            ]);

            return $submission->fresh('invoice');
        });
    }

    public function ensureBuyerLink(Invoice $invoice): EinvoiceSubmission
    {
        $submission = EinvoiceSubmission::query()->firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'status' => 'pending_buyer',
                'buyer_token' => Str::random(40),
                'buyer_info' => $this->buyerFromCustomer($invoice->loadMissing('customer')),
            ]
        );

        if (! $submission->buyer_token) {
            $submission->update(['buyer_token' => Str::random(40)]);
        }

        return $submission->fresh();
    }

    /** @return array<string, mixed> */
    private function buyerFromCustomer(Invoice $invoice): array
    {
        $c = $invoice->customer;

        return [
            'name' => $c?->einvoice_buyer_name ?: $c?->company_name,
            'tin' => $c?->einvoice_tin ?: $c?->tin,
            'brn' => $c?->brn,
            'id_type' => $c?->einvoice_id_type ?: 'BRN',
            'id_value' => $c?->einvoice_id_value ?: $c?->brn,
            'address' => $c?->einvoice_address ?: $c?->address,
            'email' => $c?->email,
            'phone' => $c?->phone,
        ];
    }
}

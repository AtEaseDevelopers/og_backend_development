<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConvertQuotationToCsns
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private GenerateProformaInvoice $proforma,
    ) {}

    /** @return Collection<int, ConsignmentNote> */
    public function execute(Quotation $quotation, User $actor, string $billingType = 'cash_bill'): Collection
    {
        if ($quotation->status !== QuotationStatus::Confirmed && $quotation->status !== QuotationStatus::Accepted) {
            throw new InvalidArgumentException('Only confirmed or accepted quotations can be converted.');
        }

        if ($quotation->destinations()->count() === 0) {
            throw new InvalidArgumentException('Quotation has no destinations to convert.');
        }

        return DB::transaction(function () use ($quotation, $actor, $billingType) {
            $quotation->load(['destinations', 'lines', 'customer', 'branch']);
            $notes = collect();

            foreach ($quotation->destinations as $destination) {
                $destinationLines = $quotation->lines
                    ->where('quotation_destination_id', $destination->id);

                if ($destinationLines->isEmpty()) {
                    $destinationLines = $quotation->lines;
                }

                $subtotal = $destinationLines->sum('line_total');

                $csn = ConsignmentNote::query()->create([
                    'number' => $this->numbering->next($quotation->branch, DocumentType::Csn),
                    'company_id' => $quotation->company_id,
                    'source_branch_id' => $quotation->branch_id,
                    'quotation_id' => $quotation->id,
                    'quotation_destination_id' => $destination->id,
                    'customer_id' => $quotation->customer_id,
                    'billing_type' => $billingType,
                    'status' => CsnStatus::Confirmed,
                    'payment_status' => match ($billingType) {
                        CsnBillingType::Term->value => PaymentStatus::Credit->value,
                        CsnBillingType::Cod->value => PaymentStatus::CodPending->value,
                        default => PaymentStatus::Unpaid->value,
                    },
                    'customer_name' => $quotation->customer->company_name,
                    'customer_brn' => $quotation->customer->brn,
                    'customer_tin' => $quotation->customer->tin,
                    'consignor_address' => $quotation->customer->address,
                    'consignee_name' => $destination->consignee_name,
                    'consignee_pic' => $destination->consignee_pic,
                    'consignee_phone' => $destination->consignee_phone,
                    'delivery_address' => $destination->address,
                    'delivery_postcode' => $destination->postcode,
                    'delivery_state' => $destination->state,
                    'delivery_city' => $destination->city,
                    'subtotal' => $subtotal,
                    'total_amount' => $subtotal,
                    'qr_token' => (string) Str::uuid(),
                    'tracking_token' => Str::random(40),
                    'created_by' => $actor->id,
                ]);

                foreach ($destinationLines as $line) {
                    $csn->lines()->create([
                        'item_name' => $line->item_name,
                        'uom' => $line->uom,
                        'quantity' => $line->quantity,
                        'weight' => $line->weight,
                        'dimensions' => $line->dimensions,
                        'unit_price' => $line->unit_price,
                        'line_total' => $line->line_total,
                    ]);
                }

                if ($billingType === CsnBillingType::Cod->value) {
                    $this->proforma->execute($csn);
                }

                $notes->push($csn);
            }

            $from = $quotation->status->value;
            $quotation->update([
                'status' => QuotationStatus::Converted,
                'converted_at' => now(),
            ]);

            QuotationStatusLog::query()->create([
                'quotation_id' => $quotation->id,
                'from_status' => $from,
                'to_status' => QuotationStatus::Converted->value,
                'user_id' => $actor->id,
                'remarks' => 'Converted to '.$notes->count().' CSN(s)',
            ]);

            return $notes;
        });
    }
}

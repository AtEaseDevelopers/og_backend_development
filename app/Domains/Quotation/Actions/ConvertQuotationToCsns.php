<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Billing\Actions\GenerateProformaInvoice;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\User;
use App\Support\CsnDocumentNumbers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates one CSN per destination of a confirmed order (section H).
 *
 * In the new flow this runs automatically from GenerateOrderBilling after the Invoice /
 * Cash Bill succeeded, so the CSN starts at "Pending Lorry Assignment" and carries the
 * salesperson, SA prefix, order type, invoice / proforma numbers, DO number, transfer
 * code (if any), destination and service types.
 */
class ConvertQuotationToCsns
{
    public function __construct(
        private GenerateProformaInvoice $proforma,
        private CsnDocumentNumbers $documentNumbers,
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
            $quotation->load(['destinations', 'lines', 'customer', 'branch', 'saLocation', 'salesperson', 'invoices', 'proformaInvoice']);
            $notes = collect();

            $orderType = $quotation->orderType() ?? OrderType::fromBillingType($billingType);
            $billingType = $orderType?->billingType()->value ?? $billingType;
            $saLocation = $quotation->saLocation ?? $quotation->salesperson?->saLocation;
            $csnPrefix = $saLocation?->csn_prefix;
            $latestInvoice = $quotation->invoices->sortByDesc('id')->first();
            $orderProforma = $quotation->proformaInvoice;
            $destinationTypes = collect($quotation->destination_types ?? []);

            foreach ($quotation->destinations as $destination) {
                $destinationLines = $quotation->lines
                    ->where('quotation_destination_id', $destination->id);

                if ($destinationLines->isEmpty()) {
                    $destinationLines = $quotation->lines;
                }

                $subtotal = $destinationLines->sum('line_total');
                $typeSetting = $destinationTypes->first(fn ($row) => ($row['column'] ?? null) === $destination->consignee_name);

                $csnData = $this->documentNumbers->assign([
                    'company_id' => $quotation->company_id,
                    'source_branch_id' => $quotation->branch_id,
                    'quotation_id' => $quotation->id,
                    'quotation_destination_id' => $destination->id,
                    'customer_id' => $quotation->customer_id,
                    'salesperson_id' => $quotation->salesperson_id,
                    'sa_location_id' => $saLocation?->id,
                    'sa_prefix' => $csnPrefix,
                    'order_type' => $orderType?->value,
                    'billing_type' => $billingType,
                    'invoice_number' => $latestInvoice?->number,
                    'proforma_number' => $orderProforma?->number,
                    'customer_do_number' => $quotation->customer_do_number,
                    'service_type' => $destination->service_type ?? ($typeSetting['service_type'] ?? $quotation->service_type?->value),
                    'drop_off_type' => $destination->drop_off_type ?? ($typeSetting['drop_off_type'] ?? null),
                    'status' => CsnStatus::PendingAssignment,
                    'payment_status' => $this->paymentStatus($quotation, $billingType),
                    'customer_name' => $quotation->customer->company_name,
                    'customer_brn' => $quotation->customer->brn,
                    'customer_tin' => $quotation->customer->tin,
                    'customer_phone' => $quotation->customer->phone,
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
                    'issued_at' => now()->toDateString(),
                    'qr_token' => (string) Str::uuid(),
                    'tracking_token' => Str::random(40),
                    'created_by' => $actor->id,
                ], $quotation->branch, $csnPrefix);

                $csn = ConsignmentNote::query()->create($csnData);

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

                // Legacy path (manual convert without an order-level proforma): COD proforma per CSN
                if ($billingType === CsnBillingType::Cod->value && ! $orderProforma) {
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
                'remarks' => 'CSN created: '.$notes->pluck('number')->implode(', ').' (Pending Lorry Assignment)',
            ]);

            return $notes;
        });
    }

    private function paymentStatus(Quotation $quotation, string $billingType): string
    {
        if ($billingType === CsnBillingType::Term->value) {
            return PaymentStatus::Credit->value;
        }

        if ($billingType === CsnBillingType::Cod->value) {
            return PaymentStatus::CodPending->value;
        }

        if ($quotation->isFullyPaid()) {
            return PaymentStatus::Paid->value;
        }

        return (float) $quotation->paid_amount > 0 ? PaymentStatus::Partial->value : PaymentStatus::Unpaid->value;
    }
}

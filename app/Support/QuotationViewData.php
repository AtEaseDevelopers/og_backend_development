<?php

namespace App\Support;

use App\Domains\Quotation\Models\Quotation;
use App\Filament\Resources\CustomerResource;
use Filament\Facades\Filament;

class QuotationViewData
{
    public function for(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'company',
            'customer',
            'salesperson',
            'fromLocation',
            'toLocation',
            'destinations',
            'lines.destination',
        ]);

        $matrixState = app(QuotationMatrix::class)->toFormState($quotation);
        $destinations = $matrixState['matrix_columns'];
        $historyDestination = $quotation->consignee_name
            ?: $quotation->toLocation?->name
            ?: ($destinations[0] ?? null);

        $history = app(CustomerQuotationPriceHistory::class);
        $panel = app(QuotationHistoryPanel::class);

        return [
            'quotation' => $quotation,
            'summary' => $this->summary($quotation),
            'information' => $this->information($quotation),
            'consignor' => $this->consignor($quotation),
            'pricing' => [
                'history' => $history->previousQuotationPrices(
                    $quotation->customer_id,
                    null,
                    null,
                    $historyDestination,
                    Filament::getTenant()?->code,
                ),
                'special' => $panel->specialPrices($quotation->customer_id, $historyDestination),
                'master' => [
                    'destinations' => $destinations,
                    'rows' => $panel->defaultPricesForAllMeasurements($destinations),
                ],
            ],
            'details' => [
                'destinations' => $destinations,
                'matrix' => app(QuotationMatrix::class)->preview(
                    $destinations,
                    $matrixState['matrix_rows'],
                ),
                'rows' => $matrixState['matrix_rows'],
                'total_amount' => (float) $quotation->total_amount,
                'footnotes' => $quotation->notes,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function summary(Quotation $quotation): array
    {
        return [
            'number' => $quotation->number,
            'quoted_at' => $quotation->quoted_at?->format('d/m/Y'),
            'valid_until' => $quotation->valid_until?->format('d/m/Y'),
            'expected_delivery_date' => $quotation->expected_delivery_date?->format('d/m/Y'),
            'salesperson' => $quotation->salesperson?->name,
            'is_active' => (bool) $quotation->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function information(Quotation $quotation): array
    {
        return [
            'title' => $quotation->title,
            'salesperson' => $quotation->salesperson?->name,
            'terms_of_payment' => $quotation->terms_of_payment,
            'issued_by_name' => $quotation->issued_by_name,
            'attention' => $quotation->attention,
            'customer_phone_alt' => $quotation->customer_phone_alt,
            'customer_fax' => $quotation->customer_fax,
        ];
    }

    /** @return array<string, mixed> */
    private function consignor(Quotation $quotation): array
    {
        $customer = $quotation->customer;

        return [
            'consignor' => $customer
                ? trim(($customer->code ? $customer->code.' — ' : '').$customer->company_name)
                : null,
            'consignor_url' => $customer
                ? CustomerResource::getUrl('view', ['record' => $customer], true, null, $quotation->company)
                : null,
            'from' => $quotation->fromLocation?->name,
            'company_number' => $quotation->consignor_brn ?: $customer?->brn,
            'billing_address' => $quotation->customer_address ?: $customer?->address,
            'pickup_location' => $quotation->pickup_location,
            'pickup_location_detail' => $quotation->pickup_location,
        ];
    }
}

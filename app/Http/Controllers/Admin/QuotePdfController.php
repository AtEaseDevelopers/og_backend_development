<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quote;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\OrderMinimumCharge;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a quotation of Operations → Quotation (user, 20 Oct 2026), in the earlier quotation layout
 * (pdf/quotation): letterhead, Ref No. / date, the customer or lead, every product per destination with its quoted
 * price, the minimum charge top-ups and the total.
 */
class QuotePdfController
{
    public function __invoke(string $tenant, int|string $quote): Response
    {
        abort_unless(Filament::auth()->user(), 403);

        $record = Quote::query()->with(['customer', 'lead', 'branch'])->findOrFail($quote);
        $companyId = CurrentCompany::id();
        abort_if($companyId && (int) $record->company_id !== (int) $companyId, 404);

        $form = (array) $record->form;
        $customer = $record->customer;
        $lead = $record->lead;

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $record,
            'company' => ($record->company_id ? Company::query()->find($record->company_id) : null) ?? $record->branch?->defaultCompany(),
            'salesperson' => filled($form['salesperson_id'] ?? null) ? User::query()->find($form['salesperson_id']) : null,
            'party' => [
                'name' => $customer?->company_name ?? $lead?->name ?? '—',
                'address' => filled($form['customer_address'] ?? null) ? $form['customer_address'] : ($customer?->address ?? $lead?->billing_address ?? $lead?->address),
                'email' => $customer?->email ?? $lead?->email,
                'phone' => filled($form['customer_pic_phone'] ?? null) ? $form['customer_pic_phone'] : ($customer?->phone ?? $lead?->contact),
                'fax' => $customer?->fax,
                'attention' => filled($form['customer_pic_name'] ?? null) ? $form['customer_pic_name'] : ($customer?->attention ?? $lead?->pic_name),
                'credit_term_days' => $customer?->credit_term_days,
            ],
            'blocks' => $this->blocks($record),
        ])->setPaper('a4');

        return $pdf->stream($record->number.'.pdf');
    }

    /**
     * Each consignor & consignee block: its destination, products (qty, quoted price, amount) and minimum charge top-up.
     *
     * @return list<array{destination: string, consignee: ?string, lines: list<array{item: string, uom: ?string, qty: float, price: ?float, amount: ?float}>, minimum: ?array, total: float}>
     */
    private function blocks(Quote $quote): array
    {
        $locations = Location::query()->whereIn('id', collect($quote->pairs)->pluck('to_location_id')->filter()->all())->pluck('name', 'id');
        $blocks = [];

        foreach (array_values((array) $quote->pairs) as $pair) {
            $lines = [];

            foreach ($pair['items'] ?? [] as $item) {
                if (blank($item['item_name'] ?? null)) {
                    continue;
                }

                $qty = max(0.01, (float) (($item['quantity'] ?? 1) ?: 1));
                $price = is_numeric($item['manual_price'] ?? null)
                    ? round((float) $item['manual_price'], 2)
                    : (($item['unit_price'] ?? null) !== null ? round((float) $item['unit_price'], 2) : null);

                $lines[] = [
                    'item' => (string) $item['item_name'],
                    'uom' => filled($item['uom'] ?? null) ? (string) $item['uom'] : null,
                    'qty' => $qty,
                    'price' => $price,
                    'amount' => $price !== null ? round($price * $qty, 2) : null,
                ];
            }

            $location = $locations[$pair['to_location_id'] ?? 0] ?? null;
            // the top-up to the price list's minimum charge, as on Create quotation (default prices)
            $minimum = $location && $lines !== [] ? app(OrderMinimumCharge::class)->forDestination(
                null,
                $location,
                ($pair['drop_off_type'] ?? '') ?: null,
                $pair['service_type'] ?? null,
                array_map(fn (array $line) => ['item_name' => $line['item'], 'quantity' => $line['qty'], 'line_total' => (float) ($line['amount'] ?? 0), 'priced' => $line['price'] !== null], $lines),
            ) : null;
            $minimum = $minimum && (float) ($minimum['top_up'] ?? 0) > 0 ? $minimum : null;

            $blocks[] = [
                'destination' => $location ?? '—',
                'consignee' => filled($pair['consignee_name'] ?? null) ? (string) $pair['consignee_name'] : null,
                'lines' => $lines,
                'minimum' => $minimum,
                'total' => round(collect($lines)->sum(fn (array $line) => (float) ($line['amount'] ?? 0)) + (float) ($minimum['top_up'] ?? 0), 2),
            ];
        }

        return $blocks;
    }
}

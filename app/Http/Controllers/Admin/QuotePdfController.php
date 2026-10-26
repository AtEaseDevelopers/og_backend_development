<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quote;
use App\Models\User;
use App\Enums\ServiceType;
use App\Support\CurrentCompany;
use App\Support\OrderMinimumCharge;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF of a quotation of Operations → Quotation, in the client's quotation format (user, 26 Oct 2026: as their
 * "Quotation of Transportation Charges"): letterhead, Ref No. / Page No. / QUOTATION, the customer with date and
 * issued by, then rate tables in a typewriter style — per product its quantity ranges against the destinations
 * (RM 14-00), flat-rate products (chartered lorries, …) as one table of products against destinations — the minimum
 * charges and the notes.
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
            'blocks' => $blocks = $this->blocks($record),
            'tables' => $this->rateTables($record),
            'minimums' => $this->minimumNotes($record, $blocks),
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
            // the price list's minimum charge (as on Create quotation): with a pickup location the pick-up minimum applies
            $minimum = $location && $lines !== [] ? app(OrderMinimumCharge::class)->forDestination(
                null,
                $location,
                ($pair['drop_off_type'] ?? '') ?: null,
                filled($pair['from_location_id'] ?? null) ? ServiceType::Pickup->value : ServiceType::Store->value,
                array_map(fn (array $line) => ['item_name' => $line['item'], 'quantity' => $line['qty'], 'line_total' => (float) ($line['amount'] ?? 0), 'priced' => $line['price'] !== null], $lines),
            ) : null;
            $minimum = $minimum && (float) ($minimum['top_up'] ?? 0) > 0 ? $minimum : null;

            $blocks[] = [
                'destination' => $location ?? '—',
                'consignee' => filled($pair['from_location_id'] ?? null) && ($from = Location::query()->whereKey($pair['from_location_id'])->value('name'))
                    ? 'from '.$from
                    : (filled($pair['consignee_name'] ?? null) ? (string) $pair['consignee_name'] : null),
                'lines' => $lines,
                'minimum' => $minimum,
                'total' => round(collect($lines)->sum(fn (array $line) => (float) ($line['amount'] ?? 0)) + (float) ($minimum['top_up'] ?? 0), 2),
            ];
        }

        return $blocks;
    }

    /**
     * The quotation body, as the client's quotation (user, 26 Oct 2026): ITEM | CHARGES (RM) | QUANTITY, a product
     * with quantity ranges taking a line per range ("15.00 · Carton 2-9"), one with a single price just its charge;
     * only the chartered lorries have destination columns (a table at the end). Non-lorry products to more than one
     * destination are listed under a heading per destination.
     *
     * @return array{groups: list<array{destination: ?string, products: list<array{name: string, lines: list<array{charge: ?string, quantity: string}>}>}>, lorry: ?array{columns: list<string>, rows: list<array{label: string, prices: list<?string>}>}}
     */
    private function rateTables(Quote $quote): array
    {
        $locations = Location::query()->whereIn('id', collect($quote->pairs)->pluck('to_location_id')->filter()->all())->pluck('name', 'id');
        $uoms = \App\Domains\MasterData\Models\Uom::query()
            ->whereIn('id', collect($quote->pairs)->flatMap(fn ($pair) => collect($pair['items'] ?? [])->pluck('catalog_key'))
                ->map(fn ($key) => (int) (explode(':', (string) $key, 2)[1] ?? 0))->filter()->all())
            ->get(['id', 'name', 'category', 'sub_category'])->keyBy('id');
        $groups = [];
        $lorry = ['columns' => [], 'rows' => []];

        foreach ((array) $quote->pairs as $pair) {
            $destination = strtoupper((string) ($locations[$pair['to_location_id'] ?? 0] ?? '-'));
            $dropOff = (string) (($pair['drop_off_type'] ?? '') ?: 'default');
            $dropLabel = $dropOff !== 'default' ? strtoupper((string) (\App\Enums\DropOffType::tryFrom($dropOff)?->getLabel() ?? $dropOff)) : null;

            foreach ($pair['items'] ?? [] as $item) {
                // Courier (the quotation's font) has no ≤ or –: written as the original does (< and -)
                $name = strtr(trim((string) ($item['item_name'] ?? '')), ['≤' => '<', '≥' => '>', '–' => '-', '—' => '-', '·' => '-']);

                if ($name === '') {
                    continue;
                }

                $uom = $uoms[(int) (explode(':', (string) ($item['catalog_key'] ?? ''), 2)[1] ?? 0)] ?? null;
                $ranges = \App\Support\UomPriceRows::ranges((array) ($item['special'] ?? []));

                if ($ranges === []) {
                    $price = is_numeric($item['manual_price'] ?? null) ? (float) $item['manual_price'] : (is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : null);
                    $ranges = [[1.0, null, $price]];
                }

                // chartered lorries: rows of the lorry table, a column per destination
                if ($uom?->category === \App\Domains\MasterData\Models\Uom::LORRY) {
                    $lorry['columns'][$destination] = true;
                    $lorry['rows'][strtoupper($name)][$destination] = $ranges[0][2];

                    continue;
                }

                $label = strtoupper($name).($dropLabel ? ' ('.$dropLabel.')' : '');
                $unit = $uom?->category === \App\Domains\MasterData\Models\Uom::MEASUREMENT ? ucfirst(strtolower((string) $uom->sub_category)) : null;
                $single = count($ranges) === 1 && (float) $ranges[0][0] <= 1 && $ranges[0][1] === null;

                $groups[$destination][$label] = array_map(fn (array $range) => [
                    'charge' => $range[2] !== null ? number_format((float) $range[2], 2, '.', '') : null,
                    'quantity' => $single ? '' : $this->quantityLabel($range[0], $range[1], $unit),
                ], $ranges);
            }
        }

        $columns = array_keys($lorry['columns']);

        return [
            'groups' => array_map(
                fn (string $destination, array $products) => [
                    'destination' => count($groups) > 1 ? $destination : null,
                    'products' => array_map(fn (string $name, array $lines) => ['name' => $name, 'lines' => $lines], array_keys($products), array_values($products)),
                ],
                array_keys($groups),
                array_values($groups),
            ),
            'lorry' => $lorry['rows'] === [] ? null : [
                'columns' => $columns,
                'rows' => array_map(
                    fn (string $label, array $prices) => ['label' => $label, 'prices' => array_map(fn (string $column) => isset($prices[$column]) && $prices[$column] !== null ? number_format((float) $prices[$column], 2, '.', '') : null, $columns)],
                    array_keys($lorry['rows']),
                    array_values($lorry['rows']),
                ),
            ],
        ];
    }

    /** The QUANTITY column, as the client writes it: "Carton 2-9", "Carton 20 & above", "1 - 9 UNITS", "10 & above". */
    private function quantityLabel(float $from, ?float $to, ?string $unit): string
    {
        if ($unit !== null) {
            return $unit.' '.match (true) {
                $to === null => $this->qty($from).' & above',
                $to === $from => $this->qty($from),
                default => $this->qty($from).'-'.$this->qty($to),
            };
        }

        return match (true) {
            $to === null => $this->qty($from).' & above',
            $to === $from => $this->qty($from).' UNIT'.($from == 1 ? '' : 'S'),
            default => $this->qty($from).' - '.$this->qty($to).' UNITS',
        };
    }

    /**
     * The minimum charge lines under the tables (as "* Minimum charge Door To Door Service is RM 250-00 per trip"): the
     * quoted products' minimums, else the price list's for a pick-up.
     *
     * @return list<string>
     */
    private function minimumNotes(Quote $quote, array $blocks): array
    {
        $notes = [];

        foreach ((array) $quote->pairs as $pair) {
            foreach ($pair['items'] ?? [] as $item) {
                $row = (array) ($item['special'] ?? []);

                if (is_numeric($row['pickup_min_charge'] ?? null) && filled($pair['from_location_id'] ?? null)) {
                    $notes['pickup'][] = (float) $row['pickup_min_charge'];
                }

                if (is_numeric($row['construction_min_charge'] ?? null)) {
                    $notes['construction'][] = (float) $row['construction_min_charge'];
                }

                if (is_numeric($row['drop_off_min_charge'] ?? null)) {
                    $notes['drop_off'][] = (float) $row['drop_off_min_charge'];
                }
            }
        }

        foreach ($blocks as $block) {
            if (($block['minimum']['minimum'] ?? null) !== null && ! isset($notes['pickup'])) {
                $notes['pickup'][] = (float) $block['minimum']['minimum'];
            }
        }

        $lines = [];

        foreach (['pickup' => 'Minimum charge Door To Door Service is %s per trip', 'construction' => 'Minimum charge to construction site is %s per trip', 'drop_off' => 'Minimum charge per drop-off is %s'] as $key => $text) {
            if (! empty($notes[$key])) {
                $lines[] = sprintf($text, 'RM '.number_format(max($notes[$key]), 2, '.', ''));
            }
        }

        return $lines;
    }

    /** RM 1050-00, as the client's rate card writes amounts. */
    private function rm(float $amount, int $width = 1): string
    {
        $cents = (int) round($amount * 100);

        return sprintf('RM %'.$width.'d-%02d', intdiv($cents, 100), $cents % 100);
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

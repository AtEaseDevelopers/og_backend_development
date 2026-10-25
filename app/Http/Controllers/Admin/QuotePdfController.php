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

    /** How a product's quantity is counted on the rate card (CTN for cartons, …). */
    private const UNITS = ['carton' => 'CTN', 'pallet' => 'PALLET', 'bundle' => 'BUNDLE', 'case' => 'CASE'];

    /**
     * The rate tables. A product with quantity ranges: one table, its ranges as rows against its destinations. Products
     * with one price each (chartered lorries, most items): one table per type, products as rows against destinations.
     * A drop-off type other than Default is named in the table title.
     *
     * @return list<array{title: string, columns: list<string>, rows: list<array{label: string, prices: list<?string>}>}>
     */
    private function rateTables(Quote $quote): array
    {
        $locations = Location::query()->whereIn('id', collect($quote->pairs)->pluck('to_location_id')->filter()->all())->pluck('name', 'id');
        $uoms = \App\Domains\MasterData\Models\Uom::query()
            ->whereIn('id', collect($quote->pairs)->flatMap(fn ($pair) => collect($pair['items'] ?? [])->pluck('catalog_key'))
                ->map(fn ($key) => (int) (explode(':', (string) $key, 2)[1] ?? 0))->filter()->all())
            ->get(['id', 'name', 'category', 'sub_category'])->keyBy('id');
        $ranged = [];
        $flat = [];

        foreach ((array) $quote->pairs as $pair) {
            $destination = strtoupper((string) ($locations[$pair['to_location_id'] ?? 0] ?? '-'));
            $dropOff = (string) (($pair['drop_off_type'] ?? '') ?: 'default');
            $dropLabel = $dropOff !== 'default' ? strtoupper((string) (\App\Enums\DropOffType::tryFrom($dropOff)?->getLabel() ?? $dropOff)) : null;

            foreach ($pair['items'] ?? [] as $item) {
                // Courier (the rate card's font) has no ≤ or –: written as the original does (< and -)
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

                $type = $uom?->category === \App\Domains\MasterData\Models\Uom::LORRY ? 'CHARTERED LORRY' : strtoupper((string) ($uom?->sub_category ?: ($uom?->category === 'item' ? 'Item' : 'Product')));

                // one price from 1 up: a row of its type's table
                if (count($ranges) === 1 && (float) $ranges[0][0] <= 1 && $ranges[0][1] === null) {
                    $key = $type.'|'.$dropLabel;
                    $flat[$key] ??= ['title' => $type.($dropLabel ? ' - '.$dropLabel : ''), 'columns' => [], 'rows' => []];
                    $flat[$key]['columns'][$destination] = true;
                    $flat[$key]['rows'][strtoupper($name)][$destination] = $ranges[0][2];

                    continue;
                }

                $unit = self::UNITS[strtolower((string) $uom?->sub_category)] ?? '';
                $key = $name.'|'.$dropLabel;
                $ranged[$key] ??= ['title' => strtoupper($name).($dropLabel ? ' - '.$dropLabel : ''), 'columns' => [], 'rows' => []];
                $ranged[$key]['columns'][$destination] = true;

                foreach ($ranges as [$from, $to, $price]) {
                    $label = match (true) {
                        $to === null => $this->qty($from).' '.$unit.' & ABOVE',
                        $to === $from => $this->qty($from).' '.$unit,
                        default => $this->qty($from).' - '.$this->qty($to).' '.$unit,
                    };
                    $ranged[$key]['order'][trim($label)] = $from;
                    $ranged[$key]['rows'][trim($label)][$destination] = $price;
                }
            }
        }

        $tables = [];
        $all = [...array_values($ranged), ...array_values($flat)];
        // the same destination columns in every table, so JOHOR sits under JOHOR (user, 26 Oct 2026); a quotation to
        // many destinations (over 5) keeps each table's own columns
        $shared = array_keys(array_merge(...array_map(fn (array $table) => $table['columns'], $all ?: [['columns' => []]])));

        foreach ($all as $table) {
            $columns = count($shared) <= 5 ? $shared : array_keys($table['columns']);
            $rows = $table['rows'];

            if (isset($table['order'])) {
                uksort($rows, fn ($a, $b) => $table['order'][$a] <=> $table['order'][$b]);
            }

            // amounts lined up as on the rate card: RM 14-00 over RM  9-00 (the ringgit padded to the table's widest)
            $width = collect($rows)->flatten()->filter(fn ($price) => $price !== null)->map(fn ($price) => strlen((string) intdiv((int) round((float) $price * 100), 100)))->max() ?? 1;

            $tables[] = [
                'title' => $table['title'],
                'columns' => $columns,
                'rows' => array_map(
                    fn (string $label, array $prices) => ['label' => $label, 'prices' => array_map(fn (string $column) => isset($prices[$column]) && $prices[$column] !== null ? $this->rm((float) $prices[$column], $width) : null, $columns)],
                    array_keys($rows),
                    array_values($rows),
                ),
            ];
        }

        return $tables;
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
                $lines[] = sprintf($text, $this->rm(max($notes[$key])));
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

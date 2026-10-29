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
 * issued by, then the rates in a typewriter style — ITEM and a column per destination, each product a heading with a
 * line per quantity range (RM 14-00), the chartered lorries their own block — the minimum charges and the notes.
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
        $company = ($record->company_id ? Company::query()->find($record->company_id) : null) ?? $record->branch?->defaultCompany();
        // no customer or lead (a price list): addressed to the company itself, as the client's sample
        $own = ! $customer && ! $lead;

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $record,
            'company' => $company,
            'salesperson' => filled($form['salesperson_id'] ?? null) ? User::query()->find($form['salesperson_id']) : null,
            'party' => [
                'name' => $customer?->company_name ?? $lead?->name ?? ($own ? ($company?->name ?? $record->branch?->company_name) : null) ?? '—',
                'address' => filled($form['customer_address'] ?? null) ? $form['customer_address'] : ($customer?->address ?? $lead?->billing_address ?? $lead?->address ?? ($own ? ($company?->address ?? $record->branch?->address) : null)),
                'email' => $customer?->email ?? $lead?->email ?? ($own ? ($company?->email ?? $record->branch?->email) : null),
                'phone' => filled($form['customer_pic_phone'] ?? null) ? $form['customer_pic_phone'] : ($customer?->phone ?? $lead?->contact ?? ($own ? ($company?->phone ?? $record->branch?->phone) : null)),
                'fax' => $customer?->fax ?? ($own ? $record->branch?->fax : null),
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
     * The quotation body as the client's quotations (user, 29 Oct 2026: their two samples): ITEM then a column per
     * destination; each product a heading with a line per quantity range ("2 - 9 CTN   RM 11-00   RM 12-00"), a
     * product with one price a single line; the chartered lorries their own block (CHARTED LORRY). Four destination
     * columns per table.
     *
     * @return array{columns: list<string>, groups: list<array{name: string, rows: list<array{label: string, prices: array<string, ?string>}>}>, lorry: ?array{columns: list<string>, rows: list<array{label: string, prices: array<string, ?string>}>}}
     */
    private function rateTables(Quote $quote): array
    {
        // destinations with the same quotation column label print as one column (user, 29 Oct 2026: "S'BAN / MELAKA")
        $locations = Location::query()->whereIn('id', collect($quote->pairs)->pluck('to_location_id')->filter()->all())->get(['id', 'name', 'quote_column'])
            ->mapWithKeys(fn (Location $l) => [$l->id => strtoupper(trim((string) ($l->quote_column ?: $l->name)))]);
        $uoms = \App\Domains\MasterData\Models\Uom::query()
            ->whereIn('id', collect($quote->pairs)->flatMap(fn ($pair) => collect($pair['items'] ?? [])->pluck('catalog_key'))
                ->map(fn ($key) => (int) (explode(':', (string) $key, 2)[1] ?? 0))->filter()->all())
            ->get(['id', 'name', 'category', 'sub_category'])->keyBy('id');
        $columns = [];
        $groups = [];
        $lorry = [];

        foreach ((array) $quote->pairs as $pair) {
            $destination = strtoupper((string) ($locations[$pair['to_location_id'] ?? 0] ?? '-'));
            $columns[$destination] = true;
            $dropOff = (string) (($pair['drop_off_type'] ?? '') ?: 'default');
            $dropLabel = $dropOff !== 'default' ? strtoupper((string) (\App\Enums\DropOffType::tryFrom($dropOff)?->getLabel() ?? $dropOff)) : null;

            foreach ($pair['items'] ?? [] as $item) {
                // Courier (the quotation's font) has no ≤ or –: written as the original does (< and -)
                $name = strtoupper(strtr(trim((string) ($item['item_name'] ?? '')), ['≤' => '<', '≥' => '>', '–' => '-', '—' => '-', '·' => '-']));

                if ($name === '') {
                    continue;
                }

                $uom = $uoms[(int) (explode(':', (string) ($item['catalog_key'] ?? ''), 2)[1] ?? 0)] ?? null;
                $ranges = \App\Support\UomPriceRows::ranges((array) ($item['special'] ?? []));

                if ($ranges === []) {
                    $price = is_numeric($item['manual_price'] ?? null) ? (float) $item['manual_price'] : (is_numeric($item['unit_price'] ?? null) ? (float) $item['unit_price'] : null);
                    $ranges = [[1.0, null, $price]];
                }

                // chartered lorries: their own block, a line per lorry
                if ($uom?->category === \App\Domains\MasterData\Models\Uom::LORRY) {
                    $lorry[$name][$destination][] = [$ranges[0][2], $ranges[0][3] ?? null];

                    continue;
                }

                $label = $name.($dropLabel ? ' ('.$dropLabel.')' : '');
                $unit = $uom?->category === \App\Domains\MasterData\Models\Uom::MEASUREMENT ? $this->unitAbbreviation((string) $uom->sub_category) : null;
                $single = count($ranges) === 1 && (float) $ranges[0][0] <= 1 && $ranges[0][1] === null;

                foreach ($ranges as $range) {
                    $groups[$label][$single ? '' : $this->quantityLabel($range[0], $range[1], $unit)][$destination][] = [$range[2], $range[3] ?? null];
                }
            }
        }

        $columns = array_keys($columns);
        // a column's cell: the price (or formula) of its destinations; different prices in one column are both printed
        $cell = fn (array $entries) => collect($entries)
            ->map(fn (array $e) => $e[0] === null ? null : ($e[1] !== null ? $this->formula((float) $e[1], (float) $e[0]) : $this->rm((float) $e[0], 4)))
            ->filter()->unique()->implode("
") ?: null; // one line per different price (merged columns)
        $rows = fn (array $prices, array $columns) => array_combine($columns, array_map(fn (string $column) => isset($prices[$column]) ? $cell($prices[$column]) : null, $columns));
        // each table lists only the destinations it has a price for (user, 29 Oct 2026: no empty columns)
        $used = fn (array $table) => array_values(array_filter($columns, fn (string $column) => collect($table)->contains(fn (array $lines) => collect($lines)->contains(fn ($prices) => isset($prices[$column])))));
        $productColumns = $used($groups);
        $lorryColumns = $used(['lorry' => $lorry]);

        return [
            'columns' => $productColumns,
            'groups' => array_map(
                fn (string $name, array $lines) => ['name' => $name, 'rows' => array_map(fn (string $label, array $prices) => ['label' => $label, 'prices' => $rows($prices, $productColumns)], array_keys($lines), array_values($lines))],
                array_keys($groups),
                array_values($groups),
            ),
            'lorry' => $lorry === [] ? null : [
                'columns' => $lorryColumns,
                'rows' => array_map(fn (string $label, array $prices) => ['label' => $label, 'prices' => $rows($prices, $lorryColumns)], array_keys($lorry), array_values($lorry)),
            ],
        ];
    }

    /** "(250+4.0X)": a fixed charge plus a rate per unit, as the client's quotation writes it. */
    private function formula(float $fixed, float $rate): string
    {
        $num = fn (float $v, int $min) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . (str_contains(rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'), '.') || $min === 0 ? '' : '.0');

        return '('.$num($fixed, 0).'+'.$num($rate, 1).'X)';
    }

    /** The unit as the client writes it: CTN, PALLET, DRUM, BAG … */
    private function unitAbbreviation(string $subCategory): string
    {
        $unit = strtoupper(trim($subCategory));

        return ['CARTON' => 'CTN', 'CARTONS' => 'CTN'][$unit] ?? ($unit !== '' ? $unit : 'UNIT');
    }

    /** The quantity line, as the client writes it: "1 CTN", "2 - 9 CTN", "20 CTN & ABOVE", "1 - 9 UNITS", "10 & ABOVE". */
    private function quantityLabel(float $from, ?float $to, ?string $unit): string
    {
        $unit ??= 'UNIT';
        $plural = $unit === 'UNIT' ? 'UNITS' : $unit;

        return match (true) {
            $to === null => $this->qty($from).' '.($from == 1 ? $unit : $plural).' & ABOVE',
            $to === $from => $this->qty($from).' '.($from == 1 ? $unit : $plural),
            default => $this->qty($from).' - '.$this->qty($to).' '.$plural,
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
        $customerType = ! $quote->isPriceList();

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

        // the wording of each type (user, 29 Oct 2026): the price list's "Door To Door … per trip", the customer quotation's "per point / DO" and "per pick up"
        $texts = $customerType
            ? ['drop_off' => 'Minimum charge per point / DO %s', 'pickup' => 'Minimum charges per pick up %s', 'construction' => 'Minimum charge to construction site %s per trip']
            : ['pickup' => 'Minimum charge Door To Door Service is %s per trip', 'construction' => 'Minimum charge to construction site is %s per trip', 'drop_off' => 'Minimum charge per drop-off is %s'];

        foreach ($texts as $key => $text) {
            if (! empty($notes[$key])) {
                $lines[] = sprintf($text, $this->rm(max($notes[$key])));
            }
        }

        return $lines;
    }

    /** RM 1050.00 (the client's rate card writes RM 1050-00; a dot was asked for on 29 Oct 2026). */
    private function rm(float $amount, int $width = 1): string
    {
        $cents = (int) round($amount * 100);

        return sprintf('RM %'.$width.'d.%02d', intdiv($cents, 100), $cents % 100); // RM 14.00 (user, 29 Oct 2026: a dot, not the samples' hyphen)
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

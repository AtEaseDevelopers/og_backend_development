<?php

// Sample quotations (user, 26 Oct 2026): every product type and sub-category at the default prices, for
// A GOODWAY TRADING (KL). Run once on a server:  php artisan tinker docs/sample-quotations.php
// Everything is looked up by name (customer, locations, products), so the IDs of that server are used; the next
// Q numbers are given automatically. Running it again adds 4 more quotations.

use App\Domains\MasterData\Models\Uom;
use App\Domains\Quotation\Models\Quote;
use App\Support\UomPriceRows;
use Illuminate\Support\Facades\DB;

$klCompany = DB::table('companies')->where('code', 'KL')->value('id');
$customerId = (int) (DB::table('customers')->where('company_name', 'A GOODWAY TRADING')->where('company_id', $klCompany)->orderByRaw("code = '3000-A002' desc")->value('id')
    ?: DB::table('customers')->where('company_id', $klCompany)->where('status', 'active')->orderBy('id')->value('id'));

if (! $customerId) {
    echo "No KL customer found: nothing created.\n";

    return;
}

$companyId = (int) DB::table('customers')->where('id', $customerId)->value('company_id');
$branchId = (int) (DB::table('customers')->where('id', $customerId)->value('branch_id') ?: 1);
$salesperson = rescue(fn () => App\Models\User::role('salesperson')->orderBy('id')->value('id'), null, false);
$loc = fn (string $name) => (int) DB::table('locations')->where('name', $name)->value('id');
$today = today()->toDateString();

$item = function (Uom $uom, int $locationId, string $drop = 'default') use ($today, $branchId): ?array {
    if (! $locationId) {
        return null;
    }

    $row = UomPriceRows::defaultRow($uom->id, $locationId, $drop, $branchId, $today);
    $ranges = $row ? UomPriceRows::ranges($row) : [];

    if ($ranges === [] || ($drop !== 'default' && ($row['drop_off_type'] ?? 'default') !== $drop)) {
        return null;
    }

    $price = $ranges[0][2];

    return [
        'kind' => $uom->category, 'sub_category' => (string) $uom->sub_category, 'line_type' => 'uom', 'catalog_key' => 'uom:'.$uom->id,
        'item_name' => $uom->name, 'uom' => (string) $uom->code, 'quantity' => 1, 'unit_price' => $price, 'manual_price' => number_format($price, 2, '.', ''),
        'price_edited' => false, 'source' => 'master', 'tier' => null, 'special' => array_merge(UomPriceRows::blankRow(), array_intersect_key($row, UomPriceRows::blankRow())),
        'special_for' => $uom->id.'|'.$locationId.'|'.$drop, 'ranges_shown' => count($ranges), 'photos' => [], 'existing_photos' => [],
    ];
};

$pairs = function (iterable $uoms, array $destinations, string $drop = 'default', bool $pickup = true) use ($item, $loc): array {
    $out = [];

    foreach ($uoms as $uom) {
        foreach ($destinations as $name) {
            $line = $item($uom, $loc($name), $drop);

            if ($line) {
                $out[] = ['to_location_id' => (string) $loc($name), 'from_location_id' => $pickup ? (string) $loc('Local') : '', 'drop_off_type' => $drop, 'items' => [$line]];
            }
        }
    }

    return $out;
};

$make = function (string $label, array $pairs) use ($customerId, $companyId, $branchId, $salesperson, $today): void {
    if ($pairs === []) {
        echo $label, ": no default prices found, skipped\n";

        return;
    }

    $quote = Quote::query()->create([
        'company_id' => $companyId, 'branch_id' => $branchId, 'number' => Quote::nextNumber(), 'customer_id' => $customerId,
        'quote_date' => $today, 'status' => Quote::PENDING, 'form' => ['salesperson_id' => $salesperson ? (string) $salesperson : '', 'order_type' => 'cash'],
        'pairs' => $pairs, 'total_amount' => round(collect($pairs)->sum(fn ($p) => (float) $p['items'][0]['unit_price']), 2),
    ]);

    echo $quote->number, ' · ', $label, ' · ', count($pairs), " product-destination rows\n";
};

$three = ['Seremban', 'Melaka', 'Johor'];
$measurement = Uom::query()->where('is_active', true)->where('category', 'measurement')->orderBy('sub_category')->orderBy('name')->get()
    ->groupBy('sub_category')->flatMap(fn ($group) => $group->take(6));
$cartonSmall = Uom::query()->where('category', 'measurement')->where('name', 'like', 'Carton%149%up to 30 kg')->get();

DB::transaction(function () use ($make, $pairs, $three, $measurement, $cartonSmall) {
    $make('Measurement: Bundle, Carton, Case, Pallet + shopping centre cartons', [
        ...$pairs($measurement, $three),
        ...$pairs($cartonSmall, ['Seremban', 'Johor'], 'supermarket', false),
    ]);

    $items = Uom::query()->where('is_active', true)->where('category', 'item')->orderBy('sub_category')->orderBy('name')->get()
        ->groupBy('sub_category')->map(fn ($group) => $group->first(fn (Uom $u) => DB::table('uom_rate_tiers')->where('uom_id', $u->id)->whereNull('customer_code')->exists()) ?? $group->first());
    $make('Items: one product of every sub-category', $pairs($items, $three));

    $lorries = Uom::query()->where('is_active', true)->where('category', 'lorry')->orderBy('id')->get();
    $make('Chartered lorry: every size to 8 destinations', $pairs($lorries, ['Seremban', 'Melaka', 'Muar', 'Batu Pahat', 'Segamat', 'Tanjung Pelepas', 'Pengerang', 'Mersing']));

    $make('Mixed: some of every type', [
        ...$pairs($cartonSmall->take(1), $three),
        ...$pairs($measurement->where('sub_category', 'Pallet')->take(2), $three),
        ...$pairs($items->take(3), $three),
        ...$pairs($lorries->take(2), ['Seremban', 'Melaka']),
    ]);
});

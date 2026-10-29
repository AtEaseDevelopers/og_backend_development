<?php

/**
 * Two sample quotations for the client to check (user, 30 Oct 2026): a Price list and a Customer quotation, each
 * with every product category — Measurement (quantity ranges), Item and Chartered Lorry — to three destinations,
 * priced from the company's default price list (the first customer of the company on the customer quotation).
 *
 *   php artisan tinker scripts/sample_quotations.php
 *
 * OG_COMPANY=JB picks another company (default KL); OG_DRY=1 shows what would be created and rolls back.
 */

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quote;
use App\Models\User;
use App\Support\UomPriceRows;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

$companyCode = strtoupper((string) (getenv('OG_COMPANY') ?: 'KL'));
$dry = (bool) getenv('OG_DRY');

$branch = Branch::query()->where('code', $companyCode)->firstOrFail();
$company = $branch->defaultCompany();
$user = User::query()->orderBy('id')->first();
auth()->login($user);
Filament::setCurrentPanel(Filament::getPanel('admin'));
Filament::setTenant($company);
$date = today()->toDateString();

// the three destinations with the most default-priced products
$locationIds = UomRateTier::query()
    ->whereNull('customer_code')
    ->where('branch_id', $branch->id)
    ->selectRaw('location_id, count(distinct uom_id) as n')
    ->groupBy('location_id')
    ->orderByDesc('n')
    ->limit(3)
    ->pluck('location_id')
    ->map(fn ($id) => (int) $id)
    ->all();

if ($locationIds === []) {
    echo "No default prices found for {$companyCode}: nothing created.\n";

    return;
}

// one product per sub-category, the one with the most quantity ranges (so the ranges show): 3 measurements, 3 items;
// 2 chartered lorries (one sub-category: by name)
$pick = fn (string $category, int $count, string $uniqueBy = 'sub_category') => Uom::query()
    ->withoutGlobalScopes()
    ->where('branch_id', $branch->id)
    ->where('category', $category)
    ->withCount(['rateTiers as range_count' => fn ($q) => $q->whereNull('customer_code')->where('location_id', $locationIds[0])->where(fn ($q) => $q->whereNull('drop_off_type')->orWhere('drop_off_type', 'default'))])
    ->whereHas('rateTiers', fn ($q) => $q->whereNull('customer_code')->whereIn('location_id', $locationIds))
    ->orderByDesc('range_count')
    ->orderBy('sub_category')
    ->orderBy('name')
    ->get()
    ->unique($uniqueBy)
    ->take($count)
    ->sortBy('sub_category');
$products = $pick(Uom::MEASUREMENT, 3)->concat($pick(Uom::ITEM, 3))->concat($pick(Uom::LORRY, 2, 'name'))->values();
$locationByName = Location::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => (int) $id]);

$pairs = [];
$total = 0.0;

$addPair = function (Uom $uom, int $locationId, string $dropOffType) use (&$pairs, &$total, $branch, $date, $locationByName): void {
    $row = UomPriceRows::defaultRow($uom->id, $locationId, $dropOffType, $branch->id, $date);

    if (! $row || ($dropOffType !== 'default' && ($row['drop_off_type'] ?? 'default') !== $dropOffType)) {
        return;
    }

    // the special price block starts as the default price row (as Create quotation does); no dates on the page
    $special = array_merge(UomPriceRows::blankRow(), array_intersect_key($row, UomPriceRows::blankRow()), ['effective_from' => null, 'expires_on' => null]);
    $ranges = UomPriceRows::ranges($special);
    $price = $ranges[0][2] ?? null;
    $pickup = $locationByName[mb_strtolower(trim((string) ($row['pickup_location'] ?? '')))] ?? null;

    $pairs[] = [
        'items' => [[
            'uom' => $uom->code,
            'kind' => $uom->category,
            'tier' => null,
            'photos' => [],
            'source' => 'master',
            'special' => $special,
            'quantity' => 1,
            'item_name' => $uom->name,
            'line_type' => 'uom',
            'unit_price' => $price,
            'catalog_key' => 'uom:'.$uom->id,
            'special_for' => $uom->id.'|'.$locationId.'|'.$dropOffType,
            'manual_price' => $price !== null ? number_format($price, 2, '.', '') : '',
            'price_edited' => false,
            'ranges_shown' => max(1, count($ranges)),
            'sub_category' => $uom->sub_category,
            'existing_photos' => [],
        ]],
        'drop_off_type' => $dropOffType,
        'to_location_id' => (string) $locationId,
        'from_location_id' => $pickup ? (string) $pickup : '',
    ];
    $total += (float) ($price ?? 0);
};

foreach ($products as $uom) {
    foreach ($locationIds as $locationId) {
        $addPair($uom, $locationId, 'default');
    }
}

// a Shopping Centre section when the first measurement product has one
if ($first = $products->firstWhere('category', Uom::MEASUREMENT)) {
    foreach ($locationIds as $locationId) {
        $addPair($first, $locationId, 'supermarket');
    }
}

if ($pairs === []) {
    echo "No priced products found for {$companyCode}: nothing created.\n";

    return;
}

$customer = Customer::query()->where('company_id', $company->id)->where('status', 'active')->orderBy('company_name')->first();

DB::beginTransaction();

try {
    $made = [];

    foreach ([Quote::TYPE_PRICE_LIST => null, Quote::TYPE_CUSTOMER => $customer] as $type => $party) {
        $quote = Quote::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'number' => Quote::nextNumber($branch->id),
            'type' => $type,
            'customer_id' => $party?->id,
            'quote_date' => $date,
            'status' => Quote::PENDING,
            'form' => [
                'order_type' => 'cash',
                'salesperson_id' => (string) $user->id,
                'customer_address' => (string) ($party?->address ?? ''),
                'customer_pic_name' => (string) ($party?->attention ?? ''),
                'customer_pic_phone' => (string) ($party?->phone ?? ''),
            ],
            'pairs' => $pairs,
            'total_amount' => round($total, 2),
            'created_by' => $user->id,
        ]);
        $made[] = $quote;
    }

    $dry ? DB::rollBack() : DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}

echo ($dry ? '[dry run — rolled back] ' : '')."Company {$companyCode}: ".count($pairs).' price lines ('.$products->count().' products × '.count($locationIds)." destinations)\n";

foreach ($made as $quote) {
    echo '  ', $quote->number, '  ', Quote::TYPES[$quote->type] ?? $quote->type, $quote->customer ? '  customer: '.$quote->customer->company_name : '  (no customer)', "\n";
    echo '    PDF: ', route('filament.admin.quotes.pdf', ['tenant' => $company, 'quote' => $quote]), "\n";
}

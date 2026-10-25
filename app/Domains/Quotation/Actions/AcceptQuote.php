<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quote;
use App\Enums\DropOffType;
use App\Filament\Resources\ItemProductResource;
use App\Filament\Resources\LorryProductResource;
use App\Filament\Resources\UomResource;
use App\Support\UomPriceRows;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Accept by customer" on the quotation list (user, 21 Oct 2026): the customer agreed, so each quoted product becomes
 * the customer's special price for its destination and drop-off type. No order is created: the order is created
 * afterwards and takes these prices. A lead becomes a customer first.
 *
 * A special price has the same conditions and quantity ranges as the default price row it replaces; only the price
 * differs: the range that holds the quoted quantity takes the quoted price, the other ranges keep the default price.
 * A product without a default price there gets one range (1 and above) at the quoted price.
 */
class AcceptQuote
{
    /** @return array{customer: Customer, prices: int} */
    public function execute(Quote $quote, string $acceptedBy): array
    {
        return DB::transaction(function () use ($quote, $acceptedBy) {
            $quote = Quote::query()->lockForUpdate()->with(['customer', 'lead', 'branch'])->findOrFail($quote->id);

            if (! $quote->isPending()) {
                throw new InvalidArgumentException('already accepted');
            }

            if (trim($acceptedBy) === '') {
                throw new InvalidArgumentException('who accepted it is missing');
            }

            $branch = $quote->branch ?? throw new InvalidArgumentException('the quotation has no company');
            $company = $quote->company_id ? Company::query()->find($quote->company_id) : $branch->defaultCompany();
            $customer = $quote->customer ?? $this->customerFromLead($quote, $branch, $company);
            $prices = $this->saveSpecialPrices($quote, $customer, $branch);

            $quote->update([
                'customer_id' => $customer->id,
                'accepted_by' => trim($acceptedBy),
                'accepted_at' => now(),
                'status' => Quote::ACCEPTED,
            ]);

            return ['customer' => $customer, 'prices' => $prices];
        });
    }

    /** The lead becomes a customer of the quotation's branch (Cash, its details; code L + lead number). */
    private function customerFromLead(Quote $quote, Branch $branch, ?Company $company): Customer
    {
        $lead = $quote->lead ?? throw new InvalidArgumentException('no customer or lead');

        if ($lead->customer_id && ($existing = Customer::query()->find($lead->customer_id))) {
            return $existing;
        }

        $code = 'L'.str_pad((string) $lead->id, 5, '0', STR_PAD_LEFT);

        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'company_id' => $company?->id,
            'code' => Customer::query()->where('code', $code)->exists() ? $code.'-'.$quote->number : $code,
            'company_name' => $lead->name,
            'attention' => $lead->pic_name,
            'phone' => $lead->contact,
            'email' => $lead->email,
            'address' => $lead->billing_address ?: $lead->address,
            'is_credit' => false,
            'status' => 'active',
        ]);

        $lead->update(['customer_id' => $customer->id]);

        return $customer;
    }

    /** @return int the special price rows saved (one per product, destination and drop-off type) */
    private function saveSpecialPrices(Quote $quote, Customer $customer, Branch $branch): int
    {
        $code = trim((string) $customer->code);

        if ($code === '') {
            throw new InvalidArgumentException('the customer has no debtor code (special prices are kept by debtor code)');
        }

        // the default price row in force on the quotation date starts an older quotation's special price; every
        // special price is effective from the day the customer accepts
        $date = $quote->quote_date?->toDateString() ?? today()->toDateString();
        $accepted = today()->toDateString();
        $rows = [];

        foreach ((array) $quote->pairs as $pair) {
            $locationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
            $dropOffType = ($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value;
            // the row's pickup location is the special price's (the template's Pickup Location column)
            $pickup = filled($pair['from_location_id'] ?? null) ? Location::query()->whereKey($pair['from_location_id'])->value('name') : null;

            foreach ($pair['items'] ?? [] as $item) {
                $uomId = $locationId ? $this->productId($item) : null;

                if (! $uomId) {
                    continue;
                }

                $key = $uomId.'|'.$locationId.'|'.$dropOffType;
                $special = (array) ($item['special'] ?? []);

                // the row's special price block (user, 21 Oct 2026): its ranges, minimum charges, pickup location and
                // dates are saved as they stand once its ranges are filled
                if (UomPriceRows::ranges($special) !== []) {
                    // effective from the day the customer accepts, no expiry (user, 21 Oct 2026)
                    $rows[$key] = array_merge(UomPriceRows::blankRow(), $special, [
                        'uom_id' => $uomId,
                        'branch_id' => $branch->id,
                        'pickup_location' => $pickup ?? ($special['pickup_location'] ?? null),
                        'location_id' => $locationId,
                        'drop_off_type' => $dropOffType,
                        'effective_from' => $accepted,
                        'expires_on' => null,
                    ]);

                    continue;
                }

                // no ranges (an older quotation): the default price row with the quoted price in the quantity's range
                $price = $this->price($item);

                if ($price === null) {
                    continue;
                }

                $rows[$key] ??= $this->startRow($uomId, $locationId, $dropOffType, $branch, $date, $accepted);
                $rows[$key] = UomPriceRows::withPrice($rows[$key], max(1.0, (float) ($item['quantity'] ?? 1)), $price);
            }
        }

        foreach ($rows as $row) {
            // the customer's earlier special price for this product, destination and drop-off type is replaced
            UomRateTier::query()
                ->where('uom_id', $row['uom_id'])
                ->where('location_id', $row['location_id'])
                ->where('customer_code', $code)
                ->where('drop_off_type', $row['drop_off_type'])
                ->delete();

            UomPriceRows::create((int) $row['uom_id'], array_merge($row, ['source' => 'Quotation '.$quote->number]), $code);
        }

        return count($rows);
    }

    /**
     * The default price row in force on the quotation date for the product, destination and drop-off type (else the
     * Default type's), as the start of the special price; none = one empty range.
     */
    private function startRow(int $uomId, int $locationId, string $dropOffType, Branch $branch, string $date, string $accepted): array
    {
        $row = UomPriceRows::defaultRow($uomId, $locationId, $dropOffType, $branch->id, $date) ?? UomPriceRows::blankRow();

        return array_merge($row, [
            'uom_id' => $uomId,
            'branch_id' => $row['branch_id'] ?? $branch->id,
            'location_id' => $locationId,
            'drop_off_type' => $dropOffType,
            'effective_from' => $accepted,
            'expires_on' => null,
        ]);
    }

    /**
     * The product quoted: a price list product, or the new one the row describes ("+ New …" on Create quotation,
     * user 21 Oct 2026), added to the price list's products of its category as the product pages would; a product
     * of that name already there is used instead.
     */
    private function productId(array $item): ?int
    {
        [$type, $id] = array_pad(explode(':', (string) ($item['catalog_key'] ?? ''), 2), 2, null);

        if ($type === 'uom') {
            return Uom::query()->whereKey($id)->exists() ? (int) $id : null;
        }

        $name = trim((string) ($item['item_name'] ?? ''));

        if ($type !== 'new' || $name === '') {
            return null;
        }

        $existing = Uom::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $category = in_array($item['kind'] ?? null, [Uom::ITEM, Uom::LORRY], true) ? $item['kind'] : Uom::MEASUREMENT;
        $resource = match ($category) {
            Uom::ITEM => ItemProductResource::class,
            Uom::LORRY => LorryProductResource::class,
            default => UomResource::class,
        };
        $data = $resource::productData(array_merge((array) ($item['product'] ?? []), ['category' => $category, 'name' => $name, 'is_active' => true]));
        $uom = Uom::query()->create($data + ['code' => UomResource::uniqueCode($data['name'])]);
        UomResource::productSaved($uom);

        return $uom->id;
    }

    /** The quoted price: the price box (the default price, maybe changed), else the default price. */
    private function price(array $item): ?float
    {
        if (is_numeric($item['manual_price'] ?? null)) {
            return round((float) $item['manual_price'], 2);
        }

        return ($item['unit_price'] ?? null) !== null ? round((float) $item['unit_price'], 2) : null;
    }
}

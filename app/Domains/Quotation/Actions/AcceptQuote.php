<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quote;
use App\Enums\DropOffType;
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

            $branch = $quote->branch ?? throw new InvalidArgumentException('the quotation has no branch');
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

        $date = $quote->quote_date?->toDateString() ?? today()->toDateString();
        $rows = [];

        foreach ((array) $quote->pairs as $pair) {
            $locationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
            $dropOffType = ($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value;

            foreach ($pair['items'] ?? [] as $item) {
                [$type, $id] = array_pad(explode(':', (string) ($item['catalog_key'] ?? ''), 2), 2, null);
                $price = $this->price($item);

                if (! $locationId || $type !== 'uom' || $price === null || ! Uom::query()->whereKey($id)->exists()) {
                    continue;
                }

                $key = $id.'|'.$locationId.'|'.$dropOffType;
                $rows[$key] ??= $this->startRow((int) $id, $locationId, $dropOffType, $branch, $date);
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
    private function startRow(int $uomId, int $locationId, string $dropOffType, Branch $branch, string $date): array
    {
        $defaults = collect(UomPriceRows::fromTiers(
            UomRateTier::query()
                ->whereNull('customer_code')
                ->where('uom_id', $uomId)
                ->where('location_id', $locationId)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', $date))
                ->get()
        ));

        $default = $defaults
            ->sortBy([
                // the drop-off type first, then Default; the quotation's branch first; the latest effective date first
                fn (array $a, array $b) => [$a['drop_off_type'] !== $dropOffType, $a['branch_id'] !== $branch->id] <=> [$b['drop_off_type'] !== $dropOffType, $b['branch_id'] !== $branch->id],
                fn (array $a, array $b) => strcmp((string) $b['effective_from'], (string) $a['effective_from']),
            ])
            ->first(fn (array $row) => in_array($row['drop_off_type'], [$dropOffType, DropOffType::Default->value], true));

        $row = $default ?? [
            'uom_id' => $uomId,
            'branch_id' => $branch->id,
            'pickup_location' => null,
            'location_id' => $locationId,
            'pickup_min_charge' => null,
            'construction_min_charge' => null,
            'drop_off_min_charge' => null,
        ] + array_fill_keys(array_merge(...array_map(fn ($l) => ["range_{$l}_from", "range_{$l}_to", "range_{$l}_price"], UomPriceRows::RANGES)), null);

        return array_merge($row, [
            'uom_id' => $uomId,
            'location_id' => $locationId,
            'drop_off_type' => $dropOffType,
            'effective_from' => $date,
            'expires_on' => null,
        ]);
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

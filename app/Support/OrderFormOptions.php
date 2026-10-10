<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\MasterData\Models\Store;

/**
 * Option lists and consignor defaults shared by the order pages (create / edit order)
 * and the order-record builders. Replaces the helpers that lived on the retired QuotationForm.
 */
class OrderFormOptions
{
    /** @return array<int, string> */
    public static function locationOptions(): array
    {
        return Location::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Location $location) => [
                $location->id => trim($location->code.' — '.$location->name),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function customerAddressOptions(?string $customerId): array
    {
        if (! $customerId) {
            return [];
        }

        return CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->get()
            ->mapWithKeys(fn (CustomerAddress $address) => [
                (string) $address->id => trim(($address->label ?: 'Address').' — '.$address->address),
            ])
            ->all();
    }

    /**
     * Consignor defaults for a customer: billing address, BRN, attention, payment terms,
     * the branch's "from" location and (optionally) the default pickup address.
     *
     * @return array<string, mixed>
     */
    public static function consignorStateForCustomer(?string $customerId, bool $withPickupPreset = true): array
    {
        if (! $customerId) {
            return [];
        }

        $customer = Customer::query()->with(['pics', 'addresses'])->find($customerId);

        if (! $customer) {
            return [];
        }

        $state = [
            'customer_address' => $customer->address ?? '',
            'consignor_brn' => $customer->brn ?? '',
            // the customer's person in charge: the default PIC, else the first, else the customer's attention / phone
            'attention' => $customer->pics->firstWhere('is_default', true)?->name
                ?? $customer->pics->first()?->name
                ?? ($customer->attention ?? ''),
            'customer_pic_phone' => ($customer->pics->firstWhere('is_default', true) ?? $customer->pics->first())?->phone
                ?? ($customer->phone ?? ''),
            'terms_of_payment' => $customer->credit_term_days
                ? $customer->credit_term_days.' days'
                : 'Cash / COD',
        ];

        $branchId = $customer->branch_id ?? CurrentCompany::branchId();
        $fromLocationId = static::fromLocationIdForBranch($branchId);

        if ($fromLocationId) {
            $state['from_location_id'] = (string) $fromLocationId;
        }

        if ($withPickupPreset) {
            $defaultAddress = $customer->addresses->firstWhere('is_default', true)
                ?? $customer->addresses->first();

            if ($defaultAddress) {
                $state['pickup_location_preset'] = (string) $defaultAddress->id;
                $state['pickup_location'] = static::formatAddress($defaultAddress);
            }
        }

        return $state;
    }

    public static function fromLocationIdForBranch(?int $branchId): ?int
    {
        if (! $branchId) {
            return null;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            return null;
        }

        return Location::query()
            ->where('is_active', true)
            ->where(function ($query) use ($branch) {
                $query->where('code', $branch->code)
                    ->orWhere('name', 'like', '%'.$branch->name.'%');
            })
            ->value('id');
    }

    public static function formatAddress(CustomerAddress $address): string
    {
        return trim(collect([
            $address->label,
            $address->address,
            $address->postcode,
            $address->city,
            $address->state,
        ])->filter()->implode(', '));
    }

    /** Pickup location picker value for "type a new address" (saved addresses use their id). */
    public const NEW_ADDRESS = 'new';

    /**
     * Pickup / drop-off location picker value for an address text: the saved customer address it was taken
     * from, else "new address" when there is text, else nothing.
     */
    public static function pickupPresetFor(?string $customerId, ?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        if ($customerId) {
            $match = CustomerAddress::query()->where('customer_id', $customerId)->get()
                ->first(fn (CustomerAddress $address) => static::formatAddress($address) === $text);

            if ($match) {
                return (string) $match->id;
            }
        }

        return self::NEW_ADDRESS;
    }

    /**
     * Stores (Master Data → Stores) a consignor can bring the goods to: the company's active stores, plus the
     * given ids so a record keeps showing a store that was deactivated since. Each option has the store name
     * (with its branch) and a second line with the address, From, PIC and contact number.
     *
     * @param  list<int|string|null>  $keep
     * @return array<int, array{label: string, sub: string}>
     */
    public static function storeOptions(array $keep = []): array
    {
        $keep = array_values(array_filter(array_map('intval', $keep)));

        return Store::query()
            ->with(['branch:id,name,code', 'location:id,name'])
            ->when(CurrentCompany::id(), fn ($query, $id) => $query->where('company_id', $id))
            ->where(fn ($query) => $query->where('is_active', true)->when($keep !== [], fn ($q) => $q->orWhereIn('id', $keep)))
            ->get()
            ->sortBy(fn (Store $store) => [$store->branch?->name, $store->name])
            ->mapWithKeys(fn (Store $store) => [$store->id => [
                'label' => $store->label().($store->branch ? ' · '.$store->branch->name : ''),
                'sub' => collect([
                    static::oneLine($store->address),
                    $store->location ? 'From '.$store->location->name : null,
                    filled($store->pic_name) ? 'PIC '.$store->pic_name : null,
                    $store->pic_phone,
                ])->filter()->implode(' · '),
            ]])
            ->all();
    }

    /**
     * What a Store block takes from the picked store: its address as the pickup location (the store name when it
     * has none), its PIC and contact number, its "From" location (else the price-list location of its branch)
     * and its branch.
     *
     * @return array{store_id: int, branch_id: int, pickup_location: string, consignor_pic_name: string, consignor_pic_phone: string, from_location_id: string}|null
     */
    public static function storeDefaults(mixed $storeId): ?array
    {
        $store = filled($storeId) ? Store::query()->find($storeId) : null;

        if (! $store) {
            return null;
        }

        return [
            'store_id' => (int) $store->id,
            'branch_id' => (int) $store->branch_id,
            'pickup_location' => static::storeAddress($store),
            'consignor_pic_name' => trim((string) $store->pic_name),
            'consignor_pic_phone' => trim((string) $store->pic_phone),
            'from_location_id' => (string) ($store->location_id ?: (static::fromLocationIdForBranch($store->branch_id) ?? '')),
        ];
    }

    /** A store's pickup location: its address on one line, else its name. */
    public static function storeAddress(?Store $store): string
    {
        if (! $store) {
            return '';
        }

        return static::oneLine($store->address) ?: (string) $store->name;
    }

    private static function oneLine(?string $text): string
    {
        return trim((string) preg_replace('/\s*\R\s*/', ', ', trim((string) $text)));
    }
}

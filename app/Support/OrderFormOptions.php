<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;

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
            'attention' => $customer->pics->firstWhere('is_default', true)?->name
                ?? $customer->pics->first()?->name
                ?? '',
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
}

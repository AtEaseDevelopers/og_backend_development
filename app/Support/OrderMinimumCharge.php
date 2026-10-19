<?php

namespace App\Support;

use App\Enums\DropOffType;
use App\Enums\ServiceType;

/**
 * The minimum charge of one order record's drop-off, from the client's price list rows of its products
 * (19 Oct 2026, applied automatically): the drop-off minimum of the route; for a Construction Site drop-off the
 * construction site minimum; for a Pick Up (collected from the consignor) the pick-up minimum. The highest one
 * applies; when the record's total is below it, a "Minimum charge" line tops it up (QuotationMatrix::sync).
 */
class OrderMinimumCharge
{
    public const LINE = 'Minimum charge';

    private const LABELS = [
        'pickup' => 'Pick-up minimum',
        'construction' => 'Construction site minimum',
        'drop_off' => 'Drop-off minimum',
    ];

    public function __construct(private QuotationPricingLookup $lookup) {}

    /**
     * @param  list<array{item_name: string, quantity: float, line_total: float, priced: bool}>  $lines  the record's lines to this destination
     * @return array{minimum: float, label: ?string, subtotal: float, top_up: float, pending: bool, note: ?string}
     */
    public function forDestination(?int $customerId, string $location, ?string $dropOffType, ?string $serviceType, array $lines): array
    {
        $minimums = ['pickup' => 0.0, 'construction' => 0.0, 'drop_off' => 0.0];
        $subtotal = 0.0;
        $pending = false;
        $construction = $dropOffType === DropOffType::Construction->value;
        $pickup = ($serviceType ?: ServiceType::Pickup->value) === ServiceType::Pickup->value;

        foreach ($lines as $line) {
            $subtotal += (float) $line['line_total'];
            $pending = $pending || ! $line['priced'];
            $tier = $this->lookup->rateTierFor($customerId, $line['item_name'], $location, max(0.01, (float) $line['quantity']), $dropOffType);

            if (! $tier) {
                continue;
            }

            $minimums['drop_off'] = max($minimums['drop_off'], (float) $tier->drop_off_min_charge);

            if ($construction) {
                $minimums['construction'] = max($minimums['construction'], (float) $tier->construction_min_charge);
            }

            if ($pickup) {
                $minimums['pickup'] = max($minimums['pickup'], (float) $tier->pickup_min_charge);
            }
        }

        $minimum = max($minimums);
        $key = $minimum > 0 ? array_search($minimum, $minimums, true) : null;
        $subtotal = round($subtotal, 2);
        // no top-up while a product still has no price (the total is not known yet)
        $topUp = ! $pending && $minimum > $subtotal + 0.005 ? round($minimum - $subtotal, 2) : 0.0;

        return [
            'minimum' => $minimum,
            'label' => $key ? self::LABELS[$key] : null,
            'subtotal' => $subtotal,
            'top_up' => $topUp,
            'pending' => $pending,
            'note' => $topUp > 0
                ? self::LABELS[$key].' RM '.number_format($minimum, 2).' · products RM '.number_format($subtotal, 2)
                : null,
        ];
    }
}

<?php

namespace App\Support;

/**
 * The stops of an order record with several pick-up / drop-off locations (user, 27 Oct 2026): each stop is a
 * pick-up or a drop-off with its own location and DO number, kept as JSON on the record (quotations.stops) and
 * copied to its CSN (consignment_notes.stops), where the driver scans each stop's DO on the way.
 */
class OrderStops
{
    public const PICKUP = 'pickup';

    public const DROPOFF = 'dropoff';

    public const TYPES = [self::PICKUP => 'Pick-up', self::DROPOFF => 'Drop-off'];

    /** A blank stop row of the order form. @return array{type: string, location: string, do_number: string} */
    public static function blank(string $type = self::DROPOFF): array
    {
        return ['type' => $type, 'location' => '', 'do_number' => ''];
    }

    /**
     * The rows worth keeping (a type with a location or a DO number), in order, as saved.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @return list<array{type: string, location: ?string, do_number: ?string}>
     */
    public static function clean(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $location = trim((string) ($row['location'] ?? ''));
            $do = trim((string) ($row['do_number'] ?? ''));

            if ($location === '' && $do === '') {
                continue;
            }

            $out[] = [
                'type' => ($row['type'] ?? '') === self::PICKUP ? self::PICKUP : self::DROPOFF,
                'location' => $location !== '' ? $location : null,
                'do_number' => $do !== '' ? $do : null,
                // kept when a CSN's stop was scanned already (the order form never sets these)
                ...array_intersect_key($row, ['scanned_at' => true, 'scanned_by' => true]),
            ];
        }

        return $out;
    }

    /**
     * The stops of a form block: the consignor side's extra addresses (pickup) then the consignee's (dropoff), each
     * side only when its "Multiple address" is ticked (user, 28 Oct 2026).
     *
     * @param  array<string, mixed>  $pair
     * @return list<array{type: string, location: ?string, do_number: ?string}>
     */
    public static function merge(array $pair): array
    {
        $rows = [];

        foreach (['pickup' => self::PICKUP, 'dropoff' => self::DROPOFF] as $side => $type) {
            if (! empty($pair['multi_'.$side])) {
                foreach ((array) ($pair[$side.'_stops'] ?? []) as $row) {
                    $rows[] = is_array($row) ? ['type' => $type] + $row : ['type' => $type];
                }
            }
        }

        return static::clean($rows);
    }

    /**
     * The form block's sides from saved stops: multi_pickup / pickup_stops and multi_dropoff / dropoff_stops.
     *
     * @return array{multi_pickup: bool, pickup_stops: list<array<string, mixed>>, multi_dropoff: bool, dropoff_stops: list<array<string, mixed>>}
     */
    public static function split(?array $stops): array
    {
        $pickup = array_values(array_filter($stops ?? [], fn ($s) => ($s['type'] ?? '') === self::PICKUP));
        $dropoff = array_values(array_filter($stops ?? [], fn ($s) => ($s['type'] ?? '') !== self::PICKUP));

        return [
            'multi_pickup' => $pickup !== [],
            'pickup_stops' => array_map(fn (array $s) => $s + self::blank(self::PICKUP), $pickup),
            'multi_dropoff' => $dropoff !== [],
            'dropoff_stops' => array_map(fn (array $s) => $s + self::blank(self::DROPOFF), $dropoff),
        ];
    }

    /** The DO numbers of the stops, in order. @return list<string> */
    public static function doNumbers(?array $stops): array
    {
        return array_values(array_filter(array_map(fn ($stop) => (string) ($stop['do_number'] ?? ''), $stops ?? []), 'strlen'));
    }

    public static function label(array $stop): string
    {
        return self::TYPES[$stop['type'] ?? ''] ?? 'Stop';
    }
}

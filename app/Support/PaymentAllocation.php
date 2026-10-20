<?php

namespace App\Support;

/**
 * One amount received for several items (CSNs or orders), user 21 Oct 2026 (Cash Bill Payment and Create order):
 * received the total or more → every item is paid in full and the rest is change; received less → the user says how
 * much goes to each item (all to one, half each, 40 / 60, …), each at most its due and together exactly the amount
 * received; an item paid less than its due is a partial payment. Until the user changes them, the amounts fill the
 * items in their order.
 */
class PaymentAllocation
{
    /**
     * The amount received spread over the items in their order (each up to its due).
     *
     * @param  array<string, float>  $dues
     * @return array<string, string>
     */
    public static function fill(array $dues, float $received): array
    {
        $left = round(max(0, $received), 2);
        $amounts = [];

        foreach ($dues as $key => $due) {
            $amount = round(min($left, max(0, (float) $due)), 2);
            $amounts[$key] = number_format($amount, 2, '.', '');
            $left = round($left - $amount, 2);
        }

        return $amounts;
    }

    /**
     * The amounts the user entered over the items, the missing ones filled in order (only items still listed).
     *
     * @param  array<string, float>  $dues
     * @param  array<string, mixed>  $entered
     * @return array<string, string>
     */
    public static function amounts(array $dues, float $received, array $entered): array
    {
        if (static::isFull($dues, $received)) {
            return [];
        }

        $kept = array_filter(array_intersect_key($entered, $dues), fn ($value) => $value !== null && $value !== '');

        return array_merge(static::fill($dues, $received), array_map(fn ($value) => (string) $value, $kept));
    }

    /**
     * @param  array<string, float>  $dues
     * @param  array<string, mixed>  $entered
     * @return array{full: bool, total: float, received: float, change: float, short: float, amounts: array<string, float>, allocated: float, left: float, errors: array<string, string>, ok: bool}
     */
    public static function plan(array $dues, float $received, array $entered): array
    {
        $total = round(array_sum(array_map('floatval', $dues)), 2);
        $received = round(max(0, $received), 2);
        $full = static::isFull($dues, $received);
        $amounts = [];
        $errors = [];

        if ($full) {
            $amounts = array_map(fn ($due) => round((float) $due, 2), $dues);
        } else {
            foreach (static::amounts($dues, $received, $entered) as $key => $value) {
                $amount = is_numeric($value) ? round((float) $value, 2) : 0.0;

                if ($amount < 0) {
                    $errors[$key] = 'Cannot be below 0.';
                } elseif ($amount > round((float) $dues[$key], 2) + 0.004) {
                    $errors[$key] = 'More than its due (RM '.number_format((float) $dues[$key], 2).').';
                }

                $amounts[$key] = max(0, $amount);
            }
        }

        $allocated = round(array_sum($amounts), 2);
        $left = $full ? 0.0 : round($received - $allocated, 2);

        return [
            'full' => $full,
            'total' => $total,
            'received' => $received,
            'change' => $full ? round($received - $total, 2) : 0.0,
            'short' => $full ? 0.0 : round($total - $received, 2),
            'amounts' => $amounts,
            'allocated' => $allocated,
            'left' => $left,
            'errors' => $errors,
            'ok' => $received > 0 && $total > 0 && ($full || ($errors === [] && abs($left) < 0.005)),
        ];
    }

    /** @param  array<string, float>  $dues */
    private static function isFull(array $dues, float $received): bool
    {
        return round($received, 2) + 0.004 >= round(array_sum(array_map('floatval', $dues)), 2);
    }
}

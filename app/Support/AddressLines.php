<?php

namespace App\Support;

/** Address text for the documents: a long single line is split at a comma near its middle (user, 30 Oct 2026). */
class AddressLines
{
    public static function wrap(?string $address, int $maxLength = 58): string
    {
        $address = trim((string) $address);

        if ($address === '' || str_contains($address, "\n") || mb_strlen($address) <= $maxLength) {
            return $address;
        }

        $parts = array_map('trim', explode(',', $address));

        if (count($parts) < 2) {
            return wordwrap($address, $maxLength, "\n", true);
        }

        // the comma split whose first line is closest to (but not over) the limit, else the one nearest the middle
        $best = null;
        $bestScore = PHP_INT_MAX;

        for ($i = 1; $i < count($parts); $i++) {
            $first = implode(', ', array_slice($parts, 0, $i));
            $length = mb_strlen($first);
            $score = $length <= $maxLength ? $maxLength - $length : 1000 + $length;

            if ($score < $bestScore) {
                [$best, $bestScore] = [$i, $score];
            }
        }

        $second = implode(', ', array_slice($parts, $best));

        return implode(', ', array_slice($parts, 0, $best)).",\n".(mb_strlen($second) > $maxLength ? static::wrap($second, $maxLength) : $second);
    }
}

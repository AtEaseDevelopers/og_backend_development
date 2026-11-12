<?php

namespace App\Support;

/** Address text for the documents: a long single line is split at a comma near its middle (user, 30 Oct 2026). */
class AddressLines
{
    /**
     * The address in exactly three lines (client, 13 Nov 2026: billing, consignor and consignee, never more or fewer):
     * filled at the commas up to $width characters a line, wider only when it would not fit in three; a short
     * address leaves its last lines empty.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function three(?string $address, int $width = 46): array
    {
        $flat = trim((string) preg_replace('/\s*,?\s*[\r\n]+\s*/', ', ', trim((string) $address)));
        $flat = trim((string) preg_replace('/\s+/', ' ', $flat), ' ,');

        if ($flat === '') {
            return ['', '', ''];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $flat)), fn (string $part): bool => $part !== ''));
        // an address without commas is split at its spaces
        if (count($parts) === 1 && mb_strlen($flat) > $width) {
            $parts = explode(' ', $flat);
            $glue = ' ';
        } else {
            $glue = ', ';
        }

        $fill = function (int $limit) use ($parts, $glue): array {
            $lines = [''];
            foreach ($parts as $part) {
                $last = count($lines) - 1;
                $candidate = $lines[$last] === '' ? $part : $lines[$last].$glue.$part;
                if ($lines[$last] !== '' && mb_strlen($candidate) > $limit) {
                    $lines[] = $part;
                } else {
                    $lines[$last] = $candidate;
                }
            }

            return $lines;
        };

        $lines = $fill($width);
        for ($limit = $width + 4; count($lines) > 3 && $limit < 400; $limit += 4) {
            $lines = $fill($limit);
        }
        if (count($lines) > 3) {
            $lines = [$lines[0], $lines[1], implode($glue, array_slice($lines, 2))];
        }
        // a comma closes a line that continues on the next
        if ($glue === ', ') {
            foreach ($lines as $i => $line) {
                if (isset($lines[$i + 1])) {
                    $lines[$i] = $line.',';
                }
            }
        }

        return array_pad($lines, 3, '');
    }

    /** The three lines as HTML: escaped, an empty line kept as a blank line so every address is as tall. */
    public static function threeHtml(?string $address, int $width = 46): string
    {
        $lines = static::three($address, $width);
        if ($lines[0] === '') {
            $lines[0] = '—'; // no address: a dash on the first line, the block as tall as any other
        }

        $html = implode('<br>', array_map(fn (string $line): string => $line === '' ? '&nbsp;' : e($line), $lines));
        // a very long address still takes three lines: its type is made smaller instead of running into a fourth
        $longest = max(array_map('mb_strlen', $lines));
        if ($longest > $width + 2) {
            $html = '<span style="white-space:nowrap;font-size:'.max(60, (int) floor(100 * ($width + 2) / $longest)).'%">'.$html.'</span>';
        }

        return $html;
    }

    /** Phone first, then the person (client, 13 Nov 2026: "Tel / PIC" on one line). */
    public static function telPic(?string $phone, ?string $pic): string
    {
        return trim(implode(' / ', array_filter([trim((string) $phone), trim((string) $pic)], fn (string $v): bool => $v !== ''))) ?: '—';
    }

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

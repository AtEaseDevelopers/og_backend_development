<?php

namespace App\Support;

class AmountInWords
{
    public static function ringgit(float $amount): string
    {
        $whole = (int) round($amount, 0, PHP_ROUND_HALF_DOWN);
        $words = self::spell($whole);

        return 'RINGGIT MALAYSIA : '.strtoupper($words).' RINGGIT';
    }

    private static function spell(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = [
            '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
            'seventeen', 'eighteen', 'nineteen',
        ];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $scales = ['', 'thousand', 'million', 'billion'];

        $parts = [];
        $scaleIndex = 0;

        while ($number > 0) {
            $chunk = $number % 1000;

            if ($chunk !== 0) {
                $chunkWords = self::spellChunk($chunk, $units, $tens);
                $scale = $scales[$scaleIndex] ?? '';
                $parts[] = trim($chunkWords.' '.$scale);
            }

            $number = intdiv($number, 1000);
            $scaleIndex++;
        }

        return implode(' ', array_reverse($parts));
    }

    /** @param  list<string>  $units
     * @param  list<string>  $tens
     */
    private static function spellChunk(int $number, array $units, array $tens): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = $units[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = $tens[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $words[] = $units[$number];
        }

        return implode(' ', $words);
    }
}

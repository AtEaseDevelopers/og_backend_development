<?php

namespace App\Support;

use App\Domains\MasterData\Models\Uom;

/**
 * Quantity text for documents and lists. A product priced from the UOM price list stores its UOM master
 * code as the line's unit (e.g. EXTRA_LONG_CARTON_40KG_60KG_L_186_310_CM_OTH): the product itself is the
 * unit, so only the number is shown. Short packaging units (CTN, PAILS, KG …) are kept: "6 PAILS".
 */
class QuantityLabel
{
    /** @var array<string, true>|null */
    private static ?array $masterCodes = null;

    public static function format(mixed $quantity, ?string $uom): string
    {
        $qty = rtrim(rtrim(number_format((float) ($quantity ?? 0), 3, '.', ''), '0'), '.');
        $unit = static::unit($uom);

        return $unit !== null ? $qty.' '.$unit : $qty;
    }

    /** The unit to print, or null when there is none to show (blank, or a UOM master product code). */
    public static function unit(?string $uom): ?string
    {
        $uom = trim((string) $uom);

        if ($uom === '' || static::isMasterCode($uom)) {
            return null;
        }

        return strtoupper($uom);
    }

    public static function isMasterCode(string $uom): bool
    {
        static::$masterCodes ??= Uom::query()->pluck('code')->mapWithKeys(fn ($code) => [strtoupper((string) $code) => true])->all();

        // codes are long underscore keys; anything that looks like one is a product code even if renamed since
        return isset(static::$masterCodes[strtoupper($uom)]) || (str_contains($uom, '_') && strlen($uom) > 12);
    }
}

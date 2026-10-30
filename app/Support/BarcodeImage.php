<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;

/**
 * A Code 128 barcode of a document number as a data URI (user, 30 Oct 2026: every document carries one, scanned
 * into the system's search fields with a handheld scanner). A PNG with the GD extension, else an SVG.
 */
class BarcodeImage
{
    public static function code128DataUri(?string $text, int $widthFactor = 2, int $height = 40): ?string
    {
        $text = trim((string) $text);

        if ($text === '' || $text === '—') {
            return null;
        }

        try {
            if (extension_loaded('gd') && ! (bool) config('og.barcode_svg', false)) {
                $png = (new BarcodeGeneratorPNG)->getBarcode($text, BarcodeGeneratorPNG::TYPE_CODE_128, $widthFactor, $height);

                return 'data:image/png;base64,'.base64_encode($png);
            }

            $svg = (new BarcodeGeneratorSVG)->getBarcode($text, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}

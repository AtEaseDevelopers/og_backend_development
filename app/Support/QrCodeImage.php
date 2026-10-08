<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

/**
 * QR codes printed on documents (CSN / DO) for scanning later: a PNG data URI, which DomPDF and the
 * browser preview both show. Error correction level M still scans with ~15% of the code damaged.
 */
class QrCodeImage
{
    public static function dataUri(?string $text, int $scale = 6): ?string
    {
        $text = trim((string) $text);

        if ($text === '' || $text === '—') {
            return null;
        }

        try {
            $options = new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
                'outputBase64' => true,
                'eccLevel' => EccLevel::M,
                'scale' => $scale,
                'addQuietzone' => true,
                'quietzoneSize' => 2,
            ]);

            return (new QRCode($options))->render($text);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}

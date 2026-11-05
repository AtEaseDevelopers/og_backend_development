<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * "Page 1 of 2" at the bottom right of every page of a document (client, 5 Nov 2026), stamped on the rendered PDF so
 * the total is known; the templates print no page number of their own.
 */
class PdfPageNumbers
{
    public static function stamp(PDF $pdf, float $size = 9): PDF
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans') ?? $metrics->getFont('Helvetica');
        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        // the placeholders are replaced per page; the width is measured on a two-digit sample
        $width = $metrics->getTextWidth('Page 00 of 00', $font, $size);
        $canvas->page_text($canvas->get_width() - 28 - $width, $canvas->get_height() - 24, $text, $font, $size, [0, 0, 0]);

        return $pdf;
    }
}

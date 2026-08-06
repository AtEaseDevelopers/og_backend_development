<?php

namespace App\Domains\Integration\Services;

/**
 * Simulated OCR extraction from hardcopy quotation uploads.
 */
class OcrExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $filePath, ?string $originalFilename = null): array
    {
        $hint = strtolower($originalFilename ?? basename($filePath));

        return [
            'customer_name' => str_contains($hint, 'demo') ? 'Demo Trading Sdn Bhd' : 'OCR Customer Sdn Bhd',
            'consignee_name' => 'Warehouse Receiver',
            'consignee_phone' => '0123456789',
            'delivery_address' => '88 Persiaran Tebrau, 80000 Johor Bahru',
            'delivery_postcode' => '80000',
            'delivery_state' => 'Johor',
            'delivery_city' => 'Johor Bahru',
            'item_name' => 'General Goods',
            'uom' => 'CTN',
            'quantity' => 10,
            'unit_price' => 80,
            'line_total' => 800,
            'notes' => 'Extracted via '.config('og.ocr.mode', 'simulate').' OCR — requires human review',
            'confidence' => 0.82,
            'source_file' => $filePath,
        ];
    }
}

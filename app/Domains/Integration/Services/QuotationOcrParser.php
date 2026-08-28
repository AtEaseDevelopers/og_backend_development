<?php

namespace App\Domains\Integration\Services;

class QuotationOcrParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $text, string $sourceFile): array
    {
        $lines = collect(preg_split("/\r\n|\n|\r/", $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        $customerName = $this->extractCustomerName($lines->all());
        $deliveryAddress = $this->extractDeliveryAddress($lines->all());
        $parsedLines = $this->extractLineItems($lines->all());

        if ($parsedLines === []) {
            $parsedLines = [[
                'item_name' => 'Extracted goods / service',
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 0,
            ]];
        }

        $confidence = $this->confidenceScore($customerName, $deliveryAddress, $parsedLines, $text);

        $first = $parsedLines[0];

        return [
            'customer_name' => $customerName,
            'consignee_name' => $customerName,
            'delivery_address' => $deliveryAddress,
            'delivery_postcode' => $this->extractPostcode($deliveryAddress),
            'delivery_state' => $this->extractState($deliveryAddress),
            'delivery_city' => $this->extractCity($deliveryAddress),
            'lines' => $parsedLines,
            'item_name' => $first['item_name'],
            'quantity' => $first['quantity'],
            'unit_price' => $first['unit_price'],
            'line_total' => $first['line_total'],
            'raw_text' => mb_substr($text, 0, 12000),
            'text_length' => mb_strlen($text),
            'notes' => 'Extracted from uploaded document — requires human review',
            'confidence' => $confidence,
            'source_file' => $sourceFile,
            'progress' => 100,
        ];
    }

    /** @param  list<string>  $lines */
    private function extractCustomerName(array $lines): string
    {
        foreach ($lines as $line) {
            if (preg_match('/(.+(?:Sdn\.?\s*Bhd\.?|Bhd|Enterprise|Trading|Logistics|Transport|Industries).+)/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        foreach ($lines as $line) {
            if (mb_strlen($line) >= 6 && ! preg_match('/^(quotation|invoice|date|tel|fax|email|no\.)/i', $line)) {
                return $line;
            }
        }

        return '';
    }

    /** @param  list<string>  $lines */
    private function extractDeliveryAddress(array $lines): string
    {
        $addressLines = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/\b\d{5}\b/', $line) || preg_match('/(jalan|lorong|lot|taman|persiaran|jln|kg\.|kampung)/i', $line)) {
                $addressLines[] = $line;

                if (isset($lines[$index + 1]) && preg_match('/(selangor|johor|penang|kuala lumpur|melaka|negeri|sarawak|sabah|wilayah)/i', $lines[$index + 1])) {
                    $addressLines[] = $lines[$index + 1];
                }

                break;
            }
        }

        return implode("\n", $addressLines);
    }

    /** @param  list<string>  $lines
     * @return list<array{item_name: string, quantity: float, unit_price: float, line_total: float}>
     */
    private function extractLineItems(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            if (preg_match('/(.+?)\s+(\d+(?:\.\d+)?)\s+(?:RM|MYR)?\s*(\d+(?:[,\.]\d{2})?)\s*$/iu', $line, $matches)) {
                $qty = (float) $matches[2];
                $rate = (float) str_replace(',', '', $matches[3]);

                $items[] = [
                    'item_name' => trim($matches[1]),
                    'quantity' => $qty,
                    'unit_price' => $rate,
                    'line_total' => round($qty * $rate, 2),
                ];

                continue;
            }

            if (preg_match('/(.+?)\s+(?:RM|MYR)\s*(\d+(?:[,\.]\d{2})?)\s*$/iu', $line, $matches)) {
                $rate = (float) str_replace(',', '', $matches[2]);

                $items[] = [
                    'item_name' => trim($matches[1]),
                    'quantity' => 1,
                    'unit_price' => $rate,
                    'line_total' => $rate,
                ];
            }
        }

        return $items;
    }

    /** @param  list<array{item_name: string, quantity: float, unit_price: float, line_total: float}>  $lines */
    private function confidenceScore(string $customerName, string $deliveryAddress, array $lines, string $text): float
    {
        $score = 0.35;

        if ($customerName !== '') {
            $score += 0.2;
        }

        if ($deliveryAddress !== '') {
            $score += 0.15;
        }

        if ($lines !== [] && ($lines[0]['unit_price'] ?? 0) > 0) {
            $score += 0.2;
        }

        if (mb_strlen($text) > 120) {
            $score += 0.1;
        }

        return min(round($score, 2), 0.99);
    }

    private function extractPostcode(string $address): ?string
    {
        return preg_match('/\b(\d{5})\b/', $address, $matches) ? $matches[1] : null;
    }

    private function extractState(string $address): ?string
    {
        $states = ['Selangor', 'Johor', 'Penang', 'Kuala Lumpur', 'Melaka', 'Sabah', 'Sarawak', 'Perak', 'Negeri Sembilan'];

        foreach ($states as $state) {
            if (stripos($address, $state) !== false) {
                return $state;
            }
        }

        return null;
    }

    private function extractCity(string $address): ?string
    {
        $lines = preg_split("/\r\n|\n|\r/", $address) ?: [];

        return trim($lines[0] ?? '') ?: null;
    }
}

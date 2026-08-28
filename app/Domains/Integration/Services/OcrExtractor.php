<?php

namespace App\Domains\Integration\Services;

use Illuminate\Support\Facades\Storage;

class OcrExtractor
{
    public function __construct(
        private DocumentTextReader $reader,
        private QuotationOcrParser $parser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(string $filePath, ?string $originalFilename = null): array
    {
        if (! Storage::disk('local')->exists($filePath)) {
            throw new \InvalidArgumentException('The uploaded document is missing from storage.');
        }

        $text = $this->reader->read($filePath, $originalFilename);

        return $this->parser->parse($text, $filePath);
    }
}

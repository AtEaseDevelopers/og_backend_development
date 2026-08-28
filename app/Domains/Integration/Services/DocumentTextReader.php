<?php

namespace App\Domains\Integration\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextReader
{
    public function read(string $storagePath, ?string $originalFilename = null): string
    {
        if (! Storage::disk('local')->exists($storagePath)) {
            throw new InvalidArgumentException('Uploaded document could not be found in storage.');
        }

        $absolutePath = Storage::disk('local')->path($storagePath);
        $mime = Storage::disk('local')->mimeType($storagePath) ?: '';
        $extension = strtolower(pathinfo($originalFilename ?: $storagePath, PATHINFO_EXTENSION));

        return match (true) {
            str_contains($mime, 'pdf') || $extension === 'pdf' => $this->readPdf($absolutePath),
            str_contains($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png'], true) => $this->readImage($absolutePath),
            default => throw new InvalidArgumentException('Unsupported document type. Upload a PDF, JPG, or PNG file.'),
        };
    }

    private function readPdf(string $absolutePath): string
    {
        $parser = new PdfParser;

        try {
            $text = trim($parser->parseFile($absolutePath)->getText());
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Unable to read text from the PDF document.');
        }

        if ($text === '') {
            throw new InvalidArgumentException('No readable text was found in the PDF. Try uploading a scanned image instead.');
        }

        return $this->normalizeText($text);
    }

    private function readImage(string $absolutePath): string
    {
        $tesseract = config('og.ocr.tesseract_path', 'tesseract');

        if ($this->commandExists($tesseract)) {
            $result = Process::timeout(120)->run([
                $tesseract,
                $absolutePath,
                'stdout',
                '-l',
                config('og.ocr.tesseract_lang', 'eng'),
            ]);

            if ($result->successful()) {
                $text = trim($result->output());

                if ($text !== '') {
                    return $this->normalizeText($text);
                }
            }
        }

        throw new InvalidArgumentException(
            'Image OCR requires Tesseract to be installed on the server. Install Tesseract or upload a text-based PDF instead.'
        );
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function commandExists(string $command): bool
    {
        $result = Process::run(['which', $command]);

        return $result->successful();
    }
}

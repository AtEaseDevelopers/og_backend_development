<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Integration\Services\OcrExtractor;
use App\Domains\Quotation\Models\OcrUpload;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class RunOcrExtraction
{
    public function __construct(private OcrExtractor $extractor) {}

    public function execute(OcrUpload $upload): OcrUpload
    {
        if (! in_array($upload->status, ['extracting', 'pending_review', 'draft'], true)) {
            throw new InvalidArgumentException('This upload cannot be extracted again.');
        }

        $this->updateProgress($upload, 15, 'Buffering uploaded document…');

        if (! Storage::disk('local')->exists($upload->file_path)) {
            throw new InvalidArgumentException('Uploaded document file is not readable.');
        }

        $this->updateProgress($upload, 40, 'Reading document content…');

        $extracted = $this->extractor->extract(
            $upload->file_path,
            $upload->original_filename,
        );

        $this->updateProgress($upload, 85, 'Structuring extracted fields…');

        $upload->update([
            'extracted_data' => array_merge($extracted, [
                'progress' => 100,
                'progress_message' => 'Extraction complete',
            ]),
            'status' => 'pending_review',
            'review_notes' => null,
        ]);

        return $upload->fresh();
    }

    private function updateProgress(OcrUpload $upload, int $progress, string $message): void
    {
        $upload->update([
            'status' => 'extracting',
            'extracted_data' => array_merge($upload->extracted_data ?? [], [
                'progress' => $progress,
                'progress_message' => $message,
            ]),
        ]);

        $upload->refresh();
    }
}

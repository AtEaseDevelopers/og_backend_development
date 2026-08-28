<?php

namespace App\Jobs;

use App\Domains\Quotation\Actions\RunOcrExtraction;
use App\Domains\Quotation\Models\OcrUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessOcrExtractionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ocrUploadId) {}

    public function handle(RunOcrExtraction $runOcrExtraction): void
    {
        $upload = OcrUpload::query()->find($this->ocrUploadId);

        if (! $upload || $upload->status !== 'extracting') {
            return;
        }

        try {
            $runOcrExtraction->execute($upload);
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'extracted_data' => array_merge($upload->extracted_data ?? [], [
                    'progress' => 100,
                    'progress_message' => 'Extraction failed',
                    'error' => $exception->getMessage(),
                ]),
                'review_notes' => $exception->getMessage(),
            ]);
        }
    }
}

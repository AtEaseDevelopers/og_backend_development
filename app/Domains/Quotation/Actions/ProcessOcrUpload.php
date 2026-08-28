<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\OcrUpload;
use App\Jobs\ProcessOcrExtractionJob;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessOcrUpload
{
    public function execute(UploadedFile $file, User $actor, ?int $branchId = null, ?int $customerId = null): OcrUpload
    {
        $path = $file->store('ocr-uploads', 'local');

        $upload = DB::transaction(function () use ($file, $actor, $branchId, $customerId, $path) {
            return OcrUpload::query()->create([
                'company_id' => CurrentCompany::id(),
                'branch_id' => $branchId ?? $actor->defaultBranch()?->id,
                'customer_id' => $customerId,
                'uploaded_by' => $actor->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'extracted_data' => [
                    'progress' => 5,
                    'progress_message' => 'Document buffered — waiting to extract',
                ],
                'status' => 'extracting',
            ]);
        });

        ProcessOcrExtractionJob::dispatch($upload->id)->afterResponse();

        return $upload;
    }

    public function reprocess(OcrUpload $upload): OcrUpload
    {
        if (! Storage::disk('local')->exists($upload->file_path)) {
            throw new \InvalidArgumentException('The original uploaded document is no longer available.');
        }

        $upload->update([
            'status' => 'extracting',
            'extracted_data' => [
                'progress' => 5,
                'progress_message' => 'Re-scan queued',
            ],
            'review_notes' => null,
        ]);

        ProcessOcrExtractionJob::dispatch($upload->id)->afterResponse();

        return $upload->fresh();
    }
}

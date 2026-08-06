<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Integration\Services\OcrExtractor;
use App\Domains\Quotation\Models\OcrUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessOcrUpload
{
    public function __construct(private OcrExtractor $extractor) {}

    public function execute(UploadedFile $file, User $actor, ?int $branchId = null, ?int $customerId = null): OcrUpload
    {
        $path = $file->store('ocr-uploads', 'local');

        return DB::transaction(function () use ($file, $actor, $branchId, $customerId, $path) {
            $extracted = $this->extractor->extract($path, $file->getClientOriginalName());

            return OcrUpload::query()->create([
                'branch_id' => $branchId ?? $actor->defaultBranch()?->id,
                'customer_id' => $customerId,
                'uploaded_by' => $actor->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'extracted_data' => $extracted,
                'status' => 'pending_review',
            ]);
        });
    }

    public function reprocess(OcrUpload $upload): OcrUpload
    {
        $extracted = $this->extractor->extract($upload->file_path, $upload->original_filename);
        $upload->update([
            'extracted_data' => $extracted,
            'status' => 'pending_review',
        ]);

        return $upload->fresh();
    }
}

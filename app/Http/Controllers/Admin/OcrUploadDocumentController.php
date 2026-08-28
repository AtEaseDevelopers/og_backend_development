<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Quotation\Models\OcrUpload;
use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OcrUploadDocumentController extends Controller
{
    public function __invoke(OcrUpload $ocrUpload): StreamedResponse
    {
        if ($companyId = CurrentCompany::id()) {
            abort_unless((int) $ocrUpload->company_id === (int) $companyId, 404);
        }

        abort_unless(Storage::disk('local')->exists($ocrUpload->file_path), 404);

        return Storage::disk('local')->response(
            $ocrUpload->file_path,
            $ocrUpload->original_filename ?: basename($ocrUpload->file_path),
        );
    }
}

<?php

namespace Database\Seeders;

use App\Domains\Quotation\Models\OcrUpload;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class OcrQuotationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $removed = 0;

        OcrUpload::query()->get()->each(function (OcrUpload $upload) use (&$removed): void {
            if (! Storage::disk('local')->exists($upload->file_path)) {
                $upload->delete();
                $removed++;
            }
        });

        $this->command?->info("Removed {$removed} OCR upload(s) without real buffered documents.");
        $this->command?->info('Upload a real PDF or image via OCR Quotation Processing to test extraction.');
    }
}

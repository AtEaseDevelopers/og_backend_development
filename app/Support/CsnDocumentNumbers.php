<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;

class CsnDocumentNumbers
{
    public function __construct(private DocumentNumberingService $numbering) {}

    /** @param  array<string, mixed>  $data */
    public function assign(array $data, Branch|int $branch): array
    {
        $data['number'] ??= $this->numbering->next($branch, DocumentType::Csn);
        $data['do_number'] ??= $this->numbering->next($branch, DocumentType::Do);
        $data['job_no'] ??= $this->numbering->next($branch, DocumentType::JobSheet);

        if (empty($data['job_date'])) {
            $data['job_date'] = $data['issued_at'] ?? now()->toDateString();
        }

        return $data;
    }
}

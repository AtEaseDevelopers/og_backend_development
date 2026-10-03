<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Enums\DocumentType;
use App\Services\DocumentNumberingService;

class CsnDocumentNumbers
{
    public function __construct(private DocumentNumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  string|null  $csnPrefix  SA-location prefix printed on the CSN number (section H)
     */
    public function assign(array $data, Branch|int $branch, ?string $csnPrefix = null): array
    {
        $data['number'] ??= $this->numbering->next($branch, DocumentType::Csn, $csnPrefix);
        $data['do_number'] ??= $this->numbering->next($branch, DocumentType::Do);
        $data['job_no'] ??= $this->numbering->next($branch, DocumentType::JobSheet);

        if (empty($data['job_date'])) {
            $data['job_date'] = $data['issued_at'] ?? now()->toDateString();
        }

        return $data;
    }
}

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
     * @param  string|null  $csnPrefix  the branch's CSN code printed on the CSN number (KL, SA, …)
     * @param  \DateTimeInterface|null  $orderedAt  when the order was placed (the CSN number's YYMM)
     */
    public function assign(array $data, Branch|int $branch, ?string $csnPrefix = null, ?\DateTimeInterface $orderedAt = null): array
    {
        // the client's CSN number format XX-YYMM##### (user, 26 Oct 2026)
        $data['number'] ??= $this->numbering->nextCsn($branch, $csnPrefix, $orderedAt);
        $data['do_number'] ??= $this->numbering->next($branch, DocumentType::Do);
        $data['job_no'] ??= $this->numbering->next($branch, DocumentType::JobSheet);

        if (empty($data['job_date'])) {
            $data['job_date'] = $data['issued_at'] ?? now()->toDateString();
        }

        return $data;
    }
}

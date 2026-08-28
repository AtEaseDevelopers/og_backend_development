<?php

namespace App\Support;

use App\Domains\Quotation\Models\OcrUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class OcrQuotationData
{
    /** @return array<string, mixed> */
    public function queueRow(OcrUpload $upload): array
    {
        $status = $this->normalizeStatus($upload->status);
        $extracted = $upload->extracted_data ?? [];

        return [
            'id' => $upload->id,
            'filename' => $upload->original_filename ?: basename((string) $upload->file_path),
            'uploaded_by' => $upload->uploader?->name ?? '—',
            'timestamp' => $upload->created_at?->format('d/m/Y H:i') ?? '—',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'progress' => (int) ($extracted['progress'] ?? 0),
            'progress_message' => (string) ($extracted['progress_message'] ?? ''),
            'can_review' => in_array($status, ['pending_review', 'draft'], true),
            'is_failed' => $status === 'failed',
            'error' => (string) ($extracted['error'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    public function verificationPanel(OcrUpload $upload): array
    {
        $extracted = $upload->extracted_data ?? [];
        $lines = $this->normalizeLines($extracted);
        $confidence = (float) ($extracted['confidence'] ?? 0.82);

        return [
            'id' => $upload->id,
            'filename' => $upload->original_filename ?: basename((string) $upload->file_path),
            'confidence' => $confidence,
            'confidence_label' => round($confidence * 100).'% Confidence',
            'customer_name' => (string) ($extracted['customer_name'] ?? ''),
            'delivery_address' => (string) ($extracted['delivery_address'] ?? ''),
            'lines' => $lines,
            'preview' => $this->previewBlocks($extracted, $lines),
            'document_url' => route('filament.admin.ocr-uploads.document', [
                'tenant' => CurrentCompany::get()?->code ?? 'KL',
                'ocrUpload' => $upload,
            ]),
            'mime_type' => $this->guessMimeType($upload),
        ];
    }

    /** @param  array<string, mixed>  $extracted */
    public function normalizeLines(array $extracted): array
    {
        if (! empty($extracted['lines']) && is_array($extracted['lines'])) {
            return collect($extracted['lines'])
                ->map(fn (array $line): array => [
                    'description' => (string) ($line['item_name'] ?? $line['description'] ?? ''),
                    'quantity' => (string) ($line['quantity'] ?? '1'),
                    'rate' => number_format((float) ($line['unit_price'] ?? $line['rate'] ?? 0), 2, '.', ''),
                ])
                ->values()
                ->all();
        }

        return [[
            'description' => (string) ($extracted['item_name'] ?? ''),
            'quantity' => (string) ($extracted['quantity'] ?? '1'),
            'rate' => number_format((float) ($extracted['unit_price'] ?? 0), 2, '.', ''),
        ]];
    }

    /** @param  array<string, mixed>  $corrections */
    public function mergeVerification(array $extracted, array $corrections): array
    {
        $merged = array_merge($extracted, [
            'customer_name' => $corrections['customer_name'] ?? $extracted['customer_name'] ?? null,
            'delivery_address' => $corrections['delivery_address'] ?? $extracted['delivery_address'] ?? null,
        ]);

        if (! empty($corrections['lines'])) {
            $merged['lines'] = collect($corrections['lines'])
                ->map(fn (array $line): array => [
                    'item_name' => $line['description'] ?? '',
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'unit_price' => (float) ($line['rate'] ?? 0),
                    'line_total' => round((float) ($line['quantity'] ?? 0) * (float) ($line['rate'] ?? 0), 2),
                ])
                ->all();

            $first = $merged['lines'][0] ?? null;
            if ($first) {
                $merged['item_name'] = $first['item_name'];
                $merged['quantity'] = $first['quantity'];
                $merged['unit_price'] = $first['unit_price'];
                $merged['line_total'] = $first['line_total'];
            }
        }

        return $merged;
    }

    /** @return Builder<OcrUpload> */
    public function queueQuery(): Builder
    {
        $query = OcrUpload::query()
            ->with(['uploader', 'customer', 'quotation'])
            ->orderByDesc('id');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function queueRows(): Collection
    {
        return $this->queueQuery()
            ->limit(20)
            ->get()
            ->map(fn (OcrUpload $upload): array => $this->queueRow($upload));
    }

    public function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'completed',
            'draft' => 'draft',
            'extracting' => 'extracting',
            'failed' => 'failed',
            'rejected' => 'rejected',
            default => 'pending_review',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'COMPLETED',
            'extracting' => 'EXTRACTING…',
            'failed' => 'FAILED',
            'draft' => 'DRAFT',
            'rejected' => 'REJECTED',
            default => 'PENDING REVIEW',
        };
    }

    /** @param  list<array{description: string, quantity: string, rate: string}>  $lines
     * @return list<array{label: string, text: string, tone: string}>
     */
    private function previewBlocks(array $extracted, array $lines): array
    {
        $blocks = [
            ['label' => 'Customer', 'text' => (string) ($extracted['customer_name'] ?? ''), 'tone' => 'green'],
            ['label' => 'Address', 'text' => (string) ($extracted['delivery_address'] ?? ''), 'tone' => 'blue'],
        ];

        foreach ($lines as $index => $line) {
            $blocks[] = [
                'label' => 'Line '.($index + 1),
                'text' => trim($line['description'].' · Qty '.$line['quantity'].' · RM '.$line['rate']),
                'tone' => 'red',
            ];
        }

        return array_values(array_filter($blocks, fn (array $block): bool => filled($block['text'])));
    }

    private function guessMimeType(OcrUpload $upload): string
    {
        if (Storage::disk('local')->exists($upload->file_path)) {
            return Storage::disk('local')->mimeType($upload->file_path) ?: 'application/octet-stream';
        }

        $extension = strtolower(pathinfo((string) $upload->original_filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    public function hasExtractingUploads(): bool
    {
        return $this->queueQuery()->where('status', 'extracting')->exists();
    }
}

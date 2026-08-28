<?php

namespace App\Filament\Resources\OcrUploadResource\Pages;

use App\Domains\Quotation\Actions\ApproveOcrQuotation;
use App\Domains\Quotation\Actions\ProcessOcrUpload;
use App\Domains\Quotation\Models\OcrUpload;
use App\Filament\Resources\OcrUploadResource;
use App\Support\CurrentCompany;
use App\Support\OcrQuotationData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class ListOcrUploads extends ListRecords
{
    use WithFileUploads;

    protected static string $resource = OcrUploadResource::class;

    protected static string $view = 'filament.resources.ocr-upload-resource.pages.list-ocr-uploads';

    public $uploadFile;

    public ?int $selectedUploadId = null;

    public ?int $awaitingExtractionId = null;

    public string $verifyCustomerName = '';

    public string $verifyDeliveryAddress = '';

    /** @var list<array{description: string, quantity: string, rate: string}> */
    public array $verifyLines = [];

    public function getHeading(): string
    {
        return 'OCR Quotation Processing';
    }

    public function getSubheading(): ?string
    {
        return 'Upload hardcopy quotations, review extracted data and create verified quotes.';
    }

    /** @return array<string, mixed>|null */
    public function getVerificationData(): ?array
    {
        if (! $this->selectedUploadId) {
            return null;
        }

        $upload = $this->findUpload($this->selectedUploadId);

        if (! $upload) {
            return null;
        }

        return app(OcrQuotationData::class)->verificationPanel($upload);
    }

    /** @return list<array<string, mixed>> */
    public function getQueueRows(): array
    {
        return app(OcrQuotationData::class)->queueRows()->all();
    }

    public function hasExtractingUploads(): bool
    {
        return app(OcrQuotationData::class)->hasExtractingUploads();
    }

    public function pollExtractionQueue(): void
    {
        if (! $this->awaitingExtractionId) {
            return;
        }

        $upload = $this->findUpload($this->awaitingExtractionId);

        if (! $upload) {
            $this->awaitingExtractionId = null;

            return;
        }

        if (in_array($upload->status, ['pending_review', 'draft'], true)) {
            $this->awaitingExtractionId = null;
            $this->openVerification((int) $upload->id);

            Notification::make()
                ->title('Extraction complete')
                ->body('Review the extracted fields before creating the quotation.')
                ->success()
                ->send();

            return;
        }

        if ($upload->status === 'failed') {
            Notification::make()
                ->title('Extraction failed')
                ->body($upload->extracted_data['error'] ?? $upload->review_notes ?? 'Unable to extract this document.')
                ->danger()
                ->send();

            $this->awaitingExtractionId = null;
        }
    }

    public function processUpload(): void
    {
        $this->validate([
            'uploadFile' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            /** @var TemporaryUploadedFile $file */
            $file = $this->uploadFile;
            $upload = app(ProcessOcrUpload::class)->execute(
                $file,
                auth()->user(),
                CurrentCompany::branchId() ?? auth()->user()?->defaultBranch()?->id,
            );

            $this->uploadFile = null;
            $this->awaitingExtractionId = (int) $upload->id;

            Notification::make()
                ->title('Document buffered')
                ->body('The uploaded file is being extracted. This page will refresh automatically.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function openVerification(int $uploadId): void
    {
        $upload = $this->findUpload($uploadId);

        if (! $upload) {
            return;
        }

        if ($upload->status === 'extracting') {
            $this->awaitingExtractionId = $uploadId;

            Notification::make()
                ->title('Extraction in progress')
                ->body('Please wait while the document is processed.')
                ->info()
                ->send();

            return;
        }

        if (! in_array($upload->status, ['pending_review', 'draft'], true)) {
            Notification::make()
                ->title('Not ready for review')
                ->body(match ($upload->status) {
                    'failed' => $upload->extracted_data['error'] ?? 'Extraction failed for this document.',
                    'approved' => 'This upload has already been converted into a quotation.',
                    default => 'This upload is not available for verification.',
                })
                ->warning()
                ->send();

            return;
        }

        $panel = app(OcrQuotationData::class)->verificationPanel($upload);

        $this->selectedUploadId = $uploadId;
        $this->verifyCustomerName = $panel['customer_name'];
        $this->verifyDeliveryAddress = $panel['delivery_address'];
        $this->verifyLines = $panel['lines'] ?: [['description' => '', 'quantity' => '1', 'rate' => '0.00']];
    }

    public function closeVerification(): void
    {
        $this->selectedUploadId = null;
        $this->verifyCustomerName = '';
        $this->verifyDeliveryAddress = '';
        $this->verifyLines = [];
    }

    public function addVerifyLine(): void
    {
        $this->verifyLines[] = ['description' => '', 'quantity' => '1', 'rate' => '0.00'];
    }

    public function removeVerifyLine(int $index): void
    {
        if (count($this->verifyLines) <= 1) {
            return;
        }

        unset($this->verifyLines[$index]);
        $this->verifyLines = array_values($this->verifyLines);
    }

    public function saveDraft(): void
    {
        $upload = $this->findUpload($this->selectedUploadId);

        if (! $upload) {
            return;
        }

        $upload->update([
            'extracted_data' => app(OcrQuotationData::class)->mergeVerification(
                $upload->extracted_data ?? [],
                $this->verificationPayload(),
            ),
            'status' => 'draft',
            'review_notes' => 'Saved as draft during OCR verification',
        ]);

        Notification::make()->title('Draft saved')->success()->send();
    }

    public function rescanDocument(): void
    {
        $upload = $this->findUpload($this->selectedUploadId);

        if (! $upload) {
            return;
        }

        try {
            app(ProcessOcrUpload::class)->reprocess($upload);
            $this->awaitingExtractionId = (int) $upload->id;
            $this->closeVerification();

            Notification::make()
                ->title('Re-scan queued')
                ->body('The document is being extracted again.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function verifyAndCreateQuote(): void
    {
        $upload = $this->findUpload($this->selectedUploadId);

        if (! $upload) {
            return;
        }

        $payload = $this->verificationPayload();

        try {
            $upload = app(ApproveOcrQuotation::class)->execute($upload, $payload, auth()->user());

            Notification::make()
                ->title('Quotation created')
                ->body($upload->quotation?->number ?? 'Draft quotation created from OCR.')
                ->success()
                ->send();

            $this->closeVerification();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /** @return array<string, mixed> */
    protected function verificationPayload(): array
    {
        return [
            'customer_name' => $this->verifyCustomerName,
            'delivery_address' => $this->verifyDeliveryAddress,
            'lines' => $this->verifyLines,
        ];
    }

    protected function findUpload(?int $uploadId): ?OcrUpload
    {
        if (! $uploadId) {
            return null;
        }

        $query = OcrUpload::query()->whereKey($uploadId);

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->first();
    }
}

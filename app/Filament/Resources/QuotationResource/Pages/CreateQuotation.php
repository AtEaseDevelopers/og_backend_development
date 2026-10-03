<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\Quotation\Actions\LinkPortalEnquiryQuotation;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\DocumentType;
use App\Enums\PortalEnquiryStatus;
use App\Filament\Resources\QuotationResource;
use App\Support\CurrentCompany;
use App\Support\PortalEnquiryQuotationPrefill;
use App\Support\QuotationMatrix;
use App\Services\DocumentNumberingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    use \App\Filament\Resources\QuotationResource\Concerns\ValidatesPriceOverrides;

    protected static string $resource = QuotationResource::class;

    /** @var list<string> */
    protected array $matrixColumns = [];

    /** @var list<array<string, mixed>> */
    protected array $matrixRows = [];

    public ?int $portalEnquiryId = null;

    public function mount(): void
    {
        $enquiryId = session()->pull('portal_enquiry_id') ?? request()->query('portal_enquiry_id');
        $this->portalEnquiryId = filled($enquiryId) ? (int) $enquiryId : null;

        parent::mount();

        if ($this->portalEnquiryId) {
            $this->applyPortalEnquiryPrefill();
        }
    }

    protected function applyPortalEnquiryPrefill(): void
    {
        $enquiry = $this->findEnquiry((int) $this->portalEnquiryId);

        if (! $enquiry) {
            Notification::make()
                ->title('Portal enquiry not found')
                ->body('The enquiry could not be loaded for this company. Check you are on the correct branch tenant.')
                ->warning()
                ->send();

            return;
        }

        if ($enquiry->status === PortalEnquiryStatus::Quoted) {
            Notification::make()
                ->title('Quotation already created')
                ->body('This enquiry is linked to quotation '.$enquiry->quotation?->number.'.')
                ->warning()
                ->send();

            return;
        }

        // Section A: keep the enquiry locked while this order is being prepared (2s heartbeat below)
        if (auth()->user() && ! $enquiry->acquireLock(auth()->user())) {
            Notification::make()
                ->title('Currently being attended')
                ->body(($enquiry->locker?->name ?? 'Another user').' is already preparing this enquiry.')
                ->warning()
                ->send();

            $this->redirect(\App\Filament\Pages\Orders::getUrl(), navigate: false);

            return;
        }

        $prefill = app(PortalEnquiryQuotationPrefill::class)->formState($enquiry);

        $this->data = array_merge($this->data ?? [], $prefill);
        $this->form->fill($this->data);

        if ($enquiry->status === PortalEnquiryStatus::Pending) {
            $enquiry->update(['status' => PortalEnquiryStatus::InReview]);
        }

        Notification::make()
            ->title('Enquiry data loaded')
            ->body('Fields prefilled from '.$enquiry->reference_no.'. Add pricing and save the quotation.')
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->portalEnquiryId && empty($data['customer_id'])) {
            $enquiry = PortalEnquiry::query()->find($this->portalEnquiryId);

            if ($enquiry?->customer_id) {
                $data['customer_id'] = (string) $enquiry->customer_id;
            }
        }

        $branch = Branch::query()->findOrFail($data['branch_id']);

        $this->matrixColumns = $data['matrix_columns'] ?? ['Seremban', 'Melaka', 'Johor'];
        $this->matrixRows = $data['matrix_rows'] ?? [];

        $data = $this->enforcePriceOverrideRules($data, $this->matrixColumns, $this->matrixRows);

        unset($data['matrix_columns'], $data['matrix_rows']);

        $data['number'] = app(DocumentNumberingService::class)->next($branch, DocumentType::Quotation);
        $data['created_by'] = auth()->id();
        $data['subtotal'] = 0;
        $data['total_amount'] = (float) ($data['tax_amount'] ?? 0);

        if (empty($data['title'])) {
            $data['title'] = 'Quotation Of Transport Charges';
        }

        if ($this->portalEnquiryId) {
            $data['portal_enquiry_id'] = $this->portalEnquiryId;
            $data['pricing_source'] = $data['pricing_source'] ?? 'portal';

            $enquiry = PortalEnquiry::query()->find($this->portalEnquiryId);

            // Section A: the enquiry owner is fixed on every order created from it
            if ($enquiry?->salesperson_id) {
                $data['salesperson_id'] = $enquiry->salesperson_id;
                $data['salesperson_locked'] = true;
                $data['sa_location_id'] = $data['sa_location_id'] ?? $enquiry->sa_location_id ?? $enquiry->salesperson?->sa_location_id;
            }
        }

        if (empty($data['sa_location_id']) && ! empty($data['salesperson_id'])) {
            $data['sa_location_id'] = \App\Models\User::query()->find($data['salesperson_id'])?->sa_location_id;
        }

        $data['version'] = 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        // each order is its own version root until it is revised
        $this->record->forceFill(['root_quotation_id' => $this->record->id])->saveQuietly();

        app(QuotationMatrix::class)->sync(
            $this->record,
            $this->matrixColumns,
            $this->matrixRows,
        );

        \App\Domains\Quotation\Models\QuotationStatusLog::query()->create([
            'quotation_id' => $this->record->id,
            'from_status' => null,
            'to_status' => \App\Enums\QuotationStatus::Draft->value,
            'user_id' => auth()->id(),
            'remarks' => $this->portalEnquiryId ? 'Order created from customer enquiry' : 'Order created (admin entry)',
        ]);

        if ($this->portalEnquiryId) {
            $enquiry = PortalEnquiry::query()->find($this->portalEnquiryId);

            if ($enquiry) {
                app(LinkPortalEnquiryQuotation::class)->execute(
                    $enquiry,
                    $this->record,
                    auth()->user(),
                );
            }
        }

        $this->record->refresh();
    }

    /** Polled every 2 seconds while the form is open (see filament.hooks.enquiry-lock-heartbeat). */
    public function heartbeat(): void
    {
        if ($this->portalEnquiryId && auth()->user()) {
            PortalEnquiry::query()->find($this->portalEnquiryId)?->heartbeat(auth()->user());
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->portalEnquiryId && auth()->user()) {
            PortalEnquiry::query()->find($this->portalEnquiryId)?->releaseLock(auth()->user());
        }

        return parent::getRedirectUrl();
    }

    private function findEnquiry(int $enquiryId): ?PortalEnquiry
    {
        $query = PortalEnquiry::query()
            ->with(['customer.pics', 'customer.addresses', 'branch', 'quotation']);

        if ($companyId = CurrentCompany::id()) {
            $query->where(function ($builder) use ($companyId): void {
                $builder->where('company_id', $companyId)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_id', $companyId));
            });
        }

        return $query->find($enquiryId);
    }
}

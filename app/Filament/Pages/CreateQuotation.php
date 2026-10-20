<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Lead;
use App\Domains\Quotation\Models\Quote;
use App\Enums\OrderType;
use App\Enums\ServiceType;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\QuoteResource;
use App\Support\CurrentCompany;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Create quotation (Operations → Quotation, user 20 Oct 2026): the Create order page for a quotation. A customer
 * or a lead ("+ New lead" adds one to Master Data → Leads), the quotation date (today), PIC and billing address,
 * and the consignor & consignee blocks with products at the default price (editable: the price quoted). Saved as a
 * Pending confirmation quotation; "Accept by customer" on the quotation list makes the order from it later.
 */
class CreateQuotation extends CreateOrder
{
    protected static ?string $slug = 'quotations/new';

    /** Editing a quotation (?quote=id): its id. */
    public ?int $quoteId = null;

    public function mount(): void
    {
        parent::mount();

        $id = (int) request()->query('quote', 0);

        if ($id > 0) {
            $quote = Quote::query()->when(CurrentCompany::id(), fn ($q, $companyId) => $q->where('company_id', $companyId))->find($id);
            abort_unless($quote && $quote->isPending(), 404);

            $this->quoteId = $quote->id;
            $this->form = array_merge($this->formTemplate(), (array) $quote->form, [
                'customer_id' => $quote->customer_id ? (string) $quote->customer_id : '',
                'lead_id' => $quote->lead_id ? (string) $quote->lead_id : '',
                'party' => $quote->customer_id ? 'customer:'.$quote->customer_id : ($quote->lead_id ? 'lead:'.$quote->lead_id : ''),
                'quote_date' => $quote->quote_date?->toDateString() ?? today()->toDateString(),
            ]);
            // one product per row (an older quotation's block of several products becomes one row each)
            $rows = [];

            foreach ((array) ($quote->pairs ?: [$this->pairTemplate()]) as $pair) {
                foreach (($pair['items'] ?? []) ?: [$this->itemTemplate()] as $item) {
                    $rows[] = array_merge($this->pairTemplate(), $pair, ['items' => [array_merge($this->itemTemplate(), $item)]]);
                }
            }

            $this->pairs = $rows;
            $this->orders = [$this->snapshotActive()];
        }
    }

    public static function getRelativeRouteName(): string
    {
        return 'quotations.create';
    }

    public function getTitle(): string
    {
        return $this->quoteId ? 'Edit quotation' : 'Create quotation';
    }

    public function isQuote(): bool
    {
        return true;
    }

    public function quoteNumber(): ?string
    {
        return $this->quoteId ? 'Quotation '.Quote::query()->whereKey($this->quoteId)->value('number') : null;
    }

    public function pageCopy(): array
    {
        $number = $this->quoteId ? Quote::query()->whereKey($this->quoteId)->value('number') : null;

        return [
            'back_url' => QuoteResource::getUrl('index'),
            'back_label' => '← Back to quotations',
            'crumb' => 'Operations / Quotation',
            'title' => $number ? 'Edit quotation '.$number : 'Create quotation',
            'sub' => 'Quote a customer or a lead. When the customer agrees, use Accept by customer on the quotation list.',
            'cancel_url' => QuoteResource::getUrl('index'),
            'submit' => $this->quoteId ? 'Save quotation' : 'Create quotation',
        ];
    }

    /** @return array<string, mixed> */
    protected function formTemplate(): array
    {
        return parent::formTemplate() + [
            // "customer:12" or "lead:3"
            'party' => '',
            'lead_id' => '',
            'quote_date' => today()->toDateString(),
        ];
    }

    /**
     * The price list's minimum charge of a product row (user, 21 Oct 2026): with a pickup location the goods are
     * collected, so the pick-up minimum applies (e.g. RM 250); without one only the drop-off / construction site
     * minimums do. The highest applies; a row below it is topped up.
     */
    public function pairMinimum(int $index, array $lineTotals): ?array
    {
        if (! isset($this->pairs[$index])) {
            return null;
        }

        $service = $this->pairs[$index]['service_type'] ?? null;
        $this->pairs[$index]['service_type'] = filled($this->pairs[$index]['from_location_id'] ?? null) ? ServiceType::Pickup->value : ServiceType::Store->value;

        try {
            return parent::pairMinimum($index, $lineTotals);
        } finally {
            $this->pairs[$index]['service_type'] = $service;
        }
    }

    /** One quotation per page. */
    public function canAddOrder(): bool
    {
        return false;
    }

    /** Prices show at once (the default price, editable). */
    public function showPrices(): bool
    {
        return true;
    }

    public function showReceivedThrough(): bool
    {
        return false;
    }

    /** The default price is quoted (not the customer's special price). */
    protected function pricingCustomerId(): ?int
    {
        return null;
    }

    /** @return array<string, string> */
    public function catalogOptions(string $type): array
    {
        return app(\App\Support\QuotationPricingLookup::class)->catalogOptionsForType($type ?: 'uom', null);
    }

    /** Customers and leads of the company for the picker ("customer:12" / "lead:3"). @return array<string, array{label: string, lead: bool}> */
    public function partyOptions(): array
    {
        $companyId = CurrentCompany::id();
        $customers = Customer::query()->when($companyId, fn ($q, $id) => $q->where('company_id', $id))->orderBy('company_name')->get(['id', 'code', 'company_name'])
            ->mapWithKeys(fn (Customer $c) => ['customer:'.$c->id => ['label' => trim(($c->code ? $c->code.' — ' : '').$c->company_name), 'lead' => false]]);
        $leads = Lead::query()->when($companyId, fn ($q, $id) => $q->where('company_id', $id))->whereNull('customer_id')->orderBy('name')->get(['id', 'name'])
            ->mapWithKeys(fn (Lead $lead) => ['lead:'.$lead->id => ['label' => $lead->name, 'lead' => true]]);

        return $leads->all() + $customers->all();
    }

    public function customerName(): ?string
    {
        if (filled($this->form['customer_id'] ?? null)) {
            return parent::customerName();
        }

        return filled($this->form['lead_id'] ?? null) ? Lead::query()->whereKey($this->form['lead_id'])->value('name') : null;
    }

    /** A lead (not a customer yet): Cash or COD (Credit / Term needs a credit customer). */
    public function orderTypeOptions(): array
    {
        if (blank($this->form['customer_id'] ?? null) && filled($this->form['lead_id'] ?? null)) {
            return [OrderType::Cash->value => OrderType::Cash->getLabel(), OrderType::Cod->value => OrderType::Cod->getLabel()];
        }

        return parent::orderTypeOptions();
    }

    public function updatingForm(mixed $value, ?string $key = null): void
    {
        if ($key === 'party') {
            parent::updatingForm(str_starts_with((string) $value, 'customer:') ? substr((string) $value, 9) : '', 'customer_id');

            return;
        }

        parent::updatingForm($value, $key);
    }

    /** Picking a customer fills section 1 as on Create order; picking a lead fills it with the lead's details. */
    public function updatedForm($value, string $key): void
    {
        if ($key !== 'party') {
            parent::updatedForm($value, $key);

            return;
        }

        [$type, $id] = array_pad(explode(':', (string) $value, 2), 2, '');

        if ($type === 'customer' && $id !== '') {
            $this->form['lead_id'] = '';
            $this->form['customer_id'] = $id;
            parent::updatedForm($id, 'customer_id');

            return;
        }

        $previousPic = [
            'customer_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
            'customer_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
        ];
        $lead = $type === 'lead' && $id !== '' ? Lead::query()->find($id) : null;
        $this->form['lead_id'] = $lead ? (string) $lead->id : '';
        $this->form['customer_id'] = '';
        parent::updatedForm('', 'customer_id');

        if ($lead) {
            $this->form['customer_address'] = (string) ($lead->billing_address ?: $lead->address);
            $this->form['customer_pic_name'] = (string) $lead->pic_name;
            $this->form['customer_pic_phone'] = (string) $lead->contact;
            $this->syncConsignorPic($previousPic);
        }
    }

    /** "+ New lead": added to Master Data → Leads and picked for this quotation. */
    public function createLeadAction(): Action
    {
        return Action::make('createLead')
            ->label('New lead')
            ->modalHeading('New lead')
            ->modalDescription('A customer without a record yet. The lead is kept in Master Data → Leads; it becomes a customer when its quotation is made into an order.')
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->form(LeadResource::leadFields())
            ->modalSubmitActionLabel('Create lead')
            ->action(function (array $data): void {
                $lead = Lead::query()->create($data + ['company_id' => CurrentCompany::id()]);
                $this->stashActive();
                $this->form['party'] = 'lead:'.$lead->id;
                $this->updatedForm($this->form['party'], 'party');
                $this->stashActive();
                Notification::make()->title('Lead '.$lead->name.' created and selected')->success()->send();
            });
    }

    protected function orderValidationRules(): array
    {
        $rules = parent::orderValidationRules();
        unset($rules['form.customer_id']);

        return array_merge($rules, [
            'form.party' => ['required', 'regex:/^(customer|lead):\d+$/'],
            'form.quote_date' => 'required|date',
            // no salesperson and no DO number on a quotation (user, 21 Oct 2026)
            'form.salesperson_id' => 'nullable|exists:users,id',
            'pairs.*.customer_do_number' => 'nullable|string|max:100',
            // each product's drop-off location: its price list price, and its special price once accepted
            'pairs.*.to_location_id' => 'required|exists:locations,id',
        ]);
    }

    protected function orderValidationMessages(): array
    {
        return parent::orderValidationMessages() + [
            'form.party.required' => 'Select a customer or a lead (or create a new lead).',
            'pairs.*.to_location_id.required' => 'Select the drop-off location.',
            'pairs.*.items.*.item_name.required' => 'Select the product.',
        ];
    }

    /** Saves the quotation (Pending confirmation) and goes back to the quotation list. */
    public function save(): void
    {
        $this->stashActive();
        $this->resetErrorBag();
        $this->errorOrder = null;
        $this->validateWorkingOrder();

        $branch = CurrentCompany::branch();

        if (! $branch) {
            Notification::make()->title('Select a branch first')->danger()->send();

            return;
        }

        try {
            $quote = DB::transaction(function () use ($branch) {
                $quote = $this->quoteId ? Quote::query()->lockForUpdate()->findOrFail($this->quoteId) : null;

                if ($quote && ! $quote->isPending()) {
                    throw new InvalidArgumentException('Quotation '.$quote->number.' is already an order.');
                }

                $quote ??= new Quote([
                    'number' => Quote::nextNumber(),
                    'status' => Quote::PENDING,
                    'created_by' => auth()->id(),
                ]);

                [$type, $id] = explode(':', (string) $this->form['party'], 2);

                $quote->fill([
                    'company_id' => CurrentCompany::id(),
                    'branch_id' => $branch->id,
                    'customer_id' => $type === 'customer' ? (int) $id : null,
                    'lead_id' => $type === 'lead' ? (int) $id : null,
                    'quote_date' => $this->form['quote_date'],
                    'form' => [
                        'customer_address' => trim((string) ($this->form['customer_address'] ?? '')),
                        'customer_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
                        'customer_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
                        'salesperson_id' => $this->form['salesperson_id'] ?: null,
                        'order_type' => $this->form['order_type'] ?? null,
                    ],
                    'pairs' => $this->quotePairs(),
                    'total_amount' => $this->pairTotals()['total'],
                ])->save();

                return $quote;
            });
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title('Quotation '.$quote->number.' '.($this->quoteId ? 'saved' : 'created'))
            ->body(($quote->partyName() ?? '').' · RM '.number_format((float) $quote->total_amount, 2).' · Pending confirmation')
            ->success()
            ->send();

        $this->redirect(QuoteResource::getUrl('index'), navigate: false);
    }

    /** The blocks as kept on the quotation (no picked files). @return list<array<string, mixed>> */
    protected function quotePairs(): array
    {
        return array_map(function (array $pair): array {
            unset($pair['pickup_restore'], $pair['pickup_from_location_id']);
            $pair['items'] = array_map(function (array $item): array {
                $item['photos'] = [];
                $item['existing_photos'] = [];

                return array_filter($item, fn ($value) => ! $value instanceof TemporaryUploadedFile);
            }, array_values($pair['items'] ?? []));

            return $pair;
        }, array_values($this->pairs));
    }
}

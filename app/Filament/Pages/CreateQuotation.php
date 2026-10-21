<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Lead;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quote;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\ServiceType;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\QuoteResource;
use App\Filament\Resources\UomResource;
use App\Services\Imports\ClientPriceListImporter;
use App\Support\CurrentCompany;
use App\Support\UomPriceRows;
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
            // the new product and the special price block of each row
            'pairs.*.items.*.product.sub_category' => 'nullable|string|max:100',
            'pairs.*.items.*.product.base_item' => 'nullable|string|max:150',
            'pairs.*.items.*.special.pickup_location' => 'nullable|string|max:150',
            'pairs.*.items.*.special.effective_from' => 'nullable|date',
            'pairs.*.items.*.special.expires_on' => 'nullable|date',
            ...array_fill_keys(array_map(fn (string $column) => 'pairs.*.items.*.product.'.$column, array_keys(UomResource::LIMITS)), 'nullable|numeric|min:0'),
            ...array_fill_keys(array_map(fn (string $column) => 'pairs.*.items.*.special.'.$column, ['pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge']), 'nullable|numeric|min:0'),
            ...array_fill_keys(array_merge(...array_map(fn (string $l) => ["pairs.*.items.*.special.range_{$l}_from", "pairs.*.items.*.special.range_{$l}_to", "pairs.*.items.*.special.range_{$l}_price"], UomPriceRows::RANGES)), 'nullable|numeric|min:0'),
        ]);
    }

    protected function orderValidationMessages(): array
    {
        return parent::orderValidationMessages() + [
            'form.party.required' => 'Select a customer or a lead (or create a new lead).',
            'pairs.*.to_location_id.required' => 'Select the drop-off location.',
            'pairs.*.items.*.item_name.required' => 'Select the product, or type the new product\'s name.',
        ];
    }

    /*
     * The special price block of a product row (user, 21 Oct 2026): a quotation is the customer's special price in
     * the making, so each row carries the price list template's columns for its category (the conditions, minimum
     * charges and quantity ranges A–E), filled from the product's default price row and changed here; "+ New …"
     * describes a product not in the price list yet (sub-category, item, size and weight limits). Accept by
     * customer saves the block as the customer's special price and adds the new product.
     */

    /** @return array<string, mixed> */
    protected function itemTemplate(): array
    {
        return parent::itemTemplate() + [
            'product' => array_fill_keys(array_merge(['sub_category', 'base_item'], array_keys(UomResource::LIMITS)), ''),
            'special' => UomPriceRows::blankRow(),
            // what the block was filled for ("uom:12|34|default", or "new"): it starts again when that changes
            'special_for' => null,
        ];
    }

    /** The price list columns a row's category uses, as on the product pages. */
    public function specialColumns(array $item): array
    {
        return UomResource::columnsFor($this->itemKind($item) ?: Uom::MEASUREMENT);
    }

    /** "+ New …" chosen: a product not in the price list yet. */
    public function isNewProduct(array $item): bool
    {
        return ($item['catalog_key'] ?? '') === 'new';
    }

    /** The sub-categories of a category, offered when a new product is typed. @return list<string> */
    public function subCategoryOptions(string $kind): array
    {
        return Uom::query()->where('category', $kind)->whereNotNull('sub_category')->distinct()->orderBy('sub_category')->pluck('sub_category')->all();
    }

    /** The pickup locations of the price list, offered in the special price block. @return list<string> */
    public function pickupLocationOptions(): array
    {
        return UomRateTier::query()->whereNotNull('pickup_location')->distinct()->orderBy('pickup_location')->pluck('pickup_location')->all();
    }

    /** A new product's name, built as on the product pages: the item and its limits ("Carton · L+W+H ≤ 149 cm"). */
    protected function newProductName(array $item): string
    {
        $product = (array) ($item['product'] ?? []);
        $base = trim((string) ($product['base_item'] ?? '')) ?: trim((string) ($product['sub_category'] ?? ''));

        if ($base === '') {
            return '';
        }

        if ($this->itemKind($item) === Uom::LORRY) {
            return $base;
        }

        $bands = [];

        foreach (array_keys(UomResource::LIMITS) as $column) {
            $bands[$column] = is_numeric($product[$column] ?? null) ? (float) $product[$column] : null;
        }

        return app(ClientPriceListImporter::class)->productName($base, $bands);
    }

    public function updatedPairs($value, string $key): void
    {
        // the new product's fields name the row
        if (preg_match('/^(\d+)\.items\.(\d+)\.product\./', $key, $m)) {
            $item = &$this->pairs[(int) $m[1]]['items'][(int) $m[2]];

            if ($this->isNewProduct($item)) {
                $item['item_name'] = $this->newProductName($item);
                $this->refreshItemPrice((int) $m[1], (int) $m[2]);
            }

            return;
        }

        // the special price block changed: its ranges set the unit price
        if (preg_match('/^(\d+)\.items\.(\d+)\.special\./', $key, $m)) {
            $this->pairs[(int) $m[1]]['items'][(int) $m[2]]['price_edited'] = false;
            $this->refreshItemPrice((int) $m[1], (int) $m[2]);

            return;
        }

        // "+ New …": the row describes a product not in the price list yet
        if (preg_match('/^(\d+)\.items\.(\d+)\.(catalog_key|sub_category)$/', $key, $m) && in_array($value, ['new', '__new__'], true)) {
            $item = &$this->pairs[(int) $m[1]]['items'][(int) $m[2]];
            $item = array_merge($item, [
                'line_type' => 'uom', 'catalog_key' => 'new', 'uom' => '', 'no_band' => false, 'manual_price' => '', 'price_edited' => false,
                'sub_category' => $m[3] === 'sub_category' ? '__new__' : ($item['sub_category'] ?? ''),
                'special' => UomPriceRows::blankRow(),
                'special_for' => 'new',
            ]);
            $item['item_name'] = $this->newProductName($item);
            $this->refreshItemPrice((int) $m[1], (int) $m[2]);

            return;
        }

        // the rows whose product, destination or drop-off type this change may alter
        $index = (int) explode('.', $key, 2)[0];
        $itemIndexes = preg_match('/^\d+\.items\.(\d+)\./', $key, $m) ? [(int) $m[1]] : array_keys($this->pairs[$index]['items'] ?? []);

        parent::updatedPairs($value, $key);

        if (preg_match('/^(\d+)\.items\.(\d+)\.kind$/', $key, $m)) {
            // another type: the row starts again, its new product and special price too
            $item = &$this->pairs[(int) $m[1]]['items'][(int) $m[2]];
            $item['product'] = $this->itemTemplate()['product'];
            $item['special'] = UomPriceRows::blankRow();
            $item['special_for'] = null;

            return;
        }

        if (preg_match('/^(\d+)\.items\.(\d+)\.manual_price$/', $key, $m)) {
            // the unit price typed goes into the range that holds the quantity (what Accept would save)
            $item = &$this->pairs[(int) $m[1]]['items'][(int) $m[2]];

            if (is_numeric($item['manual_price'] ?? null)) {
                $item['special'] = UomPriceRows::withPrice($item['special'] ?? UomPriceRows::blankRow(), max(1.0, (float) ($item['quantity'] ?: 1)), round((float) $item['manual_price'], 2));
            }

            return;
        }

        // a block filled for another product, destination or drop-off type (special_for) starts again
        foreach ($itemIndexes as $i) {
            $now = $this->specialKey($index, $i);

            if ($now !== null && $now !== ($this->pairs[$index]['items'][$i]['special_for'] ?? null)) {
                $this->prefillSpecial($index, $i);
            }
        }
    }

    /** "uom:12|34|default": the product, destination and drop-off type a row's special price is for (null until all are set). */
    private function specialKey(int $index, int $itemIndex): ?string
    {
        $pair = $this->pairs[$index] ?? null;
        $item = $pair['items'][$itemIndex] ?? null;

        if (! $item || ! str_starts_with((string) ($item['catalog_key'] ?? ''), 'uom:') || blank($pair['to_location_id'] ?? null)) {
            return null;
        }

        return $item['catalog_key'].'|'.$pair['to_location_id'].'|'.(($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value);
    }

    /** The special price block starts as the product's default price row for the destination and drop-off type. */
    protected function prefillSpecial(int $index, int $itemIndex): void
    {
        $pair = &$this->pairs[$index];
        $item = &$pair['items'][$itemIndex];
        $date = (string) (($this->form['quote_date'] ?? '') ?: today()->toDateString());
        $row = UomPriceRows::defaultRow(
            (int) (explode(':', (string) $item['catalog_key'], 2)[1] ?? 0),
            (int) $pair['to_location_id'],
            ($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value,
            CurrentCompany::branch()?->id,
            $date,
        );

        $item['special'] = array_merge(UomPriceRows::blankRow(), array_intersect_key($row ?? [], UomPriceRows::blankRow()), ['effective_from' => $date, 'expires_on' => null]);
        $item['special_for'] = $this->specialKey($index, $itemIndex);
        $item['price_edited'] = false;
        $this->refreshItemPrice($index, $itemIndex);
    }

    /** The unit price of a row with quantity ranges is the price of the range that holds its quantity. */
    protected function refreshItemPrice(int $index, int $itemIndex): void
    {
        parent::refreshItemPrice($index, $itemIndex);

        $item = &$this->pairs[$index]['items'][$itemIndex];
        $quantity = max(1.0, (float) ($item['quantity'] ?: 1));

        foreach (UomPriceRows::ranges($item['special'] ?? []) as [$from, $to, $price]) {
            if ($from <= $quantity + 0.0001 && ($to === null || $quantity <= $to + 0.0001)) {
                $item['unit_price'] = $price;
                $item['source'] = 'quote_range';
                $item['tier'] = 'Qty '.rtrim(rtrim(number_format($from, 2, '.', ''), '0'), '.').($to === null ? '+' : '–'.rtrim(rtrim(number_format($to, 2, '.', ''), '0'), '.'));
                $this->fillPriceBox($item);

                return;
            }
        }
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

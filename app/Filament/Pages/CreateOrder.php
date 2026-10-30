<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\ConsignorConsignee;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerPricing;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\MasterData\Models\Store;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Billing\Actions\RecordOrderCreationPayment;
use App\Domains\Quotation\Actions\CreateAdminOrder;
use App\Enums\DropOffType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Filament\Resources\StoreResource;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\PaymentAllocation;
use App\Support\CustomerQuotationPriceHistory;
use App\Support\OrderFormOptions;
use App\Support\OrderMinimumCharge;
use App\Support\PriceListRates;
use App\Support\QuotationPricingLookup;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Admin-assisted order entry: customer & ownership once, then one Consignor & consignee block
 * per delivery (each becomes its own order record under the same order number).
 */
class CreateOrder extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'orders/new';

    protected static string $view = 'filament.pages.create-order';

    /**
     * Section 1 of the order being worked on. Every order of the page is in $orders (the page binds its fields as
     * orders.{n}.form / orders.{n}.pairs); $form, $pairs, $payNow and $billingAddresses are the working copies of
     * order $activeOrder, which the page logic (prices, PIC, payment terms, validation) works on.
     */
    protected array $form = [];

    /**
     * One consignor & consignee block per entry. Each product row keeps its photos picked on the page (not saved
     * yet) in 'photos' (TemporaryUploadedFile list) and the ones already saved on an edited record in
     * 'existing_photos'; 'manual_price' is the unit price box: filled with the special / default price and
     * editable ('price_edited' once changed by hand), or keyed in for a product without a rate.
     *
     * @var list<array<string, mixed>>
     */
    protected array $pairs = [];

    /**
     * Photo picker of each product row ([order][block index][row index] => files just uploaded): moved into that row's
     * 'photos' as soon as the upload finishes, so picking more files adds to the ones already chosen.
     *
     * @var array<int|string, mixed>
     */
    public array $photoUploads = [];

    /** Photos / DO attachments: images or PDF, up to 8 MB each (same rule as the earlier order-level upload). */
    public const PHOTO_RULE = 'file|mimes:jpg,jpeg,png,webp,pdf|max:8192';

    /**
     * Billing address entered per customer on this page (customer id => address): picking that customer
     * again brings it back instead of the customer's saved address.
     *
     * @var array<int|string, string>
     */
    protected array $billingAddresses = [];

    /** Section 1's PIC / contact number before the change being made (consignors still holding it follow it). */
    public array $previousCustomerPic = [];

    /**
     * Several orders on one page (user, 20 Oct 2026), every one open and editable, in page order: each
     * {form, pairs, pay_now, billingAddresses}. One Save creates them all.
     *
     * @var list<array<string, mixed>>
     */
    public array $orders = [];

    /** The order the working copies ($form, $pairs …) belong to: the one last changed. */
    public int $activeOrder = 0;

    /** The order whose fields carry the errors on the page (the one last checked); null = the working order. */
    public ?int $errorOrder = null;

    /** The working order is paid now (its Cash records): ticked in the Amount summary. */
    protected bool $payNow = false;

    /** The payment recorded while creating, for every order ticked "Pay now": method, payment_date, reference. */
    public array $payment = [];

    /** Its proof files (slip / receipt), kept with every order paid now. */
    public array $paymentProofs = [];

    /** Colours telling the orders of the page apart: [border / accent, tint]. */
    public const ORDER_COLORS = [
        ['#2563eb', 'rgba(37, 99, 235, .07)'],
        ['#059669', 'rgba(5, 150, 105, .07)'],
        ['#d97706', 'rgba(217, 119, 6, .08)'],
        ['#7c3aed', 'rgba(124, 58, 237, .07)'],
        ['#db2777', 'rgba(219, 39, 119, .07)'],
        ['#0891b2', 'rgba(8, 145, 178, .08)'],
    ];

    /**
     * The customer selected before this request changed it (set by updatingForm, this request only): the
     * billing address on the page belongs to that customer, even when its typed value arrives in the same
     * request as the new customer (deferred textarea + live select).
     */
    protected ?string $outgoingCustomerId = null;

    /** The order number this new order was prefilled from (the Duplicate icon on the Orders list, user 27 Oct 2026). */
    public ?string $duplicatedFrom = null;

    public function mount(): void
    {
        $this->form = $this->formTemplate();
        $this->pairs = [$this->pairTemplate()];
        $this->payment = $this->paymentTemplate();

        if (($duplicate = (int) request()->query('duplicate')) > 0) {
            $this->duplicateFrom($duplicate);
        }

        $this->orders = [$this->snapshotActive()];
        $this->activeOrder = 0;

        if ($this->duplicatedFrom !== null) {
            $this->fillItemKinds();
        }
    }

    /**
     * Prefills the page from an existing order (every record of it, latest versions): customer, salesperson, payment
     * term, each consignor & consignee block and its products. Nothing is created until Save; the record numbers,
     * DO numbers, delivery dates, photos and locks are not copied, and the prices are looked up again.
     */
    protected function duplicateFrom(int $id): void
    {
        $order = Quotation::query()->with('portalEnquiry')->find($id);

        if (! $order || (CurrentCompany::id() && (int) $order->company_id !== (int) CurrentCompany::id())) {
            return;
        }

        $enquiry = $order->portalEnquiry;
        $records = ($enquiry
            ? Quotation::query()->where('portal_enquiry_id', $enquiry->id)->orderBy('id')->get()
            : collect([$order]))
            ->filter(fn (Quotation $q) => $q->status !== \App\Enums\QuotationStatus::Superseded && $q->isLatestVersion())
            ->values();

        if ($records->isEmpty()) {
            return;
        }

        // the Edit order page's loaders build the blocks from the records
        $loader = new EditOrder();
        $this->form = array_merge($this->form, $loader->formFrom($enquiry, $records));
        $this->pairs = array_values(array_map(function (array $pair): array {
            unset($pair['record_id'], $pair['record_number'], $pair['original_order_type'], $pair['lock_label'], $pair['lock_note'], $pair['existing_prices'], $pair['payload_index'], $pair['pickup_restore'], $pair['legacy_store_branch_id']);
            $pair['locked'] = false;
            $pair['existing_photos'] = [];
            $pair['customer_do_number'] = '';
            $pair['expected_delivery_date'] = '';
            $pair['items'] = array_values(array_map(fn (array $item) => array_merge($item, [
                'unit_price' => null, 'tier' => null, 'source' => null, 'manual_price' => '', 'price_edited' => false, 'photos' => [], 'existing_photos' => [],
            ]), $pair['items']));

            return array_merge($this->pairTemplate(), $pair);
        }, $loader->pairsFromRecords($records, $enquiry)));

        foreach ($this->pairs as $i => $pair) {
            foreach (array_keys($pair['items']) as $j) {
                $this->refreshItemPrice($i, $j);
            }
        }

        $this->duplicatedFrom = (string) ($enquiry?->order_number ?: $order->number);
    }

    /** @return array<string, mixed> section 1 of a new order */
    protected function formTemplate(): array
    {
        return [
            'customer_id' => '',
            // the order's billing address (every record's customer_address), prefilled from the customer
            'customer_address' => '',
            // the customer's person in charge and contact number (every record's attention / customer_pic_phone)
            'customer_pic_name' => '',
            'customer_pic_phone' => '',
            // optional: how the order reached us
            'received_through' => '',
            'salesperson_id' => '',
            // follows the customer's type once a customer is picked (see orderTypeOptions)
            'order_type' => OrderType::Cash->value,
            // service (pickup / store) is chosen per consignor block; the payment method is captured when the payment is recorded
        ];
    }

    /** @return array{method: string, payment_date: string, reference: string} */
    protected function paymentTemplate(): array
    {
        // amount_received: blank = the full amount; less = split over the orders paid now (allocations "o0" → RM)
        return ['method' => PaymentMethod::BankTransfer->value, 'payment_date' => now()->toDateString(), 'reference' => '', 'amount_received' => '', 'allocations' => []];
    }

    /*
    |--------------------------------------------------------------------------
    | Several orders on one page
    |--------------------------------------------------------------------------
    */

    public function canAddOrder(): bool
    {
        return ! $this->isEditing();
    }

    /** Every request: the working copies are the active order's again (they are not kept between requests). */
    public function hydrate(): void
    {
        $this->useOrder($this->activeOrder);
    }

    /** After the page is drawn: the working copies go back into $orders. */
    public function dehydrate(): void
    {
        $this->stashActive();
        $this->fillItemKinds();

        // the Paying boxes of a short payment: the amounts entered, the missing ones filled in order
        if ($this->paysAny() && is_numeric($this->payment['amount_received'] ?? null)) {
            $this->payment['allocations'] = PaymentAllocation::amounts($this->payDues(), (float) $this->payment['amount_received'], (array) ($this->payment['allocations'] ?? []));
        }
    }

    /** Rows of a saved order or quotation: their type (and a measurement product's sub-category) from the product. */
    protected function fillItemKinds(): void
    {
        foreach ($this->orders as $o => $order) {
            foreach ($order['pairs'] ?? [] as $p => $pair) {
                foreach ($pair['items'] ?? [] as $i => $item) {
                    if (filled($item['kind'] ?? null) || blank($item['catalog_key'] ?? null)) {
                        continue;
                    }

                    $kind = $this->itemKind($item);
                    $this->orders[$o]['pairs'][$p]['items'][$i]['kind'] = $kind;

                    // every type picks a sub-category before the product (user, 25 Oct 2026)
                    if (blank($item['sub_category'] ?? null)) {
                        $this->orders[$o]['pairs'][$p]['items'][$i]['sub_category'] = (string) Uom::query()->whereKey(explode(':', (string) $item['catalog_key'], 2)[1] ?? 0)->value('sub_category');
                    }
                }
            }
        }
    }

    /** The active saved consignors / consignees of this company, loaded once per request (both roles). */
    private ?\Illuminate\Support\Collection $savedPartyRows = null;

    /**
     * Saved consignors or consignees (Master Data → Consignor / Consignee) for a block: those tagged to the selected
     * customer first, then the others. The customers are among them (user, 25 Oct 2026), tagged "Customer".
     *
     * @return array{tagged: array<int, array{label: string, tag: string, sub: string}>, others: array<int, array{label: string, tag: string, sub: string}>}
     */
    public function savedParties(string $role): array
    {
        $customerId = filled($this->form['customer_id'] ?? null) ? (int) $this->form['customer_id'] : null;
        $this->savedPartyRows ??= ConsignorConsignee::query()
            ->where('is_active', true)
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->with(['customers:id', 'location:id,name', 'customer:id,code'])
            ->orderBy('name')
            ->get();
        $parties = $this->savedPartyRows->whereIn('type', [$role, 'both']);
        $option = fn (ConsignorConsignee $party) => [
            'label' => ($party->customer?->code ? $party->customer->code.' — ' : '').$party->name
                .collect([$party->pic_name, $party->location?->name])->filter()->map(fn ($part) => ' · '.$part)->implode(''),
            'tag' => $party->isFromCustomer() ? 'Customer' : '',
            'sub' => (string) $party->address,
        ];
        $tagged = $customerId ? $parties->filter(fn (ConsignorConsignee $party) => $party->customers->contains('id', $customerId)) : collect();

        return [
            'tagged' => $tagged->mapWithKeys(fn (ConsignorConsignee $party) => [$party->id => $option($party)])->all(),
            'others' => $parties->reject(fn (ConsignorConsignee $party) => $tagged->contains('id', $party->id))->mapWithKeys(fn (ConsignorConsignee $party) => [$party->id => $option($party)])->all(),
        ];
    }

    /** The sub-category for a product not in the system: its name and price are keyed in on the order. */
    public const OTHER_PRODUCT = '__other__';

    /** The product types of the price list (Master Data → Measurements, Items and Chartered Lorry). @return array<string, string> */
    public function productKinds(): array
    {
        return Uom::CATEGORIES;
    }

    /** A row's type: the one chosen, else its product's category. */
    public function itemKind(array $item): string
    {
        if (filled($item['kind'] ?? null)) {
            return (string) $item['kind'];
        }

        [$type, $id] = array_pad(explode(':', (string) ($item['catalog_key'] ?? ''), 2), 2, null);

        return match ($type) {
            'uom' => (string) (Uom::query()->whereKey($id)->value('category') ?: Uom::MEASUREMENT),
            'chartered' => Uom::LORRY,
            default => '',
        };
    }

    /** Measurement products to choose (their sub-categories: Carton, Pallet, …). @return array<string, string> */
    public function measurementOptions(): array
    {
        return Uom::query()
            ->whereIn('id', $this->offeredProductIds())
            ->where('category', Uom::MEASUREMENT)
            ->whereNotNull('sub_category')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category')
            ->mapWithKeys(fn (string $sub) => [$sub => $sub])
            ->all();
    }

    /**
     * The sub-categories of a type's offered products (Pipe, Ladder, … for Item), chosen before the product
     * (user, 25 Oct 2026). Measurement's are measurementOptions(). @return array<string, string>
     */
    public function subCategoryOptions(string $kind): array
    {
        return Uom::query()
            ->whereIn('id', $this->offeredProductIds())
            ->where('category', $kind)
            ->whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->distinct()
            ->orderBy('sub_category')
            ->pluck('sub_category')
            ->mapWithKeys(fn (string $sub) => [$sub => $sub])
            ->all();
    }

    /** Item or Lorry products to choose, of the sub-category when one is chosen. @return array<string, string> */
    public function kindProductOptions(string $kind, ?string $subCategory = null): array
    {
        $options = $kind === Uom::LORRY ? $this->catalogOptions('lorry') : $this->catalogOptions('uom');
        $ids = Uom::query()->whereIn('id', $this->offeredProductIds())->where('category', $kind)
            ->when(filled($subCategory), fn ($q) => $q->where('sub_category', $subCategory))
            ->pluck('id')->all();

        return array_filter($options, fn (string $label, string $key) => in_array((int) (explode(':', $key, 2)[1] ?? 0), $ids, true), ARRAY_FILTER_USE_BOTH);
    }

    /** The products offered on this page (priced by default, or specially for this customer). @return list<int> */
    protected function offeredProductIds(): array
    {
        return array_map(fn (string $key) => (int) (explode(':', $key, 2)[1] ?? 0), array_keys($this->catalogOptions('uom') + $this->catalogOptions('lorry')));
    }

    /** The L, W and H of a Measurement row as [min, max] ranges and its weight (user, 28 Oct 2026); null where not entered. */
    protected function measurementRanges(array $item): array
    {
        $num = fn (string $key) => is_numeric($item[$key] ?? null) ? (float) $item[$key] : null;

        return [
            'l' => [$num('length'), $num('length_max')],
            'w' => [$num('width'), $num('width_max')],
            'h' => [$num('height'), $num('height_max')],
            'kg' => $num('weight'),
        ];
    }

    /**
     * The size band of a Measurement row: the product of its sub-category whose limits the goods' L, W, H ranges and
     * weight fit (L+W+H, length, width, height and weight ranges of the price list, the whole min – max inside the
     * band's); the tightest band when several fit.
     */
    public function measurementProduct(array $item): ?Uom
    {
        $sub = trim((string) ($item['sub_category'] ?? ''));
        $size = $this->measurementRanges($item);

        if ($sub === '' || $size['kg'] === null || in_array(null, [...$size['l'], ...$size['w'], ...$size['h']], true)) {
            return null;
        }

        // the whole entered range lies inside the band
        $within = fn (float $lo, float $hi, $min, $max) => ($min === null || min($lo, $hi) >= (float) $min - 0.0001) && ($max === null || max($lo, $hi) <= (float) $max + 0.0001);
        $lwhLo = $size['l'][0] + $size['w'][0] + $size['h'][0];
        $lwhHi = $size['l'][1] + $size['w'][1] + $size['h'][1];

        return Uom::query()
            ->whereIn('id', $this->offeredProductIds())
            ->where('category', Uom::MEASUREMENT)
            ->where('sub_category', $sub)
            ->get()
            ->filter(fn (Uom $uom) => $within($lwhLo, $lwhHi, $uom->min_lwh, $uom->max_lwh)
                && $within($size['l'][0], $size['l'][1], $uom->min_length, $uom->max_length)
                && $within($size['w'][0], $size['w'][1], null, $uom->max_width)
                && $within($size['h'][0], $size['h'][1], null, $uom->max_height)
                && $within($size['kg'], $size['kg'], $uom->min_weight, $uom->max_weight))
            // a per-kg product only when no per-piece band fits; then the tightest size, then weight
            ->sortBy([
                fn (Uom $a, Uom $b) => str_ends_with($a->name, '(per kg)') <=> str_ends_with($b->name, '(per kg)'),
                fn (Uom $a, Uom $b) => ($a->max_lwh === null ? INF : (float) $a->max_lwh) <=> ($b->max_lwh === null ? INF : (float) $b->max_lwh),
                fn (Uom $a, Uom $b) => ($a->max_weight === null ? INF : (float) $a->max_weight) <=> ($b->max_weight === null ? INF : (float) $b->max_weight),
            ])
            ->first();
    }

    /** "10 – 20" (or "15" when both ends agree) for the size text. */
    protected static function rangeText(array $range): string
    {
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

        return abs($range[0] - $range[1]) < 0.0001 ? $fmt($range[0]) : $fmt(min($range)).' – '.$fmt(max($range));
    }

    /** "Bundle · L 100 – 150 × W 100 × H 20 – 25 cm · 100 kg" once the product and its full size are entered. */
    protected function measurementSize(array $item): ?string
    {
        $sub = trim((string) ($item['sub_category'] ?? ''));
        $size = $this->measurementRanges($item);

        if ($sub === '' || $size['kg'] === null || in_array(null, [...$size['l'], ...$size['w'], ...$size['h']], true)) {
            return null;
        }

        return "{$sub} · L ".static::rangeText($size['l']).' × W '.static::rangeText($size['w']).' × H '.static::rangeText($size['h']).' cm · '.static::rangeText([$size['kg'], $size['kg']]).' kg';
    }

    /** The note under a Measurement row's size fields. */
    public function measurementNote(array $item): string
    {
        $sub = trim((string) ($item['sub_category'] ?? ''));

        if ($sub === '') {
            return 'Choose the product, then enter its size range and weight.';
        }

        $size = $this->measurementRanges($item);
        $complete = $size['kg'] !== null && ! in_array(null, [...$size['l'], ...$size['w'], ...$size['h']], true);
        $matched = filled($item['catalog_key'] ?? null);

        if (! $complete) {
            return $matched ? 'Size band: '.$item['item_name'].' · enter the L, W, H ranges and weight to check it.' : 'Enter the L, W, H ranges (min – max) and weight: the size band they fit sets the price.';
        }

        return $matched
            ? 'Size band: '.$item['item_name']
            : 'No '.$sub.' size band fits L '.static::rangeText($size['l']).' × W '.static::rangeText($size['w']).' × H '.static::rangeText($size['h']).' cm, '.static::rangeText([$size['kg'], $size['kg']]).' kg · key in the unit price, or check the size / choose another product.';
    }

    /** @return array<string, mixed> the working order as it is now */
    protected function snapshotActive(): array
    {
        return [
            'form' => $this->form,
            'pairs' => array_values($this->pairs),
            'pay_now' => $this->payNow,
            'billingAddresses' => $this->billingAddresses,
        ];
    }

    /** The working copies into $orders (the working order). */
    public function stashActive(): void
    {
        if ($this->form !== [] || $this->pairs !== []) {
            $this->orders[$this->activeOrder] = $this->snapshotActive();
        }
    }

    /** Work on order $index: its data into the working copies. */
    public function useOrder(int $index): void
    {
        if (! isset($this->orders[$index])) {
            return;
        }

        $order = $this->orders[$index];
        $this->activeOrder = $index;
        $this->form = $order['form'] ?? $this->formTemplate();
        $this->pairs = array_values($order['pairs'] ?? []);
        $this->payNow = (bool) ($order['pay_now'] ?? false);
        $this->billingAddresses = $order['billingAddresses'] ?? [];
        $this->previousCustomerPic = [];
    }

    /** Switch the working copies to another order (the current one kept in $orders first). */
    protected function switchToOrder(int $index): void
    {
        if ($index !== $this->activeOrder && isset($this->orders[$index])) {
            $this->stashActive();
            $this->useOrder($index);
        }
    }

    /** "orders.{n}.form.x" / "orders.{n}.pairs.i.x" → [n, 'form' | 'pairs' | …, the rest] */
    protected function orderKey(string $key): array
    {
        $parts = explode('.', $key, 3);

        return [(int) ($parts[0] ?? 0), (string) ($parts[1] ?? ''), (string) ($parts[2] ?? '')];
    }

    /** A field of order n is about to change: that order becomes the working one (section 1 remembers its old values). */
    public function updatingOrders(mixed $value, string $key): void
    {
        [$index, $section, $rest] = $this->orderKey($key);
        $this->switchToOrder($index);

        if ($section === 'form') {
            $this->updatingForm($value, $rest);
        }
    }

    /** A field of order n changed: the page logic of that field runs on the order, then it is kept in $orders. */
    public function updatedOrders(mixed $value, string $key): void
    {
        [$index, $section, $rest] = $this->orderKey($key);

        if (! isset($this->orders[$index])) {
            return;
        }

        // the new value is in $orders already: take the order as it now is
        $this->form = $this->orders[$index]['form'] ?? $this->form;
        $this->pairs = array_values($this->orders[$index]['pairs'] ?? $this->pairs);
        $this->activeOrder = $index;

        if ($section === 'form' && $rest !== '') {
            $this->updatedForm($value, $rest);
        } elseif ($section === 'pairs' && $rest !== '') {
            $this->updatedPairs($value, $rest);
        }

        $this->stashActive();
    }

    /** A button of order n (add / remove a block or product, consignor quick actions, remove a photo). */
    public function inOrder(int $index, string $method, mixed ...$arguments): void
    {
        $allowed = ['addPair', 'removePair', 'addItem', 'removeItem', 'clearConsignor', 'useCustomerAsConsignor', 'removePhoto', 'addStop', 'removeStop'];

        if (! in_array($method, $allowed, true) || ! isset($this->orders[$index])) {
            return;
        }

        $this->switchToOrder($index);
        $this->{$method}(...array_map('intval', $arguments));
        $this->stashActive();
    }

    /** "+ Add another order": a new order below the others (they stay open as they are). */
    public function addOrder(): void
    {
        if (! $this->canAddOrder()) {
            return;
        }

        $this->stashActive();
        $this->form = $this->formTemplate();
        $this->billingAddresses = [];
        $this->payNow = false;
        $this->pairs = [$this->pairTemplate()];
        $this->activeOrder = count($this->orders);
        $this->orders[] = $this->snapshotActive();
        $this->dispatch('og-order-opened', order: $this->activeOrder);
    }

    public function removeOrder(int $index): void
    {
        if ($this->isEditing() || count($this->orders) <= 1 || ! isset($this->orders[$index])) {
            return;
        }

        $this->stashActive();
        unset($this->orders[$index]);
        $this->orders = array_values($this->orders);
        $this->photoUploads = [];
        $this->useOrder(min($index, count($this->orders) - 1));
        $this->resetErrorBag();
    }

    /** "Pay now" of an order in the Amount summary: its Cash records are paid with the payment below on save. */
    public function togglePayOrder(int $index): void
    {
        if ($this->isEditing() || ! isset($this->orders[$index])) {
            return;
        }

        $this->switchToOrder($index);
        $this->payNow = ! $this->payNow && $this->cashRecordCount() > 0;
        $this->stashActive();
    }

    /** Some order of the page is paid now. */
    public function paysAny(): bool
    {
        return collect($this->orders)->contains(fn (array $order) => $order['pay_now'] ?? false);
    }

    /**
     * The working order in figures (the Amount summary).
     *
     * @return array{customer: ?string, records: int, items: float, minimum: float, total: float, cash_total: float, cash_records: int, priced: bool, pay_now: bool, terms: list<string>}
     */
    public function orderSummary(): array
    {
        $totals = $this->pairTotals();
        $cash = 0.0;

        foreach ($this->pairs as $i => $pair) {
            if (($pair['order_type'] ?? '') === OrderType::Cash->value) {
                $cash += array_sum($totals['lines'][$i] ?? []) + (float) ($totals['minimums'][$i]['top_up'] ?? 0);
            }
        }

        return [
            'customer' => $this->customerName(),
            'records' => count($this->pairs),
            'items' => $totals['items'],
            'minimum' => $totals['minimum'],
            'total' => $totals['total'],
            'cash_total' => round($cash, 2),
            'cash_records' => $this->cashRecordCount(),
            'priced' => $this->showPrices(),
            'pay_now' => $this->payNow,
            'terms' => collect($this->pairs)->map(fn (array $pair) => OrderType::tryFrom((string) ($pair['order_type'] ?? ''))?->getLabel())->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Every order of the page for the view: position, colour and figures. The working copies end on the order
     * they were on.
     *
     * @return list<array{index: int, number: int, color: string, tint: string, summary: array<string, mixed>}>
     */
    public function pageOrders(): array
    {
        $this->stashActive();
        $working = $this->activeOrder;
        $orders = [];

        foreach (array_keys($this->orders) as $index) {
            $this->useOrder($index);
            [$color, $tint] = self::ORDER_COLORS[$index % count(self::ORDER_COLORS)];
            $orders[] = [
                'index' => $index,
                'number' => $index + 1,
                'color' => $color,
                'tint' => $tint,
                'summary' => $this->orderSummary(),
            ];
        }

        $this->useOrder($working);

        return $orders;
    }

    /**
     * The orders paid now and their Cash amounts ("o0" → RM), as the Payment card lists them.
     *
     * @return array<string, float>
     */
    public function payDues(): array
    {
        return collect($this->pageOrders())
            ->filter(fn (array $order) => ($order['summary']['pay_now'] ?? false) && ($order['summary']['priced'] ?? false))
            ->mapWithKeys(fn (array $order) => ['o'.$order['index'] => round((float) ($order['summary']['cash_total'] ?? 0), 2)])
            ->all();
    }

    /**
     * The amount received over the orders paid now (user, 21 Oct 2026, as Cash Bill Payment): blank = in full; less
     * = split as entered (an order paid less stays Pending payment, partly paid); more = change to give back.
     */
    public function paymentPlan(?array $dues = null): array
    {
        $dues ??= $this->payDues();
        $entered = $this->payment['amount_received'] ?? '';
        $received = is_numeric($entered) ? (float) $entered : array_sum($dues);

        return PaymentAllocation::plan($dues, $received, (array) ($this->payment['allocations'] ?? []));
    }

    /** Another amount received: its split over the orders starts again (in order). */
    public function updatedPayment(mixed $value, ?string $key = null): void
    {
        if ($key === 'amount_received') {
            $this->payment['allocations'] = [];
        }
    }

    /** Cash records of the open order (the payment recorded now pays them). */
    public function cashRecordCount(): int
    {
        return collect($this->pairs)->where('order_type', OrderType::Cash->value)->count();
    }

    /** @return array<string, string> */
    public function paymentMethodOptions(): array
    {
        return PaymentMethod::options(customerFacing: true);
    }

    public function removePaymentProof(int $index): void
    {
        unset($this->paymentProofs[$index]);
        $this->paymentProofs = array_values($this->paymentProofs);
    }

    public function getTitle(): string
    {
        return 'Create order for customer';
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function getRelativeRouteName(): string
    {
        return 'orders.create';
    }

    /*
    |--------------------------------------------------------------------------
    | Page mode (the Edit order page reuses this page and its view)
    |--------------------------------------------------------------------------
    */

    public function isEditing(): bool
    {
        return false;
    }

    /**
     * Texts and links of the page chrome.
     *
     * @return array{back_url: string, back_label: string, crumb: string, title: string, sub: string, cancel_url: string, submit: string}
     */
    public function pageCopy(): array
    {
        return [
            'back_url' => Orders::getUrl(),
            'back_label' => '← Back to orders',
            'crumb' => 'Orders / Admin assisted entry',
            'title' => 'Create order for customer',
            'sub' => 'Record the customer\'s instructions and prepare a quotation in the same record.',
            'cancel_url' => Orders::getUrl(),
            'submit' => 'Save order & prepare pricing →',
        ];
    }

    /** Customer, billing address, received through and payment term can be changed. */
    public function headerEditable(): bool
    {
        return true;
    }

    public function salespersonEditable(): bool
    {
        return true;
    }

    /** Offer the "No salesperson yet" choice. */
    public function allowNoSalesperson(): bool
    {
        return true;
    }

    public function showReceivedThrough(): bool
    {
        return true;
    }

    /** Offer the "— Not specified —" choice for Received through (it is optional). */
    public function receivedThroughClearable(): bool
    {
        return true;
    }

    public function canAddPair(): bool
    {
        return true;
    }

    public function canRemovePair(int $index): bool
    {
        return count($this->pairs) > 1;
    }

    public function isPairLocked(int $index): bool
    {
        return false;
    }

    public function needsHeartbeat(): bool
    {
        return false;
    }

    /**
     * Files saved earlier for the whole order (before photos were kept per consignor & consignee block): shown
     * read-only on Edit order. A new order has none.
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function existingAttachments(): array
    {
        return [];
    }

    /** Records of this order, left out of a product's previous records (none while creating). @return list<int> */
    public function historyExcludedQuotationIds(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    /** @return array<int|string, string> */
    public function customerOptions(): array
    {
        $selected = $this->form['customer_id'] ?? null;
        // inactive customers are not offered (user, 25 Oct 2026); the one already on the order stays
        $query = Customer::query()
            ->where(fn ($q) => $q->where('status', 'active')->when(filled($selected), fn ($q) => $q->orWhere('id', $selected)))
            ->orderBy('company_name');

        if ($companyId = CurrentCompany::id()) {
            $query->where('company_id', $companyId);
        }

        return $query->get()->mapWithKeys(fn (Customer $c) => [$c->id => trim(($c->code ? $c->code.' — ' : '').$c->company_name)])->all();
    }

    /**
     * The customer type tag shown beside each customer in the picker (as in the Orders list).
     *
     * @return array<int|string, array{label: string, tone: string}>
     */
    public function customerTypeTags(): array
    {
        return Customer::query()
            ->when(CurrentCompany::id(), fn ($query, $id) => $query->where('company_id', $id))
            ->get(['id', 'default_order_type', 'is_credit'])
            ->mapWithKeys(function (Customer $c) {
                $type = $c->customerType();

                return [$c->id => ['label' => $type->shortLabel(), 'tone' => $type === OrderType::Term ? 'credit' : $type->value]];
            })
            ->all();
    }

    /** @return array<int|string, string> */
    public function salespersonOptions(): array
    {
        return User::query()->role('salesperson')->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.($u->saLocation ? ' · '.$u->saLocation->code : '')])
            ->all();
    }

    /** @return array<string, string> */
    public function receivedThroughOptions(): array
    {
        return CreateAdminOrder::RECEIVED_THROUGH;
    }

    /**
     * Payment terms by customer type (Customer::customerType()): a Credit / Term customer may order on
     * Credit / Term, Cash or COD; a COD customer on COD only; a Cash customer on Cash only. Credit / Term
     * also needs a credit customer (is_credit): one typed Term without it orders on Cash or COD. All three
     * until a customer is picked.
     *
     * @return array<string, string>
     */
    public function orderTypeOptions(): array
    {
        $customer = filled($this->form['customer_id'] ?? null) ? Customer::query()->find($this->form['customer_id']) : null;

        if (! $customer) {
            return OrderType::options();
        }

        return collect($this->orderTypesFor($customer))->mapWithKeys(fn (OrderType $type) => [$type->value => $type->getLabel()])->all();
    }

    /** @return list<OrderType> */
    /** Payment terms a block may have (the customer type's terms). */
    public function pairOrderTypeOptions(int $index): array
    {
        return $this->orderTypeOptions();
    }

    protected function orderTypesFor(Customer $customer): array
    {
        $allowed = match ($customer->customerType()) {
            OrderType::Term => [OrderType::Term, OrderType::Cash, OrderType::Cod],
            OrderType::Cod => [OrderType::Cod],
            OrderType::Cash => [OrderType::Cash],
        };

        // the credit check on acceptance only runs for credit customers: no Credit / Term without credit
        return $customer->is_credit ? $allowed : array_values(array_filter($allowed, fn (OrderType $type) => $type !== OrderType::Term));
    }

    /** The payment term an order starts with: the selected customer's type when offered, else its first term (Cash without a customer). */
    protected function defaultOrderType(): string
    {
        $customer = filled($this->form['customer_id'] ?? null) ? Customer::query()->find($this->form['customer_id']) : null;

        if (! $customer) {
            return OrderType::Cash->value;
        }

        $allowed = $this->orderTypesFor($customer);
        $type = $customer->customerType();

        return (in_array($type, $allowed, true) ? $type : ($allowed[0] ?? OrderType::Cash))->value;
    }

    /** Consignor toggle: Pick up (collected from the consignor) or Store (brought to an O&G branch). @return array<string, string> */
    public function serviceTypeOptions(): array
    {
        // the consignor brings the goods to a store = "Customer Drop Off" (user, 28 Oct 2026)
        return [ServiceType::Pickup->value => 'Pickup', ServiceType::Store->value => 'Customer Drop Off'];
    }

    /** @return array<string, string> */
    public function dropOffTypeOptions(): array
    {
        return DropOffType::options();
    }

    /**
     * Stores (Master Data → Stores) for the consignor's Store mode, each with its address / From / PIC line; a
     * store already chosen on a block stays listed.
     *
     * @return array<int, array{label: string, sub: string}>
     */
    public function storeOptions(): array
    {
        return OrderFormOptions::storeOptions(array_column($this->pairs, 'store_id'));
    }

    /**
     * What the page shows of the chosen store: its branch, address, PIC and contact number, and where to edit it.
     *
     * @return array{name: string, branch: string, address: string, pic: string, phone: string, edit_url: ?string}|null
     */
    public function storeInfo(mixed $storeId): ?array
    {
        $store = filled($storeId) ? Store::query()->with('branch')->find($storeId) : null;

        if (! $store) {
            return null;
        }

        try {
            $editUrl = StoreResource::getUrl('edit', ['record' => $store]);
        } catch (Throwable) {
            $editUrl = null;
        }

        return [
            'name' => (string) $store->name,
            'branch' => (string) ($store->branch?->name ?? ''),
            // a store without an address of its own uses its branch's (user, 28 Oct 2026)
            'address' => trim((string) $store->address) ?: trim((string) ($store->branch?->address ?? '')),
            'address_from_branch' => trim((string) $store->address) === '' && trim((string) ($store->branch?->address ?? '')) !== '',
            'pic' => trim((string) $store->pic_name),
            'phone' => trim((string) $store->pic_phone),
            'edit_url' => $editUrl,
        ];
    }

    /** @return array<int, string> */
    public function locationOptions(): array
    {
        return OrderFormOptions::locationOptions();
    }

    /** @return array<int|string, string> */
    public function addressOptions(): array
    {
        return OrderFormOptions::customerAddressOptions(filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null);
    }

    /** @return array<string, string> */
    public function catalogOptions(string $type): array
    {
        // the selected customer's own special-price products are offered too
        return app(QuotationPricingLookup::class)->catalogOptionsForType($type ?: 'uom', $this->pricingCustomerId());
    }

    /** Create quotation reuses this page (CreateQuotation). */
    public function isQuote(): bool
    {
        return false;
    }

    /** The quotation's number (Create quotation, when editing one). */
    public function quoteNumber(): ?string
    {
        return null;
    }

    /** The customer whose special prices fill the price boxes (Create quotation: none, the default price). */
    protected function pricingCustomerId(): ?int
    {
        return $this->selectedCustomerId();
    }

    protected function selectedCustomerId(): ?int
    {
        return filled($this->form['customer_id'] ?? null) ? (int) $this->form['customer_id'] : null;
    }

    /** @return list<string> */
    public function consignorSuggestions(): array
    {
        return array_values($this->customerOptions());
    }

    /** The Consignor dropdown: the company's active customers by name (searchable, as the Customer field). @return list<string> */
    public function consignorNames(): array
    {
        return Customer::query()
            ->where('status', 'active')
            ->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))
            ->orderBy('company_name')
            ->distinct()
            ->pluck('company_name')
            ->filter()
            ->values()
            ->all();
    }

    public function customerName(): ?string
    {
        return filled($this->form['customer_id'] ?? null) ? Customer::query()->whereKey($this->form['customer_id'])->value('company_name') : null;
    }

    public function showPrices(): bool
    {
        return filled($this->form['salesperson_id'] ?? null);
    }

    /*
    |--------------------------------------------------------------------------
    | Form behaviour
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    protected function pairTemplate(): array
    {
        $consignor = filled($this->form['customer_id'] ?? null) ? OrderFormOptions::consignorStateForCustomer((string) $this->form['customer_id']) : [];

        return [
            // a saved consignor / consignee picked for the block (Master Data → Consignor / Consignee)
            'consignor_party_id' => '',
            'consignee_party_id' => '',
            // consignor: starts as the customer (optional: cleared, it is saved blank)
            'consignor_name' => $this->customerName() ?? '',
            // the block's own payment term (selector beside Remove), starting as the customer's type
            'order_type' => $this->defaultOrderType(),
            // Pickup (collected from the consignor) or Store (the consignor brings the goods to a store, Master Data → Stores)
            'service_type' => ServiceType::Pickup->value,
            'store_id' => '',
            'from_location_id' => $consignor['from_location_id'] ?? '',
            // the consignor starts as the customer: its PIC and contact number are section 1's
            'consignor_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
            'consignor_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
            // saved customer address id, OrderFormOptions::NEW_ADDRESS (typed in pickup_location) or blank
            'pickup_preset' => $consignor['pickup_location_preset'] ?? '',
            'pickup_location' => $consignor['pickup_location'] ?? '',
            'consignee_name' => '',
            'to_location_id' => '',
            'consignee_pic_name' => '',
            'consignee_pic_phone' => '',
            // saved customer address id, OrderFormOptions::NEW_ADDRESS (typed in drop_off_location) or blank
            'drop_off_preset' => '',
            'drop_off_location' => '',
            'drop_off_type' => DropOffType::Default->value,
            // Deliver (to the consignee's address) or Store (to one of the company's stores, the customer collects), user 27 Oct 2026
            'consignee_mode' => 'deliver',
            'consignee_store_id' => '',
            'customer_do_number' => '',
            // several pick-up / drop-off locations, each with its own DO number (user, 27 Oct 2026)
            'multi_pickup' => false,
            'pickup_stops' => [],
            'multi_dropoff' => false,
            'dropoff_stops' => [],
            'expected_delivery_date' => '',
            // Driver notes (printed on the CSN) and Admin notes (internal), user 27 Oct 2026
            'instructions' => '',
            'admin_notes' => '',
            'items' => [$this->itemTemplate()],
        ];
    }

    /** @return array<string, mixed> */
    protected function itemTemplate(): array
    {
        return [
            'line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'quantity' => 1,
            // the product type first (Measurement / Item / Lorry, user 21 Oct 2026); a Measurement product is its
            // sub-category with the goods' size and weight: the size band they fit is the product priced
            'kind' => '', 'sub_category' => '', 'length' => '', 'length_max' => '', 'width' => '', 'width_max' => '', 'height' => '', 'height_max' => '', 'weight' => '',
            // a Measurement row whose size no band fits: named by its size, the price keyed in
            'no_band' => false,
            'unit_price' => null, 'tier' => null, 'source' => null, 'available' => null,
            // the unit price box: the special / default price filled in, editable (price_edited once changed)
            'manual_price' => '', 'price_edited' => false,
            // photos of this product: picked on the page (not saved yet) / already saved on the record
            'photos' => [], 'existing_photos' => [],
        ];
    }

    /** Runs before the new value is set: remembers which customer the billing address on the page belongs to. */
    public function updatingForm(mixed $value, ?string $key = null): void
    {
        // the PIC / contact number before this change: consignor fields still holding it follow the new value
        if (in_array($key, ['customer_pic_name', 'customer_pic_phone'], true)) {
            $this->previousCustomerPic[$key] = trim((string) ($this->form[$key] ?? ''));
        }

        if ($key === 'customer_id' && $this->outgoingCustomerId === null) {
            $this->outgoingCustomerId = (string) ($this->form['customer_id'] ?? '');
        }
    }

    public function updatedForm($value, string $key): void
    {
        if ($key === 'customer_address') {
            // remembered per customer when the customer changes (below), not here: a typed address can arrive in
            // the same request as the new customer, and is the outgoing customer's
            return;
        }

        if (in_array($key, ['customer_pic_name', 'customer_pic_phone'], true)) {
            $this->syncConsignorPic([$key => $this->previousCustomerPic[$key] ?? '']);

            return;
        }

        if ($key === 'customer_id') {
            $previousPic = [
                'customer_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
                'customer_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
            ];
            $consignor = filled($value) ? OrderFormOptions::consignorStateForCustomer((string) $value) : [];
            $name = $this->customerName() ?? '';
            $knownNames = Customer::query()->pluck('company_name')->all();

            // the address on the page (every value of this request is already set) is the outgoing customer's
            $outgoing = (string) $this->outgoingCustomerId;
            $this->outgoingCustomerId = null;

            if ($outgoing !== '' && $outgoing !== (string) $value) {
                $this->billingAddresses[$outgoing] = (string) ($this->form['customer_address'] ?? '');
            }

            // billing address: the one already entered for this customer, else the customer's saved address
            $this->form['customer_address'] = filled($value)
                ? (string) ($this->billingAddresses[(string) $value] ?? ($consignor['customer_address'] ?? ''))
                : '';
            // the customer's person in charge and contact number (and the consignors' that still had the old ones)
            $this->form['customer_pic_name'] = (string) ($consignor['attention'] ?? '');
            $this->form['customer_pic_phone'] = (string) ($consignor['customer_pic_phone'] ?? '');
            $this->syncConsignorPic($previousPic);

            foreach ($this->pairs as &$pair) {
                // keep a consignor the user typed; replace the default (a customer name) when the customer changes.
                // A consignor cleared on purpose stays blank; only the first customer picked fills an empty one.
                if (in_array($pair['consignor_name'] ?? null, $knownNames, true) || (blank($pair['consignor_name'] ?? null) && $outgoing === '')) {
                    $pair['consignor_name'] = $name;
                }

                // a drop-off picked from the outgoing customer's saved addresses stays as typed text (new address)
                if (! in_array((string) ($pair['drop_off_preset'] ?? ''), ['', OrderFormOptions::NEW_ADDRESS], true)) {
                    $pair['drop_off_preset'] = filled($pair['drop_off_location'] ?? null) ? OrderFormOptions::NEW_ADDRESS : '';
                }
                // a Store block keeps its store and the From that came with it (a switch back to Pickup takes the new customer's)
                // (and its pickup location: the store's address; a switch back to Pickup takes the new customer's)
                if (($pair['service_type'] ?? ServiceType::Pickup->value) !== ServiceType::Store->value) {
                    $pair['from_location_id'] = $consignor['from_location_id'] ?? '';
                    $pair['pickup_preset'] = $consignor['pickup_location_preset'] ?? '';
                    $pair['pickup_location'] = $consignor['pickup_location'] ?? '';
                } else {
                    $pair['pickup_from_location_id'] = $consignor['from_location_id'] ?? '';
                    $pair['pickup_restore'] = ['preset' => $consignor['pickup_location_preset'] ?? '', 'location' => $consignor['pickup_location'] ?? ''];
                }
            }
            unset($pair);

            // the payment term starts as the customer's type (Cash, COD or Credit / Term): each block that still had
            // the previous customer's default, or a term this customer does not offer, takes the new default
            $previousType = (string) ($this->form['order_type'] ?? '');
            $this->form['order_type'] = $this->defaultOrderType();

            foreach (array_keys($this->pairs) as $index) {
                if ($this->isPairLocked($index)) {
                    continue;
                }

                $term = (string) ($this->pairs[$index]['order_type'] ?? '');

                if ($term === '' || $term === $previousType || ! array_key_exists($term, $this->pairOrderTypeOptions($index))) {
                    $this->pairs[$index]['order_type'] = $this->form['order_type'];
                }
            }

            $this->refreshAllPrices();
        }

        if ($key === 'salesperson_id') {
            $this->refreshAllPrices();
        }
    }

    public function updatedPairs($value, string $key): void
    {
        // consignee Store (user, 27 Oct 2026): the store's address is the drop-off location and its area the To location
        // "Multiple pick-up / drop-off" switched on: the stops start as this record's pick-up and drop-off (with its DO number)
        if (preg_match('/^(\d+)\.multi_(pickup|dropoff)$/', $key, $m)) {
            $index = (int) $m[1];
            $side = $m[2];
            $pair = &$this->pairs[$index];

            // ticked: the side's first extra address starts with the DO number of the record (which moves up here)
            if (! empty($pair['multi_'.$side]) && empty($pair[$side.'_stops'])) {
                // a pick-up address carries no party of its own: there is one consignor (user, 30 Oct 2026); a drop-off is a consignee
                $pair[$side.'_stops'] = [array_merge(\App\Support\OrderStops::blank($side === 'pickup' ? \App\Support\OrderStops::PICKUP : \App\Support\OrderStops::DROPOFF), $side === 'pickup' ? [] : [
                    'name' => (string) ($pair['consignee_name'] ?? ''),
                    'pic_name' => (string) ($pair['consignee_pic_name'] ?? ''),
                    'pic_phone' => (string) ($pair['consignee_pic_phone'] ?? ''),
                ], [
                    'location' => (string) ($pair[$side === 'pickup' ? 'pickup_location' : 'drop_off_location'] ?? ''),
                    'do_number' => (string) ($pair['customer_do_number'] ?? ''),
                ])];
            } elseif (empty($pair['multi_pickup']) && empty($pair['multi_dropoff']) && blank($pair['customer_do_number'] ?? null)) {
                $pair['customer_do_number'] = (string) (\App\Support\OrderStops::doNumbers([...($pair['pickup_stops'] ?? []), ...($pair['dropoff_stops'] ?? [])])[0] ?? '');
            }

            unset($pair);

            return;
        }

        if (preg_match('/^(\d+)\.(consignee_mode|consignee_store_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];
            $before = OrderFormOptions::storeDefaults($pair['consignee_store_id'] ?? null);

            if (($pair['consignee_mode'] ?? '') !== 'store') {
                // back to Deliver: what the store filled in is cleared
                if ($before && trim((string) ($pair['drop_off_location'] ?? '')) === $before['pickup_location']) {
                    $pair['drop_off_location'] = '';
                }

                $pair['consignee_store_id'] = '';
                unset($pair);
                $this->refreshPairPrices($index);

                return;
            }

            if (blank($pair['consignee_store_id'] ?? null)) {
                $stores = Store::query()->active()->where('branch_id', CurrentCompany::branchId())->pluck('id');
                $pair['consignee_store_id'] = $stores->count() === 1 ? (string) $stores->first() : '';
            }

            if ($store = OrderFormOptions::storeDefaults($pair['consignee_store_id'] ?? null)) {
                $pair['drop_off_location'] = $store['pickup_location'];

                if ($store['from_location_id'] !== '') {
                    $pair['to_location_id'] = $store['from_location_id'];
                }
            }

            unset($pair);
            $this->refreshPairPrices($index);

            return;
        }

        if (preg_match('/^(\d+)\.(service_type|store_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];
            $store = OrderFormOptions::storeDefaults($pair['store_id'] ?? null);

            if (($pair['service_type'] ?? '') !== ServiceType::Store->value) {
                $pair['service_type'] = ServiceType::Pickup->value;

                // back to Pickup: what still came from the store returns to the Pickup values (the From and pickup
                // location before Store was chosen, else the customer's defaults); anything changed by hand stays
                if ($m[2] === 'service_type' && $store) {
                    $customer = OrderFormOptions::consignorStateForCustomer(filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null);

                    if ((string) ($pair['from_location_id'] ?? '') === $store['from_location_id']) {
                        $pair['from_location_id'] = array_key_exists('pickup_from_location_id', $pair)
                            ? (string) $pair['pickup_from_location_id']
                            : (string) ($customer['from_location_id'] ?? '');
                    }

                    if (trim((string) ($pair['pickup_location'] ?? '')) === $store['pickup_location']) {
                        $restore = $pair['pickup_restore'] ?? ['preset' => $customer['pickup_location_preset'] ?? '', 'location' => $customer['pickup_location'] ?? ''];
                        $pair['pickup_preset'] = (string) ($restore['preset'] ?? '');
                        $pair['pickup_location'] = (string) ($restore['location'] ?? '');
                    }

                    foreach (['consignor_pic_name', 'consignor_pic_phone'] as $field) {
                        if ($store[$field] !== '' && trim((string) ($pair[$field] ?? '')) === $store[$field]) {
                            $pair[$field] = '';
                        }
                    }
                }

                unset($pair['pickup_from_location_id'], $pair['pickup_restore']);

                return;
            }

            if ($m[2] === 'service_type') {
                // remember the Pickup From and pickup location (before the store's replace them) for a switch back
                if (! array_key_exists('pickup_from_location_id', $pair)) {
                    $pair['pickup_from_location_id'] = (string) ($pair['from_location_id'] ?? '');
                }

                if (! array_key_exists('pickup_restore', $pair)) {
                    $pair['pickup_restore'] = ['preset' => (string) ($pair['pickup_preset'] ?? ''), 'location' => (string) ($pair['pickup_location'] ?? '')];
                }

                // no store picked yet: the only store of the order's branch, when it has just one
                if (! $store) {
                    $branchStores = Store::query()->active()->where('branch_id', CurrentCompany::branchId())->pluck('id');

                    if ($branchStores->count() === 1) {
                        $pair['store_id'] = (string) $branchStores->first();
                        $store = OrderFormOptions::storeDefaults($pair['store_id']);
                    }
                }
            }

            // the store's address is the pickup location; its From, PIC and contact number fill the consignor fields
            if ($store) {
                $pair['legacy_store_branch_id'] = '';
                $pair['pickup_preset'] = OrderFormOptions::NEW_ADDRESS;
                $pair['pickup_location'] = $store['pickup_location'];

                if ($store['from_location_id'] !== '') {
                    $pair['from_location_id'] = $store['from_location_id'];
                }

                foreach (['consignor_pic_name', 'consignor_pic_phone'] as $field) {
                    if ($store[$field] !== '') {
                        $pair[$field] = $store[$field];
                    }
                }
            }

            return;
        }

        // a saved consignor / consignee (user, 21 Oct 2026): its name, address, PIC, telephone and area fill the block
        if (preg_match('/^(\d+)\.(consignor_party_id|consignee_party_id)$/', $key, $m)) {
            $index = (int) $m[1];
            $party = filled($value)
                ? ConsignorConsignee::query()->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))->find($value)
                : null;

            if (! $party || ! isset($this->pairs[$index])) {
                // none picked: the block has no consignor / consignee name any more
                if (blank($value) && isset($this->pairs[$index])) {
                    $this->pairs[$index][$m[2] === 'consignor_party_id' ? 'consignor_name' : 'consignee_name'] = '';
                }

                return;
            }

            $pair = &$this->pairs[$index];

            if ($m[2] === 'consignor_party_id') {
                $pair['consignor_name'] = $party->name;
                $pair['consignor_pic_name'] = (string) ($party->pic_name ?? $pair['consignor_pic_name'] ?? '');
                $pair['consignor_pic_phone'] = (string) ($party->phone ?? $pair['consignor_pic_phone'] ?? '');

                // the address and area only on a Pickup (a Customer Drop Off keeps the store's, user 28 Oct 2026)
                if (($pair['service_type'] ?? '') !== ServiceType::Store->value) {
                    if (filled($party->address)) {
                        $pair['pickup_preset'] = OrderFormOptions::NEW_ADDRESS;
                        $pair['pickup_location'] = (string) $party->address;
                    }

                    if ($party->location_id) {
                        $pair['from_location_id'] = (string) $party->location_id;
                    }
                }

                return;
            }

            $pair['consignee_name'] = $party->name;
            $pair['consignee_pic_name'] = (string) ($party->pic_name ?? $pair['consignee_pic_name'] ?? '');
            $pair['consignee_pic_phone'] = (string) ($party->phone ?? $pair['consignee_pic_phone'] ?? '');

            // the address and area only on a Deliver (a Self Collection keeps the store's, user 28 Oct 2026)
            $deliver = ($pair['consignee_mode'] ?? 'deliver') !== 'store';

            if ($deliver && filled($party->address)) {
                $pair['drop_off_preset'] = OrderFormOptions::NEW_ADDRESS;
                $pair['drop_off_location'] = (string) $party->address;
            }

            if ($deliver && $party->location_id) {
                $pair['to_location_id'] = (string) $party->location_id;
                unset($pair);
                $this->refreshPairPrices($index);
            }

            return;
        }

        if (preg_match('/^(\d+)\.(pickup_preset|drop_off_preset|to_location_id|drop_off_type)$/', $key, $m)) {
            $index = (int) $m[1];
            $pair = &$this->pairs[$index];

            if ($m[2] === 'pickup_preset') {
                if ($value === OrderFormOptions::NEW_ADDRESS) {
                    // "New address…": start from an empty line unless the text was already typed by hand
                    $customerId = filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null;

                    if (OrderFormOptions::pickupPresetFor($customerId, $pair['pickup_location'] ?? '') !== OrderFormOptions::NEW_ADDRESS) {
                        $pair['pickup_location'] = '';
                    }
                } else {
                    $address = filled($value) ? CustomerAddress::query()->find($value) : null;
                    $pair['pickup_location'] = $address ? $this->formatAddress($address) : '';
                }
            } elseif ($m[2] === 'drop_off_preset') {
                // same picker as the pickup location: a saved address, or "New address…" typed below it
                if ($value === OrderFormOptions::NEW_ADDRESS) {
                    $customerId = filled($this->form['customer_id'] ?? null) ? (string) $this->form['customer_id'] : null;

                    if (OrderFormOptions::pickupPresetFor($customerId, $pair['drop_off_location'] ?? '') !== OrderFormOptions::NEW_ADDRESS) {
                        $pair['drop_off_location'] = '';
                    }
                } else {
                    $address = filled($value) ? CustomerAddress::query()->find($value) : null;

                    if ($address) {
                        $pair['consignee_name'] = $address->label ?: $pair['consignee_name'];
                    }

                    $pair['drop_off_location'] = $address ? $this->formatAddress($address) : '';
                }
            } else {
                $this->refreshPairPrices($index);
            }

            return;
        }

        // the unit price box changed by hand: kept when the quantity or destination changes; cleared = the price again
        if (preg_match('/^(\d+)\.items\.(\d+)\.manual_price$/', $key, $m)) {
            $item = &$this->pairs[(int) $m[1]]['items'][(int) $m[2]];
            $entered = is_numeric($value) ? round((float) $value, 2) : null;
            $listed = $item['unit_price'] ?? null;

            if ($entered === null && $listed !== null) {
                $item['manual_price'] = $this->priceText((float) $listed);
                $item['price_edited'] = false;
            } else {
                $item['price_edited'] = $entered !== null && ($listed === null || abs($entered - (float) $listed) >= 0.005);
            }

            return;
        }

        if (preg_match('/^(\d+)\.items\.(\d+)\.(kind|sub_category|length|length_max|width|width_max|height|height_max|weight)$/', $key, $m)) {
            $index = (int) $m[1];
            $itemIndex = (int) $m[2];
            $item = &$this->pairs[$index]['items'][$itemIndex];

            // sub-category "Others" (user, 25 Oct 2026): a product not in the system, named and priced on this order
            // only (no product is created)
            if ($m[3] === 'sub_category' && $value === self::OTHER_PRODUCT) {
                $item = array_merge($item, [
                    'line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'manual_price' => '', 'price_edited' => false,
                    'unit_price' => null, 'tier' => null, 'no_band' => false, 'length' => '', 'length_max' => '', 'width' => '', 'width_max' => '', 'height' => '', 'height_max' => '', 'weight' => '',
                ]);

                return;
            }

            if (in_array($m[3], ['length', 'length_max', 'width', 'width_max', 'height', 'height_max', 'weight'], true) && ($item['sub_category'] ?? '') === self::OTHER_PRODUCT) {
                return;
            }

            if ($m[3] !== 'kind' && $this->itemKind($item) !== Uom::MEASUREMENT) {
                // an Item / Chartered Lorry row: another sub-category, the product is picked again
                if ($m[3] === 'sub_category') {
                    $item = array_merge($item, ['catalog_key' => '', 'item_name' => '', 'uom' => '', 'manual_price' => '', 'price_edited' => false, 'unit_price' => null, 'tier' => null]);
                }

                return;
            }

            if ($m[3] === 'kind') {
                // another type: the row starts again
                $item = array_merge($item, [
                    'line_type' => 'uom', 'catalog_key' => '', 'item_name' => '', 'uom' => '', 'sub_category' => '',
                    'length' => '', 'length_max' => '', 'width' => '', 'width_max' => '', 'height' => '', 'height_max' => '', 'weight' => '', 'manual_price' => '', 'price_edited' => false,
                    'no_band' => false,
                ]);
            } else {
                // a Measurement row: the size band its size and weight fit is the product
                $product = $this->measurementProduct($item);
                $catalogKey = $product ? 'uom:'.$product->id : '';

                if ($catalogKey !== (string) ($item['catalog_key'] ?? '')) {
                    $item['price_edited'] = false;
                    $item['manual_price'] = '';
                }

                // no band fits the full size: the line is named by the product and its size, its price keyed in
                $sized = $this->measurementSize($item);
                $item['no_band'] = ! $product && $sized !== null;
                $item['catalog_key'] = $catalogKey;
                $item['item_name'] = $product?->name ?? ($sized ?? trim((string) ($item['sub_category'] ?? '')));
                $item['uom'] = $product?->code ?? '';
                $item['line_type'] = 'uom';
            }

            $this->refreshItemPrice($index, $itemIndex);

            return;
        }

        if (preg_match('/^(\d+)\.items\.(\d+)\.(line_type|catalog_key|quantity)$/', $key, $m)) {
            $index = (int) $m[1];
            $itemIndex = (int) $m[2];
            $item = &$this->pairs[$index]['items'][$itemIndex];
            $lookup = app(QuotationPricingLookup::class);

            if ($m[3] === 'line_type') {
                $item['catalog_key'] = '';
                $item['item_name'] = '';
                $item['uom'] = '';
                $item['quantity'] = 1;
                $item['unit_price'] = null;
                $item['tier'] = null;

                return;
            }

            if ($m[3] === 'quantity') {
                $item['quantity'] = max(1, (int) round((float) $value)); // whole units only
            }

            if ($m[3] === 'catalog_key') {
                // another product: its own price fills the box again
                $item['price_edited'] = false;
                // the product list offers UOM products and chartered lorries (one trip each)
                $item['line_type'] = str_starts_with((string) $value, 'chartered:') ? 'lorry' : 'uom';

                if ($item['line_type'] === 'lorry') {
                    $item['quantity'] = 1;
                }

                $name = filled($value) ? $lookup->resolveCatalogName($value) : null;
                $item['item_name'] = $name ?? '';
                $item['uom'] = $item['line_type'] === 'uom' ? ($lookup->resolveUomCode($value, $name) ?? '') : '';
            }

            $this->refreshItemPrice($index, $itemIndex);
        }
    }

    protected function refreshAllPrices(): void
    {
        foreach (array_keys($this->pairs) as $index) {
            $this->refreshPairPrices($index);
        }
    }

    protected function refreshPairPrices(int $index): void
    {
        foreach (array_keys($this->pairs[$index]['items'] ?? []) as $itemIndex) {
            $this->refreshItemPrice($index, $itemIndex);
        }
    }

    /**
     * Unit price of a product row: the customer's special price when it has one, otherwise the default price
     * (tier by destination, drop-off type and quantity); only shown once a salesperson owns the order.
     */
    protected function refreshItemPrice(int $index, int $itemIndex): void
    {
        $pair = &$this->pairs[$index];
        $item = &$pair['items'][$itemIndex];
        $item['unit_price'] = null;
        $item['tier'] = null;
        $item['source'] = null;
        $item['available'] = null;

        if (! $this->showPrices() || blank($item['item_name'])) {
            $this->fillPriceBox($item);

            return;
        }

        $quantity = max(0.01, (float) ($item['quantity'] ?: 1));
        $dropOffType = ($pair['drop_off_type'] ?? '') ?: null;
        $item['available'] = ($item['line_type'] ?? 'uom') === 'uom' ? $this->ratedLocations($item['item_name'], $quantity, $dropOffType) : null;
        $locationName = filled($pair['to_location_id']) ? Location::query()->whereKey($pair['to_location_id'])->value('name') : null;

        if (! $locationName) {
            $this->fillPriceBox($item);

            return;
        }

        $lookup = app(QuotationPricingLookup::class);
        $resolved = $lookup->lookupForCustomer($this->pricingCustomerId(), $item['item_name'], $locationName, $quantity, $dropOffType);
        $tier = ($item['line_type'] ?? 'uom') === 'uom' ? $lookup->matchedUomTier($item['item_name'], $locationName, $quantity, $this->pricingCustomerId(), $dropOffType) : null;

        $item['unit_price'] = $resolved['price'] !== null ? (float) $resolved['price'] : null;
        $item['source'] = $resolved['source'];
        $item['tier'] = $tier ? 'Qty '.$tier->rangeLabel() : null;
        $this->fillPriceBox($item);
    }

    /** The unit price box shows the special / default price until it is changed by hand. */
    protected function fillPriceBox(array &$item): void
    {
        // a price changed by hand that is the list price again (e.g. after a quantity change) is no longer a change
        if (($item['price_edited'] ?? false) && ($item['unit_price'] ?? null) !== null && is_numeric($item['manual_price'] ?? null)
            && abs((float) $item['manual_price'] - (float) $item['unit_price']) < 0.005) {
            $item['price_edited'] = false;
        }

        if (! ($item['price_edited'] ?? false)) {
            $item['manual_price'] = ($item['unit_price'] ?? null) !== null ? $this->priceText((float) $item['unit_price']) : '';
        }
    }

    protected function priceText(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    /** "Johor RM 30.00 · Melaka RM 11.00" — where this product has a price (special or default) for the quantity. */
    protected function ratedLocations(string $itemName, float $quantity, ?string $dropOffType = null): ?string
    {
        $uom = Uom::query()->where('name', $itemName)->first();

        if (! $uom) {
            return null;
        }

        $tiers = app(PriceListRates::class)->uomTiersByLocation($uom, $quantity, $this->pricingCustomerId(), $dropOffType);

        if ($tiers->isEmpty()) {
            return null;
        }

        $names = Location::query()->whereIn('id', $tiers->keys())->where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $rated = $names->map(fn (string $name, int $id) => $name.' RM '.number_format((float) $tiers[$id]->price, 2))->values();

        return $rated->count() > 6
            ? $rated->take(6)->implode(' · ').' · +'.($rated->count() - 6).' more'
            : $rated->implode(' · ');
    }

    /**
     * Section 1's PIC / contact number into each consignor (Pickup) that is blank or still holds the previous value,
     * so it is not typed twice.
     *
     * @param  array<string, string>  $previous  customer_pic_name / customer_pic_phone before the change
     */
    protected function syncConsignorPic(array $previous): void
    {
        $fields = ['customer_pic_name' => 'consignor_pic_name', 'customer_pic_phone' => 'consignor_pic_phone'];

        foreach ($this->pairs as $index => $pair) {
            if (($pair['service_type'] ?? ServiceType::Pickup->value) === ServiceType::Store->value || $this->isPairLocked($index)) {
                continue; // a Store block has the store's PIC
            }

            foreach ($previous as $customerField => $old) {
                $field = $fields[$customerField];
                $current = trim((string) ($pair[$field] ?? ''));

                if ($current === '' || $current === $old) {
                    $this->pairs[$index][$field] = trim((string) ($this->form[$customerField] ?? ''));
                }
            }
        }
    }

    /**
     * The minimum charge of a block's drop-off (price list: pick-up / construction site / drop-off minimum).
     *
     * @param  array<int, float>  $lineTotals
     * @return array{minimum: float, label: ?string, subtotal: float, top_up: float, pending: bool, note: ?string}|null
     */
    public function pairMinimum(int $index, array $lineTotals): ?array
    {
        $pair = $this->pairs[$index] ?? null;

        if (! $pair || ! $this->showPrices() || blank($pair['to_location_id'] ?? null)) {
            return null;
        }

        $location = Location::query()->whereKey($pair['to_location_id'])->value('name');
        $lines = collect($pair['items'] ?? [])
            ->filter(fn (array $item) => filled($item['item_name'] ?? null))
            ->map(fn (array $item, int $j) => [
                'item_name' => (string) $item['item_name'],
                'quantity' => max(0.01, (float) ($item['quantity'] ?: 1)),
                'line_total' => (float) ($lineTotals[$j] ?? 0),
                'priced' => $this->effectivePrice($item) !== null,
            ])
            ->values()
            ->all();

        if (! $location || $lines === []) {
            return null;
        }

        return app(OrderMinimumCharge::class)->forDestination($this->pricingCustomerId(), $location, ($pair['drop_off_type'] ?? '') ?: null, $pair['service_type'] ?? null, $lines);
    }

    public function addPair(): void
    {
        $this->pairs[] = $this->pairTemplate();
    }

    /** Quick action: empty the consignor (optional: left blank it is saved blank, not as the customer). */
    public function clearConsignor(int $index): void
    {
        if (isset($this->pairs[$index])) {
            $this->pairs[$index]['consignor_name'] = '';
        }
    }

    /** Quick action: the customer is the consignor again. */
    public function useCustomerAsConsignor(int $index): void
    {
        if (isset($this->pairs[$index])) {
            $this->pairs[$index]['consignor_name'] = $this->customerName() ?? '';
        }
    }

    public function removePair(int $index): void
    {
        if (count($this->pairs) <= 1) {
            return;
        }

        unset($this->pairs[$index]);
        $this->pairs = array_values($this->pairs);
        // a picker still uploading belonged to a block that may have moved
        $this->photoUploads = [];
    }

    public function addItem(int $index): void
    {
        $this->pairs[$index]['items'][] = $this->itemTemplate();
    }

    /** Another address of a side (consignor = pickup, consignee = dropoff), with its own DO number (user, 28 Oct 2026). */
    public function addStop(int $index, string $side = 'pickup'): void
    {
        $side = $side === 'dropoff' ? 'dropoff' : 'pickup';
        $this->pairs[$index][$side.'_stops'][] = \App\Support\OrderStops::blank($side === 'pickup' ? \App\Support\OrderStops::PICKUP : \App\Support\OrderStops::DROPOFF);
    }

    public function removeStop(int $index, string $side, int $stopIndex): void
    {
        $side = $side === 'dropoff' ? 'dropoff' : 'pickup';
        unset($this->pairs[$index][$side.'_stops'][$stopIndex]);
        $this->pairs[$index][$side.'_stops'] = array_values($this->pairs[$index][$side.'_stops']);
    }

    public function removeItem(int $index, int $itemIndex): void
    {
        if (count($this->pairs[$index]['items']) <= 1) {
            return;
        }

        unset($this->pairs[$index]['items'][$itemIndex]);
        $this->pairs[$index]['items'] = array_values($this->pairs[$index]['items']);
        // a picker still uploading belonged to a row that may have moved
        $this->photoUploads = [];
    }

    /*
    |--------------------------------------------------------------------------
    | Photos per product row
    |--------------------------------------------------------------------------
    */

    /**
     * Files just uploaded with a product row's photo picker: checked (images / PDF, 8 MB) and added to the row's
     * photos; a file that fails is left out with its message under the row.
     */
    public function updatedPhotoUploads(mixed $value, ?string $key = null): void
    {
        $parts = explode('.', (string) $key);
        $order = (int) ($parts[0] ?? -1);
        $index = (int) ($parts[1] ?? -1);
        $itemIndex = (int) ($parts[2] ?? -1);
        $files = array_values(array_filter(Arr::wrap($this->photoUploads[$order][$index][$itemIndex] ?? []), fn ($file) => $file instanceof TemporaryUploadedFile));
        $this->photoUploads[$order][$index][$itemIndex] = [];

        if (! isset($this->orders[$order])) {
            return;
        }

        $this->switchToOrder($order);

        if (! isset($this->pairs[$index]['items'][$itemIndex]) || $this->isPairLocked($index) || $files === []) {
            return;
        }

        $errorKey = 'pairs.'.$index.'.items.'.$itemIndex.'.photos';
        $this->resetErrorBag([$errorKey, 'photoUploads.'.$order.'.'.$index.'.'.$itemIndex]);
        $this->errorOrder = $order;
        $accepted = [];

        foreach ($files as $file) {
            $check = Validator::make(['photo' => $file], ['photo' => self::PHOTO_RULE], [], ['photo' => $file->getClientOriginalName()]);

            if ($check->fails()) {
                $this->addError($errorKey, $check->errors()->first('photo'));

                continue;
            }

            $accepted[] = $file;
        }

        $item = &$this->pairs[$index]['items'][$itemIndex];
        $item['photos'] = array_values(array_merge(
            array_filter($item['photos'] ?? [], fn ($file) => $file instanceof TemporaryUploadedFile),
            $accepted,
        ));
        unset($item);
        $this->stashActive();
    }

    /** Takes a photo picked on the page off its product row before the order is saved. */
    public function removePhoto(int $index, int $itemIndex, int $photoIndex): void
    {
        if (! isset($this->pairs[$index]['items'][$itemIndex]['photos'][$photoIndex]) || $this->isPairLocked($index)) {
            return;
        }

        unset($this->pairs[$index]['items'][$itemIndex]['photos'][$photoIndex]);
        $this->pairs[$index]['items'][$itemIndex]['photos'] = array_values($this->pairs[$index]['items'][$itemIndex]['photos']);
    }

    /**
     * Photos picked for a product row as the page shows them (not saved yet).
     *
     * @return list<array{name: string, url: ?string, is_image: bool}>
     */
    public function newPhotos(int $index, int $itemIndex): array
    {
        return collect($this->pairs[$index]['items'][$itemIndex]['photos'] ?? [])
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
            ->map(function (TemporaryUploadedFile $file): array {
                $image = str_starts_with((string) $file->getMimeType(), 'image/');

                try {
                    $url = $image && $file->isPreviewable() ? $file->temporaryUrl() : null;
                } catch (Throwable) {
                    $url = null;
                }

                return ['name' => $file->getClientOriginalName(), 'url' => $url, 'is_image' => $image];
            })
            ->values()
            ->all();
    }

    /**
     * A block as the order actions take it (pairData) with the photos picked for each product row stored now
     * (none for a locked block). Photos are kept per product; the block itself takes none.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    protected function pairDataWithPhotos(array $pair, int $index): array
    {
        $data = $this->pairData($pair, $index);
        $data['attachments'] = [];

        foreach ($data['items'] as $j => $item) {
            $data['items'][$j]['attachments'] = $this->isPairLocked($index) ? [] : $this->storePhotos($pair['items'][$j]['photos'] ?? []);
        }

        return $data;
    }

    /**
     * Stores picked photos on the public disk, each under its own folder with its original (cleaned) name, so a
     * record's file list reads well.
     *
     * @param  array<int, mixed>  $photos
     * @return list<array{path: string, name: string, mime: ?string, size: int|false, uploaded_by: ?string, uploaded_at: string}>
     */
    protected function storePhotos(array $photos): array
    {
        $files = [];

        foreach ($photos as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
            $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'photo';
            $path = $file->storeAs('portal-enquiries/admin/'.now()->format('Ym').'/'.Str::lower(Str::random(12)), Str::limit($base, 80, '').'.'.$extension, 'public');

            $files[] = ['path' => $path, 'name' => $original, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => auth()->user()?->name, 'uploaded_at' => now()->toDateTimeString()];
        }

        return $files;
    }

    /*
    |--------------------------------------------------------------------------
    | Previous records of a product for the customer
    |--------------------------------------------------------------------------
    */

    /** History icon of a product row: the customer's special price, the price list and previous order lines of that product (information only). */
    /** "+ New customer" (user, 21 Oct 2026): added to Master Data → Customers and picked for the order. */
    public function createCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('New customer')
            ->modalHeading('New customer')
            ->modalDescription('Added to Master Data → Customers and selected for this order. Credit limit, e-invoice details and portal login can be added on the customer page later.')
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->form([
                \Filament\Forms\Components\Grid::make(3)->schema([
                    \Filament\Forms\Components\TextInput::make('company_name')->label('Company name')->required()->maxLength(255)->columnSpan(2),
                    \Filament\Forms\Components\TextInput::make('code')->label('Debtor account')->maxLength(50)->unique('customers', 'code')->helperText('Leave blank to auto-generate.'),
                    \Filament\Forms\Components\TextInput::make('brn')->label('Business Registration No (BRN)')->maxLength(50),
                    \Filament\Forms\Components\Select::make('customer_type')
                        ->label('Customer type')
                        ->options([OrderType::Cash->value => 'Cash', OrderType::Cod->value => 'COD', OrderType::Term->value => 'Credit / Term'])
                        ->default(OrderType::Cash->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    \Filament\Forms\Components\Select::make('credit_term_days')
                        ->label('Credit term')
                        ->options(fn () => array_filter(\App\Filament\Resources\CustomerResource\Schemas\CustomerForm::creditTermOptions(), fn ($days) => (int) $days > 0, ARRAY_FILTER_USE_KEY))
                        ->default(30)
                        ->required()
                        ->visible(fn (\Filament\Forms\Get $get) => $get('customer_type') === OrderType::Term->value),
                    \Filament\Forms\Components\TextInput::make('attention')->label('PIC name')->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('phone')->label('Contact number')->tel()->maxLength(50),
                    \Filament\Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('address')->label('Billing address')->rows(2)->columnSpanFull(),
                ]),
            ])
            ->modalSubmitActionLabel('Create customer')
            ->action(function (array $data, array $arguments): void {
                $this->switchToOrder((int) ($arguments['order'] ?? $this->activeOrder));
                $type = OrderType::tryFrom((string) ($data['customer_type'] ?? '')) ?? OrderType::Cash;
                $name = trim((string) $data['company_name']);
                $text = fn (string $key) => filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;

                $customer = Customer::query()->create([
                    'company_id' => CurrentCompany::id(),
                    'branch_id' => CurrentCompany::branchId(),
                    'code' => $text('code') ?? \App\Filament\Resources\CustomerResource\Schemas\CustomerForm::generateDebtorCode($name),
                    'company_name' => $name,
                    'brn' => $text('brn'),
                    'attention' => $text('attention'),
                    'phone' => $text('phone'),
                    'email' => $text('email'),
                    'address' => $text('address'),
                    'einvoice_buyer_name' => $name,
                    'default_order_type' => $type->value,
                    'is_credit' => $type === OrderType::Term,
                    'credit_term_days' => $type === OrderType::Term ? (int) ($data['credit_term_days'] ?? 30) : 0,
                    'status' => 'active',
                ]);

                // picked as if chosen in the Customer field (billing address, PIC, payment terms follow)
                $this->updatingForm((string) $customer->id, 'customer_id');
                $this->form['customer_id'] = (string) $customer->id;
                $this->updatedForm((string) $customer->id, 'customer_id');
                $this->stashActive();

                Notification::make()->title('Customer '.$customer->company_name.' ('.$customer->code.') created and selected')->success()->send();
            });
    }

    /** Every earlier order line of the selected customer, any product (the history button beside Line total, user 25 Oct 2026). */
    public function customerHistoryAction(): Action
    {
        return Action::make('customerHistory')
            ->label('Customer history')
            ->modalHeading(fn () => 'Previous records · '.($this->customerName() ?? 'customer'))
            ->modalDescription('Special prices and earlier orders of this customer, every product.')
            ->modalContent(function (array $arguments) {
                $this->switchToOrder((int) ($arguments['order'] ?? $this->activeOrder));
                $customerId = filled($this->form['customer_id'] ?? null)
                    ? Customer::query()->whereKey($this->form['customer_id'])->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))->value('id')
                    : null;

                return view('filament.pages.partials.order-customer-history', [
                    'customer' => $this->customerName(),
                    // the customer's special prices, as on Customer → Special price (user, 25 Oct 2026)
                    'special' => $customerId ? \App\Filament\Resources\CustomerResource::specialPrices(Customer::query()->find($customerId)) : [],
                    // the default price list (user, 28 Oct 2026): the same for every customer, filtered by category / sub-category
                    'defaults' => \App\Filament\Resources\CustomerResource::defaultPrices(),
                    'rows' => $customerId
                        ? app(CustomerQuotationPriceHistory::class)->productHistory((int) $customerId, null, null, $this->historyExcludedQuotationIds(), 100, CurrentCompany::id())
                        : [],
                ]);
            })
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * "+ New consignor" / "+ New consignee" (user, 26 Oct 2026, as "+ New customer"): adds it to Master Data →
     * Consignor / Consignee, tagged to the selected customer, and picks it for the block.
     */
    public function createPartyAction(): Action
    {
        return Action::make('createParty')
            ->modalHeading(fn (array $arguments) => 'New '.(($arguments['role'] ?? 'consignor') === 'consignee' ? 'consignee' : 'consignor'))
            ->modalSubmitActionLabel('Create and select')
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->form([
                \Filament\Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(255),
                \Filament\Forms\Components\Grid::make(2)->schema([
                    \Filament\Forms\Components\TextInput::make('pic_name')->label('PIC name')->maxLength(150),
                    \Filament\Forms\Components\TextInput::make('phone')->label('Telephone number')->tel()->maxLength(50),
                ]),
                \Filament\Forms\Components\Textarea::make('address')->label('Address')->rows(2),
                \Filament\Forms\Components\Select::make('location_id')
                    ->label('Area (price list location)')
                    ->options(fn () => Location::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->switchToOrder((int) ($arguments['order'] ?? $this->activeOrder));
                $index = (int) ($arguments['pair'] ?? 0);
                $role = ($arguments['role'] ?? 'consignor') === 'consignee' ? 'consignee' : 'consignor';

                if (! isset($this->pairs[$index])) {
                    return;
                }

                $party = ConsignorConsignee::query()->create([
                    'company_id' => CurrentCompany::id(),
                    'name' => trim((string) $data['name']),
                    'type' => $role,
                    'pic_name' => $data['pic_name'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'location_id' => $data['location_id'] ?? null,
                    'is_active' => true,
                ]);

                if (filled($this->form['customer_id'] ?? null)) {
                    $party->customers()->syncWithoutDetaching([(int) $this->form['customer_id']]);
                }

                $this->savedPartyRows = null;
                $this->pairs[$index][$role.'_party_id'] = (string) $party->id;
                $this->updatedPairs((string) $party->id, $index.'.'.$role.'_party_id');

                Notification::make()->title(ucfirst($role).' '.$party->name.' created and selected')->success()->send();
            });
    }

    public function productHistoryAction(): Action
    {
        return Action::make('productHistory')
            ->label('Previous records')
            ->modalHeading(fn (array $arguments) => 'Prices & previous records · '.($this->historyItem($arguments)['item_name'] ?? 'product'))
            ->modalDescription(fn () => $this->customerName() ? 'Special price, price list and earlier orders of '.$this->customerName().' for this product.' : null)
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.order-product-history', $this->productHistoryData($arguments)))
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * What the previous-records modal shows for one product row.
     *
     * @param  array<string, mixed>  $arguments  pair (block index) and item (row index)
     * @return array{item_name: string, location: ?string, customer: ?string, rows: list<array<string, mixed>>}
     */
    public function productHistoryData(array $arguments): array
    {
        $this->switchToOrder((int) ($arguments['order'] ?? $this->activeOrder));
        $item = $this->historyItem($arguments);
        // only a customer of the current company (form.customer_id is client-side state)
        $customerId = filled($this->form['customer_id'] ?? null)
            ? Customer::query()->whereKey($this->form['customer_id'])->when(CurrentCompany::id(), fn ($q, $id) => $q->where('company_id', $id))->value('id')
            : null;
        $toLocationId = $this->pairs[(int) ($arguments['pair'] ?? -1)]['to_location_id'] ?? null;
        $location = filled($toLocationId) ? Location::query()->whereKey($toLocationId)->value('name') : null;

        $name = (string) ($item['item_name'] ?? '');
        $quantity = max(0.01, (float) (($item['quantity'] ?? 1) ?: 1));

        return [
            'item_name' => $name,
            'location' => $location,
            'customer' => $this->customerName(),
            'special' => $customerId && $name !== '' ? $this->specialPricesFor((int) $customerId, $name, $location) : [],
            'default' => $name !== '' ? $this->defaultPricesFor($name, $location, $quantity) : [],
            'rows' => $customerId && filled($item['item_name'] ?? null)
                ? app(CustomerQuotationPriceHistory::class)->productHistory((int) $customerId, (string) $item['item_name'], $location, $this->historyExcludedQuotationIds(), companyId: CurrentCompany::id())
                : [],
        ];
    }

    /**
     * The customer's special prices for the product (Customer → Special pricing), this row's destination first.
     *
     * @return list<array{destination: string, uom: ?string, price: ?float, min_charge: ?float, same: bool}>
     */
    protected function specialPricesFor(int $customerId, string $itemName, ?string $location): array
    {
        $code = app(PriceListRates::class)->customerCode($customerId);
        $uom = $code ? Uom::query()->where('name', $itemName)->first() : null;
        // the customer's special price list (imported by debtor code): one row per destination and quantity range
        $listed = $uom
            ? $uom->rateTiers()->where('customer_code', $code)->with('location:id,name')->orderBy('location_id')->orderBy('min_qty')->get()
                ->map(fn (UomRateTier $tier) => [
                    'destination' => ($tier->location?->name ?? '—').(($tier->drop_off_type ?? 'default') !== DropOffType::Default->value ? ' · '.(DropOffType::tryFrom((string) $tier->drop_off_type)?->getLabel() ?? $tier->drop_off_type) : ''),
                    'uom' => 'Qty '.$tier->rangeLabel().($tier->fixed_charge !== null && (float) $tier->fixed_charge > 0 ? ' · RM '.number_format((float) $tier->fixed_charge, 2).' fixed +' : ''),
                    'price' => (float) $tier->price,
                    'min_charge' => $tier->drop_off_min_charge !== null ? (float) $tier->drop_off_min_charge : null,
                    'same' => $location !== null && $tier->location?->name === $location,
                ])
            : collect();

        return $listed->concat($this->customerPricingRows($customerId, $itemName, $location))
            ->sortBy(fn (array $row) => [$row['same'] ? 0 : 1, $row['destination']])
            ->values()
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> Customer → Special pricing rows */
    protected function customerPricingRows(int $customerId, string $itemName, ?string $location): \Illuminate\Support\Collection
    {
        return CustomerPricing::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->where('item_name', $itemName)
            ->get()
            ->map(fn (CustomerPricing $row) => [
                'destination' => filled($row->destination) ? (string) $row->destination : 'All destinations',
                'uom' => filled($row->uom) ? (string) $row->uom : null,
                'price' => ($row->unit_rate ?? $row->base_price) !== null ? (float) ($row->unit_rate ?? $row->base_price) : null,
                'min_charge' => $row->min_charge !== null ? (float) $row->min_charge : null,
                'same' => $location !== null && (string) $row->destination === $location,
            ]);
    }

    /**
     * The product's price-list (default) rates per location: the quantity tiers of a UOM product, else the one
     * rate of a transport item / lorry; this row's destination first, with the tier its quantity falls in.
     *
     * @return list<array{location: string, same: bool, tiers: list<array{range: string, price: float, active: bool}>, price: ?float}>
     */
    protected function defaultPricesFor(string $itemName, ?string $location, float $quantity): array
    {
        $lookup = app(QuotationPricingLookup::class);

        $uom = Uom::query()->where('name', $itemName)->first();
        // only the destinations the product has a default price to (a UOM product); others: every location
        $rated = $uom ? $uom->rateTiers()->whereNull('customer_code')->distinct()->pluck('location_id')->all() : null;

        return Location::query()->where('is_active', true)->when($rated !== null, fn ($q) => $q->whereIn('id', $rated))->orderBy('name')->pluck('name')
            ->map(function (string $name) use ($lookup, $itemName, $location, $quantity): ?array {
                $active = $lookup->matchedUomTier($itemName, $name, $quantity);
                $tiers = collect($lookup->uomTierBreakdown($itemName, $name))
                    ->map(fn (array $tier) => $tier + ['active' => $active !== null && abs((float) $active->price - $tier['price']) < 0.001 && $tier['range'] === ($active->max_qty ? number_format((float) $active->min_qty, 0).'–'.number_format((float) $active->max_qty, 0) : number_format((float) $active->min_qty, 0).'+')])
                    ->all();
                $price = $tiers === [] ? $lookup->lookup($itemName, $name, $quantity) : null;

                if ($tiers === [] && $price === null) {
                    return null;
                }

                return ['location' => $name, 'same' => $name === $location, 'tiers' => $tiers, 'price' => $price];
            })
            ->filter()
            ->sortBy(fn (array $row) => [$row['same'] ? 0 : 1, $row['location']])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    protected function historyItem(array $arguments): ?array
    {
        $this->switchToOrder((int) ($arguments['order'] ?? $this->activeOrder));
        $item = $this->pairs[(int) ($arguments['pair'] ?? -1)]['items'][(int) ($arguments['item'] ?? -1)] ?? null;

        return is_array($item) ? $item : null;
    }

    /** @return array{items: float, minimum: float, total: float, lines: array<int, array<int, float>>, minimums: array<int, ?array>} */
    public function pairTotals(): array
    {
        $lines = [];
        $minimums = [];
        $total = 0.0;
        $topUps = 0.0;

        foreach ($this->pairs as $i => $pair) {
            foreach ($pair['items'] as $j => $item) {
                // non-UOM rows only exist when editing an order: they keep the record's quantity
                $qty = max(0.01, (float) ($item['quantity'] ?: 1));
                $price = $this->effectivePrice($item);
                $lines[$i][$j] = $price !== null ? round($price * $qty, 2) : 0.0;
                $total += $lines[$i][$j];
            }

            $minimums[$i] = $this->pairMinimum($i, $lines[$i] ?? []);
            $topUps += (float) ($minimums[$i]['top_up'] ?? 0);
        }

        return ['items' => round($total, 2), 'minimum' => round($topUps, 2), 'total' => round($total + $topUps, 2), 'lines' => $lines, 'minimums' => $minimums];
    }

    /** A row's unit price: its rate, else the price keyed in for a product without one. */
    public function effectivePrice(array $item): ?float
    {
        if (is_numeric($item['manual_price'] ?? null)) {
            return round((float) $item['manual_price'], 2);
        }

        return $item['unit_price'] !== null ? (float) $item['unit_price'] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    /** @return array<string, string> */
    protected function orderValidationRules(): array
    {
        // each block's payment term: one its customer type (or, editing, its record) allows
        $terms = [];

        foreach (array_keys($this->pairs) as $index) {
            $terms['pairs.'.$index.'.order_type'] = 'required|in:'.implode(',', array_keys($this->pairOrderTypeOptions($index)));
        }

        return $terms + [
            'form.customer_id' => 'required|exists:customers,id',
            'form.customer_address' => 'nullable|string|max:2000',
            'form.customer_pic_name' => 'nullable|string|max:255',
            'form.customer_pic_phone' => 'nullable|string|max:50',
            // optional; when picked it must be one of the listed channels
            'form.received_through' => 'nullable|in:'.implode(',', array_keys($this->receivedThroughOptions())),
            'form.salesperson_id' => 'nullable|exists:users,id',
            'pairs' => 'required|array|min:1',
            // optional: a blank consignor / consignee is saved blank (see pairData)
            'pairs.*.consignor_name' => 'nullable|string|max:255',
            'pairs.*.consignee_name' => 'nullable|string|max:255',
            'pairs.*.service_type' => 'required|in:'.implode(',', array_keys($this->serviceTypeOptions())),
            'pairs.*.store_id' => 'nullable|required_if:pairs.*.service_type,'.ServiceType::Store->value.'|exists:stores,id',
            'pairs.*.consignee_store_id' => 'nullable|required_if:pairs.*.consignee_mode,store|exists:stores,id',
            'pairs.*.consignor_pic_name' => 'nullable|string|max:255',
            'pairs.*.consignor_pic_phone' => 'nullable|string|max:50',
            'pairs.*.consignee_pic_name' => 'nullable|string|max:255',
            'pairs.*.consignee_pic_phone' => 'nullable|string|max:50',
            'pairs.*.pickup_location' => 'nullable|string|max:2000',
            'pairs.*.drop_off_location' => 'nullable|string|max:2000',
            'pairs.*.to_location_id' => 'nullable|exists:locations,id',
            'pairs.*.from_location_id' => 'nullable|exists:locations,id',
            // with several stops the DO numbers are on the stops
            // the DO number sits on the extra addresses when a side has several (user, 28 Oct 2026)
            'pairs.*.customer_do_number' => ['nullable', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail): void {
                $index = (int) explode('.', $attribute)[1];
                $pair = $this->pairs[$index] ?? [];

                if (blank($value) && empty($pair['multi_pickup']) && empty($pair['multi_dropoff'])) {
                    $fail('The DO number is required.');
                }
            }],
            'pairs.*.pickup_stops' => 'array',
            'pairs.*.pickup_stops.*.location' => 'nullable|string|max:500',
            'pairs.*.pickup_stops.*.do_number' => 'required_if:pairs.*.multi_pickup,true|nullable|string|max:100',
            'pairs.*.dropoff_stops' => 'array',
            'pairs.*.dropoff_stops.*.name' => 'nullable|string|max:150',
            'pairs.*.dropoff_stops.*.pic_name' => 'nullable|string|max:100',
            'pairs.*.dropoff_stops.*.pic_phone' => 'nullable|string|max:50',
            'pairs.*.dropoff_stops.*.location' => 'nullable|string|max:500',
            'pairs.*.dropoff_stops.*.do_number' => 'required_if:pairs.*.multi_dropoff,true|nullable|string|max:100',
            // becomes the CSN date when the record's CSN is created
            // optional free text (a remark, e.g. "Mon 13/10 before noon"); not the CSN date
            'pairs.*.expected_delivery_date' => 'nullable|string|max:255',
            'pairs.*.drop_off_type' => 'required|in:'.implode(',', array_keys(DropOffType::options())),
            'pairs.*.items' => 'required|array|min:1',
            'pairs.*.items.*.item_name' => 'required|string|max:255',
            'pairs.*.items.*.quantity' => 'required|integer|min:1',
            // price keyed in for a product without a rate
            'pairs.*.items.*.manual_price' => 'nullable|numeric|min:0|max:9999999',
            // photos of each product row (same file rule as before: images or PDF, 8 MB)
            'pairs.*.items.*.photos' => 'nullable|array',
            'pairs.*.items.*.photos.*' => self::PHOTO_RULE,
        ];
    }

    /** @return array<string, string> */
    protected function orderValidationMessages(): array
    {
        return [
            'pairs.*.order_type.required' => 'Choose the payment term.',
            'pairs.*.order_type.in' => 'This payment term is not available for the customer\'s type.',
            'pairs.*.store_id.required_if' => 'Select the store the consignor brings the goods to.',
            'pairs.*.consignee_store_id.required_if' => 'Select the store the customer collects the goods from.',
            'pairs.*.customer_do_number.required' => 'Enter the DO number for every consignor & consignee block.',
            'pairs.*.items.*.item_name.required' => 'Select a product for every item row.',
        ];
    }

    /**
     * One consignor & consignee block as the order actions take it (Create and Edit order):
     * - a blank consignor or consignee is saved blank (the record's price column keeps the To location's name);
     * - Pickup: the pickup location typed or picked; Store: the store (and its branch), the pickup location shown
     *   (the store's address unless edited);
     * - the drop-off location: the saved address picked or the new one typed.
     *
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    protected function pairData(array $pair, int $index): array
    {
        $text = fn (mixed $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;
        $store = ($pair['service_type'] ?? '') === ServiceType::Store->value;
        $storeRow = $store && filled($pair['store_id'] ?? null) ? Store::query()->find($pair['store_id']) : null;
        $toLocationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;
        $pickup = $text($pair['pickup_location'] ?? null) ?? ($store ? $text(OrderFormOptions::storeAddress($storeRow)) : null);

        return [
            'consignor_name' => $text($pair['consignor_name'] ?? null),
            'service_type' => $store ? ServiceType::Store->value : ServiceType::Pickup->value,
            'store_id' => $storeRow?->id,
            'store_branch_id' => $storeRow?->branch_id ?? ($store && filled($pair['legacy_store_branch_id'] ?? null) ? (int) $pair['legacy_store_branch_id'] : null),
            'from_location_id' => filled($pair['from_location_id'] ?? null) ? (int) $pair['from_location_id'] : null,
            'consignor_pic_name' => $text($pair['consignor_pic_name'] ?? null),
            'consignor_pic_phone' => $text($pair['consignor_pic_phone'] ?? null),
            'pickup_location' => $pickup,
            'consignee_name' => $text($pair['consignee_name'] ?? null),
            'to_location_id' => $toLocationId,
            'consignee_pic_name' => $text($pair['consignee_pic_name'] ?? null),
            'consignee_pic_phone' => $text($pair['consignee_pic_phone'] ?? null),
            'drop_off_location' => $text($pair['drop_off_location'] ?? null),
            'drop_off_type' => ($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value,
            // the block's own payment term (Cash / COD / Credit / Term)
            'order_type' => ($pair['order_type'] ?? '') ?: ($this->form['order_type'] ?? null),
            'customer_do_number' => $text($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $text($pair['expected_delivery_date'] ?? null),
            'instructions' => $text($pair['instructions'] ?? null),
            'admin_notes' => $text($pair['admin_notes'] ?? null),
            'stops' => \App\Support\OrderStops::merge($pair) ?: null,
            'consignee_mode' => ($pair['consignee_mode'] ?? '') === 'store' ? 'store' : 'deliver',
            'consignee_store_id' => ($pair['consignee_mode'] ?? '') === 'store' ? (($pair['consignee_store_id'] ?? null) ?: null) : null,
            'items' => array_map(fn (array $item) => [
                'line_type' => ($item['line_type'] ?? null) ?: null,
                'catalog_key' => ($item['catalog_key'] ?? null) ?: null,
                'item_name' => (string) ($item['item_name'] ?? ''),
                'uom' => ($item['uom'] ?? null) ?: null,
                'quantity' => $item['quantity'] ?? 1,
                // the unit price box: the special / default price, or the price changed / keyed in by hand
                'unit_price' => $this->showPrices() ? $this->effectivePrice($item) : null,
            ], $pair['items'] ?? []),
        ];
    }

    /**
     * Creates every order of the page. Each one is checked first; an order with a mistake opens with its errors.
     * Then all are saved together, and for an order with "Record payment now" its Cash records are confirmed, paid
     * and billed (Cash Bill + CSN; a Cash / Pay at Counter payment waits for a second user's approval).
     */
    public function save(): void
    {
        $branch = CurrentCompany::branch();
        $company = Filament::getTenant();

        if (! $branch) {
            Notification::make()->title('Select a company first')->danger()->send();

            return;
        }

        $this->stashActive();
        $this->resetErrorBag();
        $this->errorOrder = null;

        $open = $this->activeOrder;

        foreach (array_keys($this->orders) as $index) {
            $this->useOrder($index);
            $this->validateWorkingOrder();
            $this->validateOrderPayment();
        }

        $this->useOrder($open);

        // the payment of every order ticked "Pay now"
        $payPlan = null;

        if (collect($this->orders)->contains(fn (array $order) => $order['pay_now'] ?? false)) {
            $this->validatePaymentDetails();
            $payPlan = $this->paymentPlan();

            if (! $payPlan['ok']) {
                throw ValidationException::withMessages(['payment.amount_received' => $payPlan['received'] <= 0
                    ? 'Enter the amount received (blank = the full amount).'
                    : 'Split the RM '.number_format($payPlan['received'], 2).' received over the orders (Paying): together the amount received, each at most its total'.(abs($payPlan['left']) >= 0.005 ? ' · RM '.number_format(abs($payPlan['left']), 2).' '.($payPlan['left'] > 0 ? 'still to give' : 'too much') : '').'.']);
            }
        }

        $receiptPaths = null;

        $created = [];

        try {
            DB::transaction(function () use (&$created, $branch, $company) {
                foreach (array_keys($this->orders) as $index) {
                    $this->useOrder($index);
                    $created[$index] = $this->createActiveOrder($branch, $company instanceof \App\Domains\MasterData\Models\Company ? $company : null);
                }
            });
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $lines = [];

        foreach ($created as $index => $result) {
            $orders = $result['orders'];
            $enquiry = $result['enquiry'];
            $paid = [];

            // an order given nothing of a short payment is created without a payment
            $payAmount = $payPlan && ! $payPlan['full'] ? round((float) ($payPlan['amounts']['o'.$index] ?? 0), 2) : null;

            if (($this->orders[$index]['pay_now'] ?? false) && $payAmount !== 0.0) {
                // the proof files are stored once and kept with every order paid now
                $receiptPaths ??= collect($this->paymentProofs)
                    ->filter(fn ($file) => $file instanceof TemporaryUploadedFile)
                    ->map(fn (TemporaryUploadedFile $file) => $file->store('payment-receipts/order-entry/'.now()->format('Y-m'), 'public'))
                    ->values()
                    ->all();

                $paid = app(RecordOrderCreationPayment::class)->execute($orders, [
                    'method' => $this->payment['method'],
                    'payment_date' => $this->payment['payment_date'],
                    'reference' => $this->payment['reference'] ?? null,
                    'receipt_paths' => $receiptPaths,
                    // null = in full; else this order's part of a short payment
                    'amount' => $payAmount,
                ], auth()->user());
            }

            // products kept without a price yet (no price-list rate): priced under Items & pricing
            $unpriced = $orders->flatMap(fn ($order) => $order->lines->whereNull('unit_price')->pluck('item_name'))->unique()->values();
            $lines[] = 'Order '.$enquiry->orderNumber().': '.$orders->pluck('number')->implode(', ')
                .($paid !== [] ? ' · '.collect($paid)->map(fn (array $p) => $p['number'].' '.$p['message'])->implode('; ') : '')
                .(! $enquiry->salesperson_id ? ' · assign a salesperson to continue' : '')
                .($enquiry->salesperson_id && $unpriced->isNotEmpty() ? ' · no price yet for '.$unpriced->implode(', ') : '');
        }

        if ($payPlan && $payPlan['change'] > 0.004) {
            $lines[] = 'Change to give back to the customer: RM '.number_format($payPlan['change'], 2);
        }

        $first = $created[0]['orders']->first();

        Notification::make()
            ->title(count($created) === 1 ? 'Order '.$created[0]['enquiry']->orderNumber().' created · '.$created[0]['orders']->count().' record(s)' : count($created).' orders created')
            ->body(implode("\n", $lines))
            ->success()
            ->persistent()
            ->send();

        $this->redirect(count($created) === 1 ? OrderDetail::urlFor('order', $first->id).'?tab=pricing' : Orders::getUrl(), navigate: false);
    }

    /**
     * Checks the working order's section 1 and blocks (orderValidationRules); an error stays on the page under its
     * field (form.x / pairs.i.x of that order).
     */
    protected function validateWorkingOrder(): void
    {
        $this->resetErrorBag();
        $data = ['form' => $this->form, 'pairs' => $this->pairs];

        $validator = Validator::make($data, $this->orderValidationRules(), $this->orderValidationMessages());

        if ($validator->fails()) {
            // the page shows the errors on this order
            $this->errorOrder = $this->activeOrder;
            $this->dispatch('og-order-opened', order: $this->activeOrder);

            throw new ValidationException($validator);
        }
    }

    /** The payment of the orders ticked "Pay now": a method and date (one payment for all of them). */
    protected function validatePaymentDetails(): void
    {
        $this->validate([
            'payment.method' => 'required|in:'.implode(',', array_keys($this->paymentMethodOptions())),
            'payment.payment_date' => 'required|date|before_or_equal:today',
            'payment.reference' => 'nullable|string|max:100',
            'paymentProofs' => 'array|max:10',
            'paymentProofs.*' => self::PHOTO_RULE,
        ], [], ['payment.method' => 'payment method', 'payment.payment_date' => 'payment date', 'paymentProofs.*' => 'payment proof']);
    }

    /** An order ticked "Pay now": a Cash record and every product of it priced. */
    protected function validateOrderPayment(): void
    {
        if (! $this->payNow) {
            return;
        }

        $problem = match (true) {
            $this->cashRecordCount() === 0 => 'Recording the payment now needs a consignor & consignee with the Cash payment term.',
            ! $this->showPrices() => 'Assign a salesperson so the order is priced before recording its payment.',
            collect($this->pairs)->where('order_type', OrderType::Cash->value)->contains(fn (array $pair) => collect($pair['items'] ?? [])->contains(fn (array $item) => $this->effectivePrice($item) === null)) => 'Enter a price for every product of the Cash consignor & consignee before recording the payment.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['payment.record' => $problem]);
        }
    }

    /** @return array{enquiry: \App\Domains\Quotation\Models\PortalEnquiry, orders: \Illuminate\Support\Collection} */
    protected function createActiveOrder(Branch $branch, ?\App\Domains\MasterData\Models\Company $company): array
    {
            // each block with the photos of its products (stored now, kept with that block's record)
            $blocks = array_values($this->pairs);
            $pairs = array_map(fn (array $pair, int $index) => $this->pairDataWithPhotos($pair, $index), $blocks, array_keys($blocks));

            return app(CreateAdminOrder::class)->execute([
                'customer_id' => $this->form['customer_id'],
                // one billing address for the whole order (each record's customer_address)
                'customer_address' => trim((string) ($this->form['customer_address'] ?? '')) ?: null,
                'customer_pic_name' => trim((string) ($this->form['customer_pic_name'] ?? '')),
                'customer_pic_phone' => trim((string) ($this->form['customer_pic_phone'] ?? '')),
                'received_through' => ($this->form['received_through'] ?? '') ?: null,
                'salesperson_id' => $this->form['salesperson_id'] ?: null,
                // each record has its own payment term; the order form keeps the first block's
                'order_type' => $pairs[0]['order_type'] ?? $this->form['order_type'],
                // pickup / store is per record; the order form keeps the first block's
                'service_type' => $pairs[0]['service_type'] ?? ServiceType::Pickup->value,
                // captured when the payment is recorded
                'payment_method' => null,
                // photos are kept per block (pairs.*.attachments); nothing for the whole order
                'attachments' => [],
                'pairs' => $pairs,
            ], auth()->user(), $branch, $company);
    }

    protected function formatAddress(CustomerAddress $address): string
    {
        return OrderFormOptions::formatAddress($address);
    }
}

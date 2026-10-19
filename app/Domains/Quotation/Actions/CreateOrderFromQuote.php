<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\CharteredLorryRate;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Store;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Domains\Quotation\Models\Quote;
use App\Enums\DropOffType;
use App\Enums\ServiceType;
use App\Models\User;
use App\Support\OrderFormOptions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Create order" on the quotation list (user, 20 Oct 2026): the customer agreed, so the quotation becomes an order
 * with its consignor & consignee blocks, products, details and quoted prices (CreateAdminOrder, as Create order
 * does). A lead becomes a customer first. Each quoted price is kept as the customer's special price for that product
 * and destination (replacing an earlier one), so the next order of the customer takes it.
 */
class CreateOrderFromQuote
{
    public function __construct(private CreateAdminOrder $createOrder) {}

    /** @return array{enquiry: \App\Domains\Quotation\Models\PortalEnquiry, orders: \Illuminate\Support\Collection} */
    public function execute(Quote $quote, User $actor, ?string $acceptedBy = null): array
    {
        return DB::transaction(function () use ($quote, $actor, $acceptedBy) {
            $quote = Quote::query()->lockForUpdate()->with(['customer', 'lead', 'branch'])->findOrFail($quote->id);

            if (! $quote->isPending()) {
                throw new InvalidArgumentException('already an order');
            }

            $branch = $quote->branch ?? throw new InvalidArgumentException('the quotation has no branch');
            $company = $quote->company_id ? Company::query()->find($quote->company_id) : $branch->defaultCompany();
            $customer = $quote->customer ?? $this->customerFromLead($quote, $branch, $company);
            $form = (array) $quote->form;

            $result = $this->createOrder->execute([
                'customer_id' => $customer->id,
                'customer_address' => filled($form['customer_address'] ?? null) ? $form['customer_address'] : null,
                'customer_pic_name' => (string) ($form['customer_pic_name'] ?? ''),
                'customer_pic_phone' => (string) ($form['customer_pic_phone'] ?? ''),
                'received_through' => null,
                'salesperson_id' => $form['salesperson_id'] ?? null,
                'order_type' => ($quote->pairs[0]['order_type'] ?? null) ?: ($form['order_type'] ?? null),
                'service_type' => ($quote->pairs[0]['service_type'] ?? null) ?: ServiceType::Pickup->value,
                'payment_method' => null,
                'attachments' => [],
                'pairs' => array_map(fn (array $pair) => $this->pairData($pair, $customer), array_values((array) $quote->pairs)),
            ], $actor, $branch, $company);

            $this->keepSpecialPrices($quote, $customer, $branch);

            $quote->update([
                'customer_id' => $customer->id,
                // who at the customer accepted the quotation
                'accepted_by' => filled($acceptedBy) ? trim($acceptedBy) : $quote->accepted_by,
                'status' => Quote::ORDERED,
                'order_enquiry_id' => $result['enquiry']->id,
                'ordered_at' => now(),
            ]);

            return $result;
        });
    }

    /** The lead becomes a customer of the quotation's branch (Cash, its details; code L + lead number). */
    private function customerFromLead(Quote $quote, Branch $branch, ?Company $company): Customer
    {
        $lead = $quote->lead ?? throw new InvalidArgumentException('no customer or lead');

        if ($lead->customer_id && ($existing = Customer::query()->find($lead->customer_id))) {
            return $existing;
        }

        $code = 'L'.str_pad((string) $lead->id, 5, '0', STR_PAD_LEFT);

        $customer = Customer::query()->create([
            'branch_id' => $branch->id,
            'company_id' => $company?->id,
            'code' => Customer::query()->where('code', $code)->exists() ? $code.'-'.$quote->number : $code,
            'company_name' => $lead->name,
            'attention' => $lead->pic_name,
            'phone' => $lead->contact,
            'email' => $lead->email,
            'address' => $lead->billing_address ?: $lead->address,
            'is_credit' => false,
            'status' => 'active',
        ]);

        $lead->update(['customer_id' => $customer->id]);

        return $customer;
    }

    /** A consignor & consignee block of the quotation as Create order sends it (CreateOrder::pairData). */
    private function pairData(array $pair, Customer $customer): array
    {
        $text = fn (mixed $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;
        $store = ($pair['service_type'] ?? '') === ServiceType::Store->value;
        $storeRow = $store && filled($pair['store_id'] ?? null) ? Store::query()->find($pair['store_id']) : null;
        $consignor = $text($pair['consignor_name'] ?? null);

        return [
            // the lead's name was the consignor: the customer now
            'consignor_name' => $consignor,
            'service_type' => $store ? ServiceType::Store->value : ServiceType::Pickup->value,
            'store_id' => $storeRow?->id,
            'store_branch_id' => $storeRow?->branch_id,
            'from_location_id' => filled($pair['from_location_id'] ?? null) ? (int) $pair['from_location_id'] : null,
            'consignor_pic_name' => $text($pair['consignor_pic_name'] ?? null),
            'consignor_pic_phone' => $text($pair['consignor_pic_phone'] ?? null),
            'pickup_location' => $text($pair['pickup_location'] ?? null) ?? ($store ? $text(OrderFormOptions::storeAddress($storeRow)) : null),
            'consignee_name' => $text($pair['consignee_name'] ?? null),
            'to_location_id' => filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null,
            'consignee_pic_name' => $text($pair['consignee_pic_name'] ?? null),
            'consignee_pic_phone' => $text($pair['consignee_pic_phone'] ?? null),
            'drop_off_location' => $text($pair['drop_off_location'] ?? null),
            'drop_off_type' => ($pair['drop_off_type'] ?? '') ?: DropOffType::Default->value,
            'order_type' => ($pair['order_type'] ?? '') ?: null,
            'customer_do_number' => $text($pair['customer_do_number'] ?? null),
            'expected_delivery_date' => $text($pair['expected_delivery_date'] ?? null),
            'instructions' => $text($pair['instructions'] ?? null),
            'attachments' => [],
            'items' => array_values(array_filter(array_map(fn (array $item) => [
                'line_type' => ($item['line_type'] ?? null) ?: null,
                'catalog_key' => ($item['catalog_key'] ?? null) ?: null,
                'item_name' => (string) ($item['item_name'] ?? ''),
                'uom' => ($item['uom'] ?? null) ?: null,
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $this->price($item),
                'attachments' => [],
            ], $pair['items'] ?? []), fn (array $item) => $item['item_name'] !== '')),
        ];
    }

    /** The quoted price: the price box (default price, maybe changed), else the default price. */
    private function price(array $item): ?float
    {
        if (is_numeric($item['manual_price'] ?? null)) {
            return round((float) $item['manual_price'], 2);
        }

        return ($item['unit_price'] ?? null) !== null ? round((float) $item['unit_price'], 2) : null;
    }

    /**
     * Each quoted price becomes the customer's special price for the product and destination (one range from 1 up,
     * replacing the customer's earlier special price there).
     */
    private function keepSpecialPrices(Quote $quote, Customer $customer, Branch $branch): void
    {
        $code = trim((string) $customer->code);

        if ($code === '') {
            return;
        }

        foreach ((array) $quote->pairs as $pair) {
            $locationId = filled($pair['to_location_id'] ?? null) ? (int) $pair['to_location_id'] : null;

            foreach ($pair['items'] ?? [] as $item) {
                $price = $this->price($item);
                [$type, $id] = array_pad(explode(':', (string) ($item['catalog_key'] ?? ''), 2), 2, null);

                if (! $locationId || $price === null || ! $id) {
                    continue;
                }

                $common = [
                    'location_id' => $locationId,
                    'customer_code' => $code,
                    'branch_id' => $branch->id,
                    'drop_off_type' => DropOffType::Default->value,
                    'source' => 'Quotation '.$quote->number,
                ];

                if ($type === 'uom' && Uom::query()->whereKey($id)->exists()) {
                    // the price-list minimum charges of the product to this destination stay
                    $default = UomRateTier::query()->where('uom_id', $id)->where('location_id', $locationId)->whereNull('customer_code')->first();
                    UomRateTier::query()->where('uom_id', $id)->where('location_id', $locationId)->where('customer_code', $code)->delete();
                    UomRateTier::query()->create($common + [
                        'uom_id' => (int) $id,
                        'min_qty' => 1,
                        'max_qty' => null,
                        'price' => $price,
                        'pickup_min_charge' => $default?->pickup_min_charge,
                        'construction_min_charge' => $default?->construction_min_charge,
                        'drop_off_min_charge' => $default?->drop_off_min_charge,
                        'effective_from' => $quote->quote_date,
                    ]);
                } elseif ($type === 'chartered' && CharteredLorry::query()->whereKey($id)->exists()) {
                    CharteredLorryRate::query()->where('chartered_lorry_id', $id)->where('location_id', $locationId)->where('customer_code', $code)->delete();
                    CharteredLorryRate::query()->create($common + [
                        'chartered_lorry_id' => (int) $id,
                        'price' => $price,
                        'effective_from' => $quote->quote_date,
                    ]);
                }
            }
        }
    }
}

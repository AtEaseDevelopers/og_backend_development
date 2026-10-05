<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\PortalEnquiry;
use App\Enums\DocumentType;
use App\Enums\PortalEnquiryStatus;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Admin-assisted order entry ("Create order for customer"): records the customer's instructions
 * as an enquiry (one order number) and immediately creates one order record per
 * consignor–consignee pair, ready for pricing.
 */
class CreateAdminOrder
{
    public const RECEIVED_THROUGH = [
        'phone_call' => 'Phone call',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'walk_in' => 'Walk-in / counter',
        'salesperson' => 'Salesperson',
    ];

    public function __construct(
        private DocumentNumberingService $numbering,
        private CreateOrderFromEnquiry $createOrders,
        private AssignEnquirySalesperson $assign,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated form data (see CreateOrder page)
     * @return array{enquiry: PortalEnquiry, orders: Collection<int, \App\Domains\Quotation\Models\Quotation>}
     */
    public function execute(array $data, User $actor, Branch $branch, ?Company $company): array
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $pairs = collect($data['pairs'] ?? [])->values();

        if ($pairs->isEmpty()) {
            throw new InvalidArgumentException('Add at least one consignor & consignee.');
        }

        $salesperson = filled($data['salesperson_id'] ?? null) ? User::query()->find($data['salesperson_id']) : null;

        return DB::transaction(function () use ($data, $actor, $branch, $company, $customer, $pairs, $salesperson): array {
            $receivedThrough = $data['received_through'] ?? 'phone_call';
            $first = $pairs->first();

            $enquiry = PortalEnquiry::query()->create([
                'company_id' => $company?->id ?? $customer->company_id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'user_id' => null,
                'reference_no' => 'ENQ-'.Str::upper(Str::random(8)),
                'order_number' => $this->numbering->next($branch, DocumentType::Order),
                'source' => $receivedThrough === 'walk_in' ? PortalEnquiry::SOURCE_WALK_IN : PortalEnquiry::SOURCE_ADMIN,
                'received_through' => $receivedThrough,
                'order_type' => $data['order_type'] ?? $customer->default_order_type,
                'service_type' => $data['service_type'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'customer_do_number' => $first['customer_do_number'] ?? null,
                'pickup_address' => $first['pickup_location'] ?? null,
                'preferred_delivery_date' => $first['expected_delivery_date'] ?? null,
                'special_requirements' => $pairs->pluck('instructions')->filter()->implode("\n") ?: null,
                'status' => PortalEnquiryStatus::InReview->value,
                'attended_by' => $actor->id,
                'attended_at' => now(),
                'attachments' => $data['attachments'] ?? [],
                'payload' => [
                    'received_through' => $receivedThrough,
                    'entered_by' => $actor->name,
                    'destinations' => $pairs->map(fn (array $pair) => [
                        'consignee_name' => $pair['consignee_name'] ?? null,
                        'address' => $pair['consignee_address'] ?? $pair['drop_off_location'] ?? null,
                        'city' => filled($pair['to_location_id'] ?? null) ? Location::query()->whereKey($pair['to_location_id'])->value('name') : null,
                        'drop_off_type' => $pair['drop_off_type'] ?? null,
                        'service_type' => $data['service_type'] ?? null,
                        'customer_do_number' => $pair['customer_do_number'] ?? null,
                        'expected_delivery_date' => $pair['expected_delivery_date'] ?? null,
                    ])->values()->all(),
                    'items' => $pairs->flatMap(fn (array $pair, int $index) => collect($pair['items'] ?? [])->map(fn (array $item) => [
                        'item_name' => $item['item_name'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'catalog_key' => $item['catalog_key'] ?? null,
                        'line_type' => $item['line_type'] ?? null,
                        'destination_index' => $index,
                    ]))->values()->all(),
                ],
            ]);

            if ($salesperson) {
                $this->assign->execute($enquiry, $salesperson, $actor, lock: false, source: $enquiry->source);
                $enquiry->refresh();
            }

            $orders = $this->createOrders->execute($enquiry, $actor, $pairs->map(function (array $pair) use ($data): array {
                return [
                    'consignor_name' => $pair['consignor_name'] ?? null,
                    'consignee_name' => $pair['consignee_name'] ?? null,
                    'consignee_brn' => $pair['consignee_brn'] ?? null,
                    'consignee_address' => $pair['consignee_address'] ?? null,
                    'drop_off_location' => $pair['drop_off_location'] ?? null,
                    'to_location_id' => $pair['to_location_id'] ?? null,
                    'from_location_id' => $pair['from_location_id'] ?? null,
                    'consignor_brn' => $pair['consignor_brn'] ?? null,
                    'customer_address' => $pair['customer_address'] ?? null,
                    'pickup_location' => $pair['pickup_location'] ?? null,
                    'drop_off_type' => $pair['drop_off_type'] ?? null,
                    'service_type' => $data['service_type'] ?? null,
                    'customer_do_number' => $pair['customer_do_number'] ?? null,
                    'expected_delivery_date' => $pair['expected_delivery_date'] ?? null,
                    'instructions' => $pair['instructions'] ?? null,
                    'items' => collect($pair['items'] ?? [])->map(fn (array $item) => [
                        'item_name' => $item['item_name'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'catalog_key' => $item['catalog_key'] ?? null,
                        'line_type' => $item['line_type'] ?? null,
                    ])->filter(fn (array $item) => filled($item['item_name']))->values()->all(),
                ];
            })->all());

            activity()
                ->performedOn($enquiry)
                ->causedBy($actor)
                ->withProperties(['orders' => $orders->pluck('number')->all(), 'received_through' => $receivedThrough])
                ->log('Order entered by admin on behalf of customer');

            return ['enquiry' => $enquiry->fresh(['quotations', 'salesperson']), 'orders' => $orders];
        });
    }
}

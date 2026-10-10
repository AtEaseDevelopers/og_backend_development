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

        // optional: blank (or unknown) is stored as null and the order shows as a plain admin entry
        $receivedThrough = filled($data['received_through'] ?? null) && array_key_exists((string) $data['received_through'], self::RECEIVED_THROUGH)
            ? (string) $data['received_through']
            : null;
        // one billing address for the whole order (every record's customer_address); blank = the customer's saved address
        $billingAddress = filled($data['customer_address'] ?? null) ? trim((string) $data['customer_address']) : null;
        // the customer's person in charge and contact number (blank stays blank); older callers: the customer's default
        $customerPic = array_key_exists('customer_pic_name', $data) || array_key_exists('customer_pic_phone', $data)
            ? [
                'attention' => filled($data['customer_pic_name'] ?? null) ? trim((string) $data['customer_pic_name']) : null,
                'customer_pic_phone' => filled($data['customer_pic_phone'] ?? null) ? trim((string) $data['customer_pic_phone']) : null,
            ]
            : [];

        return DB::transaction(function () use ($data, $actor, $branch, $company, $customer, $pairs, $salesperson, $receivedThrough, $billingAddress, $customerPic): array {
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
                // pickup / store is chosen per consignor block; the order form keeps the first block's
                'service_type' => $data['service_type'] ?? ($first['service_type'] ?? null),
                // the admin page no longer asks: the method is captured when the payment is recorded
                'payment_method' => $data['payment_method'] ?? null,
                'customer_do_number' => $first['customer_do_number'] ?? null,
                'pickup_address' => $first['pickup_location'] ?? null,
                // the expected delivery date is a free-text remark: only a real date fills the enquiry's date
                'preferred_delivery_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($first['expected_delivery_date'] ?? '')) ? $first['expected_delivery_date'] : null,
                'special_requirements' => $pairs->pluck('instructions')->filter()->implode("\n") ?: null,
                'status' => PortalEnquiryStatus::InReview->value,
                'attended_by' => $actor->id,
                'attended_at' => now(),
                // files for the whole order (older callers); photos uploaded per consignor & consignee block stay
                // with that block (payload destination) and its record
                'attachments' => $data['attachments'] ?? [],
                'payload' => [
                    'received_through' => $receivedThrough,
                    'entered_by' => $actor->name,
                    'destinations' => $pairs->map(fn (array $pair) => [
                        'consignee_name' => $pair['consignee_name'] ?? null,
                        'address' => $pair['consignee_address'] ?? $pair['drop_off_location'] ?? null,
                        'city' => filled($pair['to_location_id'] ?? null) ? Location::query()->whereKey($pair['to_location_id'])->value('name') : null,
                        'drop_off_type' => $pair['drop_off_type'] ?? null,
                        'service_type' => $pair['service_type'] ?? ($data['service_type'] ?? null),
                        'customer_do_number' => $pair['customer_do_number'] ?? null,
                        'expected_delivery_date' => $pair['expected_delivery_date'] ?? null,
                        // consignor (pickup or store) and the person in charge on each side
                        'consignor_name' => $pair['consignor_name'] ?? null,
                        'store_branch_id' => $pair['store_branch_id'] ?? null,
                        'store_id' => $pair['store_id'] ?? null,
                        'consignor_pic_name' => $pair['consignor_pic_name'] ?? null,
                        'consignor_pic_phone' => $pair['consignor_pic_phone'] ?? null,
                        'consignee_pic_name' => $pair['consignee_pic_name'] ?? null,
                        'consignee_pic_phone' => $pair['consignee_pic_phone'] ?? null,
                        'customer_address' => $billingAddress,
                        // photos / DO attachments uploaded for this block ({path, name, mime, size, …})
                        'attachments' => array_values(array_filter($pair['attachments'] ?? [], 'is_array')),
                    ] + $customerPic)->values()->all(),
                    'items' => $pairs->flatMap(fn (array $pair, int $index) => collect($pair['items'] ?? [])->map(fn (array $item) => [
                        'item_name' => $item['item_name'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'catalog_key' => $item['catalog_key'] ?? null,
                        'line_type' => $item['line_type'] ?? null,
                        // photos uploaded for this product ({path, name, mime, …})
                        'attachments' => array_values(array_filter($item['attachments'] ?? [], 'is_array')),
                        'destination_index' => $index,
                    ]))->values()->all(),
                ],
            ]);

            if ($salesperson) {
                $this->assign->execute($enquiry, $salesperson, $actor, lock: false, source: $enquiry->source);
                $enquiry->refresh();
            }

            $orders = $this->createOrders->execute($enquiry, $actor, $pairs->map(function (array $pair) use ($data, $billingAddress, $customerPic): array {
                return $customerPic + [
                    // each block's own DO number / instructions (blank stays blank, not block 1's)
                    'explicit' => true,
                    'consignor_name' => $pair['consignor_name'] ?? null,
                    'consignee_name' => $pair['consignee_name'] ?? null,
                    'consignee_address' => $pair['consignee_address'] ?? null,
                    'drop_off_location' => $pair['drop_off_location'] ?? null,
                    'to_location_id' => $pair['to_location_id'] ?? null,
                    'from_location_id' => $pair['from_location_id'] ?? null,
                    'customer_address' => $billingAddress ?? ($pair['customer_address'] ?? null),
                    'pickup_location' => $pair['pickup_location'] ?? null,
                    'drop_off_type' => $pair['drop_off_type'] ?? null,
                    // Pickup or Store (with the branch) per block
                    'service_type' => $pair['service_type'] ?? ($data['service_type'] ?? null),
                    'store_branch_id' => $pair['store_branch_id'] ?? null,
                    'store_id' => $pair['store_id'] ?? null,
                    'consignor_pic_name' => $pair['consignor_pic_name'] ?? null,
                    'consignor_pic_phone' => $pair['consignor_pic_phone'] ?? null,
                    'consignee_pic_name' => $pair['consignee_pic_name'] ?? null,
                    'consignee_pic_phone' => $pair['consignee_pic_phone'] ?? null,
                    'customer_do_number' => $pair['customer_do_number'] ?? null,
                    'expected_delivery_date' => $pair['expected_delivery_date'] ?? null,
                    'instructions' => $pair['instructions'] ?? null,
                    // the block's photos belong to its own record
                    'attachments' => array_values(array_filter($pair['attachments'] ?? [], 'is_array')),
                    'items' => collect($pair['items'] ?? [])->map(fn (array $item) => [
                        'item_name' => $item['item_name'] ?? null,
                        'uom' => $item['uom'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'catalog_key' => $item['catalog_key'] ?? null,
                        'line_type' => $item['line_type'] ?? null,
                        // price keyed in on the page for a product without a rate (used only when there is none)
                        'unit_price' => $item['unit_price'] ?? null,
                        'attachments' => array_values(array_filter($item['attachments'] ?? [], 'is_array')),
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

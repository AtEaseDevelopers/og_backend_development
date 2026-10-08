<?php

namespace App\Domains\Quotation\Actions;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;
use App\Models\User;
use App\Support\QuotationMatrix;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Applies the "Admin pricing" form of the order page to an order record:
 * item rows priced per destination column, optional pickup / drop-off / other charges,
 * pricing remarks and the section-D override rule (a rate that differs from the price list
 * needs HQ Admin / Branch Manager permission and a reason).
 */
class SaveOrderPricing
{
    public function __construct(private QuotationMatrix $matrix) {}

    /**
     * @param  array{reference?: string, columns: list<string>, rows: list<array<string, mixed>>, pickup_charge?: mixed, drop_off_charge?: mixed, other_charges?: mixed, remarks?: ?string, override_reason?: ?string}  $pricing
     */
    public function execute(Quotation $order, User $actor, array $pricing): Quotation
    {
        if (! $order->status->isEditable()) {
            throw new InvalidArgumentException('Pricing can only be changed while the order is a draft or in negotiation. Revise it to create a new version.');
        }

        if (! $order->isLatestVersion()) {
            throw new InvalidArgumentException('Only the latest version of an order can be priced.');
        }

        $columns = collect($pricing['columns'] ?? [])->filter()->values()->all();

        if ($columns === []) {
            throw new InvalidArgumentException('The order has no destination column to price.');
        }

        $itemRows = collect($pricing['rows'] ?? [])
            ->filter(fn (array $row) => filled($row['item_name'] ?? null))
            ->map(fn (array $row) => [
                'line_type' => $row['line_type'] ?? 'item',
                'item_name' => trim((string) $row['item_name']),
                'catalog_key' => $row['catalog_key'] ?? null,
                'quantity' => max(1, (int) round((float) ($row['quantity'] ?? 1))),
                'prices' => collect($columns)->mapWithKeys(fn (string $column) => [
                    $column => filled($row['prices'][$column] ?? null) ? round((float) $row['prices'][$column], 2) : null,
                ])->all(),
            ])
            ->values()
            ->all();

        if ($itemRows === []) {
            throw new InvalidArgumentException('Add at least one item to price.');
        }

        $overrides = $this->matrix->detectOverrides($order->customer_id ? (int) $order->customer_id : null, $columns, $itemRows);
        $overrideReason = trim((string) ($pricing['override_reason'] ?? ''));

        if ($overrides !== []) {
            $summary = collect($overrides)
                ->map(fn (array $o) => sprintf('%s → %s: standard RM %s, entered RM %s', $o['item'], $o['destination'], number_format($o['standard'], 2), number_format($o['entered'], 2)))
                ->implode('; ');

            if (! ($actor->is_hq || $actor->hasAnyRole(['hq_admin', 'branch_manager']))) {
                throw new InvalidArgumentException('These rates differ from the standard price list and need HQ Admin or Branch Manager permission: '.$summary);
            }

            if ($overrideReason === '') {
                throw new InvalidArgumentException('A pricing override reason is required because these rates differ from the standard price list: '.$summary);
            }
        }

        $chargeRows = [];
        $first = $columns[0];

        foreach ([
            'Pickup charge' => $pricing['pickup_charge'] ?? null,
            'Drop-off charge' => $pricing['drop_off_charge'] ?? null,
            'Other charges' => $pricing['other_charges'] ?? null,
        ] as $label => $amount) {
            if (filled($amount) && (float) $amount > 0) {
                $chargeRows[] = [
                    'line_type' => 'item',
                    'item_name' => $label,
                    'catalog_key' => null,
                    'quantity' => 1,
                    'prices' => [$first => round((float) $amount, 2)],
                ];
            }
        }

        return DB::transaction(function () use ($order, $actor, $pricing, $columns, $itemRows, $chargeRows, $overrides, $overrideReason): Quotation {
            $order->update([
                'pricing_source' => match ($pricing['reference'] ?? 'price_list') {
                    'customer_special' => 'special',
                    'manual' => 'manual',
                    default => 'default',
                },
                'pricing_override_reason' => $overrides !== [] ? $overrideReason : null,
                'price_overrides' => $overrides !== []
                    ? array_map(fn (array $o) => $o + ['by' => $actor->name, 'at' => now()->toDateTimeString()], $overrides)
                    : null,
                'notes' => filled($pricing['remarks'] ?? null) ? trim((string) $pricing['remarks']) : $order->notes,
            ]);

            // every product row becomes a line, also one without a price yet (no unit price, left out of the total)
            $this->matrix->sync($order, $columns, array_merge($itemRows, $chargeRows));

            // its lines are now the record's products: a product removed here is not offered again from the order form
            UpdateOrderRecords::settleUnpricedPayloadItems($order);

            $order->refresh();

            QuotationStatusLog::query()->create([
                'quotation_id' => $order->id,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'user_id' => $actor->id,
                'remarks' => 'Pricing saved · RM '.number_format((float) $order->total_amount, 2).($overrides !== [] ? ' · override: '.$overrideReason : ''),
            ]);

            return $order;
        });
    }
}

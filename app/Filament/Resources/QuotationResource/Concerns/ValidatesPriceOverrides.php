<?php

namespace App\Filament\Resources\QuotationResource\Concerns;

use App\Support\QuotationMatrix;
use Illuminate\Validation\ValidationException;

/**
 * Section D: a price that differs from the standard list needs a reason and permission.
 * Shared by the create and edit pages; returns the audit payload to store on the order.
 *
 * @phpstan-require-extends \Filament\Resources\Pages\Page
 */
trait ValidatesPriceOverrides
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>  $data with price_overrides filled in
     */
    protected function enforcePriceOverrideRules(array $data, array $columns, array $rows): array
    {
        $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
        $overrides = app(QuotationMatrix::class)->detectOverrides($customerId, $columns, $rows);

        if ($overrides === []) {
            $data['price_overrides'] = null;

            return $data;
        }

        $user = auth()->user();
        $summary = collect($overrides)
            ->map(fn (array $o) => sprintf('%s → %s: standard RM %s, entered RM %s', $o['item'], $o['destination'], number_format($o['standard'], 2), number_format($o['entered'], 2)))
            ->implode('; ');

        if (! ($user?->is_hq || $user?->hasAnyRole(['hq_admin', 'branch_manager']))) {
            throw ValidationException::withMessages([
                'data.matrix_rows' => 'These rates differ from the standard price list and need HQ Admin or Branch Manager permission: '.$summary,
            ]);
        }

        if (blank($data['pricing_override_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'data.pricing_override_reason' => 'A reason is required because these rates differ from the standard price list: '.$summary,
            ]);
        }

        $data['price_overrides'] = array_map(fn (array $o) => $o + [
            'by' => $user->name,
            'at' => now()->toDateTimeString(),
        ], $overrides);

        return $data;
    }
}

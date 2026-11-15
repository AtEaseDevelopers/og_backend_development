<?php

namespace App\Support;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Lead;
use App\Domains\MasterData\Models\Location;
use App\Domains\Quotation\Models\Quote;
use App\Enums\DropOffType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The change history of a quotation (user, 16 Nov 2026: "which fields were modified, from which value to which
 * value"). Each save of an existing quotation is compared with what was saved before, field by field as the form
 * names them, and the differences are kept in quote_changes with who saved and when; the Edit quotation page shows
 * them in its History window.
 *
 * A quotation is read as a flat list "field => value as shown" (snapshot); products are named by their row
 * ("Product 2 · Drop-off location"), so a row added or removed shows as its fields appearing or going.
 */
class QuoteChangeLog
{
    /** @return array<string, string> field as the form names it => its value as text */
    public static function snapshot(Quote $quote): array
    {
        $form = (array) $quote->form;
        $users = User::query()->whereIn('id', array_filter([$form['salesperson_id'] ?? null, $form['contact_person_id'] ?? null]))->pluck('name', 'id');

        $fields = [
            'Quotation type' => Quote::TYPES[$quote->type] ?? (string) $quote->type,
            'Quotation date' => $quote->quote_date ? Carbon::parse($quote->quote_date)->format('d/m/Y') : '',
            'Customer / lead' => match (true) {
                (bool) $quote->customer_id => trim((string) Customer::query()->whereKey($quote->customer_id)->value('company_name')),
                (bool) $quote->lead_id => trim((string) Lead::query()->whereKey($quote->lead_id)->value('name')).' (lead)',
                default => '',
            },
            'Billing address' => trim((string) ($form['customer_address'] ?? '')),
            'PIC name' => trim((string) ($form['customer_pic_name'] ?? '')),
            'Contact number' => trim((string) ($form['customer_pic_phone'] ?? '')),
            'Contact person' => (string) ($users[$form['contact_person_id'] ?? null] ?? ''),
            'Salesperson' => (string) ($users[$form['salesperson_id'] ?? null] ?? ''),
            'Order type' => filled($form['order_type'] ?? null) ? Str::headline((string) $form['order_type']) : '',
        ];

        $pairs = collect((array) $quote->pairs)->filter(fn ($pair) => is_array($pair))->values();
        $locationIds = $pairs->flatMap(fn (array $pair) => [$pair['from_location_id'] ?? null, $pair['to_location_id'] ?? null])->filter()->unique()->all();
        $locations = $locationIds === [] ? collect() : Location::withTrashed()->whereIn('id', $locationIds)->pluck('name', 'id');

        $row = 0;
        foreach ($pairs as $pair) {
            $from = (string) ($locations[$pair['from_location_id'] ?? null] ?? '');
            $to = (string) ($locations[$pair['to_location_id'] ?? null] ?? '');
            $type = (string) ($pair['drop_off_type'] ?? '');
            $typeLabel = $type === '' ? '' : (DropOffType::tryFrom($type)?->getLabel() ?? Str::headline($type));

            foreach ((array) ($pair['items'] ?? [[]]) as $item) {
                $item = (array) $item;
                $label = 'Product '.(++$row);
                $special = (array) ($item['special'] ?? []);

                $fields[$label.' · Product'] = trim((string) ($item['item_name'] ?? ''));
                $fields[$label.' · Pickup location'] = filled($special['pickup_location'] ?? null) ? (string) $special['pickup_location'] : $from;
                $fields[$label.' · Drop-off location'] = $to;
                $fields[$label.' · Drop-off type'] = $typeLabel;
                $fields[$label.' · Price'] = static::priceText($item, $special);
                $fields[$label.' · Pickup min charge'] = static::money($special['pickup_min_charge'] ?? null);
                $fields[$label.' · Drop-off min charge'] = static::money($special['drop_off_min_charge'] ?? null);
                $fields[$label.' · Construction min charge'] = static::money($special['construction_min_charge'] ?? null);
                $fields[$label.' · Effective from'] = filled($special['effective_from'] ?? null) ? Carbon::parse($special['effective_from'])->format('d/m/Y') : '';
                $fields[$label.' · Expires on'] = filled($special['expires_on'] ?? null) ? Carbon::parse($special['expires_on'])->format('d/m/Y') : '';
            }
        }

        $fields['Total'] = static::money($quote->total_amount);

        return array_map(fn ($value) => (string) $value, $fields);
    }

    /** The price of a product row as text: its quantity ranges ("1: 14.00 · 2–9: 11.00 · 20+: 7.00"), else its one price. */
    private static function priceText(array $item, array $special): string
    {
        $ranges = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
            $price = $special['range_'.$letter.'_price'] ?? null;
            if (blank($price)) {
                continue;
            }
            $fromQty = $special['range_'.$letter.'_from'] ?? null;
            $toQty = $special['range_'.$letter.'_to'] ?? null;
            $range = match (true) {
                blank($fromQty) && blank($toQty) => '',
                blank($toQty) => $fromQty.'+',
                (string) $fromQty === (string) $toQty => (string) $fromQty,
                default => $fromQty.'–'.$toQty,
            };
            $ranges[] = ($range !== '' ? $range.': ' : '').static::money($price);
        }

        if ($ranges !== []) {
            return implode(' · ', $ranges);
        }

        return static::money($item['manual_price'] ?? $item['unit_price'] ?? null);
    }

    private static function money(mixed $value): string
    {
        return blank($value) ? '' : number_format((float) $value, 2);
    }

    /**
     * What changed between two snapshots, in the order of the form.
     *
     * @return list<array{field: string, from: string, to: string}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $from = (string) ($before[$field] ?? '');
            $to = (string) ($after[$field] ?? '');
            if ($from !== $to) {
                $changes[] = ['field' => (string) $field, 'from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /** Keeps the changes of one save (nothing when nothing changed). */
    public static function record(Quote $quote, array $before, ?int $userId = null): void
    {
        if (! Schema::hasTable('quote_changes')) {
            return;
        }

        $changes = static::diff($before, static::snapshot($quote));
        if ($changes === []) {
            return;
        }

        DB::table('quote_changes')->insert([
            'quote_id' => $quote->getKey(),
            'user_id' => $userId,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The history of a quotation, the latest first.
     *
     * @return Collection<int, array{at: string, by: string, changes: list<array{field: string, from: string, to: string}>}>
     */
    public static function history(int $quoteId): Collection
    {
        if (! Schema::hasTable('quote_changes')) {
            return collect();
        }

        $rows = DB::table('quote_changes')->where('quote_id', $quoteId)->orderByDesc('id')->get();
        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'at' => Carbon::parse($row->created_at)->format('d/m/Y H:i'),
            'by' => (string) ($users[$row->user_id] ?? 'System'),
            'changes' => (array) json_decode((string) $row->changes, true),
        ]);
    }
}

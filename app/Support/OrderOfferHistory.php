<?php

namespace App\Support;

use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationStatusLog;

/**
 * Every price offered to the customer for an order record — sent, accepted or rejected —
 * newest first, as structured rows for the "Prices offered to the customer" card.
 *
 * New entries carry structured `meta`; older entries are parsed from their remark text.
 */
class OrderOfferHistory
{
    /**
     * @return list<array{event: string, label: string, color: string, version: ?int, channel: ?string, total: ?float, destination: ?string, lines: list<array{item: string, qty: string, unit: ?float, amount: ?float}>, reason: ?string, at: string, by: string}>
     */
    public function for(Quotation $order): array
    {
        $order->loadMissing('statusLogs.user');

        return $order->statusLogs
            ->sortByDesc(fn (QuotationStatusLog $log) => [$log->created_at?->getTimestamp() ?? 0, $log->id])
            ->map(fn (QuotationStatusLog $log) => $this->fromLog($log))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function fromLog(QuotationStatusLog $log): ?array
    {
        $meta = is_array($log->meta) ? $log->meta : null;
        $remarks = (string) $log->remarks;

        if ($meta && isset($meta['offer_event'])) {
            $event = (string) $meta['offer_event'];
            $lines = collect($meta['lines'] ?? [])->map(fn (array $l) => [
                'item' => (string) ($l['item'] ?? '—'),
                'qty' => (string) ($l['qty'] ?? '1'),
                'unit' => isset($l['unit']) ? (float) $l['unit'] : null,
                'amount' => isset($l['amount']) ? (float) $l['amount'] : null,
                'destination' => $l['destination'] ?? null,
            ])->all();

            return $this->row($log, $event, isset($meta['version']) ? (int) $meta['version'] : null, $meta['channel'] ?? null, isset($meta['total']) ? (float) $meta['total'] : null, $lines, $meta['reason'] ?? null);
        }

        // Legacy text: "<prefix> · price offered RM <total> · <item> × <qty> @ RM <unit> = RM <amount> (<dest>); ..."
        if (str_contains($remarks, 'price offered RM')) {
            [$prefix, $rest] = array_pad(explode(' · price offered RM ', $remarks, 2), 2, '');
            [$total, $lineText] = array_pad(explode(' · ', $rest, 2), 2, '');
            $event = str_starts_with(strtolower($prefix), 'customer accepted') ? 'accepted' : 'sent';
            preg_match('/v(\d+)/', $prefix, $v);
            preg_match('/via ([^(]+?)(?:\s*\(|$)/', $prefix, $ch);

            $lines = collect(explode('; ', $lineText))
                ->map(function (string $text) {
                    if (! preg_match('/^(.*) × ([\d.,]+)(?:\s+[^@]*?)?\s*@ RM ([\d.,]+) = RM ([\d.,]+)(?: \((.*)\))?$/u', trim($text), $m)) {
                        return null;
                    }

                    return [
                        'item' => trim($m[1]),
                        'qty' => $m[2],
                        'unit' => (float) str_replace(',', '', $m[3]),
                        'amount' => (float) str_replace(',', '', $m[4]),
                        'destination' => $m[5] ?? null,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            return $this->row($log, $event, isset($v[1]) ? (int) $v[1] : null, isset($ch[1]) ? trim($ch[1]) : null, (float) str_replace(',', '', $total), $lines, null);
        }

        // Legacy text: "Customer rejected quotation v<n> (RM <x>) · <category> · <reason>"
        if (preg_match('/^Customer rejected quotation v(\d+) \(RM ([\d.,]+)\) · ([^·]+) · (.*)$/u', $remarks, $m)) {
            return $this->row($log, 'rejected', (int) $m[1], null, (float) str_replace(',', '', $m[2]), [], trim($m[3]).' — '.trim($m[4]));
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function row(QuotationStatusLog $log, string $event, ?int $version, ?string $channel, ?float $total, array $lines, ?string $reason): array
    {
        $destinations = collect($lines)->pluck('destination')->filter()->unique()->values();

        return [
            'event' => $event,
            'label' => match ($event) {
                'accepted' => 'Accepted',
                'rejected' => 'Rejected',
                default => 'Sent',
            },
            'color' => match ($event) {
                'accepted' => 'done',
                'rejected' => 'issue',
                default => 'progress',
            },
            'version' => $version,
            'channel' => $channel ? ucfirst($channel) : null,
            'total' => $total,
            'destination' => $destinations->count() === 1 ? $destinations->first() : null,
            'lines' => $lines,
            'reason' => $reason,
            'at' => $log->created_at?->format('d M Y · H:i') ?? '',
            'by' => $log->user?->name ?? 'System',
        ];
    }
}

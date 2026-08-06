<?php

namespace App\Domains\Integration\Services;

use Illuminate\Support\Str;

/**
 * Simulated AutoCount adapter. Swap mode=live later with real API credentials.
 */
class AutoCountClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, external_ref:?string, message:string, raw:array}
     */
    public function push(string $documentType, array $payload): array
    {
        if (! config('og.autocount.enabled', true)) {
            return [
                'ok' => false,
                'external_ref' => null,
                'message' => 'AutoCount integration disabled',
                'raw' => [],
            ];
        }

        $failRate = (float) config('og.autocount.simulate_failure_rate', 0);
        if ($failRate > 0 && (mt_rand(1, 100) / 100) <= $failRate) {
            return [
                'ok' => false,
                'external_ref' => null,
                'message' => 'Simulated AutoCount timeout',
                'raw' => ['error' => 'timeout'],
            ];
        }

        $ref = 'AC-'.strtoupper($documentType).'-'.Str::upper(Str::random(8));

        return [
            'ok' => true,
            'external_ref' => $ref,
            'message' => 'Synced ('.config('og.autocount.mode', 'simulate').')',
            'raw' => [
                'mode' => config('og.autocount.mode', 'simulate'),
                'document_type' => $documentType,
                'payload' => $payload,
                'accepted_at' => now()->toIso8601String(),
            ],
        ];
    }
}

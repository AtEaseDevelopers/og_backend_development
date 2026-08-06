<?php

namespace App\Domains\Integration\Services;

use Illuminate\Support\Str;

/**
 * Simulated LHDN MyInvois adapter.
 */
class MyInvoisClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, uuid:?string, pdf_path:?string, message:string, raw:array}
     */
    public function submit(array $payload): array
    {
        if (! config('og.myinvois.enabled', true)) {
            return [
                'ok' => false,
                'uuid' => null,
                'pdf_path' => null,
                'message' => 'MyInvois integration disabled',
                'raw' => [],
            ];
        }

        $uuid = (string) Str::uuid();
        $pdfPath = 'einvoices/simulated/'.$uuid.'.pdf';

        return [
            'ok' => true,
            'uuid' => $uuid,
            'pdf_path' => $pdfPath,
            'message' => 'Submitted ('.config('og.myinvois.mode', 'simulate').')',
            'raw' => [
                'mode' => config('og.myinvois.mode', 'simulate'),
                'status' => 'Valid',
                'payload' => $payload,
                'validated_at' => now()->toIso8601String(),
            ],
        ];
    }
}

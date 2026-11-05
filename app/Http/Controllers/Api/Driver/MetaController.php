<?php

namespace App\Http\Controllers\Api\Driver;

use App\Domains\Delivery\Models\FailedDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lists the driver app keeps offline: failure reasons, Break-Bulk reasons and COD payment methods. */
class MetaController extends Controller
{
    public const COD_PAYMENT_METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'ewallet' => 'E-wallet / DuitNow QR',
        'cheque' => 'Cheque',
    ];

    public const BREAK_BULK_REASONS = [
        'vehicle_breakdown' => 'Vehicle breakdown',
        'overload' => 'Overload / capacity issue',
        'route_restriction' => 'Route or access restriction',
        'driver_unwell' => 'Driver unwell / off duty',
        'schedule_change' => 'Schedule change',
        'other' => 'Other',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'driver' => [
                    'id' => $user->driver?->id,
                    'name' => $user->driver?->name ?? $user->name,
                    'code' => $user->driver?->code,
                    'branch' => $user->driver?->branch?->code,
                ],
                'failure_reasons' => $this->options(FailedDelivery::REASONS),
                'break_bulk_reasons' => $this->options(self::BREAK_BULK_REASONS),
                'cod_payment_methods' => $this->options(self::COD_PAYMENT_METHODS),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    private function options(array $options): array
    {
        return collect($options)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}

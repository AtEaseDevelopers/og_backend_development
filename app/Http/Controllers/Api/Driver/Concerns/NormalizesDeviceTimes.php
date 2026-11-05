<?php

namespace App\Http\Controllers\Api\Driver\Concerns;

use Illuminate\Support\Carbon;

/**
 * Records captured offline carry the time they happened on the phone (with its UTC offset). Eloquent stores the
 * wall-clock part as given, so the times are moved to the app timezone first.
 */
trait NormalizesDeviceTimes
{
    protected function deviceTimes(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (! empty($data[$key])) {
                $data[$key] = Carbon::parse($data[$key])->setTimezone(config('app.timezone'));
            }
        }

        return $data;
    }
}

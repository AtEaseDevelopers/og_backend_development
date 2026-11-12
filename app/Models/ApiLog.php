<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per API call (App\Http\Middleware\LogApiCall); og:prune-api-logs keeps the current month only. */
class ApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['request' => 'array'];
    }
}

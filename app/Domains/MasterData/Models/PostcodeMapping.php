<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostcodeMapping extends Model
{
    protected $fillable = ['postcode', 'state', 'city', 'route_id', 'transfer_code'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}

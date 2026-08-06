<?php

namespace App\Domains\Quotation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationStatusLog extends Model
{
    protected $fillable = [
        'quotation_id', 'from_status', 'to_status', 'user_id', 'remarks',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

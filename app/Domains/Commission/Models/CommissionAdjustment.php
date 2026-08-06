<?php

namespace App\Domains\Commission\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAdjustment extends Model
{
    protected $fillable = [
        'commission_slip_id', 'amount', 'reason', 'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function slip(): BelongsTo
    {
        return $this->belongsTo(CommissionSlip::class, 'commission_slip_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}

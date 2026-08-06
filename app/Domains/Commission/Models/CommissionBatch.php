<?php

namespace App\Domains\Commission\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionBatch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'number', 'company_id', 'source_branch_id', 'month', 'cutoff_date', 'status',
        'confirmed_by', 'confirmed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function slips(): HasMany
    {
        return $this->hasMany(CommissionSlip::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}

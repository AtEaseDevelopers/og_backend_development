<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'phone', 'ic_number',
        'type', 'subcontractor_id', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}

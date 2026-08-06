<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lorry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'registration_no', 'type', 'capacity',
        'default_driver_id', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'default_driver_id');
    }
}

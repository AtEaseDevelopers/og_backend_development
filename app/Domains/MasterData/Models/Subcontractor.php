<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcontractor extends Model
{
    protected $fillable = [
        'name', 'company_no', 'phone', 'email', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }
}

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
        // the driver list's columns (21 Oct 2026)
        'emergency_contact', 'address', 'lorry_group_code', 'lorry_group', 'lorry_company_code', 'lorry_company',
        'lorry_number', 'lorry_type', 'tonnage',
        'attendant_name', 'attendant_ic', 'attendant_phone', 'attendant_emergency_contact', 'attendant_address',
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

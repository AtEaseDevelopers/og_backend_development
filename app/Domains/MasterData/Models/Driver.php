<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    /** The picked lorry company / group (Master Data, user 28 Oct 2026) fills the text columns the prints and imports use. */
    protected static function booted(): void
    {
        static::saving(function (Driver $driver): void {
            if ($driver->lorry_company_id && ($company = LorryCompany::query()->find($driver->lorry_company_id))) {
                $driver->lorry_company_code = $company->code;
                $driver->lorry_company = $company->name;
            }

            if ($driver->lorry_group_id && ($group = LorryGroup::query()->find($driver->lorry_group_id))) {
                $driver->lorry_group_code = $group->code;
                $driver->lorry_group = $group->name;
            }
        });
    }

    public function lorryCompany(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LorryCompany::class);
    }

    public function lorryGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LorryGroup::class);
    }

    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'phone', 'ic_number',
        'type', 'subcontractor_id', 'is_active',
        // the driver list's columns (21 Oct 2026)
        'emergency_contact', 'address', 'lorry_group_code', 'lorry_group', 'lorry_group_id', 'lorry_company_code', 'lorry_company', 'lorry_company_id',
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

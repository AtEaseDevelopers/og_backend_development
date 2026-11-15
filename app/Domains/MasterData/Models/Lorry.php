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
        'default_driver_id', 'status', 'is_active', 'lorry_company_id',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Insurance, road tax, service, … (Fleet → Vehicle Maintenance). */
    public function maintenanceRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The company the lorry belongs to (Master Data → Lorry Companies; client, 16 Nov 2026). */
    public function lorryCompany(): BelongsTo
    {
        return $this->belongsTo(LorryCompany::class);
    }

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'default_driver_id');
    }
}

<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenanceRecord extends Model
{
    protected $fillable = [
        'lorry_id', 'type', 'service_date', 'expiry_date', 'mileage',
        'next_service_mileage', 'next_service_date', 'cost', 'notes',
        'attachment_path', 'alerted_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'expiry_date' => 'date',
            'next_service_date' => 'date',
            'alerted_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function isDueSoon(int $withinDays = 30): bool
    {
        $until = now()->addDays($withinDays)->startOfDay();

        return ($this->expiry_date && $this->expiry_date->lte($until))
            || ($this->next_service_date && $this->next_service_date->lte($until));
    }

    public function lorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class);
    }
}

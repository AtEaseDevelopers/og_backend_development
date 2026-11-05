<?php

namespace App\Domains\Dispatch\Models;

use App\Domains\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Section O: the driver's barcode check of the consignments on a job sheet before starting delivery. */
class DriverScanValidation extends Model
{
    protected $fillable = [
        'job_sheet_id', 'driver_id', 'expected', 'scanned', 'missing', 'extra', 'unauthorized',
        'is_complete', 'continued_incomplete', 'remarks', 'latitude', 'longitude', 'client_uuid', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'expected' => 'array',
            'scanned' => 'array',
            'missing' => 'array',
            'extra' => 'array',
            'unauthorized' => 'array',
            'is_complete' => 'boolean',
            'continued_incomplete' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'validated_at' => 'datetime',
        ];
    }

    public function jobSheet(): BelongsTo
    {
        return $this->belongsTo(JobSheet::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}

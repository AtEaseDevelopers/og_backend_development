<?php

namespace App\Domains\Commission\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Driver;
use App\Domains\MasterData\Models\Lorry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommissionSlip extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'number', 'commission_batch_id', 'company_id', 'source_branch_id', 'driver_id', 'lorry_id',
        'system_amount', 'final_amount', 'psi_amount', 'pso_amount', 'deductions',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'psi_amount' => 'decimal:2',
            'pso_amount' => 'decimal:2',
            'deductions' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CommissionBatch::class, 'commission_batch_id');
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function lorry(): BelongsTo
    {
        return $this->belongsTo(Lorry::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionLineItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(CommissionPurchaseOrder::class);
    }

    /**
     * What each driver earned on this (lorry's) PO: driver name → RM, from its visible lines.
     *
     * @return array<string, float>
     */
    public function driverBreakdown(): array
    {
        return $this->lines()
            ->where('is_hidden', false)
            ->with('driver:id,name')
            ->get()
            ->groupBy(fn ($line) => $line->driver?->name ?? 'Driver removed')
            ->map(fn ($lines) => round((float) $lines->sum('amount'), 2))
            ->sortKeys()
            ->all();
    }

    /** "Ali RM 120.00 · Ahmad RM 80.00" */
    public function driverBreakdownText(): string
    {
        return collect($this->driverBreakdown())->map(fn (float $amount, string $name) => $name.' RM '.number_format($amount, 2))->implode(' · ');
    }
}

<?php

namespace App\Domains\MasterData\Models;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Quotation\Models\Quotation;
use App\Models\User;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model implements HasName
{
    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'brn',
        'tin',
        'address',
        'phone',
        'email',
        'letterhead_path',
        'is_active',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getFilamentName(): string
    {
        return sprintf('%s — %s', $this->code, $this->name);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function consignmentNotes(): HasMany
    {
        return $this->hasMany(ConsignmentNote::class);
    }

    public function jobSheets(): HasMany
    {
        return $this->hasMany(JobSheet::class);
    }
}

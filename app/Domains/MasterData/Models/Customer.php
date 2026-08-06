<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'company_name', 'brn', 'tin', 'email', 'phone',
        'address', 'einvoice_buyer_name', 'einvoice_tin', 'einvoice_id_type',
        'einvoice_id_value', 'einvoice_address',
        'is_credit', 'credit_limit', 'credit_term_days', 'status',
        'portal_approved', 'payment_methods', 'email_notifications',
    ];

    protected function casts(): array
    {
        return [
            'is_credit' => 'boolean',
            'portal_approved' => 'boolean',
            'email_notifications' => 'boolean',
            'credit_limit' => 'decimal:2',
            'payment_methods' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function pics(): HasMany
    {
        return $this->hasMany(CustomerPic::class);
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(CustomerPricing::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }
}

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
        'company_id', 'branch_id', 'code', 'control_account', 'debtor_type', 'is_group_company',
        'company_name', 'brn', 'tin', 'sst_registration_no', 'msic_code', 'business_type',
        'email', 'phone', 'fax', 'website', 'attention', 'business_nature', 'salesperson_id',
        'address', 'area', 'currency', 'statement_type', 'aging_on',
        'einvoice_buyer_name', 'einvoice_tin', 'einvoice_id_type',
        'einvoice_id_value', 'einvoice_address',
        'is_credit', 'credit_limit', 'credit_term_days', 'credit_control', 'credit_overdue_limit',
        'credit_control_scope', 'sales_tax_exemption_no', 'sales_tax_exemption_expiry',
        'discount_percent', 'tax_type', 'price_category', 'account_group', 'notes',
        'status', 'portal_approved', 'payment_methods', 'email_notifications',
    ];

    protected function casts(): array
    {
        return [
            'is_credit' => 'boolean',
            'is_group_company' => 'boolean',
            'portal_approved' => 'boolean',
            'email_notifications' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_overdue_limit' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'sales_tax_exemption_expiry' => 'date',
            'payment_methods' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
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

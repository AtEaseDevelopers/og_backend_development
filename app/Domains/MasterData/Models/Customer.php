<?php

namespace App\Domains\MasterData\Models;

use App\Enums\OrderType;
use App\Models\Concerns\BelongsToCompany;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        'pricing_reconfirmation_required', 'consent_letter_type', 'consent_valid_from',
        'consent_valid_until', 'consent_document_path', 'default_order_type',
    ];

    /** Consent letter types (section C). */
    public const CONSENT_NONE = 'none';

    public const CONSENT_NO_RECONFIRMATION = 'no_reconfirmation';

    public const CONSENT_RECONFIRMATION_REQUIRED = 'reconfirmation_required';

    /** @return array<string, string> */
    public static function consentOptions(): array
    {
        return [
            self::CONSENT_NONE => 'No consent letter on file',
            self::CONSENT_NO_RECONFIRMATION => 'No pricing reconfirmation required (approved special price)',
            self::CONSENT_RECONFIRMATION_REQUIRED => 'Pricing reconfirmation required',
        ];
    }

    /**
     * Credit-term customer with a valid "no reconfirmation" consent: quotations at the
     * agreed special price proceed without asking the customer to confirm again.
     */
    public function consentSkipsReconfirmation(): bool
    {
        if (! $this->is_credit || $this->pricing_reconfirmation_required) {
            return false;
        }

        if ($this->consent_letter_type !== self::CONSENT_NO_RECONFIRMATION) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->consent_valid_from && $this->consent_valid_from->toDateString() > $today) {
            return false;
        }

        if ($this->consent_valid_until && $this->consent_valid_until->toDateString() < $today) {
            return false;
        }

        return true;
    }

    /**
     * Customer type (Cash, COD or Credit): the default order type when set,
     * otherwise Credit for credit-term customers and Cash for everyone else.
     */
    public function customerType(): OrderType
    {
        return OrderType::tryFrom((string) $this->default_order_type)
            ?? ($this->is_credit ? OrderType::Term : OrderType::Cash);
    }

    /** customerType() as a query: customers of the given type ('cash', 'cod' or 'term'). */
    public function scopeOfCustomerType(Builder $query, string $type): void
    {
        $query->where(function (Builder $query) use ($type): void {
            $query->where('default_order_type', $type);

            // No usable default order type: is_credit decides between Credit and Cash
            if ($type !== OrderType::Cod->value) {
                $query->orWhere(fn (Builder $fallback) => $fallback
                    ->where(fn (Builder $unset) => $unset
                        ->whereNull('default_order_type')
                        ->orWhereNotIn('default_order_type', array_column(OrderType::cases(), 'value')))
                    ->where('is_credit', $type === OrderType::Term->value));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'pricing_reconfirmation_required' => 'boolean',
            'consent_valid_from' => 'date',
            'consent_valid_until' => 'date',
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

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
        'discount_percent', 'tax_type', 'charges_sst', 'price_category', 'account_group', 'notes',
        'status', 'portal_approved', 'payment_methods', 'email_notifications',
        'pricing_reconfirmation_required', 'consent_letter_type', 'consent_valid_from',
        'consent_valid_until', 'consent_document_path', 'default_order_type',
        // the client's Customer Information workbook columns (user, 25 Oct 2026)
        'display_term', 'postcode', 'phone_2', 'mobile', 'contact_person_3', 'phone_3', 'contact_person_4', 'phone_4',
        'gst_no', 'msic_code_2', 'msic_code_3', 'own_note', 'mailing_statement_remark', 'payment_note',
    ];

    protected static function booted(): void
    {
        // every customer is also a consignor / consignee (user, 25 Oct 2026)
        static::saved(fn (Customer $customer) => ConsignorConsignee::syncFromCustomer($customer));

        // the special prices are kept by debtor code: a code changed on the customer takes them along (user, 11 Nov
        // 2026: a customer created by the price list import gets a generated code, corrected later)
        static::updating(function (Customer $customer): void {
            $old = trim((string) $customer->getOriginal('code'));
            $new = trim((string) $customer->code);

            if ($customer->isDirty('code') && $old !== '' && $new !== '' && strcasecmp($old, $new) !== 0) {
                UomRateTier::query()->where('customer_code', $old)->update(['customer_code' => strtoupper($new)]);
                CharteredLorryRate::query()->where('customer_code', $old)->update(['customer_code' => strtoupper($new)]);
            }
        });
    }

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
            'charges_sst' => 'boolean',
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

    /**
     * The customer's PIC list from its contact columns (user, 10 Nov 2026), for a customer that has none yet (the
     * migration, an imported customer): attention + phone 1, phone 2, contact person 3 + phone 3, contact person 4
     * + phone 4. The first is the default.
     */
    public function createPicsFromContactColumns(): void
    {
        if ($this->pics()->exists()) {
            return;
        }

        $rows = collect([
            [$this->attention, $this->phone],
            [null, $this->phone_2],
            [$this->contact_person_3, $this->phone_3],
            [$this->contact_person_4, $this->phone_4],
        ])->map(fn (array $row) => ['name' => trim((string) $row[0]) ?: null, 'phone' => trim((string) $row[1]) ?: null])
            ->filter(fn (array $row) => $row['name'] !== null || $row['phone'] !== null)
            ->values();

        foreach ($rows as $index => $row) {
            $this->pics()->create($row + ['is_default' => $index === 0]);
        }
    }

    /**
     * After the PIC list changes (user, 10 Nov 2026): one default (the first unless one is marked), and the customer's
     * attention / phone 1 follow it (orders, documents and the portal read those); the older contact person 3 – 4 and
     * phone 2 – 4 columns are emptied, their values being in the list.
     */
    public function syncContactFromPics(): void
    {
        $pics = $this->pics()->orderByDesc('is_default')->orderBy('id')->get();
        $default = $pics->first();

        if ($default && ! $default->is_default) {
            $default->forceFill(['is_default' => true])->saveQuietly();
        }

        $pics->skip(1)->filter->is_default->each(fn (CustomerPic $pic) => $pic->forceFill(['is_default' => false])->saveQuietly());

        $this->forceFill([
            'attention' => $default?->name,
            'phone' => $default?->phone,
            'phone_2' => null,
            'phone_3' => null,
            'phone_4' => null,
            'contact_person_3' => null,
            'contact_person_4' => null,
        ])->saveQuietly();
    }

    public function pricing(): HasMany
    {
        return $this->hasMany(CustomerPricing::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }

    public function portalLinks(): HasMany
    {
        return $this->hasMany(CustomerPortalLink::class);
    }

    public function profileChanges(): HasMany
    {
        return $this->hasMany(CustomerProfileChange::class);
    }
}

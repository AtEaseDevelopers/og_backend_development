<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentSubmission;
use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Billing\Models\RefundNote;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\Notification\Models\NotificationLog;
use App\Enums\BillingStatus;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quotation extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'number', 'company_id', 'branch_id', 'customer_id', 'consignor_name', 'salesperson_id', 'portal_enquiry_id',
        'status', 'valid_until', 'title', 'is_active', 'quoted_at', 'expected_delivery_date',
        'from_location_id', 'to_location_id',
        'consignor_brn', 'pickup_location', 'consignee_name', 'consignee_brn',
        'consignee_address', 'drop_off_location', 'customer_address',
        'attention', 'customer_fax', 'customer_phone_alt', 'issued_by_name', 'terms_of_payment',
        'pricing_source', 'subtotal', 'tax_amount',
        'total_amount', 'notes', 'rejection_reason', 'sent_at', 'confirmed_at',
        'converted_at', 'created_by',
        // order-flow (sections A–G)
        'version', 'root_quotation_id', 'revision_of_id', 'accepted_version',
        'confirmation_channel', 'confirmed_by_name', 'consent_evidence',
        'order_type', 'service_type', 'payment_method', 'customer_do_number', 'sa_location_id', 'salesperson_locked',
        'pricing_override_reason', 'price_overrides',
        'attachments', 'destination_types', 'pricing_reconfirmation_required',
        'rejection_category', 'pending_review_since', 'closed_at', 'closed_reason',
        'cod_blocked', 'cod_block_reason', 'cod_blocked_by', 'cod_blocked_at',
        'released_at', 'released_by', 'release_reason', 'release_outstanding',
        'billing_status', 'billing_error', 'billed_at', 'paid_amount',
        'locked_by', 'locked_at', 'lock_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'order_type' => OrderType::class,
            'service_type' => \App\Enums\ServiceType::class,
            'price_overrides' => 'array',
            'billing_status' => BillingStatus::class,
            'attachments' => 'array',
            'destination_types' => 'array',
            'salesperson_locked' => 'boolean',
            'pricing_reconfirmation_required' => 'boolean',
            'cod_blocked' => 'boolean',
            'paid_amount' => 'decimal:2',
            'release_outstanding' => 'decimal:2',
            'pending_review_since' => 'datetime',
            'closed_at' => 'datetime',
            'cod_blocked_at' => 'datetime',
            'released_at' => 'datetime',
            'billed_at' => 'datetime',
            'locked_at' => 'datetime',
            'lock_heartbeat_at' => 'datetime',
            'valid_until' => 'date',
            'quoted_at' => 'date',
            'expected_delivery_date' => 'date',
            'is_active' => 'boolean',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\MasterData\Models\Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\MasterData\Models\Location::class, 'to_location_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(QuotationDestination::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(QuotationStatusLog::class);
    }

    public function consignmentNotes(): HasMany
    {
        return $this->hasMany(ConsignmentNote::class);
    }

    public function portalEnquiry(): BelongsTo
    {
        return $this->belongsTo(PortalEnquiry::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Order-flow relations (sections A–G)
    |--------------------------------------------------------------------------
    */

    public function saLocation(): BelongsTo
    {
        return $this->belongsTo(SaLocation::class);
    }

    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_quotation_id');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    /** Every version sharing this quotation's root, oldest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'root_quotation_id', 'root_quotation_id')->orderBy('version');
    }

    public function newerVersion(): HasOne
    {
        return $this->hasOne(self::class, 'revision_of_id');
    }

    public function proformaInvoice(): HasOne
    {
        return $this->hasOne(ProformaInvoice::class)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function refundNotes(): HasMany
    {
        return $this->hasMany(RefundNote::class);
    }

    public function notificationLogs(): MorphMany
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }

    public function codBlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cod_blocked_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** The order number shared by every record created from the same enquiry / admin entry. */
    public function orderNumber(): string
    {
        return $this->portalEnquiry?->order_number ?: $this->number;
    }

    public function rootId(): int
    {
        return (int) ($this->root_quotation_id ?? $this->id);
    }

    /** Only the latest issued version of an order may be accepted (flowchart step 7). */
    public function isLatestVersion(): bool
    {
        return ! self::query()->where('revision_of_id', $this->id)->exists();
    }

    public function orderType(): ?OrderType
    {
        return $this->order_type instanceof OrderType ? $this->order_type : OrderType::tryFrom((string) $this->order_type);
    }

    public function billingStatus(): BillingStatus
    {
        return $this->billing_status instanceof BillingStatus
            ? $this->billing_status
            : (BillingStatus::tryFrom((string) $this->billing_status) ?? BillingStatus::NotStarted);
    }

    public function outstandingAmount(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount > 0 && (float) $this->paid_amount + 0.005 >= (float) $this->total_amount;
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /** True when the confirmed version can no longer be edited without a new version. */
    public function isLocked(): bool
    {
        return $this->status instanceof QuotationStatus
            ? ($this->status->isConfirmedOrLater() || $this->status === QuotationStatus::Superseded)
            : false;
    }

    /** Section F/G gate: may this order proceed to Invoice / Cash Bill and CSN? */
    public function isEligibleForBilling(): bool
    {
        if ($this->status !== QuotationStatus::Confirmed) {
            return false;
        }

        return match ($this->orderType()) {
            OrderType::Cash => $this->isFullyPaid() || $this->isReleased(),
            OrderType::Cod => ! $this->cod_blocked,
            OrderType::Term => $this->isReleased(),
            default => $this->isFullyPaid() || $this->isReleased(),
        };
    }

    /** Human reason why billing cannot run yet (null when eligible). */
    public function billingBlockReason(): ?string
    {
        if ($this->status !== QuotationStatus::Confirmed) {
            return 'Order is not confirmed yet.';
        }

        return match ($this->orderType()) {
            OrderType::Cash => $this->isFullyPaid() || $this->isReleased()
                ? null
                : 'Cash order requires full payment (or an authorised Admin release).',
            OrderType::Cod => $this->cod_blocked ? 'COD order is blocked by Admin.' : null,
            OrderType::Term => $this->isReleased() ? null : 'Credit / Term order requires an Admin release.',
            default => $this->isFullyPaid() || $this->isReleased() ? null : 'Order type is not set.',
        };
    }
}

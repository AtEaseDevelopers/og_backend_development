<?php

namespace App\Domains\Consignment\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\ProformaInvoice;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Domains\Delivery\Models\ReturnedCsn;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\Subsheet;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\Quotation;
use App\Domains\Quotation\Models\QuotationDestination;
use App\Domains\MasterData\Models\SaLocation;
use App\Domains\MasterData\Models\TransferCode;
use App\Enums\CsnBillingType;
use App\Enums\CsnStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ConsignmentNote extends Model
{
    use BelongsToCompany;

    use LogsActivity;

    protected $fillable = [
        'number', 'company_id', 'source_branch_id', 'quotation_id', 'quotation_destination_id',
        'customer_id', 'billing_type', 'issued_at', 'status', 'return_status', 'payment_status',
        'customer_name', 'customer_brn', 'customer_tin', 'customer_phone', 'customer_reference',
        'do_number', 'job_no', 'job_date', 'from_location_id', 'to_location_id',
        'consignor_address', 'consignor_name', 'consignor_phone',
        'consignee_name', 'consignee_pic', 'consignee_phone', 'delivery_address',
        'delivery_postcode', 'delivery_state', 'delivery_city', 'remarks',
        'profit_sharing_period', 'ps_job_no', 'ps_job_date',
        'gl_account', 'gl_account_name', 'tax_code', 'tax_code_name',
        'other_do_numbers', 'marking',
        'transport_charges', 'master_charges', 'profit_sharing_amount', 'expenses',
        'subtotal', 'discount', 'tax_amount', 'tax_rate', 'total_amount',
        'cost_center', 'is_taxable', 'advance_taken', 'issue_invoice',
        'storekeeper_id', 'qr_token', 'tracking_token', 'created_by', 'cancelled_at',
        // section H
        'salesperson_id', 'sa_location_id', 'sa_prefix', 'order_type', 'invoice_number', 'proforma_number',
        'transfer_code_id', 'customer_do_number', 'service_type', 'drop_off_type',
        'claimed_by', 'claimed_at', 'claim_channel', 'assigned_by', 'assigned_at', 'transfer_claim_pending',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'service_type' => ServiceType::class,
            'claimed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'transfer_claim_pending' => 'boolean',
            'billing_type' => CsnBillingType::class,
            'status' => CsnStatus::class,
            'payment_status' => PaymentStatus::class,
            'issued_at' => 'date',
            'job_date' => 'date',
            'ps_job_date' => 'date',
            'transport_charges' => 'decimal:2',
            'master_charges' => 'decimal:2',
            'profit_sharing_amount' => 'decimal:2',
            'expenses' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'other_do_numbers' => 'array',
            'is_taxable' => 'boolean',
            'advance_taken' => 'boolean',
            'issue_invoice' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function quotationDestination(): BelongsTo
    {
        return $this->belongsTo(QuotationDestination::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CsnLine::class);
    }

    public function deliveryOrder(): HasOne
    {
        // the CSN's own delivery: not a subsheet's leg (which may exist before the CSN has a lorry)
        return $this->hasOne(DeliveryOrder::class)->whereNull('parent_do_id')->whereNull('subsheet_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function subsheetDeliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)->where(fn ($query) => $query->whereNotNull('parent_do_id')->orWhereNotNull('subsheet_id'));
    }

    public function subsheets(): HasMany
    {
        return $this->hasMany(Subsheet::class);
    }

    public function breakBulks(): HasMany
    {
        return $this->hasMany(\App\Domains\Delivery\Models\BreakBulk::class);
    }

    public function returnedCsn(): HasOne
    {
        return $this->hasOne(ReturnedCsn::class);
    }

    public function missingCsnLogs(): HasMany
    {
        return $this->hasMany(MissingCsnLog::class);
    }

    public function openMissingLog(): HasOne
    {
        return $this->hasOne(MissingCsnLog::class)
            ->whereIn('status', ['pending_return', 'missing'])
            ->latestOfMany();
    }

    public function isOriginalReturned(): bool
    {
        return $this->return_status === 'returned' || $this->returnedCsn()->exists();
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function proformaInvoice(): HasOne
    {
        return $this->hasOne(ProformaInvoice::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isCashBillFullyPaid(): bool
    {
        return $this->billing_type === CsnBillingType::CashBill
            && $this->payment_status === PaymentStatus::Paid;
    }

    public function canAssignToLorry(): bool
    {
        if ($this->status === CsnStatus::Cancelled) {
            return false;
        }

        // Orders billed through the new flow (Invoice / Cash Bill issued before the CSN exists)
        // are already paid or released; the legacy cash-bill gate only applies to manual CSNs.
        if ($this->billing_type === CsnBillingType::CashBill && $this->invoice_number === null) {
            return $this->isCashBillFullyPaid();
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Section H relations / helpers
    |--------------------------------------------------------------------------
    */

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function saLocation(): BelongsTo
    {
        return $this->belongsTo(SaLocation::class);
    }

    public function transferCode(): BelongsTo
    {
        return $this->belongsTo(TransferCode::class);
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(\App\Domains\Dispatch\Models\JobSheetTransfer::class);
    }

    public function notificationLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Domains\Notification\Models\NotificationLog::class, 'notifiable');
    }

    public function isPendingAssignment(): bool
    {
        return $this->status instanceof CsnStatus && $this->status->isAwaitingAssignment();
    }

    public function isClaimed(): bool
    {
        return $this->claimed_by !== null;
    }

    /** Can this CSN be claimed right now (scan-to-claim, section H / J)? */
    public function isClaimable(): bool
    {
        if ($this->status === CsnStatus::Cancelled || $this->cancelled_at !== null) {
            return false;
        }

        if ($this->transfer_claim_pending) {
            return true;
        }

        return $this->isPendingAssignment() && ! $this->isClaimed();
    }
}

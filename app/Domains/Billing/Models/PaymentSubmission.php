<?php

namespace App\Domains\Billing\Models;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\Quotation;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSubmissionStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A payment attempt submitted by the customer (portal) or recorded at the counter,
 * linked to the order's Proforma. Attempts are never deleted; they are approved or
 * rejected with an audit trail (section F).
 */
class PaymentSubmission extends Model
{
    use BelongsToCompany;
    use LogsActivity;

    protected $fillable = [
        'company_id', 'branch_id', 'quotation_id', 'proforma_invoice_id', 'customer_id',
        'submitted_by', 'submitted_channel', 'amount', 'payment_date', 'method', 'bank_account',
        'reference', 'receipt_path', 'receipt_paths', 'status', 'rejection_reason',
        'level1_by', 'level1_at', 'level2_by', 'level2_at', 'payment_id', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'status' => PaymentSubmissionStatus::class,
            'method' => PaymentMethod::class,
            'level1_at' => 'datetime',
            'level2_at' => 'datetime',
            'receipt_paths' => 'array',
        ];
    }

    /**
     * Every uploaded file of this submission, first file first (receipt_path plus the extra files in receipt_paths).
     *
     * @return list<string>
     */
    public function files(): array
    {
        return collect([$this->receipt_path, ...((array) ($this->receipt_paths ?? []))])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function level1Approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level1_by');
    }

    public function level2Approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level2_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function method(): ?PaymentMethod
    {
        return $this->method instanceof PaymentMethod ? $this->method : PaymentMethod::tryFrom((string) $this->method);
    }

    public function requiresTwoApprovals(): bool
    {
        return (bool) $this->method()?->requiresTwoApprovals();
    }

    public function isOpen(): bool
    {
        return $this->status instanceof PaymentSubmissionStatus && $this->status->isOpen();
    }
}

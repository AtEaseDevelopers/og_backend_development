<?php

namespace App\Domains\Billing\Models;

use App\Domains\MasterData\Models\Customer;
use App\Domains\Quotation\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Section P: one online payment attempt through the payment gateway for an order. */
class OnlinePayment extends Model
{
    public const STATUS_INITIATED = 'initiated';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'quotation_id', 'customer_id', 'user_id', 'reference', 'amount', 'gateway', 'status',
        'gateway_txn_id', 'payment_submission_id', 'failure_reason', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentSubmission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_INITIATED => 'Awaiting payment',
            self::STATUS_PAID => 'Paid online',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }
}

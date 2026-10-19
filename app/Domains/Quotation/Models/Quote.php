<?php

namespace App\Domains\Quotation\Models;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Lead;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A quotation of Operations → Quotation (user, 20 Oct 2026), on its own table: for a customer or a lead, with the
 * order's section 1 (form) and consignor & consignee blocks (pairs, the Create order shape) and the quoted prices.
 * Pending confirmation until "Create order" makes the order from it (Opened as order).
 */
class Quote extends Model
{
    use BelongsToCompany;

    public const PENDING = 'pending';

    public const ORDERED = 'ordered';

    /** Quotation numbers: Q00001, Q00002, … */
    public const PREFIX = 'Q';

    public const STATUSES = [
        self::PENDING => 'Pending confirmation',
        self::ORDERED => 'Opened as order',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'number', 'customer_id', 'lead_id', 'quote_date', 'status', 'accepted_by', 'form', 'pairs',
        'total_amount', 'order_enquiry_id', 'ordered_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'form' => 'array',
            'pairs' => 'array',
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
        ];
    }

    /**
     * The next quotation number: the last one plus 1, Q and five digits ("Q00001" when there is none yet). Call
     * inside the transaction that saves the quotation (the last number is locked).
     */
    public static function nextNumber(): string
    {
        $last = DB::table('quotes')->lockForUpdate()
            ->selectRaw('MAX(CAST(REPLACE(number, ?, \'\') AS UNSIGNED)) as last', [self::PREFIX])
            ->value('last');

        return self::PREFIX.str_pad((string) (((int) $last) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The order made from it. */
    public function orderEnquiry(): BelongsTo
    {
        return $this->belongsTo(PortalEnquiry::class, 'order_enquiry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The customer's (or the lead's) name. */
    public function partyName(): ?string
    {
        return $this->customer?->company_name ?? $this->lead?->name;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** "Duplicate": a new Pending confirmation quotation with the same customer / lead, blocks and prices, dated today. */
    public function duplicate(?int $userId = null): self
    {
        return DB::transaction(function () use ($userId) {
            $copy = $this->replicate(['number', 'status', 'accepted_by', 'order_enquiry_id', 'ordered_at', 'created_by']);
            $copy->fill([
                'number' => self::nextNumber(),
                'status' => self::PENDING,
                'quote_date' => today()->toDateString(),
                'created_by' => $userId,
            ])->save();

            return $copy;
        });
    }
}

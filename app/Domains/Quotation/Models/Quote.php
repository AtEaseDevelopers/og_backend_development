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
 * Pending confirmation until the customer accepts it: then its prices are the customer's special prices
 * (Accepted, user 21 Oct 2026; the order is created afterwards).
 */
class Quote extends Model
{
    use BelongsToCompany;

    public const PENDING = 'pending';

    /** The customer accepted it: its prices are the customer's special prices (user, 21 Oct 2026). */
    public const ACCEPTED = 'accepted';

    /** Quotation numbers (user, 28 Oct 2026): Q-KL-261000001 (Q, the company code, the month and a running number) */
    public const PREFIX = 'Q';

    /** The client's two quotation types (user, 29 Oct 2026): a general price list, or a quotation to one customer. */
    public const TYPE_PRICE_LIST = 'price_list';

    public const TYPE_CUSTOMER = 'customer';

    public const TYPES = [
        self::TYPE_CUSTOMER => 'Customer quotation',
        self::TYPE_PRICE_LIST => 'Price list (with effective date)',
    ];

    public const STATUSES = [
        self::PENDING => 'Pending confirmation',
        self::ACCEPTED => 'Accepted',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'number', 'type', 'customer_id', 'lead_id', 'quote_date', 'status', 'accepted_by', 'form', 'pairs',
        'total_amount', 'order_enquiry_id', 'ordered_at', 'accepted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'form' => 'array',
            'pairs' => 'array',
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** The next quotation number of the opened company: Q-KL-261000001 (a running number per company and month). */
    public static function nextNumber(?int $branchId = null): string
    {
        $branchId ??= \App\Support\CurrentCompany::branchId() ?? (int) Branch::query()->orderBy('id')->value('id');

        return app(\App\Services\DocumentNumberingService::class)->next($branchId, \App\Enums\DocumentType::Quote);
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

    /** The customer's (or the lead's) name; a price list without one is addressed to the company itself. */
    public function partyName(): ?string
    {
        return $this->customer?->company_name ?? $this->lead?->name ?? ($this->isPriceList() ? 'Price list (no customer)' : null);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function isPriceList(): bool
    {
        return $this->type === self::TYPE_PRICE_LIST;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** "Duplicate": a new Pending confirmation quotation with the same customer / lead, blocks and prices, dated today. */
    public function duplicate(?int $userId = null): self
    {
        return DB::transaction(function () use ($userId) {
            $copy = $this->replicate(['number', 'status', 'accepted_by', 'accepted_at', 'order_enquiry_id', 'ordered_at', 'created_by']);
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

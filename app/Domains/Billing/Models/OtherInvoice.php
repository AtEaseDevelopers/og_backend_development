<?php

namespace App\Domains\Billing\Models;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Support\QuotationMatrix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Other Invoice (client + user, 16 Nov 2026): charges that are not from a consignment note — diesel, toll reload,
 * land / lorry / permit / parking rental, transport charges of a period. Lines of an item (Master Data → Other Items),
 * a description and an amount keyed by the user. Number: OIYYMM##### (OI261200001), one running number a month.
 */
class OtherInvoice extends Model
{
    use BelongsToCompany;

    public const PREFIX = 'OI';

    protected $fillable = [
        'number', 'company_id', 'branch_id', 'customer_id', 'invoice_date', 'charges_sst',
        'subtotal', 'tax_amount', 'total_amount', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'charges_sst' => 'boolean',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->invoice_date ??= now();
            $invoice->number = filled($invoice->number) ? $invoice->number : static::nextNumber($invoice->invoice_date);
            $invoice->created_by ??= auth()->id();
        });
    }

    /** OI + year + month of the invoice date + a five-digit running number of that month. */
    public static function nextNumber(?\DateTimeInterface $date = null): string
    {
        $prefix = self::PREFIX.\Illuminate\Support\Carbon::parse($date ?? now())->format('ym');
        $last = static::query()->withoutGlobalScopes()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** The totals from the lines: the sum, the service tax when the invoice charges it, the total. */
    public function recalculate(): void
    {
        $subtotal = round((float) $this->lines()->sum('amount'), 2);
        $tax = $this->charges_sst ? round($subtotal * QuotationMatrix::sstRate() / 100, 2) : 0.0;

        $this->forceFill(['subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => round($subtotal + $tax, 2)])->saveQuietly();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OtherInvoiceLine::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

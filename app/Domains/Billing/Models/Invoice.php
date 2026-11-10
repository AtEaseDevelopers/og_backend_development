<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Integration\Models\EinvoiceSubmission;
use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'number', 'company_id', 'source_branch_id', 'customer_id', 'consignment_note_id', 'type',
        'billing_month', 'status', 'subtotal', 'tax_amount', 'rounding_amount',
        'total_amount', 'invoice_date', 'due_date', 'autocount_sync_status',
        'quotation_id', 'proforma_invoice_id', 'payment_id', 'sent_at',
    ];

    /** The record the invoice was issued from; an order's invoice bills every record of quotations(). */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Quotation\Models\Quotation::class);
    }

    /**
     * Every order record this invoice bills (client, 11 Nov 2026: 1 Order → 2 CSNs → 1 Invoice): the records of one
     * order share their invoice. An invoice saved with a quotation_id belongs to that record automatically.
     */
    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Quotation\Models\Quotation::class, 'invoice_quotation');
    }

    protected static function booted(): void
    {
        static::saved(function (Invoice $invoice): void {
            if ($invoice->quotation_id && ($invoice->wasRecentlyCreated || $invoice->wasChanged('quotation_id'))) {
                $invoice->quotations()->syncWithoutDetaching([$invoice->quotation_id]);
            }
        });
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** The cash payment this Cash Bill was issued for (one Cash Bill per cash transaction). */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isCashBill(): bool
    {
        return $this->type === 'cash_bill';
    }

    /**
     * The document's name by payment type (user, 11 Nov 2026), one layout for all three: Invoice (Credit / Term,
     * issued once a month), COD Invoice (COD, once the driver collects the payment), Cash Bill (Cash, once the
     * counter receives the payment).
     */
    public function documentLabel(): string
    {
        return match ($this->type) {
            'cash_bill' => 'Cash Bill',
            'cod' => 'COD Invoice',
            default => 'Invoice',
        };
    }

    /** The payment type printed beside the title: CASH, COD or CREDIT / TERM. */
    public function paymentLabel(): string
    {
        return match ($this->type) {
            'cash_bill' => 'CASH',
            'cod' => 'COD',
            default => 'CREDIT / TERM',
        };
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function consignmentNote(): BelongsTo
    {
        return $this->belongsTo(ConsignmentNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function einvoiceSubmission(): HasOne
    {
        return $this->hasOne(EinvoiceSubmission::class);
    }
}

<?php

namespace App\Domains\MasterData\Models;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Statement;
use App\Domains\Commission\Models\CommissionBatch;
use App\Domains\Commission\Models\CommissionSlip;
use App\Domains\Consignment\Models\ConsignmentNote;
use App\Domains\Delivery\Models\MissingCsnLog;
use App\Domains\Dispatch\Models\DeliveryOrder;
use App\Domains\Dispatch\Models\JobSheet;
use App\Domains\Quotation\Models\CreditApprovalRequest;
use App\Domains\Quotation\Models\OcrUpload;
use App\Domains\Quotation\Models\Quotation;
use App\Models\User;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model implements HasName
{
    protected $fillable = [
        'code', 'name', 'company_name', 'company_no', 'address',
        'phone', 'email', 'letterhead_path', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getFilamentName(): string
    {
        return sprintf('%s — %s', $this->code, $this->company_name ?: $this->name);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_default')->withTimestamps();
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function lorries(): HasMany
    {
        return $this->hasMany(Lorry::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function consignmentNotes(): HasMany
    {
        return $this->hasMany(ConsignmentNote::class, 'source_branch_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'source_branch_id');
    }

    public function jobSheets(): HasMany
    {
        return $this->hasMany(JobSheet::class, 'operating_branch_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'source_branch_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'source_branch_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class, 'source_branch_id');
    }

    public function commissionBatches(): HasMany
    {
        return $this->hasMany(CommissionBatch::class, 'source_branch_id');
    }

    public function commissionSlips(): HasMany
    {
        return $this->hasMany(CommissionSlip::class, 'source_branch_id');
    }

    public function missingCsnLogs(): HasMany
    {
        return $this->hasMany(MissingCsnLog::class, 'source_branch_id');
    }

    public function creditApprovalRequests(): HasMany
    {
        return $this->hasMany(CreditApprovalRequest::class, 'branch_id');
    }

    public function ocrUploads(): HasMany
    {
        return $this->hasMany(OcrUpload::class, 'branch_id');
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class, 'source_branch_id');
    }
}

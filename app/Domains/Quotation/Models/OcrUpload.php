<?php

namespace App\Domains\Quotation\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrUpload extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'customer_id', 'uploaded_by', 'file_path', 'original_filename',
        'extracted_data', 'status', 'reviewed_by', 'reviewed_at', 'review_notes',
        'quotation_id',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}

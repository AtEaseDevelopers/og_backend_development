<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Statement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'source_branch_id', 'customer_id', 'statement_date', 'from_date', 'to_date',
        'opening_balance', 'outstanding_balance', 'file_path', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
            'opening_balance' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'payload' => 'array',
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
}

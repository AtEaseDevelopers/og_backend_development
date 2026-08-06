<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'source_branch_id', 'name', 'lorry_type', 'route', 'split_type',
        'rate_percent', 'percentages', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentages' => 'array',
            'rate_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    /** @return array<int, float> */
    public function shares(): array
    {
        $shares = $this->percentages['shares'] ?? match ($this->split_type) {
            'split_2' => [50, 50],
            'split_3' => [40, 30, 30],
            'split_4' => [25, 25, 25, 25],
            default => [100],
        };

        return array_map('floatval', $shares);
    }
}

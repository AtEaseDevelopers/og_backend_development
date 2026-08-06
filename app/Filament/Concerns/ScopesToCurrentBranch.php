<?php

namespace App\Filament\Concerns;

use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToCurrentBranch
{
    protected static function applyCurrentBranchScope(Builder $query, string $column = 'source_branch_id'): Builder
    {
        $branchId = CurrentBranch::id();

        if ($branchId) {
            $query->where($column, $branchId);
        }

        return $query;
    }
}

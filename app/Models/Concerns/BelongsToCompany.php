<?php

namespace App\Models\Concerns;

use App\Domains\MasterData\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

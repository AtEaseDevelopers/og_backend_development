<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Master Data → Lorry Companies (user, 28 Oct 2026): a simple record (code, name, contact, email, address) a driver is linked to. */
class LorryCompany extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'lorry_group_id', 'code', 'name', 'contact', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'lorry_company_id');
    }

    /** The lorry group the company is assigned under (optional; user, 3 Nov 2026). */
    public function lorryGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LorryGroup::class);
    }

    public function label(): string
    {
        return trim(($this->code ? $this->code.' — ' : '').$this->name);
    }
}

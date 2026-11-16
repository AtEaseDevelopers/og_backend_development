<?php

namespace App\Domains\Billing\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** An item of an Other Invoice (Master Data → Other Items; user, 16 Nov 2026): a description only. */
class OtherItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'category', 'code', 'name', 'is_active'];

    /** "CODE — Item" as shown where an item is picked. */
    public function label(): string
    {
        return trim((filled($this->code) ? $this->code.' — ' : '').$this->name);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

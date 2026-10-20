<?php

namespace App\Domains\MasterData\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A saved consignor / consignee (Master Data → Consignor / Consignee, user 21 Oct 2026): name, address, PIC and
 * telephone, tagged to the customers it is used for; picked on Create order to fill a consignor & consignee block.
 */
class ConsignorConsignee extends Model
{
    use BelongsToCompany;

    public const TYPES = ['consignor' => 'Consignor', 'consignee' => 'Consignee', 'both' => 'Consignor & consignee'];

    protected $fillable = ['company_id', 'name', 'type', 'address', 'pic_name', 'phone', 'location_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withTimestamps();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Usable as a consignor ('consignor') or a consignee ('consignee'). */
    public function scopeUsedAs(Builder $query, string $role): void
    {
        $query->whereIn('type', [$role, 'both']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}

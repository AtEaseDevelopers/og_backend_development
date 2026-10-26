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
 * Every customer has one too (customer_id, user 25 Oct 2026), kept in step with the customer by syncFromCustomer().
 */
class ConsignorConsignee extends Model
{
    use BelongsToCompany;

    public const TYPES = ['consignor' => 'Consignor', 'consignee' => 'Consignee', 'both' => 'Consignor & consignee'];

    protected $fillable = ['company_id', 'customer_id', 'name', 'type', 'address', 'pic_name', 'phone', 'location_id', 'is_active'];

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

    /** The customer this row is (null for one created on Master Data → Consignor / Consignee). */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isFromCustomer(): bool
    {
        return $this->customer_id !== null;
    }

    /**
     * The customer's row: its name, address (or default address), PIC and telephone (or default PIC's), tagged to the
     * customer. A new row is used as both and is active when the customer is; afterwards "Used as", the area, the
     * other tags and Active stay as set here, except that a customer's status change sets Active again.
     */
    public static function syncFromCustomer(Customer $customer): self
    {
        $party = static::query()->firstOrNew(['customer_id' => $customer->id]);
        $address = filled($customer->address)
            ? $customer->address
            : $customer->addresses()->orderByDesc('is_default')->orderBy('id')->value('address');
        $pic = $customer->pics()->orderByDesc('is_default')->orderBy('id')->first(['name', 'phone']);

        // the consignor / consignee is separate from the customer (user, 26 Oct 2026): made from it once, then its
        // name, PIC, telephone and address are its own; the customer only fills what is still empty
        $party->company_id ??= $customer->company_id;

        foreach ([
            'name' => $customer->company_name,
            'address' => filled($address) ? $address : null,
            'pic_name' => filled($customer->attention) ? $customer->attention : $pic?->name,
            'phone' => filled($customer->phone) ? $customer->phone : $pic?->phone,
        ] as $field => $fromCustomer) {
            if (blank($party->{$field}) && filled($fromCustomer)) {
                $party->{$field} = $fromCustomer;
            }
        }

        if (! $party->exists) {
            $party->type = 'both';
            $party->is_active = $customer->status === 'active';
        } elseif ($customer->wasChanged('status')) {
            $party->is_active = $customer->status === 'active';
        }

        $party->save();
        $party->customers()->syncWithoutDetaching([$customer->id]);

        return $party;
    }

    /** Rows for the customers that have none yet (customers entered without model events, e.g. by an import). */
    public static function addMissingCustomers(): int
    {
        $added = 0;

        Customer::query()
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('consignor_consignees')->whereColumn('consignor_consignees.customer_id', 'customers.id'))
            ->chunkById(200, function ($customers) use (&$added) {
                foreach ($customers as $customer) {
                    static::syncFromCustomer($customer);
                    $added++;
                }
            });

        return $added;
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

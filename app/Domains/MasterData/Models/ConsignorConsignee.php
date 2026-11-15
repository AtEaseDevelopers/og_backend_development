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
        return $this->belongsTo(Location::class)->withTrashed();
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

    /** A name or an address as compared: lower case, letters and digits only ("NO.11, JLN" = "No 11 Jln"). */
    public static function plain(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $value)) ?? '';
    }

    /**
     * Keeps a consignor / consignee address keyed on an order (user, 16 Nov 2026), so the same company with several
     * addresses is offered once per address the next time: the record with this name and address when there is one
     * (its empty PIC, telephone and area are filled); else the address goes on the record of that name which has no
     * address yet; else a new record under the same name. Nothing without both a name and an address.
     */
    public static function remember(?int $companyId, ?string $name, ?string $address, ?string $pic = null, ?string $phone = null, ?int $locationId = null, ?int $customerId = null): ?self
    {
        $name = trim((string) $name);
        $address = trim((string) $address);

        if ($name === '' || $address === '' || static::plain($address) === static::plain($name)) {
            return null;
        }

        $sameName = static::query()->withoutGlobalScopes()
            ->when($companyId, fn ($q, $id) => $q->where('company_id', $id))
            ->where('name', 'like', mb_substr($name, 0, 1).'%')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $party) => static::plain($party->name) === static::plain($name))
            ->values();

        $party = $sameName->first(fn (self $party) => static::plain($party->address) === static::plain($address))
            ?? $sameName->first(fn (self $party) => blank($party->address));

        if (! $party) {
            $party = new static(['company_id' => $companyId, 'name' => $name, 'type' => 'both', 'is_active' => true]);
        }

        foreach (['address' => $address, 'pic_name' => $pic, 'phone' => $phone, 'location_id' => $locationId] as $field => $value) {
            if (blank($party->{$field}) && filled($value)) {
                $party->{$field} = is_string($value) ? trim($value) : $value;
            }
        }

        if (! $party->exists || $party->isDirty()) {
            $party->save();
        }

        if ($customerId) {
            $party->customers()->syncWithoutDetaching([$customerId]);
        }

        return $party;
    }

    /**
     * The consignor, the consignee and the extra stops of an order, kept as above. A store's address (Customer Drop
     * Off / Self Collection) is not a consignor's or a consignee's. Never stops the order from saving.
     */
    public static function rememberFromOrder(\App\Domains\Quotation\Models\Quotation $order): void
    {
        try {
            $customerId = $order->customer_id ? (int) $order->customer_id : null;

            if (! $order->store_id) {
                static::remember($order->company_id, $order->consignor_name, $order->pickup_location, $order->consignor_pic_name, $order->consignor_pic_phone, $order->from_location_id, $customerId);
            }

            if (! $order->consignee_store_id) {
                static::remember($order->company_id, $order->consignee_name, $order->drop_off_location ?: $order->consignee_address, $order->consignee_pic_name, $order->consignee_pic_phone, $order->to_location_id, $customerId);
            }

            foreach ((array) $order->stops as $stop) {
                if (! is_array($stop)) {
                    continue;
                }

                $pickup = ($stop['type'] ?? '') === \App\Support\OrderStops::PICKUP;
                static::remember($order->company_id, ($stop['name'] ?? null) ?: ($pickup ? $order->consignor_name : $order->consignee_name), $stop['location'] ?? null, $stop['pic_name'] ?? null, $stop['pic_phone'] ?? null, null, $customerId);
            }
        } catch (\Throwable $e) {
            report($e);
        }
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

    /** Every record is usable as a consignor and as a consignee (client, 16 Nov 2026: no "Used as"). */
    public function scopeUsedAs(Builder $query, string $role): void
    {
        //
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Reason captured when a customer rejects or negotiates a quotation (section D). */
enum QuotationRejectionCategory: string implements HasLabel
{
    case NotRequired = 'not_required';
    case Unavailable = 'unavailable';
    case PriceNegotiation = 'price_negotiation';
    case ScopeChange = 'scope_change';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NotRequired => 'Service not required',
            self::Unavailable => 'Service not available',
            self::PriceNegotiation => 'Price negotiation required',
            self::ScopeChange => 'Scope change',
            self::Other => 'Other',
        };
    }

    public function isNegotiation(): bool
    {
        return in_array($this, [self::PriceNegotiation, self::ScopeChange], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}

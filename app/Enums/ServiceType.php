<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** CSN service mode used by the CSN listing filters (All / Pick Up / Store). */
enum ServiceType: string implements HasLabel
{
    case Pickup = 'pickup';
    case Store = 'store';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pickup => 'Pick Up',
            self::Store => 'Store',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}

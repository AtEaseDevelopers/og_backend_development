<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Destination drop-off type (section B). O&G will supply more types later. */
enum DropOffType: string implements HasLabel
{
    case Construction = 'construction';
    case Supermarket = 'supermarket';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}

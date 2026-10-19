<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Destination drop-off type (section B): the client's price list types (19 Oct 2026). A price for a type is used for
 * that type; any other type uses the Default price. The stored values of the first types are kept from before
 * (supermarket = Shopping Centre, other = Others).
 */
enum DropOffType: string implements HasLabel
{
    case Default = 'default';
    case Supermarket = 'supermarket';
    case Construction = 'construction';
    case Household = 'household';
    case Hospital = 'hospital';
    case Hotel = 'hotel';
    case OwnCompany = 'own_company';
    case Port = 'port';
    case Factory = 'factory';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Supermarket => 'Shopping Centre',
            self::Construction => 'Construction Site',
            self::Household => 'Household',
            self::Hospital => 'Hospital',
            self::Hotel => 'Hotel',
            self::OwnCompany => 'Own Company',
            self::Port => 'Port',
            self::Factory => 'Factory',
            self::Other => 'Others',
        };
    }

    /** The type named in the price list ("Shopping Centre", "Construction Site", "Others"…); blank = Default, unknown = null. */
    public static function fromLabel(?string $label): ?self
    {
        $key = strtolower(trim((string) $label));

        if ($key === '') {
            return self::Default;
        }

        foreach (self::cases() as $case) {
            if ($key === strtolower((string) $case->getLabel()) || $key === $case->value) {
                return $case;
            }
        }

        return match ($key) {
            'supermarket' => self::Supermarket,
            'construction' => self::Construction,
            'other' => self::Other,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}

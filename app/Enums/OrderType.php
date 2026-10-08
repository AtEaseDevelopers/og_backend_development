<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Order Type captured on every order (section B). Maps 1:1 onto the CSN billing type.
 */
enum OrderType: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Cod = 'cod';
    case Term = 'term';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cod => 'COD',
            self::Term => 'Credit / Term',
        };
    }

    /** Compact label for badges: Cash, COD or Credit. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cod => 'COD',
            self::Term => 'Credit',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Cash => 'success',
            self::Cod => 'warning',
            self::Term => 'info',
        };
    }

    public function billingType(): CsnBillingType
    {
        return match ($this) {
            self::Cash => CsnBillingType::CashBill,
            self::Cod => CsnBillingType::Cod,
            self::Term => CsnBillingType::Term,
        };
    }

    public static function fromBillingType(CsnBillingType|string|null $type): ?self
    {
        $value = $type instanceof CsnBillingType ? $type->value : $type;

        return match ($value) {
            'cash_bill' => self::Cash,
            'cod' => self::Cod,
            'term' => self::Term,
            default => null,
        };
    }

    /**
     * Section C: Term may change to Term / Cash / COD; COD may change only to Cash; Cash cannot change.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Term => [self::Term, self::Cash, self::Cod],
            self::Cod => [self::Cod, self::Cash],
            self::Cash => [self::Cash],
        };
    }

    public function canChangeTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }
}

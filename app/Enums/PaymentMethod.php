<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Counter = 'counter';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';
    case Ewallet = 'ewallet';
    case Cheque = 'cheque';
    case Cod = 'cod';
    case Credit = 'credit';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Counter => 'Pay at Counter',
            self::BankTransfer => 'Bank Transfer',
            self::Online => 'Online Payment',
            self::Ewallet => 'eWallet',
            self::Cheque => 'Cheque',
            self::Cod => 'Cash on Delivery',
            self::Credit => 'Credit Term',
        };
    }

    /** Section F: only Cash / Pay at Counter submissions require two approval levels. */
    public function requiresTwoApprovals(): bool
    {
        return in_array($this, [self::Cash, self::Counter], true);
    }

    /** @return array<string, string> */
    public static function options(bool $customerFacing = false): array
    {
        return collect(self::cases())
            ->when($customerFacing, fn ($c) => $c->reject(fn (self $m) => in_array($m, [self::Cod, self::Credit], true)))
            ->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])
            ->all();
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JobSheetStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InTransit => 'In Transit',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InTransit => 'info',
            self::Completed => 'success',
        };
    }
}

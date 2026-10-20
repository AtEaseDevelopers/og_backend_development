<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Uom;
use App\Filament\Resources\LorryProductResource\Pages;

/**
 * Master Data → Lorries (user, 21 Oct 2026): the price list's Chartered Lorry category products (one lorry trip each),
 * priced like the UOM ones.
 */
class LorryProductResource extends UomResource
{
    protected static ?string $slug = 'lorry-products';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Lorries';

    protected static ?string $modelLabel = 'chartered lorry';

    protected static ?string $pluralModelLabel = 'chartered lorries';

    protected static ?int $navigationSort = 4;

    public static function category(): string
    {
        return Uom::LORRY;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLorries::route('/'),
            'create' => Pages\CreateCharteredLorry::route('/create'),
            'view' => Pages\ViewCharteredLorry::route('/{record}'),
            'edit' => Pages\EditCharteredLorry::route('/{record}/edit'),
        ];
    }
}

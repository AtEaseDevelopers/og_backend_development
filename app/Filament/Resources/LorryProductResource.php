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
    use \App\Filament\Concerns\HasModulePermissions;

    /** Open to the roles ticked for "masterdata.products" on Master Data → Roles & Permission (13 Nov 2026). */
    public static function canViewAny(): bool
    {
        return \App\Support\Permissions::screen(static::class);
    }

    protected static ?string $slug = 'lorry-products';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Chartered Lorry';

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

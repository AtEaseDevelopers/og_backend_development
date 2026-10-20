<?php

namespace App\Filament\Resources;

use App\Domains\MasterData\Models\Uom;
use App\Filament\Resources\ItemProductResource\Pages;

/**
 * Master Data → Items (user, 21 Oct 2026): the price list's Item category products, priced like the UOM ones.
 */
class ItemProductResource extends UomResource
{
    protected static ?string $slug = 'item-products';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Items';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'items';

    protected static ?int $navigationSort = 5;

    public static function category(): string
    {
        return Uom::ITEM;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'view' => Pages\ViewItem::route('/{record}'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}

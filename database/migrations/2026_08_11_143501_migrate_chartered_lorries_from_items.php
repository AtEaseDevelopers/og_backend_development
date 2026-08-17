<?php

use App\Domains\MasterData\Models\CharteredLorry;
use App\Domains\MasterData\Models\CharteredLorryRate;
use App\Domains\MasterData\Models\Item;
use App\Domains\MasterData\Models\ItemCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $category = ItemCategory::query()->where('name', 'Chartered Lorry')->first();

        if (! $category) {
            return;
        }

        Item::query()
            ->where('item_category_id', $category->id)
            ->with('rates')
            ->each(function (Item $item): void {
                $charteredLorry = CharteredLorry::query()->updateOrCreate(
                    ['name' => $item->name],
                    [
                        'code' => $item->code,
                        'is_active' => $item->is_active,
                    ],
                );

                foreach ($item->rates as $rate) {
                    CharteredLorryRate::query()->updateOrCreate(
                        [
                            'chartered_lorry_id' => $charteredLorry->id,
                            'location_id' => $rate->location_id,
                        ],
                        ['price' => $rate->price],
                    );
                }

                $item->rates()->delete();
                $item->delete();
            });
    }

    public function down(): void
    {
        // Data migration is not reversed automatically.
    }
};

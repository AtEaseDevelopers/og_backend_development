<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Domains\MasterData\Models\Uom;
use App\Filament\Resources\UomResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUom extends ViewRecord
{
    protected static string $resource = UomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('UOM details')->schema([
                Infolists\Components\TextEntry::make('code')->extraAttributes(['style' => 'overflow-wrap:anywhere;word-break:break-all']),
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\IconEntry::make('is_active')->boolean(),
            ])->columns(3),
            Infolists\Components\Section::make('Location rate tiers')
                ->description('Unit price (RM) by quantity range and location. The range that matches the order quantity is applied.')
                ->schema([
                    Infolists\Components\ViewEntry::make('rate_matrix')
                        ->hiddenLabel()
                        ->view('filament.infolists.uom-rate-matrix')
                        ->viewData(['matrix' => static::rateMatrix($this->getRecord())])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Tiers grouped by location. When every location uses the same quantity ranges the
     * view renders one matrix (rows = ranges, columns = locations); otherwise one card per location.
     *
     * @return array{shared: bool, locations: list<string>, ranges: list<string>, cells: array<string, array<string, float>>, byLocation: array<string, list<array{range: string, price: float}>>}
     */
    public static function rateMatrix(Uom $uom): array
    {
        $tiers = $uom->rateTiers()->with('location')->get()
            ->sortBy([fn ($a, $b) => strcmp($a->location?->name ?? '', $b->location?->name ?? ''), fn ($a, $b) => (float) $a->min_qty <=> (float) $b->min_qty]);

        $fmt = fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        $label = function ($tier) use ($fmt): string {
            $min = (float) $tier->min_qty;
            $max = $tier->max_qty !== null ? (float) $tier->max_qty : null;

            return match (true) {
                $max === null => $fmt($min).'+',
                $max === $min => $fmt($min),
                default => $fmt($min).' – '.$fmt($max),
            };
        };

        $byLocation = [];
        $rangeOrder = [];

        foreach ($tiers as $tier) {
            $location = $tier->location?->name ?? 'Unknown';
            $range = $label($tier);
            $byLocation[$location][] = ['range' => $range, 'price' => (float) $tier->price];
            $rangeOrder[$range] ??= (float) $tier->min_qty;
        }

        $signatures = collect($byLocation)->map(fn (array $rows) => collect($rows)->pluck('range')->implode('|'))->unique();
        asort($rangeOrder);

        $cells = [];
        foreach ($byLocation as $location => $rows) {
            foreach ($rows as $row) {
                $cells[$row['range']][$location] = $row['price'];
            }
        }

        return [
            'shared' => $signatures->count() <= 1,
            'locations' => array_keys($byLocation),
            'ranges' => array_keys($rangeOrder),
            'cells' => $cells,
            'byLocation' => $byLocation,
        ];
    }
}

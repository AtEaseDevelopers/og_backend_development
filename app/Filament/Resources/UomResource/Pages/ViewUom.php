<?php

namespace App\Filament\Resources\UomResource\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Uom;
use App\Enums\DropOffType;
use Illuminate\Support\Collection;
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
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\IconEntry::make('is_active')->boolean(),
                Infolists\Components\TextEntry::make('category')->formatStateUsing(fn (?string $state) => $state ? (Uom::CATEGORIES[$state] ?? ucfirst($state)) : null)->placeholder('—'),
                Infolists\Components\TextEntry::make('sub_category')->placeholder('—'),
                Infolists\Components\TextEntry::make('base_item')->label('Item')->placeholder('—'),
            ])->columns(3),
            // the product's limits, as the price list template's columns (Measurement: size and weight; Item: weight)
            Infolists\Components\Section::make('Size and weight limits')
                ->visible(fn (Uom $record) => filled($record->category))
                ->schema([
                    ...collect([
                        'min_lwh' => 'Min L+W+H (CM)', 'max_lwh' => 'Max L+W+H (CM)',
                        'min_length' => 'Min Length, L (CM)', 'max_length' => 'Max Length, L (CM)',
                        'max_width' => 'Max Width, W (CM)', 'max_height' => 'Max Height, H (CM)',
                    ])->map(fn (string $label, string $column) => Infolists\Components\TextEntry::make($column)
                        ->label(fn (Uom $record) => $record->category === Uom::LORRY
                            ? (['min_length' => 'Min Length (CM)', 'max_length' => 'Max Length (CM)', 'max_width' => 'Max Sides 1 (CM)', 'max_height' => 'Max Sides 2 (CM)'][$column] ?? $label)
                            : $label)
                        ->placeholder('—')
                        ->formatStateUsing(fn ($state) => static::number($state))
                        ->visible(fn (Uom $record) => in_array($record->category, [Uom::MEASUREMENT, Uom::LORRY], true)))->values()->all(),
                    Infolists\Components\TextEntry::make('min_weight')->label('Min Weight (KG)')->placeholder('—')->formatStateUsing(fn ($state) => static::number($state)),
                    Infolists\Components\TextEntry::make('max_weight')->label('Max Weight (KG)')->placeholder('—')->formatStateUsing(fn ($state) => static::number($state)),
                ])->columns(4),
            Infolists\Components\Section::make('Location rate tiers')
                ->description('Unit price (RM) by quantity range and location. The default price is used unless the selected customer has a special price; the range that matches the order quantity is applied.')
                ->schema([
                    Infolists\Components\ViewEntry::make('rate_matrix')
                        ->hiddenLabel()
                        ->view('filament.infolists.uom-rate-matrix')
                        ->viewData(['sets' => static::rateSets($this->getRecord())])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * One price table per price set: the default price first, then each customer's special price (by debtor code)
     * and each drop-off type with its own price.
     *
     * @return list<array{title: string, special: bool, matrix: array<string, mixed>, minimums: list<string>}>
     */
    public static function rateSets(Uom $uom): array
    {
        $tiers = $uom->rateTiers()->with('location')->get();
        $codes = $tiers->pluck('customer_code')->filter()->unique()->values();
        $names = $codes->isEmpty() ? collect() : Customer::query()->whereIn('code', $codes)->pluck('company_name', 'code');

        return $tiers
            ->groupBy(fn ($tier) => (string) $tier->customer_code.'|'.($tier->drop_off_type ?: DropOffType::Default->value))
            ->sortBy(fn (Collection $set, string $key) => [str_starts_with($key, '|') ? 0 : 1, $key])
            ->map(function (Collection $set) use ($names) {
                $first = $set->first();
                $type = DropOffType::tryFrom((string) $first->drop_off_type) ?? DropOffType::Default;
                $code = $first->customer_code;
                $title = $code
                    ? 'Special price · '.$code.' '.($names[$code] ?? '(not a customer yet)')
                    : 'Default price';

                if ($type !== DropOffType::Default) {
                    $title .= ' · '.$type->getLabel().' drop-off';
                }

                $minimums = collect([
                    'Pick-up minimum' => $set->max('pickup_min_charge'),
                    'Construction site minimum' => $set->max('construction_min_charge'),
                ])->filter(fn ($value) => (float) $value > 0)->map(fn ($value, $label) => $label.' RM '.number_format((float) $value, 2))->values();

                $dropOff = $set->filter(fn ($tier) => (float) $tier->drop_off_min_charge > 0)
                    ->groupBy(fn ($tier) => $tier->location?->name ?? 'Unknown')
                    ->map(fn (Collection $rows, string $location) => $location.' RM '.number_format((float) $rows->max('drop_off_min_charge'), 2));

                if ($dropOff->isNotEmpty()) {
                    $minimums->push('Drop-off minimum: '.$dropOff->implode(', '));
                }

                return [
                    'title' => $title,
                    'special' => $code !== null,
                    'matrix' => static::rateMatrix($set),
                    'minimums' => $minimums->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Tiers grouped by location. When every location uses the same quantity ranges the
     * view renders one matrix (rows = ranges, columns = locations); otherwise one card per location.
     *
     * @return array{shared: bool, locations: list<string>, ranges: list<string>, cells: array<string, array<string, float>>, byLocation: array<string, list<array{range: string, price: float}>>}
     */
    public static function rateMatrix(Collection $tiers): array
    {
        $tiers = $tiers
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

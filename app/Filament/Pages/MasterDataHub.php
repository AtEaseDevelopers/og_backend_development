<?php

namespace App\Filament\Pages;

use App\Filament\Resources;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Master Data (user, 21 Oct 2026): one menu item; the page shows a card per master data page (with its icon), each
 * opening that page. The master data pages are no longer in the menu themselves. The cards are labels only and fit
 * on one screen (user, 25 Oct 2026); "Show / hide menus" lets each user hide the cards they do not use.
 */
class MasterDataHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Master Data';

    protected static ?string $title = 'Master Data';

    protected static ?string $slug = 'master-data';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.master-data-hub';

    /** @var array<string, list<array{class: class-string, label: string, icon: string}>> */
    public const ITEMS = [
        'Customers' => [
            ['class' => Resources\CustomerResource::class, 'label' => 'Customers', 'icon' => 'heroicon-o-building-storefront'],
            ['class' => Resources\LeadResource::class, 'label' => 'Leads', 'icon' => 'heroicon-o-user-plus'],
            ['class' => Resources\ConsignorConsigneeResource::class, 'label' => 'Consignor / Consignee', 'icon' => 'heroicon-o-arrows-right-left'],
        ],
        // the three product types (user, 25 Oct 2026)
        'Products & pricing' => [
            ['class' => Resources\UomResource::class, 'label' => 'Measurements', 'icon' => 'heroicon-o-scale'],
            ['class' => Resources\ItemProductResource::class, 'label' => 'Items', 'icon' => 'heroicon-o-cube'],
            ['class' => Resources\LorryProductResource::class, 'label' => 'Chartered Lorry', 'icon' => 'heroicon-o-truck'],
            ['class' => Resources\DropOffMinChargeResource::class, 'label' => 'Drop-off Min Charges', 'icon' => 'heroicon-o-banknotes'],
        ],
        'Fleet & dispatch' => [
            ['class' => Resources\DriverResource::class, 'label' => 'Drivers', 'icon' => 'heroicon-o-user'],
            ['class' => Resources\FleetVehicleResource::class, 'label' => 'Fleet Vehicles', 'icon' => 'heroicon-o-truck'],
            ['class' => Resources\TransferCodeResource::class, 'label' => 'Transfer Codes', 'icon' => 'heroicon-o-qr-code'],
            ['class' => Resources\StoreResource::class, 'label' => 'Stores', 'icon' => 'heroicon-o-home-modern'],
        ],
        // user, 25 Oct 2026: the switcher's KL, JB, … are companies; a company's sales areas are its branches
        'Company' => [
            ['class' => Resources\BranchResource::class, 'label' => 'Company', 'icon' => 'heroicon-o-building-office-2'],
            ['class' => Resources\SaLocationResource::class, 'label' => 'Branches', 'icon' => 'heroicon-o-map-pin'],
            ['class' => Resources\UserResource::class, 'label' => 'Staff accounts', 'icon' => 'heroicon-o-users'],
            ['class' => SystemSettingsPage::class, 'label' => 'System Settings', 'icon' => 'heroicon-o-adjustments-horizontal'],
        ],
    ];

    /** The cards the user may open (their page's own access rule), by group. */
    public function available(): array
    {
        return collect(self::ITEMS)
            ->map(fn (array $items) => array_values(array_filter($items, fn (array $item) => static::canOpen($item['class']))))
            ->filter()
            ->all();
    }

    /** The cards shown: those the user may open and has not hidden, by group. */
    public function groups(): array
    {
        $hidden = $this->hiddenKeys();

        return collect($this->available())
            ->map(fn (array $items) => array_values(array_filter($items, fn (array $item) => ! in_array(static::key($item['class']), $hidden, true))))
            ->filter()
            ->all();
    }

    /** @return list<string> */
    public function hiddenKeys(): array
    {
        return array_values((array) (auth()->user()?->preferences['master_data_hidden'] ?? []));
    }

    /** The cards of each group the user hid, by label (a badge on the group; hovering it lists them). @return array<string, list<string>> */
    public function hiddenByGroup(): array
    {
        $hidden = $this->hiddenKeys();

        return collect($this->available())
            ->map(fn (array $items) => array_values(array_column(array_filter($items, fn (array $item) => in_array(static::key($item['class']), $hidden, true)), 'label')))
            ->all();
    }

    public function hiddenCount(): int
    {
        return collect($this->available())->flatten(1)->filter(fn (array $item) => in_array(static::key($item['class']), $this->hiddenKeys(), true))->count();
    }

    /** A card's key in the user's preferences: its page class name. */
    public static function key(string $class): string
    {
        return class_basename($class);
    }

    protected function getHeaderActions(): array
    {
        return [$this->showHideAction()];
    }

    /** Tick the cards to show on this page; an unticked card is hidden for this user only (its page stays open to them). */
    public function showHideAction(): Action
    {
        $groups = array_values(array_keys($this->available()));

        return Action::make('showHide')
            ->label('Show / hide menus')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Show / hide menus')
            ->modalDescription('Ticked menus show on this page. Untick a menu to hide it; tick it again to show it back. Only your own page changes.')
            ->modalSubmitActionLabel('Save')
            ->modalWidth('2xl')
            ->fillForm(function () use ($groups): array {
                $available = $this->available();
                $hidden = $this->hiddenKeys();

                return collect($groups)->mapWithKeys(fn (string $group, int $i) => ['shown_'.$i => collect($available[$group])
                    ->map(fn (array $item) => static::key($item['class']))
                    ->reject(fn (string $key) => in_array($key, $hidden, true))
                    ->values()
                    ->all()])->all();
            })
            ->form(fn () => collect($groups)->map(function (string $group, int $i) {
                $options = collect($this->available()[$group])->mapWithKeys(fn (array $item) => [static::key($item['class']) => $item['label']])->all();

                // Select all / Deselect all as buttons beside the group label (user, 25 Oct 2026)
                return Forms\Components\CheckboxList::make('shown_'.$i)
                    ->label($group)
                    ->options($options)
                    ->live()
                    ->hintActions([
                        Forms\Components\Actions\Action::make('selectAll'.$i)
                            ->label('Select all')
                            ->button()
                            ->size('xs')
                            ->color('gray')
                            // only when something is unticked (user, 25 Oct 2026)
                            ->visible(fn (Forms\Get $get) => count((array) $get('shown_'.$i)) < count($options))
                            ->action(fn (Forms\Set $set) => $set('shown_'.$i, array_keys($options))),
                        Forms\Components\Actions\Action::make('deselectAll'.$i)
                            ->label('Deselect all')
                            ->button()
                            ->size('xs')
                            ->color('gray')
                            ->visible(fn (Forms\Get $get) => count((array) $get('shown_'.$i)) > 0)
                            ->action(fn (Forms\Set $set) => $set('shown_'.$i, [])),
                    ])
                    ->columns(2);
            })->all())
            ->action(function (array $data) use ($groups): void {
                $user = auth()->user();
                $available = collect($this->available())->flatten(1)->map(fn (array $item) => static::key($item['class']));
                $shown = collect($groups)->keys()->flatMap(fn (int $i) => (array) ($data['shown_'.$i] ?? []));
                // a card the user cannot open now keeps its setting
                $hidden = $available->diff($shown)
                    ->merge(collect($this->hiddenKeys())->diff($available))
                    ->unique()
                    ->values()
                    ->all();

                $user->forceFill(['preferences' => array_merge((array) $user->preferences, ['master_data_hidden' => $hidden])])->save();

                Notification::make()->title($hidden === [] ? 'All menus are shown' : count($hidden).' menu'.(count($hidden) === 1 ? '' : 's').' hidden')->success()->send();
            });
    }

    public static function canOpen(string $class): bool
    {
        try {
            return is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::canViewAny() : $class::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function url(string $class): string
    {
        return is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl();
    }
}

<?php

namespace App\Filament\Pages;

use App\Support\SystemSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'system-settings';

    protected static string $view = 'filament.pages.system-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SystemSettings::all());
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    public function getSubheading(): ?string
    {
        return 'Configurable behaviour for the order → quotation → CSN flow.';
    }

    public function form(Form $form): Form
    {
        $labels = SystemSettings::labels();

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Customer review')
                    ->description('Quotations left under customer review are closed automatically after this many days without any update.')
                    ->schema([
                        Forms\Components\TextInput::make(SystemSettings::PENDING_REVIEW_DAYS)
                            ->label($labels[SystemSettings::PENDING_REVIEW_DAYS])
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Notifications')
                    ->schema([
                        Forms\Components\Toggle::make(SystemSettings::EMAILS_ENABLED)
                            ->label($labels[SystemSettings::EMAILS_ENABLED])
                            ->helperText('When off, the system records the notification but does not send the email.'),
                        Forms\Components\Toggle::make(SystemSettings::WHATSAPP_ENABLED)
                            ->label($labels[SystemSettings::WHATSAPP_ENABLED]),
                    ])->columns(2),
                Forms\Components\Section::make('Enquiry ownership')
                    ->schema([
                        Forms\Components\TextInput::make(SystemSettings::ENQUIRY_LOCK_SECONDS)
                            ->label($labels[SystemSettings::ENQUIRY_LOCK_SECONDS])
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(300)
                            ->required()
                            ->helperText('The salesperson screen sends a heartbeat every 2 seconds; the lock is released after this many seconds without one.'),
                    ])->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save settings')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_keys(SystemSettings::DEFAULTS) as $key) {
            if (array_key_exists($key, $state)) {
                SystemSettings::set($key, $state[$key]);
            }
        }

        Notification::make()->title('Settings saved')->success()->send();
    }
}

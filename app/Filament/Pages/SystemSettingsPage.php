<?php

namespace App\Filament\Pages;

use App\Support\MailSettings;
use App\Support\SystemSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
        // the SMTP password is never sent back to the browser
        $this->form->fill(array_merge(SystemSettings::all(), [MailSettings::PASSWORD => null]));
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
                Forms\Components\Section::make('Email (SMTP)')
                    ->description('The account used to email quotations, proformas, invoices, CSNs and other notifications to customers. Until SMTP is set up, emails are only written to the system log and are not delivered.')
                    ->schema([
                        Forms\Components\Select::make(MailSettings::MAILER)
                            ->label('Email sending')
                            ->options(['log' => 'Not set up (log only, nothing is delivered)', 'smtp' => 'SMTP'])
                            ->default('log')
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make(MailSettings::HOST)
                            ->label('SMTP host')
                            ->placeholder('e.g. smtp.gmail.com')
                            ->required(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\TextInput::make(MailSettings::PORT)
                            ->label('Port')
                            ->numeric()
                            ->placeholder('587')
                            ->required(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\Select::make(MailSettings::ENCRYPTION)
                            ->label('Encryption')
                            ->options(['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'])
                            ->default('tls')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\TextInput::make(MailSettings::USERNAME)
                            ->label('Username')
                            ->autocomplete('off')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\TextInput::make(MailSettings::PASSWORD)
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder(fn () => MailSettings::hasPassword() ? '•••••••• (saved, leave blank to keep)' : 'App password / SMTP password')
                            ->helperText('Stored encrypted. Leave blank to keep the saved password.')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\TextInput::make(MailSettings::FROM_ADDRESS)
                            ->label('From address')
                            ->email()
                            ->placeholder('e.g. billing@ogtransport.com')
                            ->required(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
                        Forms\Components\TextInput::make(MailSettings::FROM_NAME)
                            ->label('From name')
                            ->placeholder('e.g. O & G Transport')
                            ->visible(fn (Forms\Get $get) => $get(MailSettings::MAILER) === 'smtp'),
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
            Action::make('testEmail')
                ->label('Send test email')
                ->color('gray')
                ->modalHeading('Send a test email')
                ->modalDescription('Uses the saved SMTP settings. Save your changes first.')
                ->modalSubmitActionLabel('Send test')
                ->form([
                    Forms\Components\TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    if (! MailSettings::isSmtp()) {
                        Notification::make()->title('SMTP is not set up yet')->body('Choose SMTP under Email (SMTP), fill in the account and save first.')->warning()->send();

                        return;
                    }

                    try {
                        MailSettings::apply();
                        Mail::raw('This is a test email from '.config('app.name').'. Your SMTP settings work.', fn ($m) => $m->to($data['to'])->subject('Test email from '.config('app.name')));
                        Notification::make()->title('Test email sent to '.$data['to'])->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Test email failed')->body(mb_substr($e->getMessage(), 0, 300))->danger()->persistent()->send();
                    }
                }),
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

        // email: hidden SMTP fields are not in the state when "Not set up" is chosen; keep what was saved
        foreach (MailSettings::KEYS as $key) {
            if (! array_key_exists($key, $state)) {
                continue;
            }

            if ($key === MailSettings::PASSWORD) {
                if (filled($state[$key])) {
                    SystemSettings::set($key, MailSettings::encryptPassword((string) $state[$key]));
                }

                continue;
            }

            SystemSettings::set($key, $state[$key]);
        }

        $this->data[MailSettings::PASSWORD] = null;

        Notification::make()->title('Settings saved')->success()->send();
    }
}

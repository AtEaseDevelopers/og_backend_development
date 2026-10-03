<?php

namespace App\Filament\Resources;

use App\Domains\Notification\Models\NotificationLog;
use App\Filament\Resources\NotificationLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Message / email history: recipient, type, channel, time, delivery status, related document. */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Integrations';

    protected static ?string $navigationLabel = 'Notifications Log';

    protected static ?int $navigationSort = 65;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Time')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('event')->badge()->color('gray')->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state))),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('recipient_name')->label('Recipient')->description(fn (NotificationLog $r) => $r->recipient_contact)->searchable(),
                Tables\Columns\TextColumn::make('subject')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'sent' => 'success', 'ready' => 'info', 'failed' => 'danger', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('notifiable_type')->label('Related')->formatStateUsing(fn ($state, NotificationLog $r) => class_basename((string) $state).' #'.$r->notifiable_id)->toggleable(),
                Tables\Columns\TextColumn::make('error')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('channel')->options(['email' => 'Email', 'whatsapp' => 'WhatsApp', 'system' => 'System']),
                Tables\Filters\SelectFilter::make('status')->options(['sent' => 'Sent', 'ready' => 'Ready (WhatsApp)', 'skipped' => 'Skipped', 'failed' => 'Failed']),
                Tables\Filters\SelectFilter::make('recipient_type')->options(['customer' => 'Customer', 'driver' => 'Driver', 'user' => 'Staff']),
            ])
            ->actions([
                Tables\Actions\Action::make('openWhatsApp')
                    ->label('Send via WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (NotificationLog $r) => filled($r->whatsapp_url))
                    ->url(fn (NotificationLog $r) => $r->whatsapp_url, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('preview')
                    ->label('Message')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn (NotificationLog $r) => new \Illuminate\Support\HtmlString('<pre class="whitespace-pre-wrap text-sm">'.e($r->subject."\n\n".$r->message).'</pre>'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
        ];
    }
}

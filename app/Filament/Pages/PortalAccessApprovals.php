<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\CustomerPortalLink;
use App\Domains\Notification\Actions\SendNotification;
use App\Domains\Notification\Models\NotificationLog;
use App\Filament\Concerns\HasExcelColumnFilters;
use App\Support\CurrentBranch;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Section P: Admin approves customer portal access: a new self-registration, another login for a BRN already
 * registered, or an existing login asking for another BRN. Only approved access can enter the portal.
 */
class PortalAccessApprovals extends Page implements HasTable
{
    use HasExcelColumnFilters, InteractsWithTable {
        HasExcelColumnFilters::filterTableQuery insteadof InteractsWithTable;
        InteractsWithTable::filterTableQuery as filamentFilterTableQuery;
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Portal Registrations';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.portal-access-approvals';

    public function getTitle(): string
    {
        return 'Customer Portal Registrations';
    }

    public function getSubheading(): ?string
    {
        return 'Approve or reject customer logins asking for portal access to a company account (BRN).';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_hq || $user?->hasAnyRole(['hq_admin', 'branch_manager', 'finance']));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::scoped(CustomerPortalLink::query())->where('status', CustomerPortalLink::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Requests for customers of the branch being viewed. */
    private static function scoped(Builder $query): Builder
    {
        $branchId = CurrentBranch::id();

        return $query->whereHas('customer', fn (Builder $q) => $q->when($branchId, fn ($b) => $b->where('branch_id', $branchId)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::scoped(CustomerPortalLink::query()->with(['customer', 'user', 'reviewer'])))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Requested')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Login')->description(fn (CustomerPortalLink $r) => $r->user?->email)->searchable(),
                Tables\Columns\IconColumn::make('user.email_verified_at')->label('Email verified')->boolean()
                    ->getStateUsing(fn (CustomerPortalLink $r) => (bool) $r->user?->email_verified_at),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Company')->searchable()
                    ->description(fn (CustomerPortalLink $r) => trim(($r->customer?->address ?? '').($r->customer?->phone ? ' · '.$r->customer->phone : ''))),
                Tables\Columns\TextColumn::make('customer.brn')->label('BRN')->searchable(),
                Tables\Columns\TextColumn::make('customer.tin')->label('TIN')->toggleable(),
                Tables\Columns\TextColumn::make('kind')->label('Request')
                    ->getStateUsing(fn (CustomerPortalLink $r) => $r->customer?->portal_approved ? 'Existing customer' : 'New company')
                    ->badge()->color(fn (string $state) => $state === 'New company' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        CustomerPortalLink::STATUS_APPROVED => 'success',
                        CustomerPortalLink::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewed by')
                    ->description(fn (CustomerPortalLink $r) => $r->review_remarks)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        CustomerPortalLink::STATUS_PENDING => 'Pending',
                        CustomerPortalLink::STATUS_APPROVED => 'Approved',
                        CustomerPortalLink::STATUS_REJECTED => 'Rejected',
                    ])
                    ->default(CustomerPortalLink::STATUS_PENDING),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CustomerPortalLink $r) => $r->status !== CustomerPortalLink::STATUS_APPROVED)
                    ->form([Forms\Components\Textarea::make('remarks')->label('Remarks (optional)')->rows(2)])
                    ->modalDescription(fn (CustomerPortalLink $r) => $r->customer?->portal_approved
                        ? 'Give '.$r->user?->email.' access to '.$r->customer?->company_name.'.'
                        : $r->customer?->company_name.' is a new company: approving also approves the customer account for the portal. Check the BRN and details first.')
                    ->action(fn (CustomerPortalLink $record, array $data) => $this->decide($record, true, $data['remarks'] ?? null)),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CustomerPortalLink $r) => $r->status === CustomerPortalLink::STATUS_PENDING)
                    ->form([Forms\Components\Textarea::make('remarks')->label('Reason (sent to the customer)')->required()->rows(2)])
                    ->action(fn (CustomerPortalLink $record, array $data) => $this->decide($record, false, $data['remarks'])),
            ]);
    }

    private function decide(CustomerPortalLink $link, bool $approve, ?string $remarks): void
    {
        DB::transaction(function () use ($link, $approve, $remarks) {
            $link->update([
                'status' => $approve ? CustomerPortalLink::STATUS_APPROVED : CustomerPortalLink::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            if ($approve && ! $link->customer->portal_approved) {
                $link->customer->update(['portal_approved' => true]);
            }
        });

        $user = $link->user;

        if ($user?->email) {
            app(SendNotification::class)->execute(
                'portal_access_'.($approve ? 'approved' : 'rejected'),
                ['name' => $user->name, 'email' => $user->email, 'type' => 'portal_user'],
                'Customer portal access '.($approve ? 'approved' : 'not approved').' — '.$link->customer->company_name,
                $approve
                    ? "Hello {$user->name},\n\nYour access to {$link->customer->company_name} (BRN {$link->customer->brn}) on the O&G Customer Portal has been approved.\n\nSign in: ".route('portal.login').($remarks ? "\n\nRemarks: {$remarks}" : '')
                    : "Hello {$user->name},\n\nYour request for portal access to {$link->customer->company_name} (BRN {$link->customer->brn}) was not approved.\n\nReason: {$remarks}",
                $link->customer,
                [NotificationLog::CHANNEL_EMAIL],
            );
        }

        Notification::make()
            ->title($approve ? 'Access approved' : 'Request rejected')
            ->body($user?->email.' · '.$link->customer->company_name)
            ->success()
            ->send();
    }
}

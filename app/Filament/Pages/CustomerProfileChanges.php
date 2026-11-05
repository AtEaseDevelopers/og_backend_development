<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Actions\ReviewCustomerProfileChange;
use App\Domains\MasterData\Models\CustomerProfileChange;
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
use InvalidArgumentException;

/**
 * Section P: profile changes customers made in the portal. Major changes (company name, BRN, TIN, e-invoice
 * buyer details) wait here for re-verification; the rest are listed as the audit trail.
 */
class CustomerProfileChanges extends Page implements HasTable
{
    use HasExcelColumnFilters, InteractsWithTable {
        HasExcelColumnFilters::filterTableQuery insteadof InteractsWithTable;
        InteractsWithTable::filterTableQuery as filamentFilterTableQuery;
    }

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?string $navigationLabel = 'Customer Profile Changes';

    protected static ?int $navigationSort = 21;

    protected static string $view = 'filament.pages.portal-access-approvals';

    public function getTitle(): string
    {
        return 'Customer Profile Changes';
    }

    public function getSubheading(): ?string
    {
        return 'Verify major profile changes from the customer portal. Every portal profile change is kept here.';
    }

    public static function canAccess(): bool
    {
        return PortalAccessApprovals::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::scoped(CustomerProfileChange::query())->where('status', CustomerProfileChange::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    private static function scoped(Builder $query): Builder
    {
        $branchId = CurrentBranch::id();

        return $query->whereHas('customer', fn (Builder $q) => $q->when($branchId, fn ($b) => $b->where('branch_id', $branchId)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::scoped(CustomerProfileChange::query()->with(['customer', 'user', 'reviewer'])))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.company_name')->label('Customer')->searchable()
                    ->description(fn (CustomerProfileChange $r) => $r->customer?->brn),
                Tables\Columns\TextColumn::make('user.name')->label('Changed by')->description(fn (CustomerProfileChange $r) => $r->user?->email),
                Tables\Columns\TextColumn::make('section')->label('Section')
                    ->formatStateUsing(fn (CustomerProfileChange $r) => $r->sectionLabel().' · '.$r->action),
                Tables\Columns\TextColumn::make('changes')->label('Change')
                    ->getStateUsing(fn (CustomerProfileChange $r) => $r->summary())
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (CustomerProfileChange $r) => $r->statusLabel())
                    ->color(fn (string $state) => match ($state) {
                        CustomerProfileChange::STATUS_PENDING => 'warning',
                        CustomerProfileChange::STATUS_APPROVED => 'success',
                        CustomerProfileChange::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reviewer.name')->label('Reviewed by')
                    ->description(fn (CustomerProfileChange $r) => $r->review_remarks)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        CustomerProfileChange::STATUS_PENDING => 'Pending verification',
                        CustomerProfileChange::STATUS_APPROVED => 'Approved',
                        CustomerProfileChange::STATUS_REJECTED => 'Rejected',
                        CustomerProfileChange::STATUS_APPLIED => 'Applied (no verification needed)',
                    ])
                    ->default(CustomerProfileChange::STATUS_PENDING),
                Tables\Filters\SelectFilter::make('section')->options(CustomerProfileChange::SECTIONS),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CustomerProfileChange $r) => $r->status === CustomerProfileChange::STATUS_PENDING)
                    ->modalDescription(fn (CustomerProfileChange $r) => 'Apply to '.$r->customer?->company_name.': '.$r->summary())
                    ->form([Forms\Components\Textarea::make('remarks')->label('Remarks (optional)')->rows(2)])
                    ->action(fn (CustomerProfileChange $record, array $data) => $this->review($record, true, $data['remarks'] ?? null)),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (CustomerProfileChange $r) => $r->status === CustomerProfileChange::STATUS_PENDING)
                    ->form([Forms\Components\Textarea::make('remarks')->label('Reason (sent to the customer)')->required()->rows(2)])
                    ->action(fn (CustomerProfileChange $record, array $data) => $this->review($record, false, $data['remarks'])),
            ]);
    }

    private function review(CustomerProfileChange $change, bool $approve, ?string $remarks): void
    {
        $action = app(ReviewCustomerProfileChange::class);

        try {
            $approve ? $action->approve($change, auth()->user(), $remarks) : $action->reject($change, auth()->user(), (string) $remarks);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($approve ? 'Change applied' : 'Change rejected')->body($change->customer?->company_name)->success()->send();
    }
}

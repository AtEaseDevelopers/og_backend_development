<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Support\CurrentBranch;
use App\Support\DailyLorryScheduleData;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Daily lorry schedule: every lorry trip for a date with origin and destinations,
 * grouped by branch. Open to every admin-panel user; printable as PDF.
 */
class DailyLorrySchedule extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'Dispatch';

    protected static ?string $navigationLabel = 'Daily Lorry Schedule';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'daily-lorry-schedule';

    protected static string $view = 'filament.pages.daily-lorry-schedule';

    #[Url(as: 'date')]
    public string $date = '';

    /** 'all' or a branch id */
    #[Url(as: 'branch')]
    public string $branch = '';

    public function mount(): void
    {
        if ($this->date === '' || ! strtotime($this->date)) {
            $this->date = now()->toDateString();
        }

        if ($this->branch === '') {
            $this->branch = (string) (CurrentBranch::id() ?? 'all');
        }
    }

    public function getTitle(): string
    {
        return 'Daily Lorry Schedule';
    }

    public function getSubheading(): ?string
    {
        return 'Where every lorry starts and goes on the selected day, by branch.';
    }

    /** @return array<string, string> */
    public function branchOptions(): array
    {
        return ['all' => 'All branches'] + Branch::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Branch $b) => [(string) $b->id => $b->code.' — '.$b->name])
            ->all();
    }

    /** The schedule is always for one day: clearing the date picker goes back to today. */
    public function updatedDate(): void
    {
        if ($this->date === '' || ! strtotime($this->date)) {
            $this->date = now()->toDateString();
        }
    }

    public function shiftDate(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->date = now()->toDateString();
    }

    /** @return array<string, mixed> */
    public function getSchedule(): array
    {
        return app(DailyLorryScheduleData::class)->for(
            $this->date,
            $this->branch === 'all' ? null : (int) $this->branch,
        );
    }

    public function pdfUrl(): string
    {
        return route('filament.admin.daily-lorry-schedule.pdf', [
            'tenant' => Filament::getTenant(),
            'date' => $this->date,
            'branch' => $this->branch,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print / PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => $this->pdfUrl())
                ->openUrlInNewTab(),
        ];
    }
}

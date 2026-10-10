<?php

namespace App\Filament\Pages;

use App\Domains\Dispatch\Models\JobSheet;
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

    /** First day (Y-m-d) of the 7-day date strip (same strip as Orders / CSN management). */
    public string $stripStart = '';

    public function mount(): void
    {
        if ($this->date === '' || ! strtotime($this->date)) {
            $this->date = now()->toDateString();
        }

        if ($this->branch === '') {
            $this->branch = (string) (CurrentBranch::id() ?? 'all');
        }

        $this->centreStrip($this->date);
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

    /** The schedule is always for one day: clearing the date picker goes back to today. A day picked off the strip brings it into view. */
    public function updatedDate(): void
    {
        if ($this->date === '' || ! strtotime($this->date)) {
            $this->date = now()->toDateString();
        }

        if (! $this->stripShows($this->date)) {
            $this->centreStrip($this->date);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Date strip: 7 days with their trip counts (branch filter applies)
    |--------------------------------------------------------------------------
    */

    /** @return list<array{date: string, label: string, count: int, selected: bool, today: bool}> */
    public function stripDays(): array
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);

        $counts = JobSheet::query()
            ->whereBetween('operating_date', [$start->toDateString(), $end->toDateString()])
            ->when($this->branch !== 'all' && filled($this->branch), fn ($q) => $q->where('operating_branch_id', (int) $this->branch))
            ->toBase()
            ->selectRaw('DATE(operating_date) as day, COUNT(*) as total')
            ->groupByRaw('DATE(operating_date)')
            ->pluck('total', 'day');

        $today = today()->toDateString();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $counts, $today): array {
                $date = $start->copy()->addDays($offset);
                $day = $date->toDateString();

                return [
                    'date' => $day,
                    'label' => $date->format('D j M'),
                    'count' => (int) ($counts[$day] ?? 0),
                    'selected' => $day === $this->date,
                    'today' => $day === $today,
                ];
            })
            ->all();
    }

    public function selectStripDay(string $day): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && strtotime($day)) {
            $this->date = $day;
        }
    }

    public function shiftStrip(int $days): void
    {
        $this->stripStart = $this->stripStartDate()->addDays(max(-31, min(31, $days)))->toDateString();
    }

    /** Today's schedule, with today in the middle of the strip. */
    public function stripToday(): void
    {
        $this->date = now()->toDateString();
        $this->centreStrip($this->date);
    }

    /** e.g. "7 – 13 Oct 2026", "28 Sep – 4 Oct 2026". */
    public function stripRangeLabel(): string
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);

        return match (true) {
            $start->month === $end->month => $start->format('j').' – '.$end->format('j M Y'),
            $start->year === $end->year => $start->format('j M').' – '.$end->format('j M Y'),
            default => $start->format('j M Y').' – '.$end->format('j M Y'),
        };
    }

    /** "Today" link: shown unless today is the selected day and in view. */
    public function stripShowsToday(): bool
    {
        return $this->date === today()->toDateString() && $this->stripShows($this->date);
    }

    protected function stripShows(string $day): bool
    {
        $start = $this->stripStartDate();

        return $day >= $start->toDateString() && $day <= $start->copy()->addDays(6)->toDateString();
    }

    protected function centreStrip(?string $day = null): void
    {
        $anchor = $day && strtotime($day) ? Carbon::parse($day)->startOfDay() : today();

        $this->stripStart = $anchor->subDays(3)->toDateString();
    }

    protected function stripStartDate(): Carbon
    {
        return $this->stripStart !== '' && strtotime($this->stripStart)
            ? Carbon::parse($this->stripStart)->startOfDay()
            : Carbon::parse($this->date ?: today()->toDateString())->startOfDay()->subDays(3);
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

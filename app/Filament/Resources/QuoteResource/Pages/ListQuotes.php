<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Lead;
use App\Domains\Quotation\Models\Quote;
use App\Filament\Pages\CreateQuotation;
use App\Filament\Resources\QuoteResource;
use App\Support\CurrentCompany;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * The quotation list with the filter card and day strip of the CSN list (user, 20 Oct 2026): search, quotation
 * date, customer / lead and status; a day of the strip shows only that day's quotations.
 */
class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected static string $view = 'filament.resources.quote-resource.pages.list-quotes';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'until', except: '')]
    public string $dateTo = '';

    /** '' = all, "customer:12" or "lead:3" */
    #[Url(as: 'party', except: '')]
    public string $party = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    /** First day of the 7-day strip (Y-m-d); blank = today in the middle. */
    public string $stripStart = '';

    public function getTitle(): string
    {
        return 'Quotation';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')->label('+ Create quotation')->url(CreateQuotation::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn (Builder $query) => $this->applyFilterBar($query));
    }

    protected function applyFilterBar(Builder $query, bool $withDate = true): Builder
    {
        $term = trim($this->search);
        [$type, $id] = array_pad(explode(':', $this->party, 2), 2, '');

        return $query
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('number', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn (Builder $c) => $c->where('company_name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%'))
                ->orWhereHas('lead', fn (Builder $l) => $l->where('name', 'like', '%'.$term.'%'))))
            ->when($withDate ? $this->date($this->dateFrom) : null, fn (Builder $q, $day) => $q->whereDate('quote_date', '>=', $day))
            ->when($withDate ? $this->date($this->dateTo) : null, fn (Builder $q, $day) => $q->whereDate('quote_date', '<=', $day))
            ->when($type === 'customer' && $id !== '', fn (Builder $q) => $q->where('customer_id', (int) $id))
            ->when($type === 'lead' && $id !== '', fn (Builder $q) => $q->where('lead_id', (int) $id))
            ->when(array_key_exists($this->status, Quote::STATUSES), fn (Builder $q) => $q->where('status', $this->status));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'dateFrom', 'dateTo', 'party', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = $this->dateFrom = $this->dateTo = $this->party = $this->status = '';
        $this->resetPage();
    }

    /** @return array<string, string> customers and leads that have quotations */
    public function partyOptions(): array
    {
        $base = QuoteResource::getEloquentQuery();
        $customers = Customer::query()->whereIn('id', (clone $base)->whereNotNull('customer_id')->select('customer_id'))->orderBy('company_name')->pluck('company_name', 'id')
            ->mapWithKeys(fn ($name, $id) => ['customer:'.$id => $name]);
        $leads = Lead::query()->whereIn('id', (clone $base)->whereNotNull('lead_id')->select('lead_id'))->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => ['lead:'.$id => $name.' (lead)']);

        return ['' => 'All'] + $customers->all() + $leads->all();
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return ['' => 'All'] + Quote::STATUSES;
    }

    /*
    |--------------------------------------------------------------------------
    | Quotation date strip (as the CSN list's)
    |--------------------------------------------------------------------------
    */

    /** @return list<array{date: string, label: string, count: int, selected: bool, today: bool}> */
    public function stripDays(): array
    {
        $start = $this->stripStartDate();
        $end = $start->copy()->addDays(6);
        $counts = $this->applyFilterBar(QuoteResource::getEloquentQuery(), withDate: false)
            ->whereBetween('quote_date', [$start->toDateString(), $end->toDateString()])
            ->toBase()
            ->selectRaw('DATE(quote_date) as day, COUNT(*) as total')
            ->groupByRaw('DATE(quote_date)')
            ->pluck('total', 'day');
        $from = $this->date($this->dateFrom);
        $to = $this->date($this->dateTo);
        $today = today()->toDateString();

        return collect(range(0, 6))->map(function (int $offset) use ($start, $counts, $from, $to, $today): array {
            $day = $start->copy()->addDays($offset);
            $date = $day->toDateString();

            return [
                'date' => $date,
                'label' => $day->format('D j M'),
                'count' => (int) ($counts[$date] ?? 0),
                'selected' => ($from !== null || $to !== null) && ($from === null || $date >= $from) && ($to === null || $date <= $to),
                'today' => $date === $today,
            ];
        })->all();
    }

    /** A day shows only that day; the selected day again clears the date. */
    public function selectStripDay(string $day): void
    {
        if (($day = $this->date($day)) === null) {
            return;
        }

        $selected = $this->dateFrom === $day && $this->dateTo === $day;
        $this->dateFrom = $this->dateTo = $selected ? '' : $day;
        $this->resetPage();
    }

    public function shiftStrip(int $days): void
    {
        $this->stripStart = $this->stripStartDate()->addDays(max(-31, min(31, $days)))->toDateString();
    }

    public function stripToday(): void
    {
        $this->stripStart = today()->subDays(3)->toDateString();
    }

    public function clearDate(): void
    {
        $this->dateFrom = $this->dateTo = '';
        $this->resetPage();
    }

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

    public function stripShowsToday(): bool
    {
        $today = today()->toDateString();
        $start = $this->stripStartDate();

        return $today >= $start->toDateString() && $today <= $start->copy()->addDays(6)->toDateString();
    }

    protected function stripStartDate(): Carbon
    {
        $start = $this->date($this->stripStart);

        return $start !== null ? Carbon::createFromFormat('!Y-m-d', $start) : today()->subDays(3);
    }

    protected function date(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }
}

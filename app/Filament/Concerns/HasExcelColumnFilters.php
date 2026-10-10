<?php

namespace App\Filament\Concerns;

use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Excel-style column filters for a Filament table: public/js/og/excel-filter.js puts a funnel on every
 * column header (tagged data-og-col in AppServiceProvider) of a page using this trait. A funnel opens
 * Sort A→Z / Z→A plus a checklist of the values that column shows (what the cell displays: badges,
 * related fields, formatted money), or a date range for a date column.
 *
 * Values are read from the rows left by every other filter, then the selection narrows the table query
 * to the matching records. A page that uses InteractsWithTable directly (not a ListRecords page) aliases
 * InteractsWithTable::filterTableQuery as filamentFilterTableQuery.
 */
trait HasExcelColumnFilters
{
    /** Tells excel-filter.js this table has column filters. */
    public bool $ogExcelEnabled = true;

    /** column => ['values' => list<string>] or ['from' => Y-m-d|null, 'to' => Y-m-d|null] */
    #[Url(as: 'xf', except: [])]
    public array $ogExcelFilters = [];

    /** Records read per filter evaluation (keeps big tables responsive). */
    protected int $ogExcelLimit = 5000;

    protected ?string $ogExcelSkip = null;

    protected bool $ogExcelBusy = false;

    /** Matching record keys per filter set, for this request (Filament builds the query several times per render). */
    protected array $ogExcelKeyCache = [];

    public const OG_EXCEL_BLANK = '(Blank)';

    public function filterTableQuery(Builder $query): Builder
    {
        $query = method_exists($this, 'filamentFilterTableQuery')
            ? $this->filamentFilterTableQuery($query)
            : parent::filterTableQuery($query);

        return $this->applyOgExcelFilters($query);
    }

    /** The checklist (or date range) for one column, from the rows every other filter leaves. */
    #[Renderless] // only reads: no re-render (it would replace the funnel the menu is anchored to)
    public function ogExcelOptions(string $name): array
    {
        $column = $this->ogExcelColumn($name);

        if (! $column) {
            return ['type' => 'none'];
        }

        $base = [
            'label' => strip_tags((string) $column->getLabel()),
            'sortable' => $column->isSortable(),
        ];

        if ($this->ogExcelIsDate($column)) {
            return $base + ['type' => 'date', 'from' => $this->ogExcelFilters[$name]['from'] ?? null, 'to' => $this->ogExcelFilters[$name]['to'] ?? null];
        }

        $this->ogExcelSkip = $name;

        try {
            $counts = [];

            foreach ($this->ogExcelRecords($this->getFilteredTableQuery(), $column) as $record) {
                foreach ($this->ogExcelCellValues($column, $record) as $value) {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
        } finally {
            $this->ogExcelSkip = null;
        }

        uksort($counts, fn ($a, $b) => $a === self::OG_EXCEL_BLANK ? 1 : ($b === self::OG_EXCEL_BLANK ? -1 : strnatcasecmp((string) $a, (string) $b)));

        return $base + [
            'type' => 'list',
            'values' => collect($counts)->map(fn (int $count, $value) => ['value' => (string) $value, 'count' => $count])->values()->all(),
            'selected' => $this->ogExcelFilters[$name]['values'] ?? null,
        ];
    }

    /**
     * The column toggle in the header row (excel-filter.js): every column that can be shown / hidden, with its
     * state. Toggling sets Filament's own toggledTableColumns, so the choice is remembered the Filament way.
     *
     * @return list<array{name: string, label: string, visible: bool}>
     */
    #[Renderless]
    public function ogToggleableColumns(): array
    {
        return collect($this->getTable()->getColumns())
            ->filter(fn (Column $column) => $column->isToggleable())
            ->map(fn (Column $column) => [
                'name' => $column->getName(),
                'label' => strip_tags((string) $column->getLabel()) ?: $column->getName(),
                'visible' => ! $this->isTableColumnToggledHidden($column->getName()),
            ])
            ->values()
            ->all();
    }

    /**
     * OK / Clear in a column's filter. $payload: null clears it; ['values' => [...]] keeps those values;
     * ['from' => .., 'to' => ..] filters a date column.
     */
    public function ogExcelApply(string $name, ?array $payload): void
    {
        if (! $this->ogExcelColumn($name)) {
            return;
        }

        if ($payload === null) {
            unset($this->ogExcelFilters[$name]);
        } elseif (array_key_exists('values', $payload) && is_array($payload['values'])) {
            $this->ogExcelFilters[$name] = ['values' => array_values(array_unique(array_map('strval', $payload['values'])))];
        } else {
            $from = $this->ogExcelDate($payload['from'] ?? null);
            $to = $this->ogExcelDate($payload['to'] ?? null);

            if ($from === null && $to === null) {
                unset($this->ogExcelFilters[$name]);
            } else {
                $this->ogExcelFilters[$name] = ['from' => $from, 'to' => $to];
            }
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function applyOgExcelFilters(Builder $query): Builder
    {
        if ($this->ogExcelBusy) {
            return $query;
        }

        $active = collect($this->ogExcelFilters)
            ->reject(fn ($filter, $name) => $name === $this->ogExcelSkip || ! is_array($filter) || ! $this->ogExcelColumn((string) $name))
            ->all();

        if ($active === []) {
            return $query;
        }

        // same base query (filters, search, page filter bars) and same column filters: same keys
        $cacheKey = md5(serialize([$active, $query->toSql(), $query->getBindings()]));

        if (array_key_exists($cacheKey, $this->ogExcelKeyCache)) {
            $keys = $this->ogExcelKeyCache[$cacheKey];

            return $keys === [] ? $query->whereRaw('1 = 0') : $query->whereKey($keys);
        }

        // the records left by the Filament filters and search, narrowed column by column in PHP
        $this->ogExcelBusy = true;

        try {
            $records = $this->ogExcelRecords($this->getFilteredTableQuery(), ...array_map(fn ($name) => $this->ogExcelColumn((string) $name), array_keys($active)));
        } finally {
            $this->ogExcelBusy = false;
        }

        $keys = [];

        foreach ($records as $record) {
            foreach ($active as $name => $filter) {
                if (! $this->ogExcelMatches($this->ogExcelColumn((string) $name), $record, $filter)) {
                    continue 2;
                }
            }

            $keys[] = $record->getKey();
        }

        $this->ogExcelKeyCache[$cacheKey] = $keys;

        return $keys === [] ? $query->whereRaw('1 = 0') : $query->whereKey($keys);
    }

    protected function ogExcelMatches(Column $column, Model $record, array $filter): bool
    {
        if (array_key_exists('values', $filter)) {
            return array_intersect($this->ogExcelCellValues($column, $record), $filter['values']) !== [];
        }

        $state = $this->ogExcelState($column, $record);
        $date = $state instanceof CarbonInterface ? $state : (filled($state) ? rescue(fn () => Carbon::parse((string) $state), null, false) : null);

        if (! $date) {
            return false;
        }

        $day = $date->toDateString();

        return (! ($filter['from'] ?? null) || $day >= $filter['from']) && (! ($filter['to'] ?? null) || $day <= $filter['to']);
    }

    /** What a cell shows, as checklist values (a multi-value cell gives several). @return list<string> */
    protected function ogExcelCellValues(Column $column, Model $record): array
    {
        $state = $this->ogExcelState($column, $record);
        $states = is_iterable($state) && ! is_string($state) ? collect($state)->all() : [$state];
        $values = [];

        foreach ($states as $value) {
            $values[] = $this->ogExcelText($column, $value);
        }

        return $values === [] ? [self::OG_EXCEL_BLANK] : array_values(array_unique($values));
    }

    protected function ogExcelText(Column $column, mixed $value): string
    {
        if ($column instanceof IconColumn && is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof HasLabel) {
            $text = $value->getLabel();
        } elseif ($value instanceof BackedEnum) {
            $text = (string) $value->value;
        } elseif ($value instanceof CarbonInterface) {
            $text = $value->format('d/m/Y');
        } elseif ($value === null || $value === '') {
            $text = null;
        } else {
            try {
                $text = $column instanceof TextColumn ? $column->formatState($value) : $value;
            } catch (Throwable) {
                $text = $value;
            }

            if ($text instanceof HasLabel) {
                $text = $text->getLabel();
            } elseif ($text instanceof Htmlable) {
                $text = strip_tags($text->toHtml());
            } elseif (is_bool($text)) {
                $text = $text ? 'Yes' : 'No';
            }
        }

        $text = trim(html_entity_decode(strip_tags((string) $text)));

        return $text === '' || $text === '—' ? self::OG_EXCEL_BLANK : $text;
    }

    protected function ogExcelState(Column $column, Model $record): mixed
    {
        try {
            return $column->record($record)->getState();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return iterable<Model> */
    protected function ogExcelRecords(Builder $query, ?Column ...$columns): iterable
    {
        // related fields shown in these columns ("customer.company_name") are loaded with the rows
        $relations = collect($columns)->filter()
            ->map(fn (Column $column) => Str::contains($column->getName(), '.') ? Str::beforeLast($column->getName(), '.') : null)
            ->filter(fn ($relation) => $relation && method_exists($query->getModel(), Str::before($relation, '.')))
            ->unique()
            ->all();

        return (clone $query)->with($relations)->limit($this->ogExcelLimit)->get();
    }

    protected function ogExcelColumn(string $name): ?Column
    {
        $column = $this->getTable()->getColumn($name);

        return $column instanceof TextColumn || $column instanceof IconColumn ? $column : null;
    }

    protected function ogExcelIsDate(Column $column): bool
    {
        return $column instanceof TextColumn && ($column->isDate() || $column->isDateTime());
    }

    protected function ogExcelDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}

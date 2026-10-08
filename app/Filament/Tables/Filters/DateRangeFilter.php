<?php

namespace App\Filament\Tables\Filters;

use App\Filament\Forms\Components\DateRangePicker;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Table filter for a date column with the shared range picker (one field instead of "From" + "Until").
 *
 * Usage: DateRangeFilter::make('issued_at')->label('CSN date')
 * Filters whereDate(column) >= from and <= until (either end may be empty) and shows an indicator such as
 * "CSN date: 1 Oct 2026 – 7 Oct 2026". The column defaults to the filter name; ->column('orders.created_at') overrides it.
 * Filter state: ['range' => ['from' => 'Y-m-d' | null, 'until' => 'Y-m-d' | null]].
 */
class DateRangeFilter extends BaseFilter
{
    protected string | Closure | null $column = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query(function (Builder $query, array $data): void {
            ['from' => $from, 'until' => $until] = DateRangePicker::normalize($data['range'] ?? null);
            $column = $this->getColumn();

            $query
                ->when($from, fn (Builder $query) => $query->whereDate($column, '>=', $from))
                ->when($until, fn (Builder $query) => $query->whereDate($column, '<=', $until));
        });

        $this->indicateUsing(function (array $data): array {
            $range = DateRangePicker::describe($data['range'] ?? null);

            return $range === null ? [] : ['range' => $this->getLabel().': '.$range];
        });
    }

    public function column(string | Closure | null $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->evaluate($this->column) ?? $this->getName();
    }

    /** Passes ->default(['from' => 'Y-m-d', 'until' => 'Y-m-d']) on to the field, as Filament's own filters do. */
    public function getFormField(): Field
    {
        $field = DateRangePicker::make('range')->label($this->getLabel());

        // a bare ->default() stores true, which normalizes to an empty range
        if (filled($defaultState = $this->getDefaultState())) {
            $field->default(DateRangePicker::normalize($defaultState));
        }

        return $field;
    }
}

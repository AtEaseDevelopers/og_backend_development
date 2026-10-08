<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Illuminate\Support\Carbon;

/**
 * One field for a date range in a Filament form (page forms, table filters), drawn with the shared
 * picker <x-og.date-range> (public/js/og/date-range-picker.js): click it, pick the start then the end day.
 *
 * State: ['from' => 'Y-m-d' | null, 'until' => 'Y-m-d' | null].
 * Usage: DateRangePicker::make('period')->label('Date')->live()
 * Live forms (->live(), non-deferred table filters) send each pick straight away; deferred forms send it with the next request.
 */
class DateRangePicker extends Field
{
    use HasPlaceholder;

    protected string $view = 'filament.forms.components.date-range-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['from' => null, 'until' => null]);

        $this->afterStateHydrated(static function (DateRangePicker $component, mixed $state): void {
            $component->state(static::normalize($state));
        });

        $this->dehydrateStateUsing(static fn (mixed $state): array => static::normalize($state));
    }

    /**
     * Both ends as Y-m-d (or null), swapped when the start is after the end.
     *
     * @return array{from: ?string, until: ?string}
     */
    public static function normalize(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $date = static fn (mixed $value): ?string => filled($value) && is_string($value)
            ? rescue(fn () => Carbon::parse($value)->toDateString(), null, false)
            : null;

        $from = $date($state['from'] ?? null);
        $until = $date($state['until'] ?? null);

        if ($from !== null && $until !== null && $from > $until) {
            [$from, $until] = [$until, $from];
        }

        return ['from' => $from, 'until' => $until];
    }

    /** The range as the picker shows it ("1 Oct 2026 – 7 Oct 2026", "From 1 Oct 2026", …), null when empty. */
    public static function describe(mixed $state): ?string
    {
        ['from' => $from, 'until' => $until] = static::normalize($state);
        $format = static fn (string $date): string => Carbon::parse($date)->format('j M Y');

        return match (true) {
            $from !== null && $from === $until => $format($from),
            $from !== null && $until !== null => $format($from).' – '.$format($until),
            $from !== null => 'From '.$format($from),
            $until !== null => 'Until '.$format($until),
            default => null,
        };
    }
}

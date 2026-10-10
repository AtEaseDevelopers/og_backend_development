<?php

namespace App\Providers;

use App\Http\Controllers\Admin\RedirectToSelectBranchController;
use App\Http\Responses\Auth\LoginResponse;
use Filament\Http\Controllers\RedirectToTenantController;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(RedirectToTenantController::class, RedirectToSelectBranchController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * UI convention: positive actions (Create / Save / Submit / Confirm) sit on the RIGHT,
         * Cancel / Close to their left. With end alignment Filament renders the action row
         * reversed, so [Save, Cancel] shows as "Cancel  Save" at the right edge.
         */
        \Filament\Pages\BasePage::alignFormActionsEnd();

        \Filament\Actions\MountableAction::configureUsing(
            fn (\Filament\Actions\MountableAction $action) => $action->modalFooterActionsAlignment(\Filament\Support\Enums\Alignment::End),
        );

        /*
         * UI convention: every dropdown is a select2-style picker (type to filter, then pick).
         * searchable() swaps the native <select> for Filament's Choices.js select. These defaults
         * run before the field's own setUp() and chained calls, so a field can still opt out with
         * ->searchable(false) or narrow the search with ->searchable(['col', ...]). preload() only
         * affects relationship selects (list the first options on open instead of an empty "start
         * typing" box); static ->options() lists are filtered in the browser, and their options
         * limit is raised so the whole list can still be scrolled (Choices.js only renders the
         * first optionsLimit entries; for relationships it stays a DB LIMIT of 50). SelectFilter
         * builds its own Select and passes its own searchable/preload flags into it, so filters
         * (incl. TernaryFilter) need a separate default.
         */
        \Filament\Forms\Components\Select::configureUsing(
            fn (\Filament\Forms\Components\Select $select) => $select
                ->searchable()
                ->preload()
                ->optionsLimit(fn (\Filament\Forms\Components\Select $component): int => $component->hasRelationship() ? 50 : 500),
        );

        \Filament\Tables\Filters\SelectFilter::configureUsing(
            fn (\Filament\Tables\Filters\SelectFilter $filter) => $filter->searchable()->preload(),
        );

        /*
         * Excel-style column filters: tag each text / icon column header with its column name.
         * public/js/og/excel-filter.js adds the funnel on pages using HasExcelColumnFilters.
         */
        foreach ([\Filament\Tables\Columns\TextColumn::class, \Filament\Tables\Columns\IconColumn::class] as $columnClass) {
            $columnClass::configureUsing(
                // every column can be shown / hidden with the column toggle (a resource may still pass toggleable(...) itself)
                fn (\Filament\Tables\Columns\Column $column) => $column
                    ->extraHeaderAttributes(fn (): array => ['data-og-col' => $column->getName()], merge: true)
                    ->toggleable(),
            );
        }
    }
}

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
        //
    }
}

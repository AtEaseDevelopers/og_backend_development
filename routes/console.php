<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('og:flag-incomplete-deliveries')
    ->dailyAt('16:05')
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

Schedule::command('og:flag-missing-csns')
    ->dailyAt('06:30')
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

Schedule::command('og:flag-vehicle-maintenance-due')
    ->dailyAt('07:00')
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

Schedule::command('og:submit-pending-einvoices')
    ->hourly()
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

// Section D: unanswered quotations → Pending Customer Review → Closed Case (configurable days)
Schedule::command('og:close-pending-reviews')
    ->dailyAt('01:00')
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

// API log (api_logs): on the last day of each month, clear everything older than this month
Artisan::command('og:prune-api-logs', function () {
    $deleted = \App\Models\ApiLog::query()->where('created_at', '<', now()->startOfMonth())->delete();
    $this->info("Deleted {$deleted} API log rows from before ".now()->startOfMonth()->toDateString().'.');
})->purpose('Delete API log rows older than the current month');

Schedule::command('og:prune-api-logs')
    ->lastDayOfMonth('23:30')
    ->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'));

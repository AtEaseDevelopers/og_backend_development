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

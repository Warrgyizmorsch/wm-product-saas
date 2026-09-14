<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The ECB publishes reference rates around 16:00 CET on working days.
// Requires the server cron: * * * * * cd <app> && php artisan schedule:run
Schedule::command('accounting:sync-exchange-rates')
    ->weekdays()
    ->dailyAt('17:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanad:send-session-reminders')->everyFifteenMinutes();
Schedule::command('sanad:process-no-response-transfers')->everyFifteenMinutes();
Schedule::command('sanad:process-long-case-transfers')->daily();
Schedule::command('sanad:send-org-periodic-reports')->weeklyOn(1, '8:00');

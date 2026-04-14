<?php

use App\Jobs\AutoCloseTicketsJob;
use App\Jobs\CheckSlaBreachesJob;
use Illuminate\Support\Facades\Schedule;

// SLA breach detection — every 5 minutes during business hours
Schedule::job(new CheckSlaBreachesJob)
    ->everyFiveMinutes()
    ->weekdays()
    ->between('07:55', '18:05')
    ->withoutOverlapping();

// Auto-close resolved tickets after 7 days — daily at 6am
Schedule::job(new AutoCloseTicketsJob)
    ->dailyAt('06:00')
    ->withoutOverlapping();

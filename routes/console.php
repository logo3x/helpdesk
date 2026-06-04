<?php

use App\Jobs\AutoCloseTicketsJob;
use App\Jobs\AutoMarkSurveysPositiveJob;
use App\Jobs\CheckSlaBreachesJob;
use Illuminate\Support\Facades\Schedule;

// SLA breach detection — every 5 minutes during business hours
Schedule::job(new CheckSlaBreachesJob)
    ->everyFiveMinutes()
    ->weekdays()
    ->between('07:55', '18:05')
    ->withoutOverlapping();

// Auto-close resolved tickets — daily at 6am
Schedule::job(new AutoCloseTicketsJob)
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Auto-marca encuestas como 5★ si el cliente no respondió — diario 6:30am
Schedule::job(new AutoMarkSurveysPositiveJob)
    ->dailyAt('06:30')
    ->withoutOverlapping();

// Sync Kactus → users — cada hora durante horario laboral, solo si está habilitado.
Schedule::command('kactus:sync')
    ->hourly()
    ->weekdays()
    ->between('06:00', '20:00')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('kactus.enabled'));

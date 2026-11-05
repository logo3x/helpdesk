<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Programación de respaldos en la madrugada (America/Bogota).
 */
function backupScheduleEvent(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_ends_with(str_replace(["'", '"'], '', (string) $event->command), 'artisan '.$command));
}

it('programa los respaldos, la limpieza y el monitor a las horas indicadas', function (string $command, string $expression) {
    $event = backupScheduleEvent($command);

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression)
        ->and($event->withoutOverlapping)->toBeTrue();
})->with([
    'BD diaria 01:30' => ['backups:database', '30 1 * * *'],
    'completo domingo 02:30' => ['backups:full', '30 2 * * 0'],
    'limpieza BD 04:00' => ['backup:clean', '0 4 * * *'],
    'limpieza completo 04:15' => ['backup:clean --config=backup.full', '15 4 * * *'],
    'monitor 05:00' => ['backup:monitor', '0 5 * * *'],
]);

it('los respaldos no coinciden con otras tareas programadas', function () {
    $backupMinutes = collect(['30 1', '30 2'])->all();

    $others = collect(app(Schedule::class)->events())
        ->reject(fn (Event $event): bool => str_contains((string) $event->command, 'backup'))
        ->filter(fn (Event $event): bool => in_array(implode(' ', array_slice(explode(' ', $event->expression), 0, 2)), $backupMinutes, true));

    expect($others)->toBeEmpty();
});

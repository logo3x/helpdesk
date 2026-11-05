<?php

use App\Jobs\AutoCloseTicketsJob;
use App\Jobs\AutoMarkMaintenanceSurveysPositiveJob;
use App\Jobs\AutoMarkSurveysPositiveJob;
use App\Jobs\AwaitingCustomerFollowUpJob;
use App\Jobs\CheckSlaBreachesJob;
use App\Jobs\GenerateKbSuggestionsJob;
use App\Jobs\MaintenanceAlertJob;
use App\Jobs\NotifyDueMaintenancesJob;
use App\Services\KbSuggestionService;
use App\Services\LlmService;
use Illuminate\Support\Facades\Artisan;
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

// Auto-marca encuestas de tickets como 5★ si el cliente no respondió — diario 6:30am
Schedule::job(new AutoMarkSurveysPositiveJob)
    ->dailyAt('06:30')
    ->withoutOverlapping();

// Auto-marca encuestas de mantenimiento como 5★ tras 1 día sin respuesta — diario 6:45am
Schedule::job(new AutoMarkMaintenanceSurveysPositiveJob)
    ->dailyAt('06:45')
    ->withoutOverlapping();

// Alertas de mantenimiento de activos — lunes 7am.
// Campanita (7 días) + correo semanal (14 días).
Schedule::job(new MaintenanceAlertJob)
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping();

// Módulo Mantenimientos Programados — alerta a los agentes cuando
// falten MAINTENANCE_ALERT_DAYS_BEFORE (default 30) para la fecha
// programada. Diario a las 7:15am, marca notified_due_at para no
// re-notificar la misma ocurrencia.
Schedule::job(new NotifyDueMaintenancesJob)
    ->dailyAt('07:15')
    ->withoutOverlapping();

// Sync Kactus → users — cada hora durante horario laboral, solo si está habilitado.
Schedule::command('kactus:sync')
    ->hourly()
    ->weekdays()
    ->between('06:00', '20:00')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('kactus.enabled'));

// Sugerencias de artículos KB a partir de consultas del chatbot sin
// respuesta. También se puede correr a mano: php artisan kb:suggest
Schedule::job(new GenerateKbSuggestionsJob)
    ->hourlyAt(20)
    ->weekdays()
    ->between('07:00', '19:00')
    ->withoutOverlapping();

Artisan::command('kb:suggest', function (KbSuggestionService $service) {
    $created = $service->run();

    $this->info($created->isEmpty()
        ? 'No hay temas nuevos que ameriten un artículo.'
        : 'Borradores sugeridos: '.$created->pluck('title')->implode(' · '));
})->purpose('Analiza consultas del chatbot sin respuesta y propone artículos KB');

// Vigilante de la IA: si el modelo activo deja de responder, busca uno
// que funcione y lo activa (y vuelve al del .env cuando se recupera).
Artisan::command('llm:watchdog', function (LlmService $llm) {
    $result = $llm->autoHeal();

    $this->info(match ($result['status']) {
        'ok' => "La IA responde con {$result['model']}.",
        'restored' => "Se volvió al modelo preferido {$result['model']} (antes {$result['previous']}).",
        'switched' => "Cambio automático: {$result['previous']} → {$result['model']}.",
        default => 'Ningún modelo respondió. Probados: '.implode(', ', $result['tried'] ?? []),
    });
})->purpose('Comprueba el modelo de IA y cambia a uno que funcione si se cayó');

Schedule::command('llm:watchdog')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn () => filled(config('services.llm.api_key')));

// Bitácora: borra registros de más de 1 año (activitylog.clean_after_days)
// para que la tabla no crezca sin límite.
Schedule::command('activitylog:clean --force')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();

// Tickets en "Pendiente cliente": recordatorios y cierre por falta de
// respuesta (config/tickets.php → awaiting_customer). Horas hábiles:
// todo ocurre dentro de 1 día hábil, por eso revisa cada 15 minutos.
Schedule::job(new AwaitingCustomerFollowUpJob)
    ->everyFifteenMinutes()
    ->weekdays()
    ->between('08:00', '18:15')
    ->withoutOverlapping();

// Respaldos
// Franja de madrugada (America/Bogota), sin usuarios conectados y lejos
// de los jobs de las 06:00 en adelante y de activitylog:clean (día 1,
// 03:00). Horas en config/backup.php → helpdesk.schedule (.env BACKUP_*).
//  - BD: diario 01:30. Avisa solo si falla (BACKUP_DAILY_EMAIL=always
//    para confirmar a diario con el zip adjunto).
//  - Completo (archivos + BD): domingo 02:30. Envía a los admins el zip
//    de BD (adjunto si cabe) y el link de descarga del completo.
//  - Limpieza según la retención de cada perfil: 04:00 y 04:15.
//  - Revisión de salud: 05:00 (alerta si el último respaldo es viejo).
// Requiere la tarea de Windows que ejecuta `php artisan schedule:run`
// cada minuto (tools/install-servicios-helpdesk.ps1).
Schedule::command('backups:database')
    ->dailyAt(config('backup.helpdesk.schedule.database_time'))
    ->withoutOverlapping(240);

Schedule::command('backups:full')
    ->weeklyOn(config('backup.helpdesk.schedule.full_day'), config('backup.helpdesk.schedule.full_time'))
    ->withoutOverlapping(240);

Schedule::command('backup:clean')
    ->dailyAt(config('backup.helpdesk.schedule.cleanup_time'))
    ->withoutOverlapping();

Schedule::command('backup:clean --config=backup.full')
    ->dailyAt(config('backup.helpdesk.schedule.full_cleanup_time'))
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->dailyAt(config('backup.helpdesk.schedule.monitor_time'))
    ->withoutOverlapping();

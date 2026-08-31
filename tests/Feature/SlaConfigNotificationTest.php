<?php

use App\Jobs\DispatchSlaChangeNotificationsJob;
use App\Models\SlaConfig;
use App\Models\User;
use App\Notifications\SlaConfigChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['usuario_final', 'agente_soporte'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    Cache::flush(); // asegurar ventana antispam limpia entre tests
});

it('dispatch DispatchSlaChangeNotificationsJob al actualizar un campo watched', function () {
    Queue::fake();
    $sla = SlaConfig::factory()->create(['first_response_minutes' => 30]);

    $sla->update(['first_response_minutes' => 60]);

    Queue::assertPushed(DispatchSlaChangeNotificationsJob::class);
});

it('NO dispatch cuando cambian campos NO watched', function () {
    // Creamos SIN Queue::fake para dejar que el observer del create
    // se ejecute y consuma el lock. Después fake y probamos el update.
    $sla = SlaConfig::factory()->create();
    Cache::flush(); // limpiar el lock del create para poder testear update aisladamente.

    Queue::fake();
    // Update sin tocar campos watched → wasChanged devuelve false.
    $sla->timestamps = false;
    $sla->save(); // no cambia nada

    Queue::assertNotPushed(DispatchSlaChangeNotificationsJob::class);
});

it('antispam por SlaConfig — segundo update en 15 min NO dispatch', function () {
    Queue::fake();
    $sla = SlaConfig::factory()->create(['first_response_minutes' => 30]);

    $sla->update(['first_response_minutes' => 60]);
    $sla->update(['first_response_minutes' => 90]);

    Queue::assertPushed(DispatchSlaChangeNotificationsJob::class, 1);
});

it('el job notifica a todos los usuarios con rol', function () {
    Notification::fake();

    User::factory()->count(3)->create()->each(fn ($u) => $u->assignRole('agente_soporte'));
    User::factory()->create(); // sin rol — NO recibe
    $sla = SlaConfig::factory()->create();

    (new DispatchSlaChangeNotificationsJob($sla->id, ['first_response_minutes']))->handle();

    Notification::assertCount(3);
});

it('el job no duplica notificaciones al mismo user en 30 min', function () {
    Notification::fake();

    User::factory()->create()->assignRole('agente_soporte');
    $sla = SlaConfig::factory()->create();

    (new DispatchSlaChangeNotificationsJob($sla->id, ['first_response_minutes']))->handle();
    (new DispatchSlaChangeNotificationsJob($sla->id, ['resolution_minutes']))->handle();

    Notification::assertSentTimes(SlaConfigChangedNotification::class, 1);
});

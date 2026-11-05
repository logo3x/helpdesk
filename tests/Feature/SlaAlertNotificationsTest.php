<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Support\SlaDefinitions;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaAlertNotification;
use App\Services\SlaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Avisos de las alertas SLA que registra SlaService::checkBreaches().
 *
 * El ticket nace el martes 2026-04-14 a las 08:00 con 100 min de primera
 * respuesta y una resolución muy holgada, así que solo corre el reloj de
 * primera respuesta: 09:10 = 70 %, 09:30 = 90 %, 09:40 = 100 %.
 */
beforeEach(function () {
    foreach (['supervisor_soporte', 'agente_soporte', 'admin', 'super_admin'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Notification::fake();
    Carbon::setTestNow('2026-04-14 08:00:00');

    $this->sla = app(SlaService::class);
    $this->department = Department::factory()->create();

    $this->agent = User::factory()->create(['department_id' => $this->department->id]);
    $this->agent->assignRole('agente_soporte');

    $this->supervisor = User::factory()->create(['department_id' => $this->department->id]);
    $this->supervisor->assignRole('supervisor_soporte');

    $this->otherSupervisor = User::factory()->create(['department_id' => Department::factory()->create()->id]);
    $this->otherSupervisor->assignRole('supervisor_soporte');

    $this->slaConfig = SlaConfig::create([
        'department_id' => $this->department->id,
        'priority' => TicketPriority::Media->value,
        'first_response_minutes' => 100,
        'resolution_minutes' => 100000,
        'is_active' => true,
    ]);

    // Crear el SLA avisa a los supervisores (SlaConfigObserver): se descarta.
    Notification::fake();

    $this->makeTicket = fn (?User $assignee = null, array $attributes = []): Ticket => Ticket::factory()->create([
        'department_id' => $this->department->id,
        'sla_config_id' => $this->slaConfig->id,
        'priority' => TicketPriority::Media,
        'assigned_to_id' => $assignee?->id,
        'first_response_due_at' => now()->addMinutes(100),
        'resolution_due_at' => now()->addYear(),
        'created_at' => now(),
        ...$attributes,
    ]);
});

/**
 * @return Closure(SlaAlertNotification, array<int, string>): bool
 */
function slaAlertOfType(string $type, ?array $expectedChannels = null): Closure
{
    return fn (SlaAlertNotification $notification, array $channels): bool => $notification->log->type === $type
        && ($expectedChannels === null || $channels === $expectedChannels);
}

it('al 70 % avisa solo al agente asignado y solo por la campanita', function () {
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();

    Notification::assertSentTo($this->agent, SlaAlertNotification::class, slaAlertOfType('warning_70_first_response', ['database']));
    Notification::assertNotSentTo($this->supervisor, SlaAlertNotification::class);
    Notification::assertCount(1);
});

it('al 90 % avisa al asignado por campanita y correo', function () {
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();
    Carbon::setTestNow('2026-04-14 09:30:00');
    $this->sla->checkBreaches();

    Notification::assertSentTo($this->agent, SlaAlertNotification::class, slaAlertOfType('warning_90_first_response', ['database', 'mail']));
    Notification::assertNotSentTo($this->supervisor, SlaAlertNotification::class);
    Notification::assertCount(2);
});

it('el incumplimiento avisa al asignado y a los supervisores del departamento, no a los de otro', function () {
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:35:00');
    $this->sla->checkBreaches();
    Carbon::setTestNow('2026-04-14 09:40:00');
    $this->sla->checkBreaches();

    Notification::assertSentTo($this->agent, SlaAlertNotification::class, slaAlertOfType('first_response_breach', ['database', 'mail']));
    Notification::assertSentTo($this->supervisor, SlaAlertNotification::class, slaAlertOfType('first_response_breach', ['database', 'mail']));
    Notification::assertNotSentTo($this->otherSupervisor, SlaAlertNotification::class);
});

it('si el ticket no tiene asignado, las alertas preventivas llegan a los supervisores del departamento', function () {
    $ticket = ($this->makeTicket)();

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();

    Notification::assertSentTo($this->supervisor, SlaAlertNotification::class, slaAlertOfType('warning_70_first_response'));
    Notification::assertNotSentTo($this->otherSupervisor, SlaAlertNotification::class);

    $log = $ticket->escalationLogs()->sole();
    expect($log->notified_user_id)->toBe($this->supervisor->id)
        ->and($log->notes)->toContain($this->supervisor->name);
});

it('no avisa a supervisores retirados', function () {
    $this->supervisor->forceFill(['employment_status' => 'terminated'])->save();
    $ticket = ($this->makeTicket)();

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();

    Notification::assertNothingSent();
    expect($ticket->escalationLogs()->sole()->notes)->toContain('Sin destinatarios');
});

it('un ticket que ya pasó el 100 % sin registros previos recibe un solo aviso: el de incumplimiento', function () {
    $ticket = ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 10:00:00');
    $created = $this->sla->checkBreaches();

    expect($created)->toBe(3)
        ->and($ticket->escalationLogs()->count())->toBe(3);

    Notification::assertSentToTimes($this->agent, SlaAlertNotification::class, 1);
    Notification::assertSentToTimes($this->supervisor, SlaAlertNotification::class, 1);
    Notification::assertSentTo($this->agent, SlaAlertNotification::class, slaAlertOfType('first_response_breach'));
    Notification::assertCount(2);

    $breach = $ticket->escalationLogs()->where('type', 'first_response_breach')->sole();
    expect($breach->notified_user_id)->toBe($this->agent->id)
        ->and($breach->notes)->toContain($this->agent->name)->toContain($this->supervisor->name);
});

it('una segunda revisión no vuelve a avisar umbrales ya registrados', function () {
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 10:00:00');
    $this->sla->checkBreaches();
    $created = $this->sla->checkBreaches();

    expect($created)->toBe(0);
    Notification::assertCount(2);
});

it('con los avisos desactivados registra las escalaciones pero no notifica', function () {
    config(['tickets.sla_alerts.enabled' => false]);
    $ticket = ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 10:00:00');

    expect($this->sla->checkBreaches())->toBe(3)
        ->and($ticket->escalationLogs()->count())->toBe(3)
        ->and($ticket->fresh()->first_response_breached)->toBeTrue();
    Notification::assertNothingSent();
});

it('incluye a los administradores en los incumplimientos si está configurado', function () {
    config(['tickets.sla_alerts.notify_admins_on_breach' => true]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();
    Notification::assertNotSentTo($admin, SlaAlertNotification::class);

    Carbon::setTestNow('2026-04-14 09:40:00');
    $this->sla->checkBreaches();
    Notification::assertSentTo($admin, SlaAlertNotification::class, slaAlertOfType('first_response_breach'));
});

it('por defecto no avisa a los administradores', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:40:00');
    $this->sla->checkBreaches();

    Notification::assertNotSentTo($admin, SlaAlertNotification::class);
});

it('el correo y la campanita muestran los datos del ticket sin HTML inyectado', function () {
    $ticket = ($this->makeTicket)($this->agent, [
        'number' => 'TK-2026-00042',
        'subject' => '<script>alert(1)</script>Impresora <b>caída</b>',
    ]);
    $log = EscalationLog::create([
        'ticket_id' => $ticket->id,
        'type' => 'warning_90_first_response',
        'sla_minutes' => 120,
        'elapsed_minutes' => 108,
    ]);

    $notification = new SlaAlertNotification($log);
    $mail = $notification->toMail($this->agent);
    $mailText = implode(' ', [...$mail->introLines, ...$mail->outroLines]);
    $database = $notification->toDatabase($this->agent);

    expect($mail->subject)->toBe('⚠️ SLA al 90%: ticket TK-2026-00042 — alert 1 Impresora caída')
        ->not->toContain('<')
        ->and($mailText)->toContain('Primera respuesta')
        ->toContain('90 %')
        ->toContain('2 h hábiles')
        ->toContain($this->department->name)
        ->toContain('Media')
        ->toContain($this->agent->name)
        ->not->toContain('<script>')
        ->and($mail->actionUrl)->toBe(url("/soporte/tickets/{$ticket->id}"))
        ->and($database['title'])->toContain('TK-2026-00042')->not->toContain('<')
        ->and($database['actions'][0]['url'])->toBe(url("/soporte/tickets/{$ticket->id}"));
});

it('el incumplimiento sin asignado se titula como incumplido e indica "sin asignar"', function () {
    $ticket = ($this->makeTicket)(null, ['number' => 'TK-2026-00043']);
    $log = EscalationLog::create([
        'ticket_id' => $ticket->id,
        'type' => 'resolution_breach',
        'sla_minutes' => 600,
        'elapsed_minutes' => 660,
    ]);

    $mail = (new SlaAlertNotification($log))->toMail($this->supervisor);

    expect($mail->subject)->toStartWith('⛔ SLA incumplido: ticket TK-2026-00043')
        ->and(implode(' ', $mail->introLines))->toContain('Resolución')->toContain('110 %')->toContain('sin asignar');
});

it('un ticket en pendiente de cliente no consume plazo ni avisa mientras dura la pausa', function () {
    $ticket = ($this->makeTicket)($this->agent, [
        'status' => TicketStatus::PendienteCliente,
        'paused_at' => Carbon::parse('2026-04-14 08:30:00'),
    ]);

    Carbon::setTestNow('2026-04-16 17:00:00');

    expect($this->sla->checkBreaches())->toBe(0)
        ->and($ticket->escalationLogs()->count())->toBe(0)
        ->and($ticket->fresh()->first_response_breached)->toBeFalse();
    Notification::assertNothingSent();
});

it('descuenta la pausa en curso además de las pausas ya cerradas', function () {
    $ticket = ($this->makeTicket)($this->agent, [
        'status' => TicketStatus::PendienteCliente,
        'paused_minutes' => 20,
        'paused_at' => Carbon::parse('2026-04-14 09:30:00'),
    ]);

    Carbon::setTestNow('2026-04-14 11:00:00');
    $this->sla->checkBreaches();

    $log = $ticket->escalationLogs()->sole();
    expect($log->type)->toBe('warning_70_first_response')
        ->and($log->elapsed_minutes)->toBe(70);
});

it('si el asignado está retirado, el registro apunta al supervisor que sí recibió el aviso', function () {
    $this->agent->forceFill(['employment_status' => 'terminated'])->save();
    $ticket = ($this->makeTicket)($this->agent);

    Carbon::setTestNow('2026-04-14 09:10:00');
    $this->sla->checkBreaches();

    Notification::assertNotSentTo($this->agent, SlaAlertNotification::class);
    Notification::assertSentTo($this->supervisor, SlaAlertNotification::class);

    $log = $ticket->escalationLogs()->sole();
    expect($log->notified_user_id)->toBe($this->supervisor->id)
        ->and($log->notes)->toContain($this->supervisor->name)->not->toContain($this->agent->name);
});

it('no envía el aviso encolado si el ticket se eliminó o se cerró antes de procesarlo', function () {
    $ticket = ($this->makeTicket)($this->agent);
    $log = EscalationLog::create([
        'ticket_id' => $ticket->id,
        'type' => 'first_response_breach',
        'sla_minutes' => 100,
        'elapsed_minutes' => 110,
    ]);

    expect((new SlaAlertNotification($log->fresh()))->shouldSend($this->agent, 'mail'))->toBeTrue();

    $ticket->update(['status' => TicketStatus::Cerrado]);
    expect((new SlaAlertNotification($log->fresh()))->shouldSend($this->agent, 'mail'))->toBeFalse();

    $ticket->delete();
    expect((new SlaAlertNotification($log->fresh()))->shouldSend($this->agent, 'database'))->toBeFalse();
});

it('la definición de alertas SLA explica quién recibe el aviso', function () {
    expect(SlaDefinitions::help('sla_alerts'))
        ->toContain('agente asignado')
        ->toContain('supervisores del departamento')
        ->toContain('campanita y correo')
        ->not->toContain('administradores');

    config(['tickets.sla_alerts.notify_admins_on_breach' => true]);
    expect(SlaDefinitions::help('sla_alerts'))->toContain('administradores');

    config(['tickets.sla_alerts.enabled' => false]);
    expect(SlaDefinitions::help('sla_alerts'))->toContain('desactivados');
});

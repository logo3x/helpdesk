<?php

use App\Enums\TicketStatus;
use App\Jobs\AutoCloseTicketsJob;
use App\Jobs\AwaitingCustomerFollowUpJob;
use App\Jobs\SendSatisfactionSurveyJob;
use App\Livewire\Portal\ViewTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCommentedNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Queue::fake([SendSatisfactionSurveyJob::class]);

    $this->requester = User::factory()->create(['name' => 'Ana Cliente']);

    // Lunes 2 de noviembre de 2026, 9:00.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));

    $this->ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'status' => TicketStatus::PendienteCliente,
        'paused_at' => now(),
    ]);
});

function runFollowUp(): void
{
    app()->call([new AwaitingCustomerFollowUpJob, 'handle']);
}

it('a las 3 horas hábiles envía el primer recordatorio, una sola vez', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:05')); // 3 h hábiles

    runFollowUp();
    runFollowUp();

    $reminders = $this->ticket->comments()->where('event_type', AwaitingCustomerFollowUpJob::REMINDER_EVENT)->get();

    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->is_private)->toBeFalse()
        ->and($reminders->first()->body)->toContain('Ana Cliente')
        ->and($reminders->first()->body)->toContain($this->ticket->number)
        ->and($reminders->first()->body)->toContain('7 horas hábiles');

    Notification::assertSentToTimes($this->requester, TicketCommentedNotification::class, 1);
});

it('antes de las 3 horas hábiles no hace nada', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 11:30'));

    runFollowUp();

    expect($this->ticket->comments()->count())->toBe(0);
});

it('las horas fuera de la jornada y el fin de semana no cuentan', function () {
    // Pausado el viernes a las 17:00: a las 12:30 del lunes van 1 h + 4,5 h = 5,5 h hábiles.
    $this->ticket->update(['paused_at' => CarbonImmutable::parse('2026-11-06 17:00')]);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:30'));

    runFollowUp();

    expect($this->ticket->comments()->count())->toBe(1)
        ->and($this->ticket->fresh()->status)->toBe(TicketStatus::PendienteCliente);
});

it('a las 7 horas hábiles envía el último aviso', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:05'));
    runFollowUp();

    $this->travelTo(CarbonImmutable::parse('2026-11-02 16:05')); // 7 h hábiles
    runFollowUp();

    $bodies = $this->ticket->comments()->pluck('body');

    expect($bodies)->toHaveCount(2)
        ->and($bodies->last())->toContain('último aviso')
        ->and($bodies->last())->toContain('3 horas hábiles');
});

it('al completar 1 día hábil cierra el ticket por falta de respuesta y sin encuesta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 09:05')); // 9 h del lunes + 1 h del martes = 10 h

    runFollowUp();

    $ticket = $this->ticket->fresh();

    expect($ticket->status)->toBe(TicketStatus::Cerrado)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->paused_at)->toBeNull()
        ->and($ticket->comments()->where('event_type', AwaitingCustomerFollowUpJob::CLOSED_EVENT)->first()->body)
        ->toContain('no recibimos respuesta');

    Queue::assertNotPushed(SendSatisfactionSurveyJob::class);
});

it('si el cliente responde, el ticket vuelve a En progreso y el SLA se reanuda', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00'));

    Livewire::actingAs($this->requester)
        ->test(ViewTicket::class, ['ticket' => $this->ticket])
        ->set('commentBody', 'Ya te envío la información que pediste.')
        ->call('addComment');

    $ticket = $this->ticket->fresh();

    expect($ticket->status)->toBe(TicketStatus::EnProgreso)
        ->and($ticket->paused_at)->toBeNull()
        ->and($ticket->paused_minutes)->toBeGreaterThan(0);
});

it('al cerrar un ticket resuelto deja un mensaje explicando el cierre', function () {
    $ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'status' => TicketStatus::Resuelto,
        'resolved_at' => now()->subDays(10),
    ]);

    app()->call([new AutoCloseTicketsJob, 'handle']);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Cerrado)
        ->and($ticket->comments()->where('event_type', 'auto_closed')->first()->body)
        ->toContain('se marcó como resuelto');
});

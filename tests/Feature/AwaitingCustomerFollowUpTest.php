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

it('el día hábil 2 envía el primer recordatorio, una sola vez', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00')); // miércoles

    runFollowUp();
    runFollowUp();

    $reminders = $this->ticket->comments()->where('event_type', AwaitingCustomerFollowUpJob::REMINDER_EVENT)->get();

    expect($reminders)->toHaveCount(1)
        ->and($reminders->first()->is_private)->toBeFalse()
        ->and($reminders->first()->body)->toContain('Ana Cliente')
        ->and($reminders->first()->body)->toContain($this->ticket->number)
        ->and($reminders->first()->body)->toContain('3 días hábiles');

    Notification::assertSentToTimes($this->requester, TicketCommentedNotification::class, 1);
});

it('el fin de semana no cuenta como día hábil', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
    $this->ticket->update(['paused_at' => CarbonImmutable::parse('2026-11-06 09:00')]); // viernes
    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00')); // lunes: 1 día hábil

    runFollowUp();

    expect($this->ticket->comments()->count())->toBe(0);
});

it('el día hábil 4 envía el último aviso', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00')); // día hábil 2
    runFollowUp();

    $this->travelTo(CarbonImmutable::parse('2026-11-06 08:00')); // viernes: día hábil 4
    runFollowUp();

    $bodies = $this->ticket->comments()->pluck('body');

    expect($bodies)->toHaveCount(2)
        ->and($bodies->last())->toContain('último aviso');
});

it('el día hábil 5 cierra el ticket por falta de respuesta y sin encuesta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00')); // lunes siguiente: 5 días hábiles

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

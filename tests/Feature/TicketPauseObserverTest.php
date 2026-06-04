<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\Carbon;

it('activa paused_at cuando el ticket entra en pendiente_cliente', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::EnProgreso]);

    expect($ticket->paused_at)->toBeNull();

    $ticket->status = TicketStatus::PendienteCliente;
    $ticket->save();

    expect($ticket->fresh()->paused_at)->not->toBeNull();
});

it('acumula paused_minutes cuando el ticket sale de pendiente_cliente', function () {
    Carbon::setTestNow('2026-06-03 10:00:00'); // Miércoles 10 AM

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::EnProgreso,
        'paused_minutes' => 0,
    ]);

    // Pausa.
    $ticket->status = TicketStatus::PendienteCliente;
    $ticket->save();

    // Pasan 2 horas hábiles.
    Carbon::setTestNow('2026-06-03 12:00:00');

    // Reanuda.
    $ticket->status = TicketStatus::EnProgreso;
    $ticket->save();

    $ticket->refresh();
    expect($ticket->paused_at)->toBeNull()
        ->and($ticket->paused_minutes)->toBe(120);

    Carbon::setTestNow();
});

it('acumula múltiples pausas sucesivas', function () {
    Carbon::setTestNow('2026-06-03 10:00:00');
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::EnProgreso,
        'paused_minutes' => 0,
    ]);

    // Pausa 1: 30 min.
    $ticket->update(['status' => TicketStatus::PendienteCliente]);
    Carbon::setTestNow('2026-06-03 10:30:00');
    $ticket->update(['status' => TicketStatus::EnProgreso]);

    // Pausa 2: 45 min.
    Carbon::setTestNow('2026-06-03 11:00:00');
    $ticket->update(['status' => TicketStatus::PendienteCliente]);
    Carbon::setTestNow('2026-06-03 11:45:00');
    $ticket->update(['status' => TicketStatus::EnProgreso]);

    expect($ticket->fresh()->paused_minutes)->toBe(75); // 30 + 45

    Carbon::setTestNow();
});

it('no recalcula paused_at si el ticket ya estaba en pendiente_cliente', function () {
    Carbon::setTestNow('2026-06-03 10:00:00');
    $ticket = Ticket::factory()->create(['status' => TicketStatus::PendienteCliente]);

    $firstPausedAt = $ticket->paused_at;

    // Update sin cambiar status — no debería tocar paused_at.
    Carbon::setTestNow('2026-06-03 11:00:00');
    $ticket->update(['subject' => 'titulo nuevo']);

    expect($ticket->fresh()->paused_at?->format('Y-m-d H:i'))->toBe($firstPausedAt?->format('Y-m-d H:i'));

    Carbon::setTestNow();
});

it('pausas fuera de horario hábil no suman al paused_minutes', function () {
    // Viernes 17:30 → Lunes 09:00. Solo 30 min hábiles (Vie 17:30-18:00).
    Carbon::setTestNow('2026-06-05 17:30:00'); // Viernes 5:30 PM
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::EnProgreso,
        'paused_minutes' => 0,
    ]);
    $ticket->update(['status' => TicketStatus::PendienteCliente]);

    Carbon::setTestNow('2026-06-08 09:00:00'); // Lunes 9 AM
    $ticket->update(['status' => TicketStatus::EnProgreso]);

    // Viernes 17:30-18:00 = 30 min + Lunes 08:00-09:00 = 60 min = 90 min hábiles.
    expect($ticket->fresh()->paused_minutes)->toBe(90);

    Carbon::setTestNow();
});

<?php

use App\Enums\TicketStatus;
use App\Jobs\AutoCloseTicketsJob;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('cierra tickets resueltos hace más de auto_close_days', function () {
    config()->set('tickets.auto_close_days', 7);

    $old = Ticket::factory()->resolved()->create([
        'resolved_at' => now()->subDays(8),
    ]);

    (new AutoCloseTicketsJob)->handle(app(TicketService::class));

    expect($old->fresh()->status)->toBe(TicketStatus::Cerrado);
});

it('no cierra tickets resueltos dentro de la ventana', function () {
    config()->set('tickets.auto_close_days', 7);

    $recent = Ticket::factory()->resolved()->create([
        'resolved_at' => now()->subDays(3),
    ]);

    (new AutoCloseTicketsJob)->handle(app(TicketService::class));

    expect($recent->fresh()->status)->toBe(TicketStatus::Resuelto);
});

it('respeta el umbral configurado vía config', function () {
    config()->set('tickets.auto_close_days', 3);

    $ticket = Ticket::factory()->resolved()->create([
        'resolved_at' => now()->subDays(4),
    ]);

    (new AutoCloseTicketsJob)->handle(app(TicketService::class));

    expect($ticket->fresh()->status)->toBe(TicketStatus::Cerrado);
});

it('ignora tickets que no están en estado Resuelto', function () {
    config()->set('tickets.auto_close_days', 7);

    $assigned = Ticket::factory()->assigned()->create([
        'resolved_at' => now()->subDays(10),
    ]);

    (new AutoCloseTicketsJob)->handle(app(TicketService::class));

    expect($assigned->fresh()->status)->toBe(TicketStatus::Asignado);
});

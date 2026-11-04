<?php

use App\Enums\TicketStatus;
use App\Filament\Soporte\Resources\Tickets\Pages\ViewTicket;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommentedNotification;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $department = Department::factory()->create();
    $this->agent = User::factory()->create(['department_id' => $department->id, 'name' => 'Agente Uno']);
    $this->agent->assignRole('agente_soporte');
    $this->requester = User::factory()->create(['name' => 'Ana Cliente']);

    $this->ticket = Ticket::factory()->create([
        'requester_id' => $this->requester->id,
        'assigned_to_id' => $this->agent->id,
        'department_id' => $department->id,
        'status' => TicketStatus::EnProgreso,
    ]);

    $this->actingAs($this->agent);
});

it('muestra la conversación estilo chat con notas internas y eventos', function () {
    TicketComment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->requester->id, 'body' => 'No me abre **SAP**', 'is_private' => false]);
    TicketComment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->agent->id, 'body' => 'Revisar licencia', 'is_private' => true]);
    TicketComment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->agent->id, 'body' => 'Cambio de urgencia', 'is_private' => true, 'is_system_event' => true, 'event_type' => 'recalibrated']);

    Livewire::test(ViewTicket::class, ['record' => $this->ticket->getRouteKey()])
        ->assertSee('Conversación')
        ->assertSeeHtml('<strong>SAP</strong>')
        ->assertSee('Solicitante')
        ->assertSee('Nota interna')
        ->assertSee('Prioridad recalibrada');
});

it('responder envía el mensaje al solicitante y marca la primera respuesta', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->getRouteKey()])
        ->callAction('respond', ['body' => 'Hola Ana, ya lo reviso.', 'is_private' => false])
        ->assertHasNoActionErrors();

    expect($this->ticket->fresh()->first_responded_at)->not->toBeNull()
        ->and(TicketComment::where('body', 'Hola Ana, ya lo reviso.')->first()->is_private)->toBeFalse();

    Notification::assertSentTo($this->requester, TicketCommentedNotification::class);
});

it('la nota interna no notifica al solicitante', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->getRouteKey()])
        ->callAction('respond', ['body' => 'Pendiente revisar con redes', 'is_private' => true]);

    Notification::assertNotSentTo($this->requester, TicketCommentedNotification::class);
    expect($this->ticket->fresh()->first_responded_at)->toBeNull();
});

it('"esperar respuesta del cliente" pausa el SLA', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->getRouteKey()])
        ->callAction('respond', ['body' => '¿Me envías una captura del error?', 'is_private' => false, 'await_customer' => true]);

    $ticket = $this->ticket->fresh();

    expect($ticket->status)->toBe(TicketStatus::PendienteCliente)
        ->and($ticket->paused_at)->not->toBeNull();
});

it('no se puede responder un ticket cerrado', function () {
    $this->ticket->update(['status' => TicketStatus::Cerrado, 'closed_at' => now()]);

    Livewire::test(ViewTicket::class, ['record' => $this->ticket->getRouteKey()])
        ->assertActionHidden('respond');
});

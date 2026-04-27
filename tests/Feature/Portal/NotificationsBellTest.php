<?php

use App\Livewire\Portal\NotificationsBell;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Helper para escribir directamente una notificación en la tabla con
 * el shape Filament. Evita tener que disparar una clase Notification
 * completa en el test.
 */
function makeFilamentDbNotif(User $user, array $data, ?string $readAt = null): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TicketCreatedNotification',
        'notifiable_type' => $user::class,
        'notifiable_id' => $user->id,
        'data' => $data,
        'read_at' => $readAt,
    ]);
}

it('muestra el contador de no leídas', function () {
    makeFilamentDbNotif($this->user, ['title' => 'Notif 1']);
    makeFilamentDbNotif($this->user, ['title' => 'Notif 2']);
    makeFilamentDbNotif($this->user, ['title' => 'Notif 3'], readAt: now()->toDateTimeString());

    Livewire::actingAs($this->user)
        ->test(NotificationsBell::class)
        ->assertSee('Notif 1')
        ->assertSee('Notif 2')
        ->assertViewHas('unreadCount', 2);
});

it('marca todas como leídas', function () {
    makeFilamentDbNotif($this->user, ['title' => 'A']);
    makeFilamentDbNotif($this->user, ['title' => 'B']);

    Livewire::actingAs($this->user)
        ->test(NotificationsBell::class)
        ->call('markAllAsRead')
        ->assertViewHas('unreadCount', 0);

    expect($this->user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('al hacer click marca la notif como leída y redirige a la URL del action (rehosteada)', function () {
    // La URL guardada apunta a un host viejo (ej. cuando APP_URL era
    // localhost). El componente debe extraer solo el path y reconstruir
    // sobre el host actual, así un cambio de APP_URL no rompe los
    // enlaces de notifs ya creadas.
    $notif = makeFilamentDbNotif($this->user, [
        'title' => 'Ticket TK-2026-00001 creado',
        'body' => 'Test',
        'actions' => [
            ['name' => 'view', 'label' => 'Ver ticket', 'url' => 'http://localhost-viejo/portal/tickets/42'],
        ],
    ]);

    Livewire::actingAs($this->user)
        ->test(NotificationsBell::class)
        ->call('markAsReadAndGo', $notif->id)
        ->assertRedirect(url('/portal/tickets/42'));

    expect($notif->fresh()->read_at)->not->toBeNull();
});

it('soporta payload legacy con ticket_id sin actions[]', function () {
    $notif = makeFilamentDbNotif($this->user, [
        'title' => 'Notif vieja',
        'ticket_id' => 99,
    ]);

    Livewire::actingAs($this->user)
        ->test(NotificationsBell::class)
        ->call('markAsReadAndGo', $notif->id)
        ->assertRedirect(url('/portal/tickets/99'));
});

it('no rompe si la notif no existe', function () {
    Livewire::actingAs($this->user)
        ->test(NotificationsBell::class)
        ->call('markAsReadAndGo', (string) Str::uuid())
        ->assertNoRedirect();
});

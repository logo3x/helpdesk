<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Enums\TicketStatus;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReceivedFromTransferNotification;
use App\Notifications\TicketTransferredNotification;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * @return array<int, Action|EditAction>
     */
    protected function getHeaderActions(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return [
            // Edit solo para supervisor/admin. Los agentes no deben
            // modificar el contenido del ticket (asunto, descripción,
            // categoría, impacto, urgencia) — solo responden con
            // comentarios. Si hay un error, lo arregla el supervisor.
            EditAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),

            // ── Tomar este ticket (solo agentes/técnicos) ──────────────
            // Acción self-assign sin dropdown: el agente que está
            // revisando el ticket lo toma para sí mismo.
            Action::make('claim')
                ->label('Tomar este ticket')
                ->icon('heroicon-o-hand-raised')
                ->color('primary')
                ->visible(fn () => $ticket->status->isOpen()
                    && auth()->user()?->hasAnyRole(['agente_soporte', 'tecnico_campo'])
                    && $ticket->assigned_to_id !== auth()->id())
                ->requiresConfirmation()
                ->modalHeading('¿Tomar este ticket?')
                ->modalDescription('Se te asignará el ticket y cambiará a estado "Asignado".')
                ->action(function () use ($ticket): void {
                    $user = auth()->user();
                    abort_unless($user?->can('update', $ticket), 403);
                    app(TicketService::class)->assign($ticket, $user);
                    Notification::make()->title('Ticket asignado a ti')->success()->send();
                    $this->refreshFormData(['status', 'assigned_to_id']);
                }),

            // ── Asignar a otro agente (solo supervisor / admin) ────────
            // Mantiene el dropdown para elegir a quién del equipo asignar.
            Action::make('assign')
                ->label('Asignar')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->visible(fn () => $ticket->status->isOpen()
                    && auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']))
                ->schema([
                    Select::make('assigned_to_id')
                        ->label('Asignar a')
                        ->helperText('Solo agentes del departamento del ticket. Si el ticket está mal clasificado, usa "Trasladar a otro depto.".')
                        ->options(function () use ($ticket) {
                            $currentUser = auth()->user();

                            // Super_admin y admin ven agentes de todos
                            // los departamentos (no tienen scope).
                            $query = User::query()
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                                    'agente_soporte', 'tecnico_campo',
                                ]));

                            // Supervisor: solo agentes del mismo depto
                            // del ticket (que coincide con el suyo por
                            // el scope de getEloquentQuery del Resource).
                            if ($currentUser && ! $currentUser->hasAnyRole(['super_admin', 'admin'])) {
                                $query->where('department_id', $ticket->department_id);
                            }

                            return $query->orderBy('name')->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->assign($ticket, User::findOrFail($data['assigned_to_id']));
                    Notification::make()->title('Ticket asignado')->success()->send();
                    $this->refreshFormData(['status', 'assigned_to_id']);
                }),

            Action::make('markFirstResponse')
                ->label('Marcar primera respuesta')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->visible(fn () => $ticket->first_responded_at === null && $ticket->status->isOpen())
                ->requiresConfirmation()
                ->action(function () use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->markFirstResponse($ticket);
                    Notification::make()->title('Primera respuesta registrada')->success()->send();
                    $this->refreshFormData(['status', 'first_responded_at']);
                }),

            Action::make('resolve')
                ->label('Resolver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($ticket->status, [
                    TicketStatus::EnProgreso,
                    TicketStatus::Asignado,
                    TicketStatus::Reabierto,
                    TicketStatus::PendienteCliente,
                ], true))
                ->requiresConfirmation()
                ->action(function () use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->resolve($ticket);
                    Notification::make()->title('Ticket resuelto')->success()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),

            Action::make('close')
                ->label('Cerrar')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn () => $ticket->status === TicketStatus::Resuelto)
                ->requiresConfirmation()
                ->action(function () use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->close($ticket);
                    Notification::make()->title('Ticket cerrado')->success()->send();
                    $this->refreshFormData(['status', 'closed_at']);
                }),

            Action::make('transfer')
                ->label('Trasladar a otro depto.')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                // Usamos el Policy directamente para tener una sola fuente
                // de verdad. La comprobación hasAnyRole anterior podía
                // desincronizarse si alguien cambiaba el policy.
                ->visible(fn () => auth()->user()?->can('transfer', $ticket) && $ticket->status->isOpen())
                ->schema([
                    Select::make('department_id')
                        ->label('Nuevo departamento')
                        ->options(fn () => Department::where('is_active', true)
                            ->where('id', '!=', $ticket->department_id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable(),
                    Textarea::make('reason')
                        ->label('Motivo del traslado')
                        ->rows(2)
                        ->placeholder('Se notificará al solicitante este motivo.')
                        ->maxLength(500),
                ])
                ->action(function (array $data) use ($ticket): void {
                    abort_unless(auth()->user()?->can('transfer', $ticket), 403);
                    $from = $ticket->department;
                    $to = Department::findOrFail($data['department_id']);
                    $reason = $data['reason'] ?? null;

                    $ticket->forceFill([
                        'department_id' => $to->id,
                        'assigned_to_id' => null, // reset assignment on transfer
                        'category_id' => null,    // force new triage of category
                    ])->save();

                    if ($ticket->requester) {
                        $ticket->requester->notify(new TicketTransferredNotification($ticket, $from, $to, $reason));
                    }

                    // Notificar a supervisores del departamento destino
                    // para que sepan que tienen un ticket nuevo en su cola.
                    $destinationSupervisors = User::query()
                        ->where('department_id', $to->id)
                        ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_soporte'))
                        ->get();

                    foreach ($destinationSupervisors as $supervisor) {
                        $supervisor->notify(new TicketReceivedFromTransferNotification(
                            ticket: $ticket,
                            fromDepartment: $from,
                            reason: $reason,
                            transferredBy: auth()->user()?->name,
                        ));
                    }

                    Notification::make()
                        ->title("Ticket trasladado a {$to->name}")
                        ->body('Se notificó al solicitante y a los supervisores del depto destino.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['department_id', 'assigned_to_id', 'category_id']);
                }),

            Action::make('reopen')
                ->label('Reabrir')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->visible(fn () => in_array($ticket->status, [TicketStatus::Resuelto, TicketStatus::Cerrado], true))
                ->requiresConfirmation()
                ->action(function () use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->reopen($ticket);
                    Notification::make()->title('Ticket reabierto')->warning()->send();
                    $this->refreshFormData(['status', 'reopened_at', 'resolved_at', 'closed_at']);
                }),
        ];
    }
}

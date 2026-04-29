<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Enums\TicketImpact;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Filament\Soporte\Resources\Tickets\Schemas\TicketViewInfolist;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\CannedResponse;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommentedNotification;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Sustituye el form en modo disabled (default de ViewRecord) por
     * un infolist organizado para escaneo rápido del agente:
     * resumen → descripción → adjuntos → SLA → clasificación.
     */
    public function infolist(Schema $schema): Schema
    {
        return TicketViewInfolist::configure($schema);
    }

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
            // Self-assign con saludo personalizable: el agente puede
            // elegir una respuesta predefinida del depto/categoría o
            // editar el texto antes de enviarlo. Reemplaza el comentario
            // genérico anterior porque obligaba a todos a saludar igual.
            Action::make('claim')
                ->label('Tomar este ticket')
                ->icon('heroicon-o-hand-raised')
                ->color('primary')
                ->visible(fn () => $ticket->status->isOpen()
                    && auth()->user()?->hasAnyRole(['agente_soporte', 'tecnico_campo'])
                    && $ticket->assigned_to_id !== auth()->id())
                ->modalHeading('Tomar este ticket')
                ->modalDescription('Te asignaremos el ticket. Escribe un saludo inicial al solicitante o usa una respuesta predefinida.')
                ->modalSubmitActionLabel('Tomar y comentar')
                ->fillForm(fn () => [
                    'body' => 'Hola, he tomado tu ticket y lo voy a revisar. Te contacto en breve con novedades.',
                ])
                ->schema([
                    Select::make('canned_response_id')
                        ->label('Respuesta predefinida')
                        ->helperText('Opcional. Inserta una plantilla y luego edítala si quieres.')
                        ->options(function () {
                            $user = auth()->user();
                            $query = CannedResponse::query()->where('is_active', true);

                            // Mismo scope que CommentsRelationManager:
                            // por categoría del ticket → del depto del
                            // usuario → globales (sin categoría).
                            if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
                                $query->where(function ($q) use ($user) {
                                    $q->whereHas('category', fn ($sub) => $sub->where('department_id', $user->department_id))
                                        ->orWhereNull('category_id');
                                });
                            }

                            return $query->orderBy('sort_order')->pluck('title', 'id')->all();
                        })
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->placeholder('— Sin respuesta predefinida —')
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (! $state) {
                                return;
                            }

                            $canned = CannedResponse::find($state);
                            if ($canned) {
                                $set('body', $canned->body);
                            }
                        }),

                    Textarea::make('body')
                        ->label('Comentario al solicitante')
                        ->rows(4)
                        ->required()
                        ->minLength(5)
                        ->maxLength(5000),
                ])
                ->action(function (array $data) use ($ticket): void {
                    $user = auth()->user();
                    abort_unless($user?->can('update', $ticket), 403);

                    $service = app(TicketService::class);

                    // Asignamos sin auto-comment (autoComment=false)
                    // porque a continuación creamos el comentario con
                    // el texto que el agente eligió/editó. Evita el
                    // saludo genérico cuando hay uno custom.
                    $service->assign($ticket, $user, autoComment: false);

                    $comment = TicketComment::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'body' => $data['body'],
                        'is_private' => false,
                    ]);

                    // Marca first_responded_at + transición a EnProgreso.
                    $service->markFirstResponse($ticket->fresh(), firstResponder: $user);

                    // Notificamos al solicitante igual que cuando se
                    // crea un comentario público desde la tabla.
                    $fresh = $ticket->fresh();
                    if ($fresh->requester) {
                        $fresh->requester->notify(new TicketCommentedNotification($fresh, $comment));
                    }

                    Notification::make()
                        ->title('Ticket tomado')
                        ->body('Quedaste como asignado y se notificó al solicitante con tu comentario.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'assigned_to_id', 'first_responded_at']);
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

            // ── Recalibrar prioridad (supervisor / admin) ──────────────
            // Permite corregir la clasificación inicial (impacto × urgencia)
            // cuando el solicitante minimizó/exageró la criticidad. Recalcula
            // la prioridad y el SLA preservando el origen del reloj (created_at)
            // y deja traza en el activity log con el motivo del cambio.
            Action::make('recalibratePriority')
                ->label('Recalibrar prioridad')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])
                    && $ticket->status->isOpen())
                ->fillForm(fn () => [
                    'impact' => $ticket->impact?->value,
                    'urgency' => $ticket->urgency?->value,
                ])
                ->schema([
                    Select::make('impact')
                        ->label('Impacto')
                        ->options(TicketImpact::class)
                        ->required()
                        ->helperText('Alcance real: Bajo (solicitante), Medio (equipo), Alto (toda la empresa).'),
                    Select::make('urgency')
                        ->label('Urgencia')
                        ->options(TicketUrgency::class)
                        ->required()
                        ->helperText('Qué tan rápido se necesita solución.'),
                    Textarea::make('reason')
                        ->label('Motivo del ajuste')
                        ->rows(2)
                        ->required()
                        ->maxLength(500)
                        ->placeholder('Ej: "El solicitante subestimó el impacto: afecta a toda el área de contabilidad, no solo a él."'),
                ])
                ->action(function (array $data) use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    // El service ya normaliza TicketImpact|string y
                    // TicketUrgency|string, así que pasamos el valor
                    // tal cual viene del form (puede ser enum o string).
                    app(TicketService::class)->recalibratePriority(
                        $ticket,
                        $data['impact'],
                        $data['urgency'],
                        $data['reason'] ?? null,
                    );
                    Notification::make()
                        ->title('Prioridad recalibrada')
                        ->body('Se recalculó el SLA y quedó registrado en el historial.')
                        ->success()
                        ->send();
                    $this->refreshFormData(['impact', 'urgency', 'priority', 'first_response_due_at', 'resolution_due_at']);
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

                    app(TicketService::class)->transfer(
                        ticket: $ticket,
                        toDepartment: Department::findOrFail($data['department_id']),
                        reason: $data['reason'] ?? null,
                    );

                    Notification::make()
                        ->title("Ticket trasladado a {$ticket->fresh()->department?->name}")
                        ->body('Se notificó al solicitante, a los supervisores destino y queda registrado en el historial del ticket.')
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

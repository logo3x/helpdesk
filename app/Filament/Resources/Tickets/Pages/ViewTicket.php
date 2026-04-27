<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReceivedFromTransferNotification;
use App\Notifications\TicketTransferredNotification;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * El panel /admin permite al super_admin/admin trasladar cualquier
     * ticket a cualquier departamento sin restricción (corrección rápida
     * de mal clasificaciones). Las demás operaciones (asignar, resolver,
     * cerrar, reabrir) se hacen en /soporte para mantener una sola
     * ubicación del workflow operativo.
     */
    protected function getHeaderActions(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();

        return [
            Action::make('transfer')
                ->label('Trasladar a otro depto.')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
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
                        'assigned_to_id' => null,
                        'category_id' => null,
                    ])->save();

                    if ($ticket->requester) {
                        $ticket->requester->notify(new TicketTransferredNotification($ticket, $from, $to, $reason));
                    }

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

            // Recalibrar prioridad también disponible en /admin para que
            // super_admin/admin puedan corregir tickets mal clasificados
            // sin tener que rebotar al panel de soporte.
            Action::make('recalibratePriority')
                ->label('Recalibrar prioridad')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin'])
                    && $ticket->status->isOpen())
                ->fillForm(fn () => [
                    'impact' => $ticket->impact?->value,
                    'urgency' => $ticket->urgency?->value,
                ])
                ->schema([
                    Select::make('impact')
                        ->label('Impacto')
                        ->options(TicketImpact::class)
                        ->required(),
                    Select::make('urgency')
                        ->label('Urgencia')
                        ->options(TicketUrgency::class)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Motivo del ajuste')
                        ->rows(2)
                        ->required()
                        ->maxLength(500)
                        ->placeholder('Queda registrado en el historial del ticket.'),
                ])
                ->action(function (array $data) use ($ticket): void {
                    abort_unless(auth()->user()?->can('update', $ticket), 403);
                    app(TicketService::class)->recalibratePriority(
                        $ticket,
                        $data['impact'],
                        $data['urgency'],
                        $data['reason'] ?? null,
                    );
                    Notification::make()
                        ->title('Prioridad recalibrada')
                        ->success()
                        ->send();
                    $this->refreshFormData(['impact', 'urgency', 'priority', 'first_response_due_at', 'resolution_due_at']);
                }),

            Action::make('open_in_soporte')
                ->label('Abrir en Soporte')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => url("/soporte/tickets/{$ticket->id}"))
                ->openUrlInNewTab(),
        ];
    }
}

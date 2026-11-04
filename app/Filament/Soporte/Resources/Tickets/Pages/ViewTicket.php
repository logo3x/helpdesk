<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Filament\Soporte\Resources\Tickets\Concerns\HasTicketWorkflowActions;
use App\Filament\Soporte\Resources\Tickets\Schemas\TicketViewInfolist;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTicket extends ViewRecord
{
    use HasTicketWorkflowActions;

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
     * En la vista, la conversación ya se muestra estilo chat dentro del
     * infolist y se responde con el botón "Responder"; la tabla de
     * comentarios sigue disponible en la página de edición.
     *
     * @return array<class-string>
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    /**
     * @return array<int, Action|EditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Edit solo para supervisor/admin. Los agentes no deben
            // modificar el contenido del ticket: solo responden.
            EditAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),

            ...$this->ticketWorkflowActions(),
        ];
    }
}

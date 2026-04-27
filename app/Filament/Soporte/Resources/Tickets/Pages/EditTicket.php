<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Filament\Soporte\Resources\Tickets\Schemas\EditTicketForm;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Solo supervisor+admin pueden llegar al form de edición.
     * Los agentes que intenten acceder a /soporte/tickets/{id}/edit por
     * URL directa reciben 403. Su flujo de trabajo es "Tomar ticket" +
     * agregar comentarios, no editar el contenido original.
     */
    public function mount(int|string $record): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']),
            403,
            'Los agentes no pueden modificar el contenido de un ticket. Usa comentarios para responder.'
        );

        parent::mount($record);
    }

    /**
     * El form de edición es reducido a propósito (asunto, descripción,
     * categoría). Prioridad, asignación, traslado y workflow se hacen
     * con las acciones del detalle para mantener una sola fuente de
     * verdad (el servicio) y un audit trail consistente.
     */
    public function form(Schema $schema): Schema
    {
        return EditTicketForm::configure($schema);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

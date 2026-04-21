<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Enlace al panel /soporte donde están las acciones operativas
     * (asignar, resolver, trasladar, etc.). El panel /admin es
     * solo lectura para mantener una única fuente de verdad.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_in_soporte')
                ->label('Abrir en Soporte')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => url("/soporte/tickets/{$this->record->id}"))
                ->openUrlInNewTab(),
        ];
    }
}

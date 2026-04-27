<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use App\Filament\Soporte\Resources\Tickets\Schemas\CreateTicketForm;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\User;
use App\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Schema dedicado para create con secciones agrupadas por
     * pregunta natural (problema → solicitante → criticidad).
     */
    public function form(Schema $schema): Schema
    {
        return CreateTicketForm::configure($schema);
    }

    /**
     * Aprovecha todo el ancho disponible para que la descripción
     * Markdown y los campos en grid de 3 columnas respiren.
     */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * Override the default "hydrate + save" flow so tickets always go
     * through TicketService — this guarantees atomic numbering and the
     * priority matrix cannot be bypassed from the UI.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $requester */
        $requester = User::findOrFail($data['requester_id']);

        // El service ya normaliza TicketImpact|string y TicketUrgency|string,
        // así que pasamos el valor tal cual venga del form (puede ser enum
        // hidratado por Filament o string crudo según versión).
        return app(TicketService::class)->create($requester, [
            'subject' => $data['subject'],
            'description' => $data['description'],
            'impact' => $data['impact'],
            'urgency' => $data['urgency'],
            'category_id' => $data['category_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}

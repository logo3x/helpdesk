<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\User;
use App\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Override the default "hydrate + save" flow so tickets always go
     * through TicketService — this guarantees atomic numbering and the
     * priority matrix cannot be bypassed from the UI.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $requester */
        $requester = User::findOrFail($data['requester_id']);

        return app(TicketService::class)->create($requester, [
            'subject' => $data['subject'],
            'description' => $data['description'],
            'impact' => TicketImpact::from($data['impact']),
            'urgency' => TicketUrgency::from($data['urgency']),
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

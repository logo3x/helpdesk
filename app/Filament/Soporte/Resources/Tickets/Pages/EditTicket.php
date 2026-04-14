<?php

namespace App\Filament\Soporte\Resources\Tickets\Pages;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Recompute priority from impact × urgency on save so edits from the
     * admin form stay consistent with TicketService::create().
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $impact = $data['impact'] instanceof TicketImpact
            ? $data['impact']
            : TicketImpact::from($data['impact']);

        $urgency = $data['urgency'] instanceof TicketUrgency
            ? $data['urgency']
            : TicketUrgency::from($data['urgency']);

        $data['priority'] = TicketPriority::fromMatrix($impact, $urgency)->value;

        return $data;
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

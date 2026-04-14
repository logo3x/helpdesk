<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Pages;

use App\Filament\Soporte\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketTemplate extends EditRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

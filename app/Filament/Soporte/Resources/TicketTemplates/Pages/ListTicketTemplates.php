<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Pages;

use App\Filament\Soporte\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketTemplates extends ListRecords
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

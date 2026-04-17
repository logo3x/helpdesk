<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Pages;

use App\Filament\Soporte\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketTemplate extends CreateRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

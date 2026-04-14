<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Pages;

use App\Filament\Soporte\Resources\CannedResponses\CannedResponseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCannedResponses extends ListRecords
{
    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Pages;

use App\Filament\Soporte\Resources\CannedResponses\CannedResponseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCannedResponse extends EditRecord
{
    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

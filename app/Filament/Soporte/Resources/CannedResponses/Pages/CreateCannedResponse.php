<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Pages;

use App\Filament\Soporte\Resources\CannedResponses\CannedResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCannedResponse extends CreateRecord
{
    protected static string $resource = CannedResponseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

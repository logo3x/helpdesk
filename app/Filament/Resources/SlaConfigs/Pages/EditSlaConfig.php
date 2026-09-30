<?php

namespace App\Filament\Resources\SlaConfigs\Pages;

use App\Filament\Resources\SlaConfigs\SlaConfigResource;
use Filament\Resources\Pages\EditRecord;

class EditSlaConfig extends EditRecord
{
    protected static string $resource = SlaConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

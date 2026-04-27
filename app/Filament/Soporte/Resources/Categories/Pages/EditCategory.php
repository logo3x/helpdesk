<?php

namespace App\Filament\Soporte\Resources\Categories\Pages;

use App\Filament\Soporte\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * Defensa de profundidad: si un supervisor manipula el payload
     * para reasignar la categoría a otro depto, lo restauramos al suyo.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        if (! $isAdmin) {
            $data['department_id'] = $this->record->department_id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

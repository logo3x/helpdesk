<?php

namespace App\Filament\Soporte\Resources\Categories\Pages;

use App\Filament\Soporte\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * Para supervisor: forzamos department_id al suyo. El admin sí lo
     * elige libremente desde el form. Validación cruzada por seguridad
     * en caso de payload manipulado.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        if (! $isAdmin) {
            $data['department_id'] = $user?->department_id;
        }

        return $data;
    }
}

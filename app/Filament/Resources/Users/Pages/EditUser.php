<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Sincroniza el rol seleccionado en el form. El campo `roles` está
     * marcado `dehydrated(false)` para que Filament no intente
     * persistirlo como columna, así que hacemos el sync acá.
     */
    protected function afterSave(): void
    {
        $role = $this->data['roles'] ?? null;
        if (! $role) {
            return;
        }

        /** @var User $user */
        $user = $this->record;
        $user->syncRoles([$role]);
    }
}

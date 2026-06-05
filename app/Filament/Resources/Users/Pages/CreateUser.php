<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Sincroniza el rol seleccionado en el form. El campo `roles` del
     * schema está marcado `dehydrated(false)` para que Filament no
     * intente persistirlo como columna del User, así que hacemos el
     * sync acá usando $this->data['roles'].
     */
    protected function afterCreate(): void
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

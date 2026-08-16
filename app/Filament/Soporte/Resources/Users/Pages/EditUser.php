<?php

namespace App\Filament\Soporte\Resources\Users\Pages;

use App\Filament\Soporte\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            $data['department_id'] = $user->department_id;
        }

        return $data;
    }

    /**
     * Sincroniza el rol seleccionado tras guardar. El campo `role` del
     * form es `dehydrated(false)` (no es columna del User), así que
     * leemos de $this->data. Un supervisor puede pasar entre
     * usuario_final y agente_soporte; un agente_soporte (que no debería
     * estar acá) queda forzado a usuario_final.
     */
    protected function afterSave(): void
    {
        $authUser = auth()->user();
        $selected = $this->data['role'] ?? null;

        if (! $selected) {
            return;
        }

        $canPickAgent = $authUser && $authUser->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
        $role = $canPickAgent ? $selected : 'usuario_final';

        // Whitelist para prevenir escalamiento vía payload manipulado.
        if (! in_array($role, ['usuario_final', 'agente_soporte'], true)) {
            $role = 'usuario_final';
        }

        $this->record->syncRoles([$role]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Soporte\Resources\Users\Pages;

use App\Filament\Soporte\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Force department for supervisors (no matter what the form sent)
     * and assign agente_soporte role after the record is created.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $authUser = auth()->user();

        // Supervisors cannot change the department: force their own.
        if ($authUser && ! $authUser->hasAnyRole(['super_admin', 'admin'])) {
            $data['department_id'] = $authUser->department_id;
        }

        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $authUser = auth()->user();

        // Agentes solo pueden crear usuario_final, sin importar lo que llegue en el form.
        $role = ($authUser && $authUser->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']))
            ? ($this->data['role'] ?? 'usuario_final')
            : 'usuario_final';

        $this->record->assignRole($role);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

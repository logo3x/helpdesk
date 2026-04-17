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
        $user = auth()->user();

        // Supervisors cannot change the department: force their own.
        if ($user && ! $user->hasAnyRole(['super_admin', 'admin'])) {
            $data['department_id'] = $user->department_id;
        }

        $data['email_verified_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole('agente_soporte');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

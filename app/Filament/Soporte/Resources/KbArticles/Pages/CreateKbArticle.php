<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use Filament\Resources\Pages\CreateRecord;

class CreateKbArticle extends CreateRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $isSupervisor = $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;

        // Agentes solo crean en Borrador y SIEMPRE en su propio departamento.
        // El form tiene el campo deshabilitado pero revalidamos aquí por
        // seguridad — el payload podría reescribirse vía Livewire.
        if (! $isSupervisor) {
            $data['status'] = 'draft';
            $data['department_id'] = $user?->department_id;
        }

        return $data;
    }

    /**
     * author_id y published_at NO están en $fillable por diseño
     * (para prevenir mass assignment). Se setean vía forceFill después
     * de que el form valida + se crea el registro.
     */
    protected function handleRecordCreation(array $data): KbArticle
    {
        /** @var KbArticle $record */
        $record = static::getModel()::create($data);

        $record->forceFill([
            'author_id' => auth()->id(),
            'published_at' => ($data['status'] ?? null) === 'published' ? now() : null,
        ])->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

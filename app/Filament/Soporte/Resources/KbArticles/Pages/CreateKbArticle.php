<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKbArticle extends CreateRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $isSupervisor = $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;

        $data['author_id'] = $user?->id;

        // Agentes solo crean en Borrador y SIEMPRE en su propio departamento.
        // El form tiene el campo deshabilitado pero validamos también aquí
        // por seguridad (alguien podría bypass vía Livewire request).
        if (! $isSupervisor) {
            $data['status'] = 'draft';
            $data['department_id'] = $user?->department_id;
        }

        // Auto-asignar published_at si el supervisor publica directamente.
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

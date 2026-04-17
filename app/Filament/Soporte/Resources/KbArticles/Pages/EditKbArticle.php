<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditKbArticle extends EditRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function getHeaderActions(): array
    {
        $canManage = auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);

        return [
            DeleteAction::make()->visible(fn () => $canManage),
            ForceDeleteAction::make()->visible(fn () => $canManage),
            RestoreAction::make()->visible(fn () => $canManage),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Non-supervisors cannot change status away from draft
        $user = auth()->user();
        if (! $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            // Keep existing status when agente edits
            $data['status'] = $this->record->status;
        }

        // Auto-assign published_at when transitioning to published
        if (($data['status'] ?? null) === 'published' && empty($this->record->published_at)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
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
        $user = auth()->user();
        $isSupervisor = $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;

        if (! $isSupervisor) {
            // Agente no puede cambiar el depto del artículo ni su status.
            $data['department_id'] = $this->record->department_id;

            // Si el agente edita un artículo que YA estaba publicado,
            // el cambio requiere re-aprobación: vuelve a "draft" y se
            // limpia published_at. El supervisor verá el artículo en la
            // lista de borradores pendientes de aprobar.
            if ($this->record->status === 'published') {
                $data['status'] = 'draft';
                $data['published_at'] = null;

                Notification::make()
                    ->title('Artículo devuelto a Borrador')
                    ->body('Tu cambio quedó registrado pero el artículo debe ser aprobado de nuevo por un supervisor.')
                    ->warning()
                    ->send();
            } else {
                // Mantiene el status actual (ej: draft o archived)
                $data['status'] = $this->record->status;
            }
        }

        // Auto-assign published_at cuando un supervisor publica.
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

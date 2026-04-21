<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditKbArticle extends EditRecord
{
    protected static string $resource = KbArticleResource::class;

    /**
     * Flag interna (no en $fillable) para marcar que este save debe
     * transicionar published_at a null (re-aprobación) o a now().
     */
    protected ?string $publishedAtTransition = null;

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

        $this->publishedAtTransition = null;

        if (! $isSupervisor) {
            // Agente no cambia depto del artículo ni su status.
            $data['department_id'] = $this->record->department_id;

            // Si edita un artículo publicado, vuelve a Borrador para
            // re-aprobación del supervisor.
            if ($this->record->status === 'published') {
                $data['status'] = 'draft';
                $this->publishedAtTransition = 'clear';

                Notification::make()
                    ->title('Artículo devuelto a Borrador')
                    ->body('Tu cambio quedó registrado pero el artículo debe ser aprobado de nuevo por un supervisor.')
                    ->warning()
                    ->send();
            } else {
                $data['status'] = $this->record->status;
            }
        } elseif (($data['status'] ?? null) === 'published' && empty($this->record->published_at)) {
            $this->publishedAtTransition = 'publish';
        }

        return $data;
    }

    /**
     * published_at ya no está en $fillable (anti mass-assignment).
     * Se setea aquí vía forceFill según la transición computada.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        if ($this->publishedAtTransition === 'publish') {
            $record->forceFill(['published_at' => now()])->save();
        } elseif ($this->publishedAtTransition === 'clear') {
            $record->forceFill(['published_at' => null])->save();
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

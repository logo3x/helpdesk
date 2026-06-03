<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\KbArticlePublishedNotification;
use App\Notifications\KbArticleRejectedNotification;
use App\Notifications\KbArticleReviewRequestedNotification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
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
        $user = auth()->user();
        $isSupervisor = $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
        /** @var KbArticle $article */
        $article = $this->getRecord();

        return [
            // ── Solicitar publicación (agente sobre su propio borrador) ──
            // Marca el artículo como "listo para revisar" y notifica a
            // los supervisores del depto. Se oculta cuando ya está
            // pendiente o cuando el usuario es supervisor (ese flujo
            // usa "Aprobar y publicar" directamente).
            Action::make('requestReview')
                ->label('Solicitar publicación')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => ! $isSupervisor
                    && $article->status === 'draft'
                    && $article->pending_review_at === null)
                ->requiresConfirmation()
                ->modalHeading('¿Solicitar publicación de este artículo?')
                ->modalDescription('Se notificará a los supervisores de tu departamento para que lo revisen y publiquen.')
                ->action(function () use ($article, $user): void {
                    $article->forceFill([
                        'pending_review_at' => now(),
                        'pending_review_by_id' => $user?->id,
                    ])->save();

                    $supervisors = User::query()
                        ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_soporte'))
                        ->when(
                            $article->department_id,
                            fn ($q, $deptId) => $q->where('department_id', $deptId)
                        )
                        ->get();

                    foreach ($supervisors as $supervisor) {
                        $supervisor->notify(new KbArticleReviewRequestedNotification($article, $user));
                    }

                    Notification::make()
                        ->title('Solicitud de publicación enviada')
                        ->body($supervisors->isEmpty()
                            ? 'Marcado como pendiente, pero no hay supervisores en tu departamento para notificar.'
                            : "Notificados {$supervisors->count()} supervisor(es) del departamento.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['pending_review_at']);
                }),

            // ── Cancelar solicitud (autor que se arrepiente) ──
            Action::make('cancelReview')
                ->label('Cancelar solicitud')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => ! $isSupervisor
                    && $article->status === 'draft'
                    && $article->pending_review_at !== null
                    && $article->pending_review_by_id === $user?->id)
                ->requiresConfirmation()
                ->action(function () use ($article): void {
                    $article->forceFill([
                        'pending_review_at' => null,
                        'pending_review_by_id' => null,
                    ])->save();

                    Notification::make()
                        ->title('Solicitud cancelada')
                        ->success()
                        ->send();

                    $this->refreshFormData(['pending_review_at']);
                }),

            // ── Aprobar y publicar (supervisor sobre artículo en revisión) ──
            Action::make('approveAndPublish')
                ->label('Aprobar y publicar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $isSupervisor
                    && $article->status === 'draft'
                    && $article->pending_review_at !== null)
                ->requiresConfirmation()
                ->modalHeading('¿Aprobar y publicar este artículo?')
                ->modalDescription('Quedará visible inmediatamente en el chatbot y el centro de ayuda. Se notificará al autor.')
                ->action(function () use ($article, $user): void {
                    $author = $article->author;

                    $article->forceFill([
                        'status' => 'published',
                        'published_at' => now(),
                        'pending_review_at' => null,
                        'pending_review_by_id' => null,
                    ])->save();

                    if ($author) {
                        $author->notify(new KbArticlePublishedNotification($article, $user));
                    }

                    Notification::make()
                        ->title('Artículo publicado')
                        ->body($author
                            ? "Se notificó al autor ({$author->name}) que su artículo ya está disponible."
                            : 'Publicado. El artículo no tiene autor registrado para notificar.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'published_at', 'pending_review_at']);
                }),

            // ── Rechazar con motivo (supervisor sobre artículo en revisión) ──
            // Devuelve el artículo a Borrador puro y notifica al autor
            // con el motivo. El autor puede corregir y volver a solicitar.
            Action::make('rejectReview')
                ->label('Rechazar con motivo')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn () => $isSupervisor
                    && $article->status === 'draft'
                    && $article->pending_review_at !== null)
                ->schema([
                    Textarea::make('reason')
                        ->label('Motivo del rechazo')
                        ->placeholder('Ej: Falta agregar el procedimiento de escalamiento al final.')
                        ->required()
                        ->minLength(10)
                        ->maxLength(500)
                        ->rows(4)
                        ->helperText('El autor verá este mensaje. Sé específico para que sepa qué corregir.'),
                ])
                ->modalHeading('Rechazar solicitud de publicación')
                ->modalDescription('El artículo vuelve a Borrador. Se notifica al autor con el motivo para que pueda corregir y volver a solicitar la publicación.')
                ->action(function (array $data) use ($article, $user): void {
                    $author = $article->author;

                    $article->forceFill([
                        'pending_review_at' => null,
                        'pending_review_by_id' => null,
                    ])->save();

                    if ($author && $user) {
                        $author->notify(new KbArticleRejectedNotification($article, $user, $data['reason']));
                    }

                    Notification::make()
                        ->title('Solicitud rechazada')
                        ->body($author
                            ? "Se notificó al autor ({$author->name}) con el motivo."
                            : 'Devuelto a Borrador. El artículo no tiene autor registrado para notificar.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['pending_review_at']);
                }),

            DeleteAction::make()->visible(fn () => $isSupervisor),
            ForceDeleteAction::make()->visible(fn () => $isSupervisor),
            RestoreAction::make()->visible(fn () => $isSupervisor),
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
     * Si el supervisor publica directo, también limpiamos cualquier
     * pending_review pendiente.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        if ($this->publishedAtTransition === 'publish') {
            $record->forceFill([
                'published_at' => now(),
                'pending_review_at' => null,
                'pending_review_by_id' => null,
            ])->save();
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

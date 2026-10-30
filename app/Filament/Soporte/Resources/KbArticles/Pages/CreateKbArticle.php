<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Concerns\HasAiContentActions;
use App\Filament\Concerns\HasKbAiDraftAction;
use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateKbArticle extends CreateRecord
{
    use HasAiContentActions, HasKbAiDraftAction;

    protected static string $resource = KbArticleResource::class;

    /**
     * Acepta ?title=... en la URL para pre-rellenar el campo título.
     * Usado por el botón "Crear KB con esta pregunta" del reporte de
     * métricas del chatbot — cuando un gap (fallback) aparece muchas
     * veces, el admin lo convierte en artículo con 1 click.
     */
    public function mount(): void
    {
        parent::mount();

        $prefillTitle = request()->query('title');
        if ($prefillTitle) {
            $clean = mb_strimwidth(trim((string) $prefillTitle), 0, 255);
            $this->form->fill([
                ...$this->form->getRawState(),
                'title' => $clean,
                'slug' => Str::slug($clean),
            ]);
        }
    }

    /**
     * Botones de IA: "Redactar con IA" (trait HasKbAiDraftAction) y
     * "Refinar con IA" cuando ya hay contenido en el cuerpo.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->draftKbWithAiAction(),
            $this->refineWithAiAction(),
        ];
    }

    protected function aiAssistantKind(): string
    {
        return 'kb_article';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyAiResult(array $data): void
    {
        $this->form->fill([...$this->form->getRawState(), ...array_filter($data)]);
    }

    protected function currentAiSourceText(): ?string
    {
        return $this->form->getRawState()['body'] ?? null;
    }

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

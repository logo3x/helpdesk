<?php

namespace App\Filament\Concerns;

use App\Models\Department;
use App\Services\LlmService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Botón "Redactar con IA" de los artículos KB (crear y editar).
 * Abre un modal con descripción libre + tono; al enviar, llama al
 * LlmService y rellena título y cuerpo del formulario.
 * Controlado por el feature flag ENABLE_AI_KB_DRAFT.
 */
trait HasKbAiDraftAction
{
    protected function draftKbWithAiAction(): Action
    {
        return Action::make('draftWithAi')
            ->label('✨ Redactar con IA')
            ->icon('heroicon-o-sparkles')
            ->color('info')
            ->visible(fn (): bool => (bool) config('services.llm.kb_drafting_enabled'))
            ->modalHeading('Redactar artículo KB con IA')
            ->modalDescription('Cuéntale al asistente de qué se trata el artículo en tus propias palabras. La IA lo estructurará en Markdown y rellenará el formulario por ti. Siempre podrás editarlo después.')
            ->modalSubmitActionLabel('Generar')
            ->modalWidth('2xl')
            ->schema([
                Textarea::make('natural_language')
                    ->label('Descripción libre')
                    ->placeholder('Ej: "cuando alguien no puede entrar al wifi le pedimos que reinicie el router del piso. si no funciona que venga a TI con el equipo"')
                    ->rows(6)
                    ->required()
                    ->minLength(20)
                    ->maxLength(3000),
                Select::make('tone')
                    ->label('Tono')
                    ->options([
                        'formal' => 'Formal (profesional, apto para todos)',
                        'amigable' => 'Amigable (cercano, usa "tú")',
                        'tecnico' => 'Técnico (audiencia con conocimiento)',
                    ])
                    ->default('formal')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();
                $deptName = $user?->department_id
                    ? Department::find($user->department_id)?->name
                    : null;

                $result = app(LlmService::class)->draftKbArticle(
                    naturalLanguageInput: $data['natural_language'],
                    tone: $data['tone'] ?? 'formal',
                    departmentName: $deptName,
                );

                if ($result === null) {
                    Notification::make()
                        ->title('No se pudo generar el borrador')
                        ->body('El asistente IA no respondió. Verifica que LLM_API_KEY esté configurado o redacta manualmente.')
                        ->danger()
                        ->send();

                    return;
                }

                $state = [
                    ...$this->form->getRawState(),
                    'title' => $result['title'],
                    'body' => $result['body'],
                ];

                // Al editar no se cambia el slug: rompería los enlaces existentes.
                if (! isset($this->record)) {
                    $state['slug'] = Str::slug($result['title']);
                }

                $this->form->fill($state);

                Notification::make()
                    ->title('Borrador generado')
                    ->body('Revisa el contenido, ajústalo si es necesario y guarda.')
                    ->success()
                    ->send();
            });
    }
}

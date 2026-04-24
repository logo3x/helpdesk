<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Models\Department;
use App\Models\KbArticle;
use App\Services\LlmService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateKbArticle extends CreateRecord
{
    protected static string $resource = KbArticleResource::class;

    /**
     * Botón "Redactar con IA" en el header de la página de creación.
     * Abre modal con textarea en lenguaje natural + selector de tono;
     * al enviar, llama al LlmService y rellena title + body del form.
     * Controlado por feature flag ENABLE_AI_KB_DRAFT.
     */
    protected function getHeaderActions(): array
    {
        if (! config('services.llm.kb_drafting_enabled')) {
            return [];
        }

        return [
            Action::make('draftWithAi')
                ->label('✨ Redactar con IA')
                ->icon('heroicon-o-sparkles')
                ->color('info')
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

                    // Aplicar el resultado al form actual
                    $this->form->fill([
                        ...$this->form->getRawState(),
                        'title' => $result['title'],
                        'slug' => Str::slug($result['title']),
                        'body' => $result['body'],
                    ]);

                    Notification::make()
                        ->title('Borrador generado')
                        ->body('Revisa el contenido, ajústalo si es necesario y guarda.')
                        ->success()
                        ->send();
                }),
        ];
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

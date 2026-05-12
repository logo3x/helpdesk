<?php

namespace App\Filament\Concerns;

use App\Services\AiContentAssistant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Acciones reutilizables de generación/refinamiento con IA para los
 * recursos Filament de plantillas de ticket y canned responses.
 *
 * Cada página que use este trait debe implementar:
 *  - `aiAssistantKind()`: 'ticket_template' | 'canned_response'.
 *  - `applyAiResult(array $data)`: copia los campos generados al form.
 *  - `currentAiSourceText()`: texto actual del campo principal para
 *    refinar (description o body). Devolver null si todavía no aplica
 *    (típicamente en Create antes de guardar).
 */
trait HasAiContentActions
{
    /**
     * Acción "Generar con IA" — pide un brief, llama al asistente y
     * pre-rellena el formulario con `subject + description` o
     * `title + body` según el tipo.
     */
    protected function generateWithAiAction(): Action
    {
        return Action::make('aiGenerate')
            ->label('✨ Generar con IA')
            ->color('info')
            ->icon('heroicon-o-sparkles')
            ->modalHeading('Generar contenido con IA')
            ->modalDescription('Describe brevemente la idea y la IA propondrá un borrador. Podrás editarlo antes de guardar.')
            ->modalSubmitActionLabel('Generar')
            ->modalWidth('lg')
            ->schema([
                Textarea::make('brief')
                    ->label('Idea / brief')
                    ->placeholder($this->aiAssistantKind() === 'ticket_template'
                        ? 'Ej: "Solicitud de reseteo de contraseña Outlook"'
                        : 'Ej: "Confirmación de recibido + solicitar marca y modelo de PC"')
                    ->rows(3)
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                $assistant = app(AiContentAssistant::class);

                $brief = trim((string) ($data['brief'] ?? ''));

                $result = match ($this->aiAssistantKind()) {
                    'ticket_template' => $assistant->generateTicketTemplate($brief),
                    'canned_response' => $assistant->generateCannedResponse($brief),
                    default => null,
                };

                if ($result === null) {
                    Notification::make()
                        ->title('No fue posible generar el contenido')
                        ->body('Verifica que la API key del LLM esté configurada (LLM_API_KEY) y reintenta.')
                        ->danger()
                        ->send();

                    return;
                }

                $this->applyAiResult($result);

                Notification::make()
                    ->title('Borrador generado')
                    ->body('Revisa el contenido antes de guardar — siempre puedes refinarlo.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Acción "Refinar con IA" — toma el contenido actual del campo
     * principal + una instrucción y devuelve la versión reescrita.
     * Solo aplica a páginas de Edit donde ya hay contenido para refinar.
     */
    protected function refineWithAiAction(): Action
    {
        return Action::make('aiRefine')
            ->label('🪄 Refinar con IA')
            ->color('gray')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Refinar contenido con IA')
            ->modalDescription('Describe cómo quieres mejorar el texto actual. La IA reescribirá conservando el formato Markdown.')
            ->modalSubmitActionLabel('Refinar')
            ->modalWidth('lg')
            ->visible(fn () => filled($this->currentAiSourceText()))
            ->schema([
                Select::make('preset')
                    ->label('Instrucción rápida')
                    ->options([
                        'shorter' => 'Hazlo más conciso',
                        'formal' => 'Tono más formal',
                        'friendly' => 'Tono más cercano',
                        'fix_grammar' => 'Corregir ortografía y gramática',
                        'add_steps' => 'Estructurar como pasos numerados',
                    ])
                    ->native(false)
                    ->placeholder('— Opcional —')
                    ->live(),
                Textarea::make('instruction')
                    ->label('Instrucción libre (opcional)')
                    ->placeholder('Ej: "Agrega un disclaimer sobre VPN al final"')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                $assistant = app(AiContentAssistant::class);

                $text = $this->currentAiSourceText();

                if (blank($text)) {
                    Notification::make()
                        ->title('No hay contenido para refinar')
                        ->warning()
                        ->send();

                    return;
                }

                $instruction = $this->buildRefineInstruction($data);

                if (blank($instruction)) {
                    Notification::make()
                        ->title('Indica al menos una instrucción')
                        ->warning()
                        ->send();

                    return;
                }

                $refined = $assistant->refine((string) $text, $instruction);

                if ($refined === null) {
                    Notification::make()
                        ->title('No fue posible refinar el contenido')
                        ->body('Verifica la API key del LLM y reintenta.')
                        ->danger()
                        ->send();

                    return;
                }

                $this->applyAiResult($this->refinedPayload($refined));

                Notification::make()
                    ->title('Texto refinado')
                    ->body('Revisa el resultado antes de guardar.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array{preset?: ?string, instruction?: ?string}  $data
     */
    protected function buildRefineInstruction(array $data): string
    {
        $presets = [
            'shorter' => 'Hazlo más conciso pero conserva la información clave.',
            'formal' => 'Reescríbelo con un tono más formal y profesional.',
            'friendly' => 'Reescríbelo con un tono más cercano y empático.',
            'fix_grammar' => 'Corrige errores de ortografía, gramática y puntuación sin cambiar el sentido.',
            'add_steps' => 'Reestructúralo como una lista numerada de pasos cuando aplique.',
        ];

        $parts = [];

        if (! blank($data['preset'] ?? null) && isset($presets[$data['preset']])) {
            $parts[] = $presets[$data['preset']];
        }

        if (! blank($data['instruction'] ?? null)) {
            $parts[] = trim((string) $data['instruction']);
        }

        return implode("\n", $parts);
    }

    /**
     * Genera el payload para `applyAiResult` cuando viene de refine().
     * Cada page mapea el texto refinado al campo correcto.
     *
     * @return array<string, string>
     */
    protected function refinedPayload(string $refinedText): array
    {
        return match ($this->aiAssistantKind()) {
            'ticket_template' => ['description' => $refinedText],
            'canned_response' => ['body' => $refinedText],
            default => [],
        };
    }
}

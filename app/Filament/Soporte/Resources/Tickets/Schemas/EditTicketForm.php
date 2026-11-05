<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Models\Category;
use App\Models\Ticket;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulario reducido para editar un ticket existente.
 *
 * Diferente del form de creación: aquí solo se permite corregir
 * contenido que NO afecta la prioridad ni el SLA:
 *
 *   - asunto
 *   - categoría (acotada al depto actual del ticket)
 *   - descripción (editor Markdown plegado; la versión formateada se
 *     muestra arriba con la misma sección de la página Ver)
 *
 * Para cambiar impacto/urgencia → acción "Recalibrar prioridad".
 * Para cambiar departamento → acción "Trasladar a otro depto.".
 * Para cambiar asignado → acción "Asignar".
 * Para cambiar estado → acciones de workflow (resolver/cerrar/reabrir).
 *
 * Así se mantiene una sola fuente de verdad (el servicio) para cada
 * transición y se evita que una edición directa bypassee el audit
 * trail o recalcule mal el SLA.
 */
class EditTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        $sections = TicketViewInfolist::sections();

        return $schema
            ->columns(1)
            ->components([
                $sections['summary'],

                // Misma descripción que la página Ver y el panel admin
                // (origen, resumen destacado y transcripción plegada).
                $sections['description'],

                Section::make('Contenido del ticket')
                    ->icon('heroicon-o-pencil-square')
                    ->description('Solo para corregir redacción o categoría mal elegida. Prioridad, asignación y traslado se gestionan con los botones de arriba.')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])
                            ->schema([
                                TextInput::make('number')
                                    ->label('Número')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('subject')
                                    ->label('Asunto')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['lg' => 2]),
                            ]),

                        Select::make('category_id')
                            ->label('Categoría')
                            ->helperText('Solo categorías del departamento actual del ticket. Si el ticket está mal clasificado a nivel de depto, usa "Trasladar a otro depto.".')
                            ->options(function (?Ticket $record): array {
                                if (! $record) {
                                    return [];
                                }

                                return Category::query()
                                    ->where('department_id', $record->department_id)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable(),

                        Section::make('Editar la descripción (Markdown)')
                            ->icon('heroicon-o-code-bracket')
                            ->collapsible()
                            ->collapsed()
                            ->compact()
                            ->schema([
                                MarkdownEditor::make('description')
                                    ->hiddenLabel()
                                    ->required()
                                    ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'codeBlock', 'blockquote', 'heading', 'undo', 'redo']),
                            ]),
                    ])
                    ->columnSpanFull(),

                $sections['conversation'],
                $sections['attachments'],
                $sections['sla'],
                $sections['classification'],
            ]);
    }
}

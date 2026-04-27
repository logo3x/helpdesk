<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Models\Category;
use App\Models\Ticket;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Formulario reducido para editar un ticket existente.
 *
 * Diferente del form de creación: aquí solo se permite corregir
 * contenido que NO afecta la prioridad ni el SLA:
 *
 *   - asunto
 *   - descripción
 *   - categoría (acotada al depto actual del ticket)
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
        return $schema
            ->columns(1)
            ->components([
                Section::make('Contenido del ticket')
                    ->description('Solo para corregir redacción o categoría mal elegida. Prioridad, impacto, urgencia, asignación y traslado se gestionan desde las acciones del detalle del ticket.')
                    ->schema([
                        TextInput::make('number')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),

                        MarkdownEditor::make('description')
                            ->label('Descripción')
                            ->required()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'codeBlock', 'blockquote', 'heading', 'undo', 'redo']),

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
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}

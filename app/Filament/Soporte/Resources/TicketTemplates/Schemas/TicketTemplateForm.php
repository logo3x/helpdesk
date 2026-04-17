<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Schemas;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plantilla')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre interno')
                            ->helperText('Nombre con el que los agentes identificarán esta plantilla (no se muestra al usuario).')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('subject')
                            ->label('Asunto del ticket')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        MarkdownEditor::make('description')
                            ->label('Descripción pre-rellenada')
                            ->required()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'codeBlock', 'blockquote', 'heading', 'undo', 'redo'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación pre-asignada')
                    ->schema([
                        Select::make('category_id')
                            ->label('Categoría')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query) {
                                    $user = auth()->user();
                                    if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
                                        $query->where('department_id', $user->department_id);
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Solo se muestran categorías de tu departamento.'),

                        Select::make('impact')
                            ->label('Impacto por defecto')
                            ->options(TicketImpact::class),

                        Select::make('urgency')
                            ->label('Urgencia por defecto')
                            ->options(TicketUrgency::class),

                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}

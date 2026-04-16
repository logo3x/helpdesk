<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CannedResponseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Respuesta')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        MarkdownEditor::make('body')
                            ->label('Contenido')
                            ->required()
                            ->helperText('Admite formato Markdown. Se puede insertar desde un ticket con un click.')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación')
                    ->schema([
                        Select::make('category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_shared')
                            ->label('Compartida con todo el equipo')
                            ->helperText('Si está activo, todos los agentes pueden usar esta respuesta.')
                            ->default(true),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}

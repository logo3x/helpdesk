<?php

namespace App\Filament\Soporte\Resources\KbArticles\Schemas;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class KbArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, TextInput $component) {
                                if ($component->getRecord() !== null) {
                                    return;
                                }

                                $component
                                    ->getContainer()
                                    ->getComponent(fn ($c) => $c instanceof TextInput && $c->getName() === 'slug')
                                    ?->state(Str::slug((string) $state));
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(KbArticle::class, 'slug', ignoreRecord: true)
                            ->helperText('Identificador único en la URL. Se auto-genera desde el título.')
                            ->columnSpanFull(),

                        MarkdownEditor::make('body')
                            ->label('Cuerpo del artículo')
                            ->required()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'codeBlock', 'blockquote', 'heading', 'undo', 'redo'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Clasificación')
                    ->schema([
                        Select::make('kb_category_id')
                            ->label('Categoría KB')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(KbCategory::class, 'slug'),
                            ]),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'published' => 'Publicado',
                                'archived' => 'Archivado',
                            ])
                            ->default('draft')
                            ->required()
                            ->live(),

                        Select::make('visibility')
                            ->label('Visibilidad')
                            ->options([
                                'public' => 'Pública (todos los usuarios)',
                                'internal' => 'Interna (solo agentes)',
                            ])
                            ->default('public')
                            ->required(),

                        DateTimePicker::make('published_at')
                            ->label('Publicado el')
                            ->helperText('Se asigna automáticamente al publicar si está vacío.')
                            ->visible(fn (Get $get) => $get('status') === 'published'),
                    ])
                    ->columns(2),
            ]);
    }
}

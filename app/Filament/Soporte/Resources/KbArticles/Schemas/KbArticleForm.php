<?php

namespace App\Filament\Soporte\Resources\KbArticles\Schemas;

use App\Models\KbArticle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                        Select::make('department_id')
                            ->label('Departamento')
                            ->relationship('department', 'name', fn ($query) => $query->where('is_active', true))
                            ->default(fn () => auth()->user()?->department_id)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('El artículo se asocia al departamento al que pertenece.'),

                        Select::make('status')
                            ->label('Estado')
                            ->options(fn () => static::statusOptionsForUser())
                            ->default('draft')
                            ->required()
                            ->helperText(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])
                                ? 'Puedes publicar o archivar este artículo.'
                                : 'Solo los supervisores pueden publicar o archivar artículos.'),

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
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se asigna automáticamente al publicar.'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Agents can only set Borrador. Supervisors can set any status.
     *
     * @return array<string, string>
     */
    protected static function statusOptionsForUser(): array
    {
        $isSupervisor = auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;

        if ($isSupervisor) {
            return [
                'draft' => 'Borrador',
                'published' => 'Publicado',
                'archived' => 'Archivado',
            ];
        }

        return [
            'draft' => 'Borrador',
        ];
    }
}

<?php

namespace App\Filament\Soporte\Resources\KbArticles\Schemas;

use App\Models\KbArticle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
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

                        Select::make('visibility')
                            ->label('Visibilidad')
                            ->options([
                                'public' => 'Pública (todos los usuarios)',
                                'internal' => 'Interna (solo agentes)',
                            ])
                            ->default('public')
                            ->required(),

                        // ── Estado: solo visible para supervisores+ ─────────
                        // Los agentes crean siempre en Borrador (se fuerza
                        // en CreateKbArticle::mutateFormDataBeforeCreate).
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'published' => 'Publicado',
                                'archived' => 'Archivado',
                            ])
                            ->default('draft')
                            ->required()
                            ->visible(fn () => static::isSupervisor())
                            ->helperText('Como supervisor puedes publicar o archivar este artículo.'),

                        // ── Publicado el: solo visible para supervisores+ ──
                        DateTimePicker::make('published_at')
                            ->label('Publicado el')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn () => static::isSupervisor())
                            ->helperText('Se asigna automáticamente al publicar.'),
                    ])
                    ->columns(2),

                // ── Banner informativo para agentes ─────────────────────
                Section::make()
                    ->schema([
                        Placeholder::make('draft_info')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">'
                                .'<strong>📝 Este artículo se guardará como <em>Borrador</em>.</strong><br>'
                                .'Un supervisor de tu departamento debe revisarlo y publicarlo '
                                .'para que sea visible en la Base de Conocimiento del asistente virtual '
                                .'y el portal del usuario.'
                                .'</div>'
                            )),
                    ])
                    ->visible(fn () => ! static::isSupervisor()),
            ]);
    }

    /**
     * El usuario tiene permiso para elegir status / ver published_at.
     */
    protected static function isSupervisor(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }
}

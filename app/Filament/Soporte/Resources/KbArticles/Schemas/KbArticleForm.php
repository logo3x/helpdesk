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
                            // El agente NO puede cambiar el depto: siempre queda
                            // fijado al suyo. Solo supervisor+ puede elegir.
                            ->disabled(fn () => ! static::isSupervisor())
                            ->dehydrated()
                            ->helperText(fn () => static::isSupervisor()
                                ? 'El artículo se asocia al departamento al que pertenece.'
                                : 'Tu departamento se asigna automáticamente.'),

                        // ── Estado: solo visible para supervisores+ ─────────
                        // Los agentes crean siempre en Borrador (se fuerza
                        // en CreateKbArticle::mutateFormDataBeforeCreate)
                        // y un supervisor debe aprobarlo pasándolo a
                        // Publicado. Al estar Publicado, queda visible
                        // en el chatbot (RagService) y en /portal.
                        Select::make('status')
                            ->label('Estado')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Fase del ciclo de vida. · Borrador: en construcción, solo staff lo ve en /soporte. · Publicado: visible en el chatbot y en /portal para todos los empleados. · Archivado: ya no aplica, se conserva por histórico. Los agentes solo pueden dejarlo en Borrador; un supervisor debe Publicarlo.')
                            ->options([
                                'draft' => 'Borrador',
                                'published' => 'Publicado',
                                'archived' => 'Archivado',
                            ])
                            ->default('draft')
                            ->required()
                            ->visible(fn () => static::isSupervisor()),

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

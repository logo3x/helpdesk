<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\TicketTemplate;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Form de creación de tickets en /soporte. Pensado para que el agente
 * o supervisor pueda llenarlo de arriba abajo siguiendo preguntas
 * naturales:
 *
 *   1. ¿Hay plantilla que sirva?         → autocompleta el resto.
 *   2. ¿Cuál es el problema?             → asunto + descripción + adjuntos.
 *   3. ¿Para quién y de qué área?        → solicitante + categoría.
 *   4. ¿Qué tan crítico es?              → impacto × urgencia → prioridad.
 *
 * Diferencias con TicketForm (que se sigue usando en /admin para vista
 * legacy): aquí no hay campo `number`, ni `status`, ni `assigned_to_id`
 * (la asignación se hace después con las acciones del detalle), y no
 * hay sección "Tiempos" (irrelevante al crear).
 */
class CreateTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // ── Plantilla opcional ─────────────────────────────────
                Section::make('¿Hay una plantilla que sirva?')
                    ->description('Las plantillas auto-llenan asunto, descripción, categoría e impacto/urgencia para casos repetitivos. Puedes editarlo todo después.')
                    ->icon('heroicon-o-document-duplicate')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Select::make('_template_id')
                            ->label('Usar plantilla')
                            ->options(function () {
                                $user = auth()->user();
                                $query = TicketTemplate::query()->where('is_active', true);

                                if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
                                    $query->whereHas('category', fn ($q) => $q->where('department_id', $user->department_id));
                                }

                                return $query->orderBy('sort_order')->pluck('name', 'id')->all();
                            })
                            ->dehydrated(false)
                            ->live()
                            ->searchable()
                            ->placeholder('— Sin plantilla —')
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $template = TicketTemplate::find($state);
                                if (! $template) {
                                    return;
                                }

                                $set('subject', $template->subject);
                                $set('description', $template->description);
                                $category = $template->category;
                                if ($category) {
                                    $set('category_id', $category->id);
                                    $set('department_id', $category->department_id);
                                }

                                $impactEnum = $template->impact instanceof TicketImpact
                                    ? $template->impact
                                    : ($template->impact ? TicketImpact::from($template->impact) : null);
                                $urgencyEnum = $template->urgency instanceof TicketUrgency
                                    ? $template->urgency
                                    : ($template->urgency ? TicketUrgency::from($template->urgency) : null);

                                if ($impactEnum) {
                                    $set('impact', $impactEnum->value);
                                }
                                if ($urgencyEnum) {
                                    $set('urgency', $urgencyEnum->value);
                                }
                                if ($impactEnum && $urgencyEnum) {
                                    $set('priority', TicketPriority::fromMatrix($impactEnum, $urgencyEnum)->value);
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── ¿Cuál es el problema? ──────────────────────────────
                Section::make('¿Cuál es el problema?')
                    ->description('Resume el problema con un asunto corto y luego describe el detalle con todo el contexto que tengas.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Asunto')
                            ->placeholder('Ej: "No puedo enviar correos externos"')
                            ->required()
                            ->minLength(5)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        MarkdownEditor::make('description')
                            ->label('Descripción')
                            ->helperText('Explica qué pasa, cuándo empezó, qué error viste y qué intentaste. Admite Markdown.')
                            ->required()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'codeBlock', 'blockquote', 'heading', 'undo', 'redo'])
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->label('Adjuntos')
                            ->helperText('Capturas, logs, PDFs. Hasta 10 archivos de 10 MB cada uno.')
                            ->collection('attachments')
                            ->multiple()
                            ->maxFiles(10)
                            ->maxSize(10240)
                            ->acceptedFileTypes([
                                'image/*', 'application/pdf',
                                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain', 'text/csv',
                                'application/zip', 'application/x-rar-compressed',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── ¿Para quién y de qué área? ─────────────────────────
                Section::make('¿Para quién y de qué área?')
                    ->description('Quién reporta el problema y a qué categoría/depto pertenece. El depto se deduce de la categoría.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('requester_id')
                                    ->label('Solicitante')
                                    ->relationship('requester', 'name')
                                    ->searchable(['name', 'email'])
                                    ->preload()
                                    ->required()
                                    ->placeholder('Buscar usuario...'),

                                Select::make('department_id')
                                    ->label('Departamento')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->placeholder('Selecciona depto'),

                                Select::make('category_id')
                                    ->label('Categoría')
                                    ->options(fn (Get $get): array => Category::query()
                                        ->when($get('department_id'), fn ($q, $dep) => $q->where('department_id', $dep))
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->live()
                                    ->placeholder('Elige categoría'),
                            ]),
                    ])
                    ->columnSpanFull(),

                // ── ¿Qué tan crítico es? ───────────────────────────────
                Section::make('¿Qué tan crítico es?')
                    ->description('Impacto = a quién afecta. Urgencia = qué tan rápido se necesita. La prioridad se calcula automáticamente con la matriz ITIL.')
                    ->icon('heroicon-o-fire')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('impact')
                                    ->label('Impacto')
                                    ->options(TicketImpact::class)
                                    ->default(TicketImpact::Medio)
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(self::recomputePriority(...))
                                    ->helperText('Bajo · Medio · Alto'),

                                Select::make('urgency')
                                    ->label('Urgencia')
                                    ->options(TicketUrgency::class)
                                    ->default(TicketUrgency::Media)
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(self::recomputePriority(...))
                                    ->helperText('Baja · Media · Alta'),

                                Select::make('priority')
                                    ->label('Prioridad (calculada)')
                                    ->options(TicketPriority::class)
                                    ->default(TicketPriority::Media)
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Determina el SLA aplicable'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Mantiene "priority" sincronizado con impact × urgency.
     */
    protected static function recomputePriority(Get $get, Set $set): void
    {
        $impact = $get('impact');
        $urgency = $get('urgency');

        if (blank($impact) || blank($urgency)) {
            return;
        }

        $set('priority', TicketPriority::fromMatrix(
            $impact instanceof TicketImpact ? $impact : TicketImpact::from($impact),
            $urgency instanceof TicketUrgency ? $urgency : TicketUrgency::from($urgency),
        )->value);
    }
}

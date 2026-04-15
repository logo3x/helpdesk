<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        TextInput::make('number')
                            ->label('Número')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Identificador único del ticket. Formato TK-YYYY-NNNNN. Se asigna automáticamente al guardar y reinicia el contador cada año.')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('TK-YYYY-NNNNN (se asigna al guardar)'),

                        TextInput::make('subject')
                            ->label('Asunto')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Un resumen corto del problema, idealmente entre 5 y 100 caracteres. Ej: "No puedo enviar correos externos".')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Explica el problema con detalle: cuándo empezó, qué estabas haciendo, qué mensaje de error viste y qué has intentado. A mayor detalle, más rápido se resuelve.')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Clasificación')
                    ->schema([
                        Select::make('impact')
                            ->label('Impacto')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Alcance del problema: Bajo (solo al solicitante), Medio (al equipo o área), Alto (a toda la empresa u operación crítica).')
                            ->options(TicketImpact::class)
                            ->default(TicketImpact::Medio)
                            ->live()
                            ->required()
                            ->afterStateUpdated(self::recomputePriority(...)),

                        Select::make('urgency')
                            ->label('Urgencia')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Qué tan rápido se necesita solución: Baja (cuando se pueda), Media (esta semana), Alta (hoy mismo o bloquea trabajo).')
                            ->options(TicketUrgency::class)
                            ->default(TicketUrgency::Media)
                            ->live()
                            ->required()
                            ->afterStateUpdated(self::recomputePriority(...)),

                        Select::make('priority')
                            ->label('Prioridad (matriz)')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Calculada automáticamente desde Impacto × Urgencia según la matriz ITIL. Determina el SLA aplicable al ticket.')
                            ->options(TicketPriority::class)
                            ->default(TicketPriority::Media)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Se calcula desde impacto × urgencia.'),

                        Select::make('status')
                            ->label('Estado')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Estado del ciclo de vida: Nuevo → Asignado → En progreso → Resuelto → Cerrado. Los tickets nuevos siempre inician en Nuevo.')
                            ->options(TicketStatus::class)
                            ->default(TicketStatus::Nuevo)
                            ->required()
                            ->disabledOn('create'),
                    ])
                    ->columns(2),

                Section::make('Partes y asignación')
                    ->schema([
                        Select::make('requester_id')
                            ->label('Solicitante')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'La persona que reporta el problema. Es quien recibe las notificaciones de avance y la encuesta de satisfacción al cerrar.')
                            ->relationship('requester', 'name')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required(),

                        Select::make('assigned_to_id')
                            ->label('Asignado a')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Agente responsable de resolver el ticket. Puede dejarse vacío inicialmente; el supervisor puede asignarlo después.')
                            ->options(fn () => User::query()
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                                    'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
                                ]))
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->placeholder('Sin asignar'),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Área responsable del ticket (TI, RRHH, Compras...). Determina el SLA aplicable y el grupo de soporte que atiende.')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('category_id')
                            ->label('Categoría')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Tipo específico de solicitud dentro del departamento (ej: Hardware, Correo y Teams, Nómina). Las opciones cambian según el departamento seleccionado.')
                            ->options(fn (Get $get): array => Category::query()
                                ->when($get('department_id'), fn ($q, $dep) => $q->where('department_id', $dep))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Adjuntos')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->label('Archivos adjuntos')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Imágenes, PDFs, Word, Excel, CSV, ZIP o RAR. Capturas de pantalla y logs ayudan mucho a diagnosticar el problema. Máximo 10 archivos, 10 MB cada uno.')
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
                    ->collapsible(),

                Section::make('Tiempos')
                    ->schema([
                        DateTimePicker::make('first_responded_at')->label('Primera respuesta'),
                        DateTimePicker::make('resolved_at')->label('Resuelto en'),
                        DateTimePicker::make('closed_at')->label('Cerrado en'),
                        DateTimePicker::make('reopened_at')->label('Reabierto en'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->hiddenOn('create'),

                Hidden::make('requester_id')->dehydrated(false),
            ]);
    }

    /**
     * Keep the "priority" field in sync with impact × urgency.
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

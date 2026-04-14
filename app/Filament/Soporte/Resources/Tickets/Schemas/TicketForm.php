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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
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
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('TK-YYYY-NNNNN (se asigna al guardar)'),

                        TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Clasificación')
                    ->schema([
                        Select::make('impact')
                            ->label('Impacto')
                            ->options(TicketImpact::class)
                            ->default(TicketImpact::Medio)
                            ->live()
                            ->required()
                            ->afterStateUpdated(self::recomputePriority(...)),

                        Select::make('urgency')
                            ->label('Urgencia')
                            ->options(TicketUrgency::class)
                            ->default(TicketUrgency::Media)
                            ->live()
                            ->required()
                            ->afterStateUpdated(self::recomputePriority(...)),

                        Select::make('priority')
                            ->label('Prioridad (matriz)')
                            ->options(TicketPriority::class)
                            ->default(TicketPriority::Media)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Se calcula desde impacto × urgencia.'),

                        Select::make('status')
                            ->label('Estado')
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
                            ->relationship('requester', 'name')
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required(),

                        Select::make('assigned_to_id')
                            ->label('Asignado a')
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
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('category_id')
                            ->label('Categoría')
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

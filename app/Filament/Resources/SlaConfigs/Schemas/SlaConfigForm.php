<?php

namespace App\Filament\Resources\SlaConfigs\Schemas;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Services\SlaService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SlaConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Política')
                    ->description('Cada combinación de departamento y prioridad tiene una sola política.')
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn () => Department::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('priority')
                            ->label('Prioridad')
                            ->options(TicketPriority::class)
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('department_id', $get('department_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Ya existe una política SLA para este departamento y prioridad.',
                            ]),
                    ]),

                Section::make('Tiempos (minutos hábiles)')
                    ->description('Se cuentan de lunes a viernes, de 8:00 a 18:00. 60 = 1 h y 600 = 1 día hábil. El tiempo en «Pendiente cliente» no cuenta.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_response_minutes')
                            ->label('Primera respuesta')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Tiempo máximo para la primera respuesta pública del agente.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->suffix('min')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(fn ($state): ?string => is_numeric($state) ? 'Equivale a '.SlaService::formatMinutes((int) $state).' hábiles.' : null),

                        TextInput::make('resolution_minutes')
                            ->label('Resolución')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Tiempo máximo para dejar el ticket resuelto. Si se supera, el ticket queda con SLA incumplido.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->gte('first_response_minutes')
                            ->suffix('min')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(fn ($state): ?string => is_numeric($state) ? 'Equivale a '.SlaService::formatMinutes((int) $state).' hábiles.' : null)
                            ->validationMessages([
                                'gte' => 'La resolución no puede ser menor que la primera respuesta.',
                            ]),
                    ]),

                Toggle::make('is_active')
                    ->label('Política activa')
                    ->helperText('Si está inactiva, los tickets nuevos de este departamento y prioridad no tendrán SLA.')
                    ->default(true),
            ])
            ->columns(1);
    }
}

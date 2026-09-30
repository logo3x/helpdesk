<?php

namespace App\Filament\Resources\SatisfactionSurveys\Tables;

use App\Filament\Concerns\HasHelpLabels;
use App\Models\Department;
use App\Models\SatisfactionSurvey;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabla de encuestas de satisfacción, compartida por /admin y /soporte.
 *
 * Los filtros se muestran sobre la tabla (como en el Reporte SLA) y el
 * widget de estadísticas de la página se recalcula con ellos.
 */
class SatisfactionSurveysTable
{
    use HasHelpLabels;

    /**
     * Marca que AutoMarkSurveysPositiveJob agrega al comentario.
     */
    public const AUTO_POSITIVE_MARK = '(auto-positiva:';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket.number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('ticket.subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (SatisfactionSurvey $record): ?string => $record->ticket?->subject),

                TextColumn::make('user.name')
                    ->label(static::helpLabel('Usuario', 'Solicitante del ticket: quien recibe y responde la encuesta.'))
                    ->searchable(),

                TextColumn::make('ticket.assignee.name')
                    ->label(static::helpLabel('Atendido por', 'Agente asignado al ticket, es decir, la persona evaluada en la encuesta.'))
                    ->searchable()
                    ->placeholder('Sin asignar'),

                TextColumn::make('ticket.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('rating')
                    ->label(static::helpLabel('Calificación', 'Promedio redondeado de las 6 preguntas (1 a 5). Las auto-positivas quedan en 5.'))
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => $state ? str_repeat('★', $state).str_repeat('☆', 5 - $state)." {$state}" : '—')
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->placeholder('—'),

                TextColumn::make('origin')
                    ->label(static::helpLabel('Origen', 'Usuario: la respondió el solicitante. Auto-positiva: no respondió a tiempo y el sistema la marcó con 5★. Pendiente: aún sin respuesta.'))
                    ->state(fn (SatisfactionSurvey $record): string => static::originOf($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Usuario' => 'success',
                        'Auto-positiva' => 'info',
                        default => 'gray',
                    }),

                ...collect(SatisfactionSurvey::DIMENSIONS)->map(
                    fn (string $label, string $field) => TextColumn::make($field)
                        ->label($label)
                        ->sortable()
                        ->alignCenter()
                        ->placeholder('—')
                        ->color(fn (?int $state): ?string => $state !== null && $state <= 2 ? 'danger' : null)
                        ->toggleable(isToggledHiddenByDefault: true),
                )->values()->all(),

                TextColumn::make('comment')
                    ->label('Comentario')
                    ->limit(60)
                    ->tooltip(fn (SatisfactionSurvey $record): ?string => $record->comment)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('responded_at')
                    ->label('Respondida')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Pendiente'),

                TextColumn::make('created_at')
                    ->label(static::helpLabel('Enviada', 'La encuesta se envía al solicitante cuando el ticket se cierra.'))
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('sent_between')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Enviada desde')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Fecha en que se envió la encuesta (cierre del ticket).'),
                        DatePicker::make('until')
                            ->label('Enviada hasta'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime($data['from']));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime($data['until']));
                        }

                        return $indicators;
                    }),

                Filter::make('department')
                    ->schema([
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn () => Department::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Todos'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['department_id'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('ticket', fn ($t) => $t->where('department_id', $id))
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['department_id'] ?? null)
                        ? 'Depto: '.Department::find($data['department_id'])?->name
                        : null),

                Filter::make('agent')
                    ->schema([
                        Select::make('agent_id')
                            ->label('Atendido por')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Agente asignado al ticket evaluado.')
                            ->options(fn () => User::query()
                                ->whereIn('id', fn ($q) => $q->select('assigned_to_id')->from('tickets')->whereNotNull('assigned_to_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Todos'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['agent_id'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('ticket', fn ($t) => $t->where('assigned_to_id', $id))
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['agent_id'] ?? null)
                        ? 'Agente: '.User::find($data['agent_id'])?->name
                        : null),

                Filter::make('origin')
                    ->schema([
                        Select::make('origin')
                            ->label('Estado / origen')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Auto-positiva: el solicitante no respondió en el plazo configurado y el sistema la marcó con 5★ sin calificar las dimensiones.')
                            ->options([
                                'user' => 'Respondida por el usuario',
                                'auto' => 'Auto-positiva',
                                'pending' => 'Pendiente',
                            ])
                            ->placeholder('Todas'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['origin'] ?? null) {
                        'user' => $query->whereNotNull('responded_at')->where(fn (Builder $q) => $q
                            ->whereNull('comment')
                            ->orWhere('comment', 'not like', '%'.self::AUTO_POSITIVE_MARK.'%')),
                        'auto' => $query->whereNotNull('responded_at')->where('comment', 'like', '%'.self::AUTO_POSITIVE_MARK.'%'),
                        'pending' => $query->whereNull('responded_at'),
                        default => $query,
                    })
                    ->indicateUsing(fn (array $data): ?string => match ($data['origin'] ?? null) {
                        'user' => 'Respondidas por el usuario',
                        'auto' => 'Auto-positivas',
                        'pending' => 'Pendientes',
                        default => null,
                    }),

                Filter::make('rating_range')
                    ->schema([
                        Select::make('rating_min')
                            ->label('Calificación mínima')
                            ->options(static::ratingOptions())
                            ->placeholder('Cualquiera'),
                        Select::make('rating_max')
                            ->label('Calificación máxima')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Por ejemplo, un máximo de 2 muestra solo las encuestas insatisfechas.')
                            ->options(static::ratingOptions())
                            ->placeholder('Cualquiera'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['rating_min'] ?? null, fn (Builder $q, $min) => $q->where('rating', '>=', (int) $min))
                        ->when($data['rating_max'] ?? null, fn (Builder $q, $max) => $q->where('rating', '<=', (int) $max)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['rating_min'] ?? null) {
                            $indicators[] = 'Calificación ≥ '.$data['rating_min'];
                        }

                        if ($data['rating_max'] ?? null) {
                            $indicators[] = 'Calificación ≤ '.$data['rating_max'];
                        }

                        return $indicators;
                    }),

                Filter::make('low_dimension')
                    ->schema([
                        Toggle::make('isActive')
                            ->label('Con alguna pregunta en 1 o 2')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Encuestas donde al menos una de las 6 preguntas tuvo 1 o 2 estrellas, aunque el promedio sea bueno.'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['isActive'] ?? false,
                        fn (Builder $q) => $q->where(function (Builder $w) {
                            foreach (array_keys(SatisfactionSurvey::DIMENSIONS) as $field) {
                                $w->orWhere($field, '<=', 2);
                            }
                        })
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['isActive'] ?? false) ? 'Con pregunta en 1 o 2' : null),

                Filter::make('with_comment')
                    ->schema([
                        Toggle::make('isActive')
                            ->label('Solo con comentario')
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Encuestas donde el usuario dejó un comentario escrito (no cuenta la nota automática de auto-positiva).'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['isActive'] ?? false,
                        fn (Builder $q) => $q->whereNotNull('comment')
                            ->where('comment', '!=', '')
                            ->where('comment', 'not like', '(auto-positiva:%')
                    ))
                    ->indicateUsing(fn (array $data): ?string => ($data['isActive'] ?? false) ? 'Con comentario' : null),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function originOf(SatisfactionSurvey $survey): string
    {
        if ($survey->isPending()) {
            return 'Pendiente';
        }

        return str_contains((string) $survey->comment, self::AUTO_POSITIVE_MARK) ? 'Auto-positiva' : 'Usuario';
    }

    /**
     * @return array<string, string>
     */
    protected static function ratingOptions(): array
    {
        return [
            '1' => '★ 1 – Muy insatisfecho',
            '2' => '★★ 2',
            '3' => '★★★ 3 – Regular',
            '4' => '★★★★ 4',
            '5' => '★★★★★ 5 – Muy satisfecho',
        ];
    }
}

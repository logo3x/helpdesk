<?php

namespace App\Filament\Resources\SatisfactionSurveys\Tables;

use App\Models\Department;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SatisfactionSurveysTable
{
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
                    ->limit(50),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),

                TextColumn::make('ticket.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('rating')
                    ->label('Promedio')
                    ->sortable(),

                TextColumn::make('responded_at')
                    ->label('Respondida')
                    ->since()
                    ->sortable()
                    ->placeholder('Pendiente'),

                TextColumn::make('created_at')
                    ->label('Enviada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('department')
                    ->label('Departamento')
                    ->form([
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(Department::orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Todos'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['department_id'] ?? null,
                            fn (Builder $q, $id) => $q->whereHas('ticket', fn ($q) => $q->where('department_id', $id))
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['department_id'] ?? null)) {
                            return null;
                        }

                        return 'Depto: '.Department::find($data['department_id'])?->name;
                    }),

                Filter::make('rating_range')
                    ->label('Calificación')
                    ->form([
                        Select::make('rating_min')
                            ->label('Calificación mínima')
                            ->options([
                                '1' => '★ 1 – Muy insatisfecho',
                                '2' => '★★ 2',
                                '3' => '★★★ 3 – Regular',
                                '4' => '★★★★ 4',
                                '5' => '★★★★★ 5 – Muy satisfecho',
                            ])
                            ->placeholder('Cualquiera'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['rating_min'] ?? null,
                            fn (Builder $q, $min) => $q->where('rating', '>=', (float) $min)
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['rating_min'] ?? null)) {
                            return null;
                        }

                        return 'Calificación ≥ '.$data['rating_min'];
                    }),

                Filter::make('pending')
                    ->label('Solo pendientes')
                    ->query(fn (Builder $q) => $q->whereNull('responded_at'))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

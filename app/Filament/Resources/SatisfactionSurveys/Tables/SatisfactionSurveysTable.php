<?php

namespace App\Filament\Resources\SatisfactionSurveys\Tables;

use App\Models\Department;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SatisfactionSurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with([
                'ticket:id,number,subject,department_id',
                'ticket.department:id,name',
                'user:id,name',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ticket.number')
                    ->label('Ticket')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(function ($record) {
                        if (! $record->ticket_id) {
                            return null;
                        }
                        try {
                            return route('filament.soporte.resources.tickets.view', $record->ticket_id);
                        } catch (\Throwable) {
                            try {
                                return route('filament.admin.resources.tickets.view', $record->ticket_id);
                            } catch (\Throwable) {
                                return null;
                            }
                        }
                    }),

                TextColumn::make('ticket.subject')
                    ->label('Asunto')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->ticket?->subject),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable(),

                TextColumn::make('ticket.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('rating')
                    ->label('Promedio')
                    ->formatStateUsing(function ($state, $record) {
                        $avg = $record->averageRating();
                        if ($avg === null) {
                            return '—';
                        }

                        return number_format($avg, 2).'/5';
                    })
                    ->icon(fn ($record) => match (true) {
                        $record->isPending() => 'heroicon-o-clock',
                        ($record->averageRating() ?? 0) >= 4 => 'heroicon-s-star',
                        ($record->averageRating() ?? 0) >= 3 => 'heroicon-o-star',
                        default => 'heroicon-o-x-circle',
                    })
                    ->iconColor(fn ($record) => match (true) {
                        $record->isPending() => 'warning',
                        ($record->averageRating() ?? 0) >= 4 => 'success',
                        ($record->averageRating() ?? 0) >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('responded_at')
                    ->label('Respondida')
                    ->since()
                    ->sortable()
                    ->placeholder('Pendiente')
                    ->tooltip(fn ($record) => $record->responded_at?->format('Y-m-d H:i')),

                TextColumn::make('created_at')
                    ->label('Enviada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('responded')
                    ->label('Solo respondidas')
                    ->query(fn (Builder $q) => $q->whereNotNull('responded_at'))
                    ->toggle(),

                Filter::make('pending')
                    ->label('Solo pendientes')
                    ->query(fn (Builder $q) => $q->whereNull('responded_at'))
                    ->toggle(),

                SelectFilter::make('department')
                    ->label('Departamento')
                    ->options(fn () => Department::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['value'],
                        fn ($q, $v) => $q->whereHas('ticket', fn ($tq) => $tq->where('department_id', $v)),
                    )),

                SelectFilter::make('rating_range')
                    ->label('Calificación promedio')
                    ->options([
                        'high' => 'Alta (≥ 4)',
                        'mid' => 'Media (3)',
                        'low' => 'Baja (≤ 2)',
                    ])
                    ->query(function (Builder $q, array $data) {
                        return match ($data['value'] ?? null) {
                            'high' => $q->where('rating', '>=', 4),
                            'mid' => $q->where('rating', 3),
                            'low' => $q->where('rating', '<=', 2),
                            default => $q,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Soporte\Resources\Tickets\Tables;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->subject),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge(),

                TextColumn::make('requester.name')
                    ->label('Solicitante')
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label('Asignado a')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i')),

                TextColumn::make('resolved_at')
                    ->label('Resuelto')
                    ->since()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(TicketStatus::class)
                    ->multiple(),

                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(TicketPriority::class)
                    ->multiple(),

                SelectFilter::make('impact')
                    ->label('Impacto')
                    ->options(TicketImpact::class),

                SelectFilter::make('urgency')
                    ->label('Urgencia')
                    ->options(TicketUrgency::class),

                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(fn () => Department::where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->options(fn () => Category::where('is_active', true)->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('assigned_to_id')
                    ->label('Asignado a')
                    ->options(fn () => User::query()
                        ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                            'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
                        ]))
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),

                Filter::make('only_open')
                    ->label('Solo abiertos')
                    ->query(fn (Builder $query) => $query->open())
                    ->default(),

                Filter::make('assigned_to_me')
                    ->label('Asignados a mí')
                    ->query(fn (Builder $query) => $query->where('assigned_to_id', auth()->id())),

                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Soporte\Resources\Tickets\Tables;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Filament\Soporte\Resources\Tickets\Schemas\TicketViewInfolist;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager loading para evitar N+1 al renderizar las columnas
            // que leen requester.name, assignee.name, department.name, category.name
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'requester:id,name',
                'assignee:id,name',
                'department:id,name',
                'category:id,name',
                'satisfactionSurvey',
            ]))
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

                TextColumn::make('satisfactionSurvey.rating')
                    ->label('Encuesta')
                    ->icon(fn ($record) => match (true) {
                        $record->satisfactionSurvey === null => 'heroicon-o-minus',
                        $record->satisfactionSurvey->isPending() => 'heroicon-o-clock',
                        default => 'heroicon-o-star',
                    })
                    ->iconColor(fn ($record) => match (true) {
                        $record->satisfactionSurvey === null => 'gray',
                        $record->satisfactionSurvey->isPending() => 'warning',
                        $record->satisfactionSurvey->rating >= 4 => 'success',
                        $record->satisfactionSurvey->rating === 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state, $record) => match (true) {
                        $record->satisfactionSurvey === null => '—',
                        $record->satisfactionSurvey->isPending() => 'Pendiente',
                        default => str_repeat('★', (int) $state).' ('.$state.'/5)',
                    })
                    ->tooltip(fn ($record) => $record->satisfactionSurvey?->comment)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
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

                Filter::make('survey_pending')
                    ->label('Encuesta pendiente')
                    ->query(fn (Builder $q) => $q->whereHas('satisfactionSurvey', fn ($sq) => $sq->whereNull('responded_at')))
                    ->toggle(),

                Filter::make('survey_responded')
                    ->label('Encuesta respondida')
                    ->query(fn (Builder $q) => $q->whereHas('satisfactionSurvey', fn ($sq) => $sq->whereNotNull('responded_at')))
                    ->toggle(),

                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('viewSurvey')
                    ->label(fn ($record) => $record->satisfactionSurvey?->isPending() ? 'Encuesta pendiente' : 'Ver encuesta')
                    ->icon('heroicon-o-star')
                    ->color(fn ($record) => $record->satisfactionSurvey?->isPending() ? 'warning' : 'success')
                    ->tooltip(fn ($record) => $record->satisfactionSurvey?->isPending()
                        ? 'El usuario aún no ha respondido la encuesta'
                        : 'Ver resultado de encuesta de satisfacción')
                    ->visible(fn ($record) => $record->satisfactionSurvey !== null)
                    ->modalHeading(fn ($record) => "Encuesta — Ticket {$record->number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn ($record) => view('filament.soporte.tickets.survey-modal', [
                        'survey' => $record->satisfactionSurvey,
                        'ticket' => $record,
                    ])),
                // "Ver" abre una lectura rápida en modal (misma vista en filas
                // que la página del ticket), sin salir de la lista.
                // Acción propia (no ViewAction) porque Filament convierte
                // ViewAction en enlace cuando existe la página del ticket.
                Action::make('quickView')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalHeading(fn (Ticket $record): string => "Ticket {$record->number} — {$record->subject}")
                    ->modalWidth(Width::FiveExtraLarge)
                    ->schema(fn (Schema $schema): Schema => TicketViewInfolist::configure($schema)),
                EditAction::make(),
            ])
            // Clic en la fila: Editar para supervisor/admin. Los agentes no
            // editan el contenido, así que van a la página del ticket
            // (donde responden y gestionan el caso).
            // La tabla se comparte con el panel admin, que no tiene página
            // de edición: se usa el resource del panel actual.
            ->recordUrl(function (Ticket $record, $livewire): string {
                $resource = $livewire::getResource();
                $canEdit = $resource::hasPage('edit')
                    && auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);

                return $resource::getUrl($canEdit ? 'edit' : 'view', ['record' => $record]);
            })
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->label('Exportar Excel'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

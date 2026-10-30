<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\TicketComment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (Activity $record): string => $record->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->description(fn (Activity $record): ?string => $record->causer instanceof User
                        ? $record->causer->getRoleNames()->implode(', ')
                        : ($record->properties?->get('email') ? 'Correo usado: '.$record->properties->get('email') : null))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHasMorph('causer', [User::class], fn (Builder $q) => $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))),

                TextColumn::make('event')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityLogResource::EVENTS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'login' => 'success',
                        'logout' => 'gray',
                        'failed', 'deleted' => 'danger',
                        'created' => 'info',
                        'updated' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('log_name')
                    ->label('Módulo')
                    ->formatStateUsing(fn (?string $state): string => ActivityLogResource::MODULES[$state] ?? (string) $state),

                TextColumn::make('subject_type')
                    ->label('Registro afectado')
                    ->formatStateUsing(fn (Activity $record): string => self::subjectLabel($record))
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('description')
                    ->label('Detalle')
                    ->searchable()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('properties.ip')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('causer')
                    ->label('Usuario')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $id) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id),
                    )),

                SelectFilter::make('role')
                    ->label('Rol del usuario')
                    ->multiple()
                    ->options(fn () => Role::orderBy('name')->pluck('name', 'name'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? [],
                        fn (Builder $q, array $roles) => $q->whereHasMorph('causer', [User::class], fn (Builder $u) => $u->role($roles)),
                    )),

                SelectFilter::make('event')
                    ->label('Acción')
                    ->multiple()
                    ->options(ActivityLogResource::EVENTS),

                SelectFilter::make('log_name')
                    ->label('Módulo')
                    ->multiple()
                    ->options(ActivityLogResource::MODULES),

                SelectFilter::make('subject_type')
                    ->label('Tipo de registro')
                    ->options(ActivityLogResource::SUBJECT_TYPES),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        TimePicker::make('from_time')->label('Hora desde')->seconds(false),
                        DatePicker::make('until')->label('Hasta'),
                        TimePicker::make('until_time')->label('Hora hasta')->seconds(false),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', $date.' '.($data['from_time'] ?? '00:00')))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', $date.' '.($data['until_time'] ?? '23:59').':59')))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime($data['from'])).' '.($data['from_time'] ?? '');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime($data['until'])).' '.($data['until_time'] ?? '');
                        }

                        return $indicators;
                    }),

                Filter::make('details')
                    ->schema([
                        TextInput::make('ip')->label('IP'),
                        TextInput::make('subject_id')->label('ID del registro')->numeric(),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['ip'] ?? null, fn (Builder $q, $ip) => $q->where('properties->ip', 'like', "%{$ip}%"))
                        ->when($data['subject_id'] ?? null, fn (Builder $q, $id) => $q->where('subject_id', $id)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['ip'] ?? null) ? 'IP: '.$data['ip'] : null,
                        ($data['subject_id'] ?? null) ? 'Registro #'.$data['subject_id'] : null,
                    ]))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('details')
                    ->label('Ver cambios')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Activity $record): string => (ActivityLogResource::EVENTS[$record->event] ?? $record->event).' · '.$record->created_at->format('d/m/Y H:i:s'))
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (Activity $record) => view('filament.activity-logs.details', [
                        'activity' => $record,
                        'subject' => self::subjectLabel($record),
                    ])),
            ]);
    }

    /**
     * Texto legible del registro afectado, p. ej. "Ticket TK-0001".
     */
    public static function subjectLabel(Activity $record): string
    {
        if ($record->subject_type === null) {
            return '—';
        }

        $type = ActivityLogResource::SUBJECT_TYPES[$record->subject_type] ?? class_basename($record->subject_type);
        $subject = $record->subject;

        $name = $subject instanceof Model
            ? ($subject->getAttribute('number')
                ?? $subject->getAttribute('asset_tag')
                ?? $subject->getAttribute('title')
                ?? $subject->getAttribute('name')
                ?? '#'.$record->subject_id)
            : '#'.$record->subject_id.' (eliminado)';

        if ($subject instanceof TicketComment && $subject->ticket) {
            return "{$type} en ticket {$subject->ticket->number}";
        }

        return "{$type} {$name}";
    }
}

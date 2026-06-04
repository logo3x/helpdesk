<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\KactusService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('identification')
                    ->label('Cédula')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'supervisor_soporte' => 'info',
                        'agente_soporte' => 'success',
                        'tecnico_campo' => 'gray',
                        'editor_kb' => 'primary',
                        'usuario_final' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->placeholder('—'),

                IconColumn::make('kactus_status')
                    ->label('Kactus')
                    ->getStateUsing(fn (User $r) => match (true) {
                        $r->employment_status === 'terminated' => 'terminated',
                        $r->kactus_employee_id !== null => 'synced',
                        default => 'unlinked',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'synced' => 'heroicon-o-check-circle',
                        'terminated' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'synced' => 'success',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state) => match ($state) {
                        'synced' => 'Sincronizado con Kactus',
                        'terminated' => 'Empleado retirado en Kactus',
                        default => 'No vinculado a Kactus',
                    }),

                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('kactus_synced_at')
                    ->label('Sync Kactus')
                    ->dateTime('d/m/Y H:i')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->options(Role::pluck('name', 'name')),

                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name'),

                SelectFilter::make('kactus_status')
                    ->label('Estado Kactus')
                    ->options([
                        'synced' => 'Sincronizado',
                        'unlinked' => 'No vinculado',
                        'terminated' => 'Retirado',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'synced' => $query->whereNotNull('kactus_employee_id')->where('employment_status', '!=', 'terminated'),
                            'unlinked' => $query->whereNull('kactus_employee_id'),
                            'terminated' => $query->where('employment_status', 'terminated'),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Impersonate::make()
                    ->label('Impersonar')
                    ->visible(fn (User $record) => auth()->user()?->canImpersonateTarget($record))
                    ->redirectTo(fn (User $record) => match (true) {
                        $record->hasAnyRole(['super_admin', 'admin']) => '/admin',
                        $record->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'editor_kb']) => '/soporte',
                        default => '/portal/tickets',
                    }),

                Action::make('syncKactus')
                    ->label('Sincronizar Kactus')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->tooltip('Re-traer datos desde el sistema de nómina')
                    ->visible(fn (User $record) => $record->kactus_employee_id !== null && config('kactus.enabled'))
                    ->requiresConfirmation()
                    ->action(function (User $record, KactusService $kactus): void {
                        $emp = $kactus->fetchEmployee((string) $record->kactus_employee_id);

                        if (! $emp) {
                            Notification::make()
                                ->title('No se pudo traer el empleado desde Kactus')
                                ->danger()
                                ->send();

                            return;
                        }

                        $kactus->syncToUser($emp);

                        Notification::make()
                            ->title('Usuario sincronizado con Kactus')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

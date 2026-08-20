<?php

namespace App\Filament\Soporte\Resources\Users;

use App\Filament\Soporte\Resources\Users\Pages\CreateUser;
use App\Filament\Soporte\Resources\Users\Pages\EditUser;
use App\Filament\Soporte\Resources\Users\Pages\ListUsers;
use App\Filament\Soporte\Resources\Users\Schemas\UserForm;
use App\Filament\Soporte\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Users resource in the Support panel.
 *
 * Only visible to supervisor_soporte (and higher). They can create
 * agente_soporte users restricted to their own department.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios del departamento';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        $authUser = auth()->user();

        if (! $authUser) {
            return false;
        }

        // Solo un super_admin puede editar a otro super_admin. Esto previene
        // que un admin/supervisor con acceso al recurso pueda modificar el
        // rol o departamento del super usuario y escalar privilegios.
        if ($record->hasRole('super_admin') && ! $authUser->hasRole('super_admin')) {
            return false;
        }

        if ($authUser->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            return true;
        }

        // Agentes solo pueden editar usuarios_final.
        return $record->hasRole('usuario_final');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Supervisors only see users of their own department. Admins/super_admin
     * see everyone.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Agentes solo ven usuarios_final (no otros agentes, supervisores, admins).
        if ($user->hasRole('agente_soporte') && ! $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            $query->role('usuario_final');
        }

        // Supervisores y agentes solo ven su propio departamento.
        if (! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}

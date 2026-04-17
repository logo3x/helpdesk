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

    protected static ?string $modelLabel = 'Agente';

    protected static ?string $pluralModelLabel = 'Agentes del departamento';

    protected static ?string $navigationLabel = 'Agentes';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
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

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
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

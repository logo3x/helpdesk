<?php

namespace App\Filament\Soporte\Resources\Categories;

use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Filament\Soporte\Resources\Categories\Pages\CreateCategory;
use App\Filament\Soporte\Resources\Categories\Pages\EditCategory;
use App\Filament\Soporte\Resources\Categories\Pages\ListCategories;
use App\Filament\Soporte\Resources\Categories\Schemas\CategoryForm;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * CategoryResource para el panel /soporte.
 *
 * - super_admin / admin → ven y crean en cualquier depto.
 * - supervisor_soporte  → ven solo categorías de su depto y al crear
 *                          se fuerza el departamento al suyo.
 * - agente / técnico    → no acceden (las usan, no las administran).
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 25;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Supervisor: solo ve las categorías de su depto.
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
}

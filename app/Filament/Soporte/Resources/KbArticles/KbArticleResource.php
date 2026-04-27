<?php

namespace App\Filament\Soporte\Resources\KbArticles;

use App\Filament\Soporte\Resources\KbArticles\Pages\CreateKbArticle;
use App\Filament\Soporte\Resources\KbArticles\Pages\EditKbArticle;
use App\Filament\Soporte\Resources\KbArticles\Pages\ListKbArticles;
use App\Filament\Soporte\Resources\KbArticles\Schemas\KbArticleForm;
use App\Filament\Soporte\Resources\KbArticles\Tables\KbArticlesTable;
use App\Models\KbArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KbArticleResource extends Resource
{
    protected static ?string $model = KbArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'Artículo KB';

    protected static ?string $pluralModelLabel = 'Base de Conocimiento';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return KbArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KbArticlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKbArticles::route('/'),
            'create' => CreateKbArticle::route('/create'),
            'edit' => EditKbArticle::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        // Respeta el scope por depto también al acceder por ID directo.
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Scope KB articles by department. Super_admin/admin see all,
     * everyone else only sees articles of their own department.
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

    /**
     * Badge: número de KB pendientes de revisión visibles para el
     * usuario actual (depende del scope por depto). Solo aparece para
     * supervisores+; para los agentes lo ocultamos para no agobiar.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('status', 'draft')
            ->whereNotNull('pending_review_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

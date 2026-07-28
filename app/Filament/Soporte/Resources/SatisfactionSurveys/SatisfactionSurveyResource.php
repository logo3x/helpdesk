<?php

namespace App\Filament\Soporte\Resources\SatisfactionSurveys;

use App\Filament\Resources\SatisfactionSurveys\Schemas\SatisfactionSurveyInfolist;
use App\Filament\Resources\SatisfactionSurveys\Tables\SatisfactionSurveysTable;
use App\Filament\Soporte\Resources\SatisfactionSurveys\Pages\ListSatisfactionSurveys;
use App\Filament\Soporte\Resources\SatisfactionSurveys\Pages\ViewSatisfactionSurvey;
use App\Models\SatisfactionSurvey;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Encuestas de satisfacción en el panel /soporte.
 * Acceso: supervisor_soporte y agente_soporte (solo lectura).
 */
class SatisfactionSurveyResource extends Resource
{
    protected static ?string $model = SatisfactionSurvey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'Encuesta';

    protected static ?string $pluralModelLabel = 'Encuestas de satisfacción';

    protected static ?string $navigationLabel = 'Encuestas';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return SatisfactionSurveyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SatisfactionSurveysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSatisfactionSurveys::route('/'),
            'view' => ViewSatisfactionSurvey::route('/{record}'),
        ];
    }
}

<?php

namespace App\Filament\Resources\SatisfactionSurveys;

use App\Filament\Resources\SatisfactionSurveys\Pages\ListSatisfactionSurveys;
use App\Filament\Resources\SatisfactionSurveys\Pages\ViewSatisfactionSurvey;
use App\Filament\Resources\SatisfactionSurveys\Schemas\SatisfactionSurveyInfolist;
use App\Filament\Resources\SatisfactionSurveys\Tables\SatisfactionSurveysTable;
use App\Models\SatisfactionSurvey;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SatisfactionSurveyResource extends Resource
{
    protected static ?string $model = SatisfactionSurvey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'Encuesta';

    protected static ?string $pluralModelLabel = 'Encuestas de satisfacción';

    protected static ?string $navigationLabel = 'Encuestas';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 50;

    public static function canCreate(): bool
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

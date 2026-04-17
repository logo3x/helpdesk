<?php

namespace App\Filament\Soporte\Resources\CannedResponses;

use App\Filament\Soporte\Resources\CannedResponses\Pages\CreateCannedResponse;
use App\Filament\Soporte\Resources\CannedResponses\Pages\EditCannedResponse;
use App\Filament\Soporte\Resources\CannedResponses\Pages\ListCannedResponses;
use App\Filament\Soporte\Resources\CannedResponses\Schemas\CannedResponseForm;
use App\Filament\Soporte\Resources\CannedResponses\Tables\CannedResponsesTable;
use App\Models\CannedResponse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CannedResponseResource extends Resource
{
    protected static ?string $model = CannedResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $modelLabel = 'Respuesta predefinida';

    protected static ?string $pluralModelLabel = 'Respuestas predefinidas';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return CannedResponseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CannedResponsesTable::configure($table);
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
            'index' => ListCannedResponses::route('/'),
            'create' => CreateCannedResponse::route('/create'),
            'edit' => EditCannedResponse::route('/{record}/edit'),
        ];
    }

    /**
     * Canned responses are filtered by department via their category.
     * Super_admin/admin see all; others only see responses whose
     * category belongs to their own department (plus shared ones
     * without category).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('category', fn ($sub) => $sub->where('department_id', $user->department_id))
                    ->orWhereNull('category_id');
            });
        }

        return $query;
    }
}
